# Manual Phase 1: non-repairing Aria checks on the separate examination copy only.
[CmdletBinding()]
param([switch]$CheckExaminationCopy)
$ErrorActionPreference = 'Stop'
if (-not $CheckExaminationCopy) { throw 'No action taken. Specify -CheckExaminationCopy for the authorized offline checks.' }

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
    $process = Start-Process -FilePath $Executable -ArgumentList $Arguments -WorkingDirectory $WorkingDirectory `
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

$attemptRoot = 'C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d'
$evidence = Join-Path $attemptRoot 'evidence'
$original = 'C:\xampp\mysql\data'
$preserved = Join-Path $attemptRoot 'preserved\data'
$examination = Join-Path $attemptRoot 'examine\data'
$checkRoot = Join-Path $attemptRoot 'offline-aria-checks'
$aria = 'C:\xampp\mysql\bin\aria_chk.exe'
$expectedHash = 'B95EAEAC7F2F6097D87EFA862D44418C474ACDF2C35B5A5CD7DA3726708F4FCD'

Assert-CheckOffline
if (Test-Path -LiteralPath $checkRoot) { throw 'Offline checker attempt already exists. Stop and report; do not retry or delete it.' }
$cert = Get-Content -LiteralPath (Join-Path $evidence 'offline-preservation-verified.json') -Raw -Encoding UTF8 | ConvertFrom-Json
if ($cert.DatabaseSHA256 -ne $expectedHash -or -not $cert.OriginalSourcesUnchanged -or
    $cert.Preserved -ine (Join-Path $attemptRoot 'preserved') -or $cert.Examine -ine (Join-Path $attemptRoot 'examine') -or
    $cert.AriaCheckerRun -ne $false -or -not $cert.NoRepair -or -not $cert.NoServerLaunch) {
    throw 'Preservation certificate mismatch. No checks permitted.'
}
if ((Get-FileHash -LiteralPath $aria -Algorithm SHA256).Hash -ne
    'B7CD8EBCECB13FEBA4FFFCF0B328FC2D826855B8A6F47D3D680354AE7875B20C') {
    throw 'Aria checker binary changed. Review installed option compatibility first.'
}
Write-Host 'Verifying original, preservation and examination database hashes before checking.'
$trees = @{}
foreach ($root in @($original,$preserved,$examination)) {
    $trees[$root] = Get-CheckTree $root
    if ($trees[$root].TreeSHA256 -ne $expectedHash) { throw 'A database tree changed since preservation. Stop; no checker started.' }
}
$indexes = @($trees[$examination].Entries | Where-Object { $_.Kind -eq 'F' -and [IO.Path]::GetExtension($_.Path) -ieq '.MAI' } | Sort-Object Path)
if ($indexes.Count -ne 24 -or @($trees[$examination].Entries | Where-Object {
    $_.Kind -eq 'F' -and [IO.Path]::GetExtension($_.Path) -in '.MYI','.isl'
}).Count) { throw 'Unexpected table/link inventory. Stop and review before checking.' }
Assert-CheckOffline
New-Item -ItemType Directory -Path $checkRoot | Out-Null
$checkTemp = Join-Path $checkRoot 'tmp'
New-Item -ItemType Directory -Path $checkTemp | Out-Null
Write-CheckJson (Join-Path $checkRoot 'attempt.json') ([ordered]@{
    StartedAt=(Get-Date).ToString('o');Examination=$examination;ExpectedTreeSHA256=$expectedHash
    IndexCount=$indexes.Count;ReadOnly=$true;SkipUpdateState=$true;IgnoreControlFile=$true;StopOnFirstIssue=$true
})
$results = New-Object 'System.Collections.Generic.List[object]'
$halt = $null
try {
    foreach ($index in $indexes) {
        Assert-CheckOffline
        $table = Assert-CheckTablePath $examination $index.Path
        $ordinal = $results.Count + 1
        Write-Host "Checking $ordinal/$($indexes.Count): $($index.Path)"
        $stdout = Join-Path $checkRoot ("table-{0:D2}.stdout.log" -f $ordinal)
        $stderr = Join-Path $checkRoot ("table-{0:D2}.stderr.log" -f $ordinal)
        $arguments = @('--no-defaults','--check','--read-only','--skip-update-state',
            '--ignore-control-file',"--tmpdir=$checkTemp",$table)
        $run = Invoke-BoundedCheck $aria $arguments $checkRoot $stdout $stderr
        $combined = [IO.File]::ReadAllText($stdout) + "`n" + [IO.File]::ReadAllText($stderr)
        $disposition = Get-CheckDisposition $run.ExitCode $run.TimedOut $combined
        $record = [pscustomobject]@{Table=$index.Path;ExitCode=$run.ExitCode;TimedOut=$run.TimedOut;
            Disposition=$disposition;Stdout=$stdout;Stderr=$stderr}
        $results.Add($record)
        Write-CheckJson (Join-Path $checkRoot ("table-{0:D2}.json" -f $ordinal)) $record
        if ($disposition -ne 'passed') {
            $halt = "Checker needs review for $($index.Path): $disposition"
            break
        }
    }
} catch {
    $halt = $_.Exception.Message
}
# Save findings before post-check verification, including an interrupted/incomplete run.
Write-CheckJson (Join-Path $checkRoot 'results.json') ([ordered]@{
    CapturedAt=(Get-Date).ToString('o');Checked=$results.Count;Inventory=$indexes.Count
    HaltReason=$halt;Results=@($results.ToArray());NoRepair=$true;NoServerLaunch=$true
})
Assert-CheckOffline
Write-Host 'Rehashing originals and both copies to verify the check changed no database file bytes.'
foreach ($root in @($original,$preserved,$examination)) {
    if ((Get-CheckTree $root).TreeSHA256 -ne $expectedHash) {
        throw 'Database bytes changed during checking. Stop and preserve all evidence; do not repair, restart or retry.'
    }
}
Assert-CheckOffline
Write-CheckJson (Join-Path $checkRoot 'preservation-after.json') ([ordered]@{
    VerifiedAt=(Get-Date).ToString('o');OriginalAndBothCopiesUnchanged=$true
    TreeSHA256=$expectedHash;Checked=$results.Count;Inventory=$indexes.Count
    AllAriaChecksPassed=($null -eq $halt -and $results.Count -eq $indexes.Count)
    HaltReason=$halt;NoRepair=$true;NoServerLaunch=$true;InnoDBIntegrity='Not checked'
})
if ($null -ne $halt) {
    Write-Host "OFFLINE_ARIA_CHECKS_STOPPED_FOR_REVIEW. Checked $($results.Count)/$($indexes.Count)."
    Write-Host $halt
} else {
    Write-Host "OFFLINE_ARIA_CHECKS_COMPLETE. $($results.Count)/$($indexes.Count) Aria checks passed."
}
Write-Host 'Original and both database copies are unchanged. No repair or server start ran. InnoDB integrity is not established.'
Write-Host "Private checker evidence: $checkRoot"
Write-Host 'Leave MySQL and Apache stopped. Send the terminal result; do not rerun.'
