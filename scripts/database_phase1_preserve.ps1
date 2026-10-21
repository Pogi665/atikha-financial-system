# Manual Phase 1 offline preservation only. Does not run a server or table checker.
[CmdletBinding()]
param([switch]$CreateOfflineCopies)
$ErrorActionPreference = 'Stop'
if (-not $CreateOfflineCopies) { throw 'No action taken. Specify -CreateOfflineCopies for the authorized offline preservation step.' }

function Assert-Phase1Stopped {
    $running = @(Get-CimInstance Win32_Process | Where-Object {
        $_.Name -in 'mysqld.exe','mariadbd.exe','httpd.exe','mysql.exe','mysqladmin.exe','mysqldump.exe'
    })
    if ($running.Count) { throw 'A database/Apache process or database client is running. Stop and report; this helper will not stop it.' }
    if (@(Get-NetTCPConnection -State Listen -ErrorAction Stop |
        Where-Object { $_.LocalPort -in 3306,3307 }).Count) {
        throw 'A database port is listening. Stop; no offline copy is permitted.'
    }
}

function Assert-Phase1Directory([string]$Path) {
    $item = Get-Item -LiteralPath $Path -Force -ErrorAction Stop
    if (-not $item.PSIsContainer -or ($item.Attributes -band [IO.FileAttributes]::ReparsePoint)) {
        throw "Not an ordinary directory: $Path"
    }
}

function Get-Phase1Tree([string]$Root,[string[]]$ExcludeTop = @()) {
    Assert-Phase1Directory $Root
    $absolute = [IO.Path]::GetFullPath($Root).TrimEnd('\')
    $pending = New-Object 'System.Collections.Generic.Stack[string]'
    $records = New-Object 'System.Collections.Generic.List[object]'
    $pending.Push($absolute)
    [long]$bytes = 0
    [int]$fileCount = 0
    while ($pending.Count) {
        $directory = $pending.Pop()
        foreach ($entry in @(Get-ChildItem -LiteralPath $directory -Force -ErrorAction Stop)) {
            if ($directory -ieq $absolute -and $entry.Name -in $ExcludeTop) { continue }
            if ($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) {
                throw "Reparse point requires separate review: $($entry.FullName)"
            }
            $relative = $entry.FullName.Substring($absolute.Length + 1)
            if ($entry.PSIsContainer) {
                $records.Add([pscustomobject]@{Kind='D';Path=$relative;Bytes=0;SHA256=''})
                $pending.Push($entry.FullName)
            } else {
                $fileHash = (Get-FileHash -LiteralPath $entry.FullName -Algorithm SHA256 -ErrorAction Stop).Hash
                $records.Add([pscustomobject]@{Kind='F';Path=$relative;Bytes=[long]$entry.Length;SHA256=$fileHash})
                $bytes += $entry.Length
                $fileCount++
                if ($fileCount % 250 -eq 0) {
                    Write-Progress -Activity 'Hashing offline preservation files' -Status "$fileCount files verified"
                }
            }
        }
    }
    Write-Progress -Activity 'Hashing offline preservation files' -Completed
    $ordered = @($records | Sort-Object Path,Kind)
    $canonical = (@($ordered | ForEach-Object {
        "$($_.Kind)`t$($_.Path)`t$($_.Bytes)`t$($_.SHA256)"
    }) -join "`n") + "`n"
    $sha = [Security.Cryptography.SHA256]::Create()
    try { $treeHash = [BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($canonical))).Replace('-','') }
    finally { $sha.Dispose() }
    [pscustomobject]@{Root=$absolute;TreeSHA256=$treeHash;FileCount=$fileCount;Bytes=$bytes;Entries=$ordered}
}

function Assert-Phase1CopyPaths([string]$Source,[string]$Destination) {
    $sourceRoot = [IO.Path]::GetFullPath($Source).TrimEnd('\')
    $destinationRoot = [IO.Path]::GetFullPath($Destination).TrimEnd('\')
    if ($sourceRoot -ieq $destinationRoot -or
        $destinationRoot.StartsWith($sourceRoot+'\',[StringComparison]::OrdinalIgnoreCase) -or
        $sourceRoot.StartsWith($destinationRoot+'\',[StringComparison]::OrdinalIgnoreCase)) {
        throw 'Source/destination are identical or nested. No copying permitted.'
    }
    Assert-Phase1Directory $sourceRoot
    if (Test-Path -LiteralPath $destinationRoot) { throw 'Copy destination already exists. Stop; do not overwrite it.' }
    Assert-Phase1Directory ([IO.Path]::GetDirectoryName($destinationRoot))
}

function Copy-Phase1Tree([string]$Source,[string]$Destination,[string]$Log,[string[]]$ExcludeTop = @()) {
    Assert-Phase1CopyPaths $Source $Destination
    New-Item -ItemType Directory -Path $Destination | Out-Null
    $copyArguments = @($Source,$Destination,'/E','/COPY:DAT','/DCOPY:DAT',
        '/R:0','/W:0','/XJ','/NP','/NFL','/NDL',"/LOG:$Log")
    if ($ExcludeTop.Count) {
        $copyArguments += '/XD'
        $copyArguments += @($ExcludeTop | ForEach-Object { Join-Path $Source $_ })
    }
    & robocopy.exe @copyArguments | Out-Null
    $copyExit = $LASTEXITCODE
    if ($copyExit -notin 0,1) { throw "Robocopy exit $copyExit requires review. Preserve partial copies/logs; do not retry." }
}

function Write-Phase1Json([string]$Path,[object]$Value) {
    if (Test-Path -LiteralPath $Path) { throw "Refusing an existing evidence file: $Path" }
    $json = $Value | ConvertTo-Json -Depth 7
    $stream = [IO.File]::Open($Path,[IO.FileMode]::CreateNew,[IO.FileAccess]::Write,[IO.FileShare]::None)
    try {
        $data = [Text.Encoding]::UTF8.GetBytes($json)
        $stream.Write($data,0,$data.Length)
        $stream.Flush($true)
    } finally {$stream.Dispose()}
}

$base = 'C:\xampp\mysql'
$source = Join-Path $base 'data'
$app = 'C:\xampp\htdocs\Financial Management System'
$uploads = Join-Path $app 'uploads'
$attemptRoot = Join-Path $base 'recovery-exports\diagnostic-20261020-032731-25c55e2d'
$evidence = Join-Path $attemptRoot 'evidence'
$preserved = Join-Path $attemptRoot 'preserved'
$examine = Join-Path $attemptRoot 'examine'
$attemptMarker = Join-Path $evidence 'offline-preservation-attempt.json'
$completeMarker = Join-Path $evidence 'offline-preservation-verified.json'
$excludeApp = @('.git','.migration-private','node_modules','uploads','.codex','.agents','.aws')

Assert-Phase1Stopped
foreach ($folder in @($base,$source,$app,$uploads,(Join-Path $base 'recovery-exports'),$attemptRoot,$evidence)) {
    Assert-Phase1Directory $folder
}
foreach ($path in @($preserved,$examine,$attemptMarker,$completeMarker)) {
    if (Test-Path -LiteralPath $path) { throw 'An offline preservation attempt/destination already exists. Stop and report; do not retry or delete it.' }
}
$stop = Get-Content -LiteralPath (Join-Path $evidence 'stopped.json') -Raw -Encoding UTF8 | ConvertFrom-Json
if ($stop.ProcessId -ne 20024 -or $stop.Graceful -ne $false -or -not $stop.NoDatabaseCopyYet -or
    $stop.Method -ne 'Explicitly authorized controlled forced stop') { throw 'Controlled-stop evidence does not match. Stop.' }
if ((Get-FileHash -LiteralPath (Join-Path $base 'bin\mysqld.exe') -Algorithm SHA256).Hash -ne
    'EE6380DB7277E0A4EBE0FF255EDD846EB3B4EE604804CF5DB3BDDE1BE44B7E8A' -or
    (Get-FileHash -LiteralPath (Join-Path $base 'bin\my.ini') -Algorithm SHA256).Hash -ne
    '268B38BBA89E1FD30E3DC9EDBB5AA70BA30A97912D98CAFC2D5283119D06E3A0') {
    throw 'Database binary/configuration changed. Stop.'
}
# Known installed storage roots were inspected statically; do not execute PHP/config.
$baseline = Get-Content -LiteralPath (Join-Path $app '.migration-private\u3a-before-source.json') -Raw -Encoding UTF8 | ConvertFrom-Json
foreach ($file in @('config.php','db_connect.php','includes/receipts.php','includes/board_uploads.php','includes/external_mail.php')) {
    if (-not $baseline.$file -or (Get-FileHash -LiteralPath (Join-Path $app $file) -Algorithm SHA256).Hash -ine $baseline.$file) {
        throw 'An inspected storage/configuration file changed. Review configured evidence roots before copying.'
    }
}

Write-Host 'Hashing the entire offline database, application source and protected uploads. Please leave both servers stopped.'
$dataBefore = Get-Phase1Tree $source
if (@($dataBefore.Entries | Where-Object { $_.Kind -eq 'F' -and [IO.Path]::GetExtension($_.Path) -ieq '.isl' }).Count) {
    throw 'External tablespace links need separate review. No copy started.'
}
$appBefore = Get-Phase1Tree $app $excludeApp
$uploadsBefore = Get-Phase1Tree $uploads
$neededBytes = (3 * $dataBefore.Bytes) + $appBefore.Bytes + $uploadsBefore.Bytes + 2GB
if (([IO.DriveInfo]::new('C:\')).AvailableFreeSpace -lt $neededBytes) { throw 'Insufficient free space for preservation, examination and recovery headroom.' }
Assert-Phase1Stopped
Write-Phase1Json $attemptMarker ([ordered]@{StartedAt=(Get-Date).ToString('o');DataRoot=$source;Preserved=$preserved;Examine=$examine;Graceful=$false;NoRepairs=$true;NoServerLaunch=$true})
Write-Phase1Json (Join-Path $evidence 'data.before.manifest.json') $dataBefore
Write-Phase1Json (Join-Path $evidence 'application.before.manifest.json') $appBefore
Write-Phase1Json (Join-Path $evidence 'uploads.before.manifest.json') $uploadsBefore
Copy-Item -LiteralPath (Join-Path $app '.migration-private\u3a-before-source.json') `
    -Destination (Join-Path $evidence 'u3a-before-source.json')
$gitHead = @(& git --no-optional-locks -C $app rev-parse HEAD)
if ($LASTEXITCODE -ne 0) { throw 'Cannot preserve Git baseline. Stop.' }
$gitStatus = @(& git --no-optional-locks -C $app status --porcelain=v1 --untracked-files=normal)
if ($LASTEXITCODE -ne 0) { throw 'Cannot preserve working-source status. Stop.' }
[IO.File]::WriteAllLines((Join-Path $evidence 'git-head.txt'),[string[]]$gitHead,[Text.Encoding]::UTF8)
[IO.File]::WriteAllLines((Join-Path $evidence 'git-status.txt'),[string[]]$gitStatus,[Text.Encoding]::UTF8)
$patchPath = Join-Path $evidence 'working-source.patch'
& git --no-optional-locks -C $app diff --no-ext-diff --no-textconv --binary HEAD "--output=$patchPath"
if ($LASTEXITCODE -ne 0) { throw 'Cannot preserve working-source patch. Stop.' }
New-Item -ItemType Directory -Path $preserved | Out-Null
New-Item -ItemType Directory -Path $examine | Out-Null
New-Item -ItemType Directory -Path (Join-Path $preserved 'storage') | Out-Null
New-Item -ItemType Directory -Path (Join-Path $preserved 'configuration') | Out-Null

Write-Host 'Copying the full offline database; no tables are excluded.'
Copy-Phase1Tree $source (Join-Path $preserved 'data') (Join-Path $evidence 'copy-data.log')
$dataCopy = Get-Phase1Tree (Join-Path $preserved 'data')
if ($dataCopy.TreeSHA256 -ne $dataBefore.TreeSHA256) { throw 'Preserved database copy differs. Stop and retain all files/logs.' }
Assert-Phase1Stopped
Write-Host 'Copying application source/configuration and all protected uploads.'
Copy-Phase1Tree $app (Join-Path $preserved 'application') (Join-Path $evidence 'copy-application.log') $excludeApp
Copy-Phase1Tree $uploads (Join-Path $preserved 'storage\uploads') (Join-Path $evidence 'copy-uploads.log')
$appCopy = Get-Phase1Tree (Join-Path $preserved 'application')
$uploadsCopy = Get-Phase1Tree (Join-Path $preserved 'storage\uploads')
if ($appCopy.TreeSHA256 -ne $appBefore.TreeSHA256 -or $uploadsCopy.TreeSHA256 -ne $uploadsBefore.TreeSHA256) {
    throw 'Application/storage copy differs. Stop and retain all files/logs.'
}
foreach ($pair in @(
    @{Source=(Join-Path $base 'bin\my.ini');Name='bin-my.ini'},
    @{Source=(Join-Path $source 'my.ini');Name='data-my.ini'},
    @{Source='C:\xampp\mysql_start.bat';Name='mysql_start.bat'},
    @{Source='C:\xampp\mysql_stop.bat';Name='mysql_stop.bat'}
)) {
    $configDestination = Join-Path (Join-Path $preserved 'configuration') $pair.Name
    Copy-Item -LiteralPath $pair.Source -Destination $configDestination
    if ((Get-FileHash -LiteralPath $pair.Source -Algorithm SHA256).Hash -ne
        (Get-FileHash -LiteralPath $configDestination -Algorithm SHA256).Hash) { throw 'Configuration copy differs. Stop.' }
}
Assert-Phase1Stopped
Write-Host 'Making a separate examination copy from the verified preservation copy.'
Copy-Phase1Tree (Join-Path $preserved 'data') (Join-Path $examine 'data') (Join-Path $evidence 'copy-examination.log')
$examinationCopy = Get-Phase1Tree (Join-Path $examine 'data')
if ($examinationCopy.TreeSHA256 -ne $dataBefore.TreeSHA256) { throw 'Examination copy differs. Stop.' }

Write-Host 'Rechecking original files and preserved copies for changes during copying.'
$dataAfter = Get-Phase1Tree $source
$appAfter = Get-Phase1Tree $app $excludeApp
$uploadsAfter = Get-Phase1Tree $uploads
$dataCopyAfter = Get-Phase1Tree (Join-Path $preserved 'data')
if ($dataAfter.TreeSHA256 -ne $dataBefore.TreeSHA256 -or
    $dataCopyAfter.TreeSHA256 -ne $dataBefore.TreeSHA256 -or
    $appAfter.TreeSHA256 -ne $appBefore.TreeSHA256 -or
    $uploadsAfter.TreeSHA256 -ne $uploadsBefore.TreeSHA256) {
    throw 'An original or preserved source changed. Stop and investigate; do not delete or retry.'
}
Assert-Phase1Stopped
if ((Get-FileHash -LiteralPath (Join-Path $base 'bin\my.ini') -Algorithm SHA256).Hash -ne
    '268B38BBA89E1FD30E3DC9EDBB5AA70BA30A97912D98CAFC2D5283119D06E3A0') {
    throw 'Active database configuration changed during preservation. Stop.'
}
Write-Phase1Json (Join-Path $evidence 'data.preserved.manifest.json') $dataCopyAfter
Write-Phase1Json (Join-Path $evidence 'data.examination.manifest.json') $examinationCopy
Write-Phase1Json $completeMarker ([ordered]@{
    VerifiedAt=(Get-Date).ToString('o');Preserved=$preserved;Examine=$examine
    DatabaseSHA256=$dataBefore.TreeSHA256;DatabaseFiles=$dataBefore.FileCount;DatabaseBytes=$dataBefore.Bytes
    ApplicationSHA256=$appBefore.TreeSHA256;UploadsSHA256=$uploadsBefore.TreeSHA256
    ExcludedApplicationTopDirectories=$excludeApp
    OriginalSourcesUnchanged=$true;StorageAndConfigurationCopied=$true
    DatabaseIntegrity='Not checked; preserved offline state after an authorized abrupt stop'
    AriaCheckerRun=$false;NoRepair=$true;NoServerLaunch=$true
})
Write-Host 'OFFLINE_PRESERVATION_VERIFIED. Original files and both database copies match.'
Write-Host "Private preservation: $preserved"
Write-Host "Private examination copy: $examine"
Write-Host 'Leave MySQL and Apache stopped. No table checker/repair/restart ran. Send this terminal result.'
