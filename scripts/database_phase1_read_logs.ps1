# One authorized display/check inspection of Aria logs on a fresh complete offline copy.
[CmdletBinding()]
param([switch]$InspectCopiedLogs)
$ErrorActionPreference = 'Stop'
if (-not $InspectCopiedLogs) { throw 'No action taken. Specify -InspectCopiedLogs for the one authorized copied-log inspection.' }

function Assert-CheckOffline {
    $processes = @(Get-CimInstance Win32_Process | Where-Object {
        $_.Name -in 'mysqld.exe','mariadbd.exe','httpd.exe','mysql.exe','mysqladmin.exe','mysqldump.exe','aria_chk.exe','myisamchk.exe','aria_read_log.exe'
    })
    if ($processes.Count -or @(Get-NetTCPConnection -State Listen -ErrorAction Stop |
        Where-Object { $_.LocalPort -in 3306,3307 }).Count) {
        throw 'A server/client/checker or database listener is running. Stop and report; nothing will be stopped automatically.'
    }
}

function Get-CheckTree([string]$Root) {
    $absolute = [IO.Path]::GetFullPath($Root).TrimEnd('\')
    $rootItem = Get-Item -LiteralPath $absolute -Force
    if (-not $rootItem.PSIsContainer -or ($rootItem.Attributes -band [IO.FileAttributes]::ReparsePoint)) {
        throw 'The database root is not an ordinary directory.'
    }
    $pending = New-Object 'System.Collections.Generic.Stack[string]'
    $records = New-Object 'System.Collections.Generic.List[object]'
    $pending.Push($absolute)
    [int]$files = 0
    while ($pending.Count) {
        foreach ($entry in @(Get-ChildItem -LiteralPath $pending.Pop() -Force -ErrorAction Stop)) {
            if ($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Reparse point requires separate review.' }
            $relative = $entry.FullName.Substring($absolute.Length + 1)
            if ($entry.PSIsContainer) {
                $records.Add([pscustomobject]@{Kind='D';Path=$relative;Bytes=0;SHA256=''})
                $pending.Push($entry.FullName)
            } else {
                $hash = (Get-FileHash -LiteralPath $entry.FullName -Algorithm SHA256).Hash
                $records.Add([pscustomobject]@{Kind='F';Path=$relative;Bytes=[long]$entry.Length;SHA256=$hash})
                $files++
                if ($files % 250 -eq 0) { Write-Progress -Activity 'Verifying offline database preservation' -Status "$files files hashed" }
            }
        }
    }
    Write-Progress -Activity 'Verifying offline database preservation' -Completed
    $ordered = @($records | Sort-Object Path,Kind)
    $canonical = (@($ordered | ForEach-Object {"$($_.Kind)`t$($_.Path)`t$($_.Bytes)`t$($_.SHA256)"}) -join "`n") + "`n"
    $sha = [Security.Cryptography.SHA256]::Create()
    try { $hash = [BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($canonical))).Replace('-','') }
    finally {$sha.Dispose()}
    [pscustomobject]@{Root=$absolute;TreeSHA256=$hash;FileCount=$files;Entries=$ordered}
}

function Write-CheckJson([string]$Path,[object]$Value) {
    $bytes = [Text.Encoding]::UTF8.GetBytes(($Value | ConvertTo-Json -Depth 7))
    $stream = [IO.File]::Open($Path,[IO.FileMode]::CreateNew,[IO.FileAccess]::Write,[IO.FileShare]::None)
    try {$stream.Write($bytes,0,$bytes.Length);$stream.Flush($true)} finally {$stream.Dispose()}
}

function Invoke-BoundedCheck([string]$Executable,[string[]]$Arguments,[string]$WorkingDirectory,[string]$Stdout,[string]$Stderr,[int]$TimeoutMs=30000) {
    if ((Test-Path -LiteralPath $Stdout) -or (Test-Path -LiteralPath $Stderr)) { throw 'Reader logs already exist. No retry permitted.' }
    $nativeArguments = @($Arguments | ForEach-Object { ConvertTo-ContextNativeArgument ([string]$_) })
    $process = Start-Process -FilePath $Executable -ArgumentList $nativeArguments -WorkingDirectory $WorkingDirectory `
        -WindowStyle Hidden -PassThru -RedirectStandardOutput $Stdout -RedirectStandardError $Stderr
    # Capture the client handle, including for reliable Windows exit-code retrieval.
    $null = $process.Handle
    $timedOut = -not $process.WaitForExit($TimeoutMs)
    if ($timedOut) {
        # Only this newly launched copied-log reader is cancelled, never a server.
        $process.Kill()
        if (-not $process.WaitForExit(5000)) { throw 'Reader did not exit after timeout cancellation. Stop; do not retry.' }
    }
    $process.WaitForExit()
    if ($null -eq $process.ExitCode) { throw 'Reader exit code unavailable. Stop; do not infer a passing result.' }
    [pscustomobject]@{ExitCode=[int]$process.ExitCode;TimedOut=$timedOut;ProcessId=$process.Id}
}

function ConvertTo-ContextNativeArgument([string]$Value) {
    if ($Value.Length -gt 0 -and $Value -notmatch '[\s"]') { return $Value }
    $escaped = [regex]::Replace($Value,'(\\*)"','$1$1\"')
    $escaped = [regex]::Replace($escaped,'(\\+)$','$1$1')
    '"' + $escaped + '"'
}

function Assert-ContextDirectories([string]$Path) {
    $current = Get-Item -LiteralPath ([IO.Path]::GetFullPath($Path)) -Force
    while ($null -ne $current) {
        if (-not ($current -is [IO.DirectoryInfo]) -or -not $current.Exists -or
            ($current.Attributes -band [IO.FileAttributes]::ReparsePoint)) {
            throw 'An attempt path or ancestor is not an ordinary directory. Stop.'
        }
        $current = $current.Parent
    }
}

function Copy-ContextData([string]$Source,[string]$Destination,[string]$Log) {
    $sourcePath = [IO.Path]::GetFullPath($Source).TrimEnd('\')
    $destPath = [IO.Path]::GetFullPath($Destination).TrimEnd('\')
    if ($sourcePath -ieq $destPath -or
        $destPath.StartsWith($sourcePath+'\',[StringComparison]::OrdinalIgnoreCase) -or
        $sourcePath.StartsWith($destPath+'\',[StringComparison]::OrdinalIgnoreCase)) {
        throw 'Source/destination overlap. Stop without copying.'
    }
    Assert-ContextDirectories $sourcePath
    Assert-ContextDirectories ([IO.Path]::GetDirectoryName($destPath))
    if (Test-Path -LiteralPath $destPath) { throw 'Fresh-copy destination already exists. No overwrite or retry.' }
    New-Item -ItemType Directory -Path $destPath | Out-Null
    & robocopy.exe $sourcePath $destPath /E /COPY:DAT /DCOPY:DAT /R:0 /W:0 /XJ /NP /NFL /NDL "/LOG:$Log" | Out-Null
    if ($LASTEXITCODE -notin 0,1) { throw 'Fresh-copy command did not finish cleanly. Preserve partial copy and stop.' }
}

function Initialize-ControlReader {
    if ('AtikhaAriaControlReadOnly' -as [type]) { return }
    Add-Type -TypeDefinition @'
using System;
public static class AtikhaAriaControlReadOnly {
    private static uint Crc(byte[] bytes,int start,int length) {
        uint crc=0xffffffffU;
        for(int i=start;i<start+length;i++) {
            crc^=bytes[i];
            for(int b=0;b<8;b++) crc=(crc&1U)!=0 ? (crc>>1)^0xedb88320U : crc>>1;
        }
        return crc^0xffffffffU;
    }
    private static uint U32(byte[] bytes,int offset) {
        return (uint)bytes[offset]|((uint)bytes[offset+1]<<8)|((uint)bytes[offset+2]<<16)|((uint)bytes[offset+3]<<24);
    }
    public static ulong ReadMaxTrid(byte[] bytes) {
        if(bytes==null || bytes.Length!=52 || bytes[0]!=254 || bytes[1]!=254 || bytes[2]!=12 || bytes[3]!=1)
            throw new InvalidOperationException("Unexpected control-file size, signature or version.");
        int fixedSize=bytes[20]|(bytes[21]<<8), variableSize=bytes[22]|(bytes[23]<<8);
        if(fixedSize!=30 || variableSize!=22 || fixedSize+variableSize!=bytes.Length)
            throw new InvalidOperationException("Unexpected control-file layout.");
        if(Crc(bytes,0,fixedSize-4)!=U32(bytes,fixedSize-4) || Crc(bytes,fixedSize+4,variableSize-4)!=U32(bytes,fixedSize))
            throw new InvalidOperationException("Control-file checksum validation failed.");
        ulong max=0;
        for(int i=0;i<6;i++) max|=((ulong)bytes[fixedSize+15+i])<<(8*i);
        return max;
    }
}
'@
}

function Get-LogReviewArguments([string]$DataRoot,[string]$TempRoot,[switch]$HelpOnly) {
    $data = [IO.Path]::GetFullPath($DataRoot).TrimEnd('\')
    $temp = [IO.Path]::GetFullPath($TempRoot).TrimEnd('\')
    if ($temp -ieq $data -or $temp.StartsWith($data+'\',[StringComparison]::OrdinalIgnoreCase) -or
        $data.StartsWith($temp+'\',[StringComparison]::OrdinalIgnoreCase)) { throw 'Log data and temporary paths overlap.' }
    $result = @('--no-defaults','--display-only','--check','--skip-apply','--skip-undo',
        "--aria-log-dir-path=$data","--tmpdir=$temp")
    if ($HelpOnly) { $result += '--help' }
    $result
}

function Assert-LogHelp([string]$Output,[string]$Errors,[int]$ExitCode,[bool]$TimedOut) {
    if ($TimedOut -or $ExitCode -ne 0 -or $Errors.Trim().Length) { throw 'Installed reader help failed. Stop.' }
    foreach ($setting in @(@('apply','FALSE'),@('undo','FALSE'),@('display-only','TRUE'),@('check','TRUE'))) {
        $pattern = '(?im)^'+[regex]::Escape($setting[0])+'\s+'+$setting[1]+'\s*$'
        if ($Output -notmatch $pattern) { throw 'Installed help does not confirm the required read-only modes. Stop.' }
    }
    foreach ($required in @('--aria-log-dir-path','--tmpdir','--display-only','--check')) {
        if (-not $Output.Contains($required)) { throw 'Required reader option missing. Stop.' }
    }
}

function Get-LogReviewDisposition([int]$ExitCode,[bool]$TimedOut,[string]$Output,[string]$Errors) {
    if ($TimedOut) { return 'timeout' }
    if ($ExitCode -ne 0) { return 'nonzero-exit' }
    if ($Errors.Trim().Length) { return 'stderr-needs-review' }
    if ($Output -match '(?i)\b(DOUBTFUL|FAILED|warning(?:s)?|error|corrupt(?:ed|ion)?|crashed|aborting)\b|can.t (?:open|find|init)|not (?:properly )?closed|not clean') {
        return 'output-needs-review'
    }
    if ($Output -match '(?i)transaction log is empty') { return 'empty-log-needs-review' }
    if ($Output -notmatch '(?m)^You are using --display-only, NOTHING will be written to disk\s*$' -or
        $Output -notmatch '(?im)^aria_read_log(?:\.exe)?: SUCCESS\s*$') { return 'completion-not-confirmed' }
    'completed-display-check'
}

$attemptRoot = 'C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d'
$evidence = Join-Path $attemptRoot 'evidence'
$original = 'C:\xampp\mysql\data'
$preserved = Join-Path $attemptRoot 'preserved\data'
$oldExamination = Join-Path $attemptRoot 'examine\data'
$correctedRoot = Join-Path $attemptRoot 'check-context-20261020-043052-9c47421f'
$correctedData = Join-Path $correctedRoot 'data'
$reader = 'C:\xampp\mysql\bin\aria_read_log.exe'
$readerHash = 'FC9121B314E0E7C6778A33DFDEF9E59A74236ECF3E5BDC7B64141DA2E77B32AA'
$expectedHash = 'B95EAEAC7F2F6097D87EFA862D44418C474ACDF2C35B5A5CD7DA3726708F4FCD'
$batchMarker = Join-Path $evidence 'aria-log-review-attempt.json'

Assert-CheckOffline
Assert-ContextDirectories $attemptRoot
Assert-ContextDirectories $evidence
Assert-ContextDirectories $correctedRoot
if (Test-Path -LiteralPath $batchMarker) { throw 'The single log-review attempt is already recorded. Do not rerun.' }
$cert = [IO.File]::ReadAllText((Join-Path $evidence 'offline-preservation-verified.json')) | ConvertFrom-Json
$previous = [IO.File]::ReadAllText((Join-Path $correctedRoot 'preservation-after.json')) | ConvertFrom-Json
if ($cert.DatabaseSHA256 -ne $expectedHash -or $cert.DatabaseFiles -ne 2768 -or $cert.DatabaseBytes -ne 412226547 -or
    -not $cert.OriginalSourcesUnchanged -or -not $cert.StorageAndConfigurationCopied -or
    -not $cert.NoRepair -or -not $cert.NoServerLaunch -or
    $cert.Preserved -ine (Join-Path $attemptRoot 'preserved') -or $cert.Examine -ine (Join-Path $attemptRoot 'examine') -or
    $previous.TreeSHA256 -ne $expectedHash -or -not $previous.OriginalAndAllCopiesUnchanged -or
    $previous.Checked -ne 3 -or $previous.Inventory -ne 24 -or $previous.AllAriaChecksPassed -or
    -not $previous.RequiredControlFile -or -not $previous.NoRepair -or -not $previous.NoServerLaunch) {
    throw 'Prior preservation/corrected-check evidence mismatch. No log inspection permitted.'
}
$pins = @{
    $reader=$readerHash
    'C:\xampp\mysql\bin\mysqld.exe'='EE6380DB7277E0A4EBE0FF255EDD846EB3B4EE604804CF5DB3BDDE1BE44B7E8A'
    'C:\xampp\mysql\bin\my.ini'='268B38BBA89E1FD30E3DC9EDBB5AA70BA30A97912D98CAFC2D5283119D06E3A0'
}
foreach ($path in $pins.Keys) {
    if ((Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash -ne $pins[$path]) { throw 'Pinned binary/configuration changed. Stop.' }
}
Write-Host 'Verifying all four existing offline database trees before creating one fresh log-review copy.'
$oldRoots = @($original,$preserved,$oldExamination,$correctedData)
foreach ($root in $oldRoots) {
    Assert-ContextDirectories $root
    if ((Get-CheckTree $root).TreeSHA256 -ne $expectedHash) { throw 'An original or earlier copy changed. No new copy/scan started.' }
}
Initialize-ControlReader
if ([AtikhaAriaControlReadOnly]::ReadMaxTrid([IO.File]::ReadAllBytes((Join-Path $preserved 'aria_log_control'))) -ne 145) {
    throw 'Preserved control-file context differs. Stop.'
}
if (([IO.DriveInfo]::new('C:\')).AvailableFreeSpace -lt ($cert.DatabaseBytes + 1GB)) { throw 'Insufficient fresh-copy headroom.' }
$stamp = (Get-Date -Format 'yyyyMMdd-HHmmss') + '-' + [Guid]::NewGuid().ToString('N').Substring(0,8)
$reviewRoot = Join-Path $attemptRoot "log-review-$stamp"
if (Test-Path -LiteralPath $reviewRoot) { throw 'Fresh attempt destination already exists. Stop.' }
$freshData = Join-Path $reviewRoot 'data'
$reviewTemp = Join-Path $reviewRoot 'tmp'
$logs = Join-Path $reviewRoot 'logs'
Assert-CheckOffline
Write-CheckJson $batchMarker ([ordered]@{
    StartedAt=(Get-Date).ToString('o');Attempt=$reviewRoot;ExpectedTreeSHA256=$expectedHash
    Scope='One fresh-copy Aria display/check inspection; no apply/undo, repair or server launch'
    DisplayOnly=$true;Check=$true;Apply=$false;Undo=$false;StopOnFirstIssue=$true
})
New-Item -ItemType Directory -Path $reviewRoot | Out-Null
New-Item -ItemType Directory -Path $reviewTemp | Out-Null
New-Item -ItemType Directory -Path $logs | Out-Null
Assert-ContextDirectories $reviewRoot
Assert-ContextDirectories $reviewTemp
Assert-ContextDirectories $logs
$freshVerified = $false
$run = $null
$halt = $null
$disposition = 'not-run'
try {
    # Help exits during option parsing, before opening any control or log file.
    $helpArgs = @(Get-LogReviewArguments $freshData $reviewTemp -HelpOnly)
    $helpOut = Join-Path $logs 'compatibility.stdout.log'
    $helpErr = Join-Path $logs 'compatibility.stderr.log'
    $helpRun = Invoke-BoundedCheck $reader $helpArgs $reviewRoot $helpOut $helpErr 15000
    Assert-LogHelp ([IO.File]::ReadAllText($helpOut)) ([IO.File]::ReadAllText($helpErr)) $helpRun.ExitCode $helpRun.TimedOut
    Assert-CheckOffline
    Write-Host 'Creating one complete fresh copy; earlier copies and logs will be retained.'
    Copy-ContextData $preserved $freshData (Join-Path $logs 'fresh-copy.log')
    $freshBefore = Get-CheckTree $freshData
    if ($freshBefore.TreeSHA256 -ne $expectedHash) { throw 'Fresh copy does not match preservation. Stop.' }
    $freshVerified = $true
    if ([AtikhaAriaControlReadOnly]::ReadMaxTrid([IO.File]::ReadAllBytes((Join-Path $freshData 'aria_log_control'))) -ne 145 -or
        -not @($freshBefore.Entries | Where-Object { $_.Kind -eq 'F' -and $_.Path -match '^aria_log\.\d+$' }).Count -or
        @($freshBefore.Entries | Where-Object { $_.Kind -eq 'F' -and [IO.Path]::GetExtension($_.Path) -ieq '.isl' }).Count) {
        throw 'Unexpected copied control/log/link inventory. Stop without scanning.'
    }
    Assert-CheckOffline
    Assert-ContextDirectories $freshData
    if ((Get-FileHash -LiteralPath $reader -Algorithm SHA256).Hash -ne $readerHash) { throw 'Reader binary changed. Stop.' }
    $arguments = @(Get-LogReviewArguments $freshData $reviewTemp)
    Write-CheckJson (Join-Path $reviewRoot 'reader-attempt.json') ([ordered]@{
        StartedAt=(Get-Date).ToString('o');Executable=$reader;SHA256=$readerHash;Arguments=$arguments
        WorkingDirectory=$reviewRoot;TimeoutMs=30000;DisplayOnly=$true;Check=$true;Apply=$false;Undo=$false
    })
    Write-Host 'Inspecting copied Aria logs once, with display/check enabled and apply/undo disabled.'
    $stdout = Join-Path $logs 'reader.stdout.log'
    $stderr = Join-Path $logs 'reader.stderr.log'
    $run = Invoke-BoundedCheck $reader $arguments $reviewRoot $stdout $stderr 30000
    $disposition = Get-LogReviewDisposition $run.ExitCode $run.TimedOut ([IO.File]::ReadAllText($stdout)) ([IO.File]::ReadAllText($stderr))
    if ($disposition -ne 'completed-display-check') { $halt = "Copied log reader needs review: $disposition" }
} catch { $halt = $_.Exception.Message }
Write-CheckJson (Join-Path $reviewRoot 'results.json') ([ordered]@{
    CapturedAt=(Get-Date).ToString('o');ReaderRun=$run;Disposition=$disposition;HaltReason=$halt
    FreshCopyVerified=$freshVerified;NoRepair=$true;NoReplay=$true;NoServerLaunch=$true
})
Assert-CheckOffline
Write-Host 'Rehashing the original and every complete copy; no bytes will be repaired or reset.'
foreach ($root in $oldRoots) {
    Assert-ContextDirectories $root
    if ((Get-CheckTree $root).TreeSHA256 -ne $expectedHash) { throw 'Original/earlier copy changed. Stop and preserve all evidence.' }
}
$freshUnchanged = $false
if (Test-Path -LiteralPath $freshData) {
    Assert-ContextDirectories $freshData
    $freshUnchanged = (Get-CheckTree $freshData).TreeSHA256 -eq $expectedHash
    if ($freshVerified -and -not $freshUnchanged) { throw 'Fresh log-review data changed. Stop and preserve it; do not reset bytes.' }
}
Assert-CheckOffline
Write-CheckJson (Join-Path $reviewRoot 'preservation-after.json') ([ordered]@{
    VerifiedAt=(Get-Date).ToString('o');OriginalAndEarlierCopiesUnchanged=$true
    FreshCopyVerified=$freshVerified;FreshCopyUnchanged=$freshUnchanged;TreeSHA256=$expectedHash
    DisplayCheckCompleted=($null -eq $halt -and $freshUnchanged);HaltReason=$halt
    NoRepair=$true;NoReplay=$true;NoServerLaunch=$true;InnoDBIntegrity='Not checked'
})
if ($null -ne $halt) {
    Write-Host 'COPIED_ARIA_LOG_REVIEW_STOPPED_FOR_REVIEW.'
    Write-Host $halt
} else { Write-Host 'COPIED_ARIA_LOG_REVIEW_COMPLETE. Display/check finished; interpretation is still required.' }
Write-Host 'Original and earlier copies are unchanged. No repair, replay or server start ran.'
Write-Host "Private log-review evidence: $reviewRoot"
Write-Host 'Leave MySQL and Apache stopped. Send the terminal result; do not rerun or share private log files.'

