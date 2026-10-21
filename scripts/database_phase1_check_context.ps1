# One authorized corrected batch: fresh full copy with its own required Aria control file.
[CmdletBinding()]
param([switch]$CheckFreshContext)
$ErrorActionPreference = 'Stop'
if (-not $CheckFreshContext) { throw 'No action taken. Specify -CheckFreshContext for the one authorized corrected batch.' }

function Assert-CheckOffline {
    $processes = @(Get-CimInstance Win32_Process | Where-Object {
        $_.Name -in 'mysqld.exe','mariadbd.exe','httpd.exe','mysql.exe','mysqladmin.exe','mysqldump.exe','aria_chk.exe','myisamchk.exe'
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

function Assert-CheckTablePath([string]$Root,[string]$Relative) {
    $absoluteRoot = [IO.Path]::GetFullPath($Root).TrimEnd('\')
    $absoluteTable = [IO.Path]::GetFullPath((Join-Path $absoluteRoot $Relative))
    if (-not $absoluteTable.StartsWith($absoluteRoot+'\',[StringComparison]::OrdinalIgnoreCase) -or
        [IO.Path]::GetExtension($absoluteTable) -ine '.MAI') { throw 'Checker table path escapes the examination root or is not an Aria index.' }
    $item = Get-Item -LiteralPath $absoluteTable -Force
    if ($item.PSIsContainer -or ($item.Attributes -band [IO.FileAttributes]::ReparsePoint)) { throw 'Checker target is not an ordinary file.' }
    $absoluteTable
}

function Write-CheckJson([string]$Path,[object]$Value) {
    $bytes = [Text.Encoding]::UTF8.GetBytes(($Value | ConvertTo-Json -Depth 7))
    $stream = [IO.File]::Open($Path,[IO.FileMode]::CreateNew,[IO.FileAccess]::Write,[IO.FileShare]::None)
    try {$stream.Write($bytes,0,$bytes.Length);$stream.Flush($true)} finally {$stream.Dispose()}
}

function Invoke-BoundedCheck([string]$Executable,[string[]]$Arguments,[string]$WorkingDirectory,[string]$Stdout,[string]$Stderr,[int]$TimeoutMs=30000) {
    if ((Test-Path -LiteralPath $Stdout) -or (Test-Path -LiteralPath $Stderr)) { throw 'Checker logs already exist. No retry permitted.' }
    $nativeArguments = @($Arguments | ForEach-Object { ConvertTo-ContextNativeArgument ([string]$_) })
    $process = Start-Process -FilePath $Executable -ArgumentList $nativeArguments -WorkingDirectory $WorkingDirectory `
        -WindowStyle Hidden -PassThru -RedirectStandardOutput $Stdout -RedirectStandardError $Stderr
    # Capture the client handle, including for reliable Windows exit-code retrieval.
    $null = $process.Handle
    $timedOut = -not $process.WaitForExit($TimeoutMs)
    if ($timedOut) {
        # Only this newly launched examination checker is cancelled, never a server.
        $process.Kill()
        if (-not $process.WaitForExit(5000)) { throw 'Checker did not exit after timeout cancellation. Stop; do not retry.' }
    }
    $process.WaitForExit()
    if ($null -eq $process.ExitCode) { throw 'Checker exit code unavailable. Stop; do not infer a passing result.' }
    [pscustomobject]@{ExitCode=[int]$process.ExitCode;TimedOut=$timedOut;ProcessId=$process.Id}
}

function Get-CheckDisposition([int]$ExitCode,[bool]$TimedOut,[string]$Output) {
    if ($TimedOut) { return 'timeout' }
    if ($ExitCode -ne 0) { return 'nonzero-exit' }
    if ($Output -match '(?i)\b(warning|error|corrupt(?:ed|ion)?|crashed)\b|not (?:properly )?closed|not clean') { return 'output-needs-review' }
    'passed'
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

function Get-ContextArguments([string]$DataRoot,[string]$TempRoot,[string]$Table,[switch]$HelpOnly) {
    $result = @('--no-defaults',"--datadir=$DataRoot", "--logdir=$DataRoot",
        '--require-control-file','--check','--read-only','--skip-update-state',"--tmpdir=$TempRoot")
    if ($HelpOnly) { $result += '--help' } else {
        $validated = Assert-CheckTablePath $DataRoot $Table
        $result += $validated
    }
    $result
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

$attemptRoot = 'C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d'
$evidence = Join-Path $attemptRoot 'evidence'
$original = 'C:\xampp\mysql\data'
$preserved = Join-Path $attemptRoot 'preserved\data'
$oldExamination = Join-Path $attemptRoot 'examine\data'
$aria = 'C:\xampp\mysql\bin\aria_chk.exe'
$expectedHash = 'B95EAEAC7F2F6097D87EFA862D44418C474ACDF2C35B5A5CD7DA3726708F4FCD'
$batchMarker = Join-Path $evidence 'corrected-control-check-attempt.json'

Assert-CheckOffline
Assert-ContextDirectories $attemptRoot
if (Test-Path -LiteralPath $batchMarker) { throw 'The single corrected batch is already recorded. Do not rerun.' }
$cert = [IO.File]::ReadAllText((Join-Path $evidence 'offline-preservation-verified.json')) | ConvertFrom-Json
$previous = [IO.File]::ReadAllText((Join-Path $attemptRoot 'offline-aria-checks\preservation-after.json')) | ConvertFrom-Json
if ($cert.DatabaseSHA256 -ne $expectedHash -or -not $cert.OriginalSourcesUnchanged -or
    -not $cert.StorageAndConfigurationCopied -or -not $cert.NoRepair -or -not $cert.NoServerLaunch -or
    $cert.Preserved -ine (Join-Path $attemptRoot 'preserved') -or
    $cert.Examine -ine (Join-Path $attemptRoot 'examine') -or
    $previous.TreeSHA256 -ne $expectedHash -or -not $previous.OriginalAndBothCopiesUnchanged -or
    $previous.Checked -ne 2 -or -not $previous.NoRepair -or -not $previous.NoServerLaunch) {
    throw 'Prior preservation/check evidence mismatch. No corrected check is permitted.'
}
if ((Get-FileHash -LiteralPath $aria -Algorithm SHA256).Hash -ne
    'B7CD8EBCECB13FEBA4FFFCF0B328FC2D826855B8A6F47D3D680354AE7875B20C') {
    throw 'Installed checker binary changed. Stop.'
}
Write-Host 'Verifying the original, preservation copy and earlier examination copy before making one fresh copy.'
$trees = @{}
foreach ($root in @($original,$preserved,$oldExamination)) {
    Assert-ContextDirectories $root
    $trees[$root] = Get-CheckTree $root
    if ($trees[$root].TreeSHA256 -ne $expectedHash) { throw 'A preserved/original tree changed. No copy/check started.' }
}
Initialize-ControlReader
$preservedMax = [AtikhaAriaControlReadOnly]::ReadMaxTrid([IO.File]::ReadAllBytes((Join-Path $preserved 'aria_log_control')))
if ($preservedMax -ne 145) { throw 'Stored control-file context differs from the reviewed value. Stop.' }
if (([IO.DriveInfo]::new('C:\')).AvailableFreeSpace -lt ($cert.DatabaseBytes + 1GB)) { throw 'Insufficient space for the fresh copy and temporary headroom.' }
$stamp = (Get-Date -Format 'yyyyMMdd-HHmmss') + '-' + [Guid]::NewGuid().ToString('N').Substring(0,8)
$checkRoot = Join-Path $attemptRoot "check-context-$stamp"
if (Test-Path -LiteralPath $checkRoot) { throw 'Fresh attempt directory already exists. Stop.' }
$freshData = Join-Path $checkRoot 'data'
$checkTemp = Join-Path $checkRoot 'tmp'
$logs = Join-Path $checkRoot 'logs'
Assert-CheckOffline
Write-CheckJson $batchMarker ([ordered]@{
    AuthorizedScope='One corrected offline batch only; no repairs or server launches'
    StartedAt=(Get-Date).ToString('o');Attempt=$checkRoot;ExpectedTreeSHA256=$expectedHash
    RequiredControlFile=$true;IgnoreControlFile=$false;StopOnFirstIssue=$true
})
New-Item -ItemType Directory -Path $checkRoot | Out-Null
New-Item -ItemType Directory -Path $checkTemp | Out-Null
New-Item -ItemType Directory -Path $logs | Out-Null

# --help parses the complete option set and exits before table/control-file open.
$helpArguments = @(Get-ContextArguments $freshData $checkTemp '' -HelpOnly)
$helpOut = Join-Path $logs 'compatibility.stdout.log'
$helpErr = Join-Path $logs 'compatibility.stderr.log'
$compatibility = Invoke-BoundedCheck $aria $helpArguments $checkRoot $helpOut $helpErr 15000
if ($compatibility.TimedOut -or $compatibility.ExitCode -ne 0 -or
    [IO.File]::ReadAllText($helpErr).Trim().Length) { throw 'Installed option-set compatibility failed. Stop without copying/checking.' }
$helpText = [IO.File]::ReadAllText($helpOut)
foreach ($required in @('--require-control-file','--datadir','--logdir','--read-only','--skip-update-state')) {
    if (-not $helpText.Contains($required)) { throw 'Required option missing from installed help. Stop.' }
}
Assert-CheckOffline
Write-Host 'Copying the complete verified preservation data into a new disposable check-context folder.'
Copy-ContextData $preserved $freshData (Join-Path $logs 'fresh-copy.log')
$freshBefore = Get-CheckTree $freshData
if ($freshBefore.TreeSHA256 -ne $expectedHash) { throw 'Fresh copy does not match the full preservation hash. Stop.' }
$controlPath = Join-Path $freshData 'aria_log_control'
$freshMax = [AtikhaAriaControlReadOnly]::ReadMaxTrid([IO.File]::ReadAllBytes($controlPath))
if ($freshMax -ne $preservedMax) { throw 'Copied control-file context mismatch. Stop.' }
Write-CheckJson (Join-Path $checkRoot 'control-context.json') ([ordered]@{
    ControlFile=$controlPath;Bytes=52;Version=1;BothChecksumsValid=$true;StoredMaxTrid=$freshMax
    CommandRequiresControlFile=$true;IgnoreControlFile=$false;NoWarningSuppression=$true
})
$indexes = @($freshBefore.Entries | Where-Object { $_.Kind -eq 'F' -and [IO.Path]::GetExtension($_.Path) -ieq '.MAI' } |
    Sort-Object @{Expression={if ($_.Path -ieq 'mysql\columns_priv.MAI') {0} else {1}}},Path)
if ($indexes.Count -ne 24 -or $indexes[0].Path -ine 'mysql\columns_priv.MAI' -or
    @($freshBefore.Entries | Where-Object { $_.Kind -eq 'F' -and [IO.Path]::GetExtension($_.Path) -in '.MYI','.isl' }).Count) {
    throw 'Unexpected inventory; no table check will run.'
}
$expectedFiles = @{}
foreach ($file in @($freshBefore.Entries | Where-Object Kind -eq 'F')) {$expectedFiles[$file.Path]=$file.SHA256}
$results = New-Object 'System.Collections.Generic.List[object]'
$halt = $null
try {
    foreach ($index in $indexes) {
        Assert-CheckOffline
        Assert-ContextDirectories $freshData
        $ordinal = $results.Count + 1
        Write-Host "Checking $ordinal/24 with required copied control context: $($index.Path)"
        $stdout = Join-Path $logs ("table-{0:D2}.stdout.log" -f $ordinal)
        $stderr = Join-Path $logs ("table-{0:D2}.stderr.log" -f $ordinal)
        $arguments = @(Get-ContextArguments $freshData $checkTemp $index.Path)
        $run = Invoke-BoundedCheck $aria $arguments $checkRoot $stdout $stderr
        $combined = [IO.File]::ReadAllText($stdout) + "`n" + [IO.File]::ReadAllText($stderr)
        $disposition = Get-CheckDisposition $run.ExitCode $run.TimedOut $combined
        $record = [pscustomobject]@{Table=$index.Path;ExitCode=$run.ExitCode;TimedOut=$run.TimedOut;
            Disposition=$disposition;Arguments=$arguments;Stdout=$stdout;Stderr=$stderr}
        $results.Add($record)
        Write-CheckJson (Join-Path $logs ("table-{0:D2}.json" -f $ordinal)) $record
        # Check index/data/control bytes immediately, in addition to final full-tree hashes.
        foreach ($relative in @($index.Path,[IO.Path]::ChangeExtension($index.Path,'.MAD'),'aria_log_control')) {
            if (-not $expectedFiles.ContainsKey($relative) -or
                (Get-FileHash -LiteralPath (Join-Path $freshData $relative) -Algorithm SHA256).Hash -ne $expectedFiles[$relative]) {
                throw 'A checked index/data/control file changed. Stop and preserve the attempt.'
            }
        }
        if ($disposition -ne 'passed') {
            $halt = "Checker needs review for $($index.Path): $disposition"
            break
        }
    }
} catch {$halt=$_.Exception.Message}
Write-CheckJson (Join-Path $checkRoot 'results.json') ([ordered]@{
    CapturedAt=(Get-Date).ToString('o');Checked=$results.Count;Inventory=24;HaltReason=$halt
    Results=@($results.ToArray());RequiredControlFile=$true;NoRepair=$true;NoServerLaunch=$true
})
Assert-CheckOffline
Write-Host 'Verifying that original, preserved, earlier examination and fresh check data all remain byte-identical.'
foreach ($root in @($original,$preserved,$oldExamination,$freshData)) {
    if ((Get-CheckTree $root).TreeSHA256 -ne $expectedHash) { throw 'Database bytes changed. Stop; no further action or retry.' }
}
Assert-CheckOffline
Write-CheckJson (Join-Path $checkRoot 'preservation-after.json') ([ordered]@{
    VerifiedAt=(Get-Date).ToString('o');OriginalAndAllCopiesUnchanged=$true;TreeSHA256=$expectedHash
    Checked=$results.Count;Inventory=24;AllAriaChecksPassed=($null -eq $halt -and $results.Count -eq 24)
    HaltReason=$halt;RequiredControlFile=$true;NoRepair=$true;NoServerLaunch=$true;InnoDBIntegrity='Not checked'
})
if ($null -ne $halt) {
    Write-Host "CONTEXT_ARIA_CHECKS_STOPPED_FOR_REVIEW. Checked $($results.Count)/24."
    Write-Host $halt
} else {Write-Host 'CONTEXT_ARIA_CHECKS_COMPLETE. 24/24 Aria checks passed.'}
Write-Host 'Original and all three copies are unchanged. No repair or server start ran; InnoDB integrity is not established.'
Write-Host "Private corrected-check evidence: $checkRoot"
Write-Host 'Leave MySQL and Apache stopped. Send the terminal result; do not rerun.'

