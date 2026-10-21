# One authorized manual startup/export of a new disposable candidate; never starts the working data.
[CmdletBinding()]
param([switch]$StartIsolatedCandidate)
$ErrorActionPreference = 'Stop'
if (-not $StartIsolatedCandidate) { throw 'No action taken. Specify -StartIsolatedCandidate for the one authorized isolated candidate batch.' }

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
    if ((Test-Path -LiteralPath $Stdout) -or (Test-Path -LiteralPath $Stderr)) { throw 'Client logs already exist. No retry permitted.' }
    $nativeArguments = @($Arguments | ForEach-Object { ConvertTo-ContextNativeArgument ([string]$_) })
    $process = Start-Process -FilePath $Executable -ArgumentList $nativeArguments -WorkingDirectory $WorkingDirectory `
        -WindowStyle Hidden -PassThru -RedirectStandardOutput $Stdout -RedirectStandardError $Stderr
    # Capture the client handle, including for reliable Windows exit-code retrieval.
    $null = $process.Handle
    $timedOut = -not $process.WaitForExit($TimeoutMs)
    if ($timedOut) {
        # Only this newly launched bounded client is cancelled, never a server.
        $process.Kill()
        if (-not $process.WaitForExit(5000)) { throw 'Client did not exit after timeout cancellation. Stop; do not retry.' }
    }
    $process.WaitForExit()
    if ($null -eq $process.ExitCode) { throw 'Client exit code unavailable. Stop; do not infer a passing result.' }
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

function Normalize-P2Path([string]$Path) {
    [IO.Path]::GetFullPath($Path.Replace('/','\')).TrimEnd('\')
}

function Get-P2Settings([string]$Root) {
    $rootPath = Normalize-P2Path $Root
    if ($rootPath -match '[\r\n";]') { throw 'Unsafe candidate path.' }
    $r = $rootPath.Replace('\','/')
    [ordered]@{
        basedir='C:/xampp/mysql'; 'plugin-dir'='C:/xampp/mysql/lib/plugin'
        datadir="$r/data"; port='3307'; 'bind-address'='127.0.0.1'; socket="$r/candidate.sock"
        'pid-file'="$r/candidate.pid"; 'log-error'="$r/logs/server-error.log"
        tmpdir="$r/tmp"; 'slave-load-tmpdir'="$r/tmp"; 'secure-file-priv'="$r/export"
        'aria-log-dir-path'="$r/data"; 'aria-recover-options'='OFF'; 'myisam-recover-options'='OFF'
        'innodb-data-home-dir'="$r/data"; 'innodb-data-file-path'='ibdata1:12M:autoextend'
        'innodb-log-group-home-dir'="$r/data"; 'innodb-undo-directory'="$r/data"
        'innodb-log-file-size'='48M'; 'innodb-log-files-in-group'='2'; 'innodb-page-size'='16384'
        'innodb-buffer-pool-size'='16M'; 'innodb-log-buffer-size'='8M'
        'innodb-buffer-pool-filename'='ib_buffer_pool'; 'innodb-buffer-pool-load-at-startup'='0'
        'innodb-buffer-pool-dump-at-shutdown'='0'; 'innodb-temp-data-file-path'='ibtmp1:12M:autoextend'
        'innodb-force-recovery'='0'; 'innodb-read-only'='0'; 'innodb-file-per-table'='1'
        'lower-case-table-names'='1'; 'read-only'='1'; 'event-scheduler'='OFF'
        'skip-slave-start'='1'; 'skip-log-bin'='1'; 'skip-grant-tables'='0'; 'named-pipe'='0'
        'skip-networking'='0'; 'general-log'='0'; 'slow-query-log'='0'; 'log-output'='FILE'
        'general-log-file'="$r/logs/general.log"; 'slow-query-log-file'="$r/logs/slow.log"
        'log-bin-index'="$r/logs/binlog.index"; 'relay-log'="$r/logs/relay"
        'relay-log-index'="$r/logs/relay.index"; 'relay-log-info-file'="$r/data/relay-log.info"
        'master-info-file'="$r/data/master.info"; 'log-tc'="$r/data/tc.log"
        'local-infile'='0'; 'character-set-server'='utf8mb4'; 'collation-server'='utf8mb4_general_ci'
        'sql-mode'='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'
    }
}

function Write-P2Ini([string]$Root) {
    Assert-ContextDirectories $Root
    $settings=Get-P2Settings $Root
    $lines=@('[mysqld]') + @($settings.GetEnumerator() | ForEach-Object {
        if ([string]$_.Value -match '[\r\n"]') { throw 'Unsafe option value.' }
        "$($_.Key)=" + '"' + [string]$_.Value + '"'
    })
    $path=Join-Path $Root 'candidate.ini'
    $bytes=[Text.Encoding]::UTF8.GetBytes(($lines -join "`r`n")+"`r`n")
    $stream=[IO.File]::Open($path,[IO.FileMode]::CreateNew,[IO.FileAccess]::Write,[IO.FileShare]::None)
    try {$stream.Write($bytes,0,$bytes.Length);$stream.Flush($true)} finally {$stream.Dispose()}
    $path
}

function Get-P2HelpMap([string]$Output) {
    $map=@{}
    foreach ($line in ($Output -split "`r?`n")) {
        if ($line -cmatch '^([a-z][a-z0-9-]*)\s{2,}(.*)$') { $map[$Matches[1]]=$Matches[2].Trim() }
    }
    $map
}

function Assert-P2Help([string]$Output,[string]$Errors,[int]$ExitCode,[bool]$TimedOut,[string]$Root) {
    if ($TimedOut -or $ExitCode -ne 0) { throw 'Candidate option compatibility failed. No startup.' }
    # With an empty synthetic datadir, help cannot read mysql.plugin. Allow only these known help notes.
    foreach ($line in ($Errors -split "`r?`n" | Where-Object { $_.Trim().Length })) {
        if ($line -notmatch "^\d{4}-\d\d-\d\d\s+\d{1,2}:\d\d:\d\d\s+0 \[Note\] Plugin 'FEEDBACK' is disabled\.$" -and
            $line -notmatch '^\d{4}-\d\d-\d\d\s+\d{1,2}:\d\d:\d\d\s+0 \[Warning\] Could not open mysql\.plugin table\. Some options may be missing from the help text$') {
            throw 'Unexpected diagnostic in candidate help. No startup.'
        }
    }
    $map=Get-P2HelpMap $Output
    $settings=Get-P2Settings $Root
    $pathKeys=@('basedir','plugin-dir','datadir','socket','pid-file','log-error','tmpdir','slave-load-tmpdir',
        'secure-file-priv','aria-log-dir-path','innodb-data-home-dir','innodb-log-group-home-dir','innodb-undo-directory',
        'general-log-file','slow-query-log-file','log-bin-index','relay-log','relay-log-index','relay-log-info-file','master-info-file','log-tc')
    $boolKeys=@('innodb-buffer-pool-load-at-startup','innodb-buffer-pool-dump-at-shutdown','innodb-read-only',
        'innodb-file-per-table','read-only','skip-slave-start','skip-grant-tables','named-pipe','skip-networking','general-log','slow-query-log','local-infile')
    foreach ($entry in $settings.GetEnumerator()) {
        $key=$entry.Key; $value=[string]$entry.Value
        if ($key -eq 'skip-log-bin') {
            if (-not $map.ContainsKey('log-bin') -or $map['log-bin'] -ne '0') { throw 'Binary logging is not explicitly disabled in help.' }
            continue
        }
        if (-not $map.ContainsKey($key)) { throw "Required option absent from effective help: $key" }
        if ($key -in $pathKeys) {
            if ((Normalize-P2Path $map[$key]) -ine (Normalize-P2Path $value)) { throw "Effective path mismatch: $key" }
        } elseif ($key -in $boolKeys) {
            $expected=if ($value -eq '1') {'TRUE'} else {'FALSE'}
            if ($map[$key] -ine $expected) { throw "Effective boolean mismatch: $key" }
        } elseif ($key -in 'innodb-log-file-size','innodb-buffer-pool-size','innodb-log-buffer-size') {
            $numeric=@{'48M'='50331648';'16M'='16777216';'8M'='8388608'}[$value]
            if ($map[$key] -ne $numeric) { throw "Effective size mismatch: $key" }
        } elseif ($map[$key] -ine $value) { throw "Effective setting mismatch: $key" }
    }
    # This pinned Windows build has no shared-memory server option. Do not pass an unsupported switch.
    if ($map.ContainsKey('shared-memory') -and $map['shared-memory'] -ne 'FALSE') { throw 'Shared-memory transport is enabled.' }
    foreach ($key in @('init-file','init-connect')) {
        if (-not $map.ContainsKey($key) -or $map[$key] -notin '', '(No default value)') { throw 'Unexpected startup SQL hook.' }
    }
}

function Get-P2ClientArguments([string]$Sql) {
    @('--no-defaults','--protocol=TCP','--host=127.0.0.1','--port=3307','--user=root',
      '--connect-timeout=3','--default-character-set=utf8mb4','--batch','--raw','--skip-column-names',"--execute=$Sql")
}

function Get-P2DumpArguments([string]$Destination) {
    if (Test-Path -LiteralPath $Destination) { throw 'Export destination already exists.' }
    @('--no-defaults','--protocol=TCP','--host=127.0.0.1','--port=3307','--user=root',
      '--default-character-set=utf8mb4','--single-transaction','--skip-lock-tables','--routines','--events',
      '--triggers','--hex-blob','--tz-utc','--quick','--comments',"--result-file=$Destination",'--databases','atikha_finance')
}

function Assert-P2Process([Diagnostics.Process]$Process,[string]$Executable,[string]$Ini,[long]$CreationTicks,[switch]$RequireListener) {
    $Process.Refresh()
    if ($Process.HasExited) { throw 'Candidate process has exited.' }
    if ($Process.StartTime.ToUniversalTime().Ticks -ne $CreationTicks -or $Process.MainModule.FileName -ine $Executable) { throw 'Held candidate identity mismatch.' }
    $servers=@(Get-CimInstance Win32_Process | Where-Object { $_.Name -in 'mysqld.exe','mariadbd.exe' })
    if ($servers.Count -ne 1 -or $servers[0].ProcessId -ne $Process.Id -or $servers[0].ExecutablePath -ine $Executable -or
        $servers[0].CommandLine -cne ('"'+$Executable+'" '+(ConvertTo-ContextNativeArgument "--defaults-file=$Ini"))) {
        throw 'Candidate command/process inventory differs from the captured launch.'
    }
    if (@(Get-Process -Name httpd,mysql,mysqladmin,mysqldump,aria_chk,myisamchk,aria_read_log -ErrorAction SilentlyContinue).Count) { throw 'Unexpected Apache/client/checker process.' }
    $listeners=@(Get-NetTCPConnection -State Listen -ErrorAction Stop | Where-Object { $_.LocalPort -in 3306,3307 })
    if (@($listeners | Where-Object { $_.LocalPort -ne 3307 -or $_.OwningProcess -ne $Process.Id -or $_.LocalAddress -ne '127.0.0.1' }).Count -or
        ($RequireListener -and $listeners.Count -ne 1)) { throw 'Candidate listener ownership/address mismatch.' }
}

function Assert-P2Identity([string]$Output,[string]$Root,[int]$ExpectedId) {
    $rows=@($Output.Trim() -split "`r?`n")
    if ($rows.Count -ne 1) { throw 'Unexpected identity response shape.' }
    $v=@($rows[0] -split "`t")
    if ($v.Count -ne 10 -or $v[0] -ne '3307' -or (Normalize-P2Path $v[1]) -ine (Normalize-P2Path (Join-Path $Root 'data')) -or
        (Normalize-P2Path $v[2]) -ine (Normalize-P2Path (Join-Path $Root 'candidate.pid')) -or
        $v[3] -notmatch '^10\.4\.32-MariaDB' -or $v[4] -ne '1' -or $v[5] -ne '0' -or
        $v[6] -ine 'OFF' -or $v[7] -ne '0' -or $v[8] -ne '0' -or $v[9] -ne '0') { throw 'Authenticated candidate identity/settings mismatch.' }
    if ([IO.File]::ReadAllText((Join-Path $Root 'candidate.pid')).Trim() -ne [string]$ExpectedId) { throw 'SQL PID-file content does not identify the held candidate.' }
}

function Assert-P2Variables([string]$Output,[string]$Root) {
    $vars=@{}
    foreach ($line in ($Output.Trim() -split "`r?`n")) {
        $v=@($line -split "`t",2)
        if ($v.Count -ne 2 -or $vars.ContainsKey($v[0].Replace('_','-'))) { throw 'Unexpected variable response.' }
        $vars[$v[0].Replace('_','-')]=$v[1]
    }
    $settings=Get-P2Settings $Root
    $keys=@('datadir','tmpdir','slave-load-tmpdir','aria-log-dir-path','innodb-data-home-dir',
        'innodb-log-group-home-dir','innodb-undo-directory','pid-file','socket','log-error','general-log-file',
        'slow-query-log-file','relay-log','relay-log-index','relay-log-info-file','secure-file-priv')
    foreach ($key in $keys) {
        if (-not $vars.ContainsKey($key) -or (Normalize-P2Path $vars[$key]) -ine (Normalize-P2Path ([string]$settings[$key]))) { throw "SQL path mismatch: $key" }
    }
    $exact=@{'innodb-data-file-path'='ibdata1:12M:autoextend';'innodb-log-file-size'='50331648';
        'innodb-log-files-in-group'='2';'innodb-page-size'='16384';'lower-case-table-names'='1';
        'innodb-buffer-pool-filename'='ib_buffer_pool';'aria-recover-options'='OFF';'myisam-recover-options'='OFF';
        'general-log'='OFF';'slow-query-log'='OFF';'named-pipe'='OFF';
        'bind-address'='127.0.0.1';'innodb-read-only'='OFF';'skip-networking'='OFF'}
    foreach ($key in $exact.Keys) {
        if (-not $vars.ContainsKey($key) -or $vars[$key] -ine $exact[$key]) { throw "SQL effective setting mismatch: $key" }
    }
}

function Get-P2StartupIssue([string]$ErrorLog) {
    if (Test-Path -LiteralPath $ErrorLog) {
        # Read shared; never rename or truncate an open error log.
        $stream=[IO.File]::Open($ErrorLog,[IO.FileMode]::Open,[IO.FileAccess]::Read,[IO.FileShare]::ReadWrite)
        $reader=New-Object IO.StreamReader($stream)
        try {$body=$reader.ReadToEnd()} finally {$reader.Dispose()}
        if ($body -match '(?im)\[(?:ERROR|Warning)\]|\bAssertion failure\b|\bAborting\b|Fatal error') { return $true }
    }
    $false
}

function Assert-P2Dump([string]$Path,[string]$Errors,[int]$ExitCode,[bool]$TimedOut) {
    if ($TimedOut -or $ExitCode -ne 0 -or $Errors.Trim().Length -or -not (Test-Path -LiteralPath $Path)) { throw 'Logical export failed or needs review.' }
    $text=[IO.File]::ReadAllText($Path)
    if ($text.Length -lt 100 -or $text -notmatch '(?m)^-- Dump completed on ' -or
        $text -notmatch '(?m)^USE `atikha_finance`;' -or $text -notmatch '(?m)^CREATE TABLE ') { throw 'Export completion/content not confirmed.' }
}

function Read-P2Log([string]$Path) {
    if (-not (Test-Path -LiteralPath $Path)) { return '' }
    $stream=[IO.File]::Open($Path,[IO.FileMode]::Open,[IO.FileAccess]::Read,[IO.FileShare]::ReadWrite)
    $reader=New-Object IO.StreamReader($stream)
    try {$reader.ReadToEnd()} finally {$reader.Dispose()}
}

function Invoke-P2Sql([object]$Context,[string]$Name,[string]$Sql,[int]$TimeoutMs=10000) {
    if ($Name -notmatch '^[a-z0-9-]+$') { throw 'Unsafe evidence name.' }
    Assert-P2Process $Context.Process $Context.Server $Context.Ini $Context.Ticks -RequireListener
    $out=Join-Path $Context.Logs "$Name.stdout.log"
    $err=Join-Path $Context.Logs "$Name.stderr.log"
    $run=Invoke-BoundedCheck $Context.Mysql @(Get-P2ClientArguments $Sql) $Context.Root $out $err $TimeoutMs
    Write-CheckJson (Join-Path $Context.Logs "$Name.json") $run
    if ($run.TimedOut -or $run.ExitCode -ne 0 -or [IO.File]::ReadAllText($err).Trim().Length) { throw "Candidate query failed: $Name. See private evidence." }
    Assert-P2Process $Context.Process $Context.Server $Context.Ini $Context.Ticks -RequireListener
    [IO.File]::ReadAllText($out)
}

function Stop-P2Gracefully([object]$Context,[string]$Admin) {
    $Context.Process.Refresh()
    if ($Context.Process.HasExited) { return [pscustomobject]@{State='already-exited';ExitCode=$Context.Process.ExitCode} }
    Assert-P2Process $Context.Process $Context.Server $Context.Ini $Context.Ticks -RequireListener
    $argsAdmin=@('--no-defaults','--protocol=TCP','--host=127.0.0.1','--port=3307','--user=root',
        '--connect-timeout=3','--shutdown-timeout=30','shutdown')
    Write-CheckJson (Join-Path $Context.Root 'graceful-shutdown-attempt.json') ([ordered]@{
        RequestedAt=(Get-Date).ToString('o');ProcessId=$Context.Process.Id;CreationTicks=$Context.Ticks;Arguments=$argsAdmin
    })
    $run=Invoke-BoundedCheck $Admin $argsAdmin $Context.Root (Join-Path $Context.Logs 'shutdown.stdout.log') (Join-Path $Context.Logs 'shutdown.stderr.log') 15000
    if ($run.TimedOut -or $run.ExitCode -ne 0 -or [IO.File]::ReadAllText((Join-Path $Context.Logs 'shutdown.stderr.log')).Trim().Length) {
        throw 'Graceful shutdown failed. Do not force-stop, restart or touch candidate files.'
    }
    if (-not $Context.Process.WaitForExit(60000)) { throw 'Candidate exit not confirmed within 60 seconds. No force-stop permitted.' }
    $Context.Process.WaitForExit()
    if ($null -eq $Context.Process.ExitCode -or $Context.Process.ExitCode -ne 0) { throw 'Candidate exited with an abnormal/unknown status after shutdown. Preserve evidence.' }
    [pscustomobject]@{State='gracefully-stopped';ExitCode=$Context.Process.ExitCode;Client=$run}
}

$attemptRoot='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d'
$evidence=Join-Path $attemptRoot 'evidence'
$expectedHash='B95EAEAC7F2F6097D87EFA862D44418C474ACDF2C35B5A5CD7DA3726708F4FCD'
$preserved=Join-Path $attemptRoot 'preserved\data'
$protectedRoots=@('C:\xampp\mysql\data',$preserved,(Join-Path $attemptRoot 'examine\data'),
    (Join-Path $attemptRoot 'check-context-20261020-043052-9c47421f\data'),
    (Join-Path $attemptRoot 'log-review-20261020-044727-5f056f22\data'))
$marker=Join-Path $evidence 'phase2a-candidate-attempt.json'
$server='C:\xampp\mysql\bin\mysqld.exe'
$mysql='C:\xampp\mysql\bin\mysql.exe'
$admin='C:\xampp\mysql\bin\mysqladmin.exe'
$dump='C:\xampp\mysql\bin\mysqldump.exe'
$pins=@{
    $server='EE6380DB7277E0A4EBE0FF255EDD846EB3B4EE604804CF5DB3BDDE1BE44B7E8A'
    $mysql='A60D3CBC24984E3C1C5DEE3726ABC685EC6FFF58BCE3FBD763F9261C652FB73D'
    $admin='5D28FB1F6FAAF93604F1A46FB5D1133832EF94E90CC9D227ACFF7ADD12DEAA7C'
    $dump='E30DDBE8F01D7DB1D3E75F0DBFB9B62E140D6202EB6FBBEA663CB63BDCD2CF56'
    'C:\xampp\mysql\bin\my.ini'='268B38BBA89E1FD30E3DC9EDBB5AA70BA30A97912D98CAFC2D5283119D06E3A0'
}
if ($env:MYSQL_PWD -or $env:MYSQL_HOME -or $env:MARIADB_HOME) { throw 'Unexpected database credential/defaults environment. Stop without displaying it.' }
Assert-CheckOffline
Assert-ContextDirectories $attemptRoot
Assert-ContextDirectories $evidence
if (Test-Path -LiteralPath $marker) { throw 'Phase 2-A has an existing attempt marker. No repeat or automatic retry.' }
$cert=[IO.File]::ReadAllText((Join-Path $evidence 'offline-preservation-verified.json')) | ConvertFrom-Json
$logCert=[IO.File]::ReadAllText((Join-Path $attemptRoot 'log-review-20261020-044727-5f056f22\preservation-after.json')) | ConvertFrom-Json
if ($cert.DatabaseSHA256 -ne $expectedHash -or $cert.DatabaseFiles -ne 2768 -or $cert.DatabaseBytes -ne 412226547 -or
    -not $cert.OriginalSourcesUnchanged -or -not $cert.StorageAndConfigurationCopied -or
    -not $cert.NoRepair -or -not $cert.NoServerLaunch -or $cert.Preserved -ine (Join-Path $attemptRoot 'preserved') -or
    $logCert.TreeSHA256 -ne $expectedHash -or -not $logCert.OriginalAndEarlierCopiesUnchanged -or
    -not $logCert.FreshCopyUnchanged -or -not $logCert.DisplayCheckCompleted -or -not $logCert.NoRepair -or
    -not $logCert.NoReplay -or -not $logCert.NoServerLaunch) { throw 'Prior preservation/log-review evidence mismatch.' }
foreach ($path in $pins.Keys) {
    if ((Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash -ne $pins[$path]) { throw 'Pinned binary/configuration changed.' }
}
Write-Host 'Verifying all five protected database trees. Keep XAMPP MySQL and Apache stopped.'
foreach ($root in $protectedRoots) {
    Assert-ContextDirectories $root
    $tree=Get-CheckTree $root
    if ($tree.TreeSHA256 -ne $expectedHash -or $tree.FileCount -ne 2768 -or
        @($tree.Entries | Where-Object { $_.Kind -eq 'F' -and [IO.Path]::GetExtension($_.Path) -ieq '.isl' }).Count) {
        throw 'Protected tree changed or contains an external tablespace link. No candidate launch.'
    }
}
Initialize-ControlReader
if ([AtikhaAriaControlReadOnly]::ReadMaxTrid([IO.File]::ReadAllBytes((Join-Path $preserved 'aria_log_control'))) -ne 145) { throw 'Preserved Aria context changed.' }
if (([IO.DriveInfo]::new('C:\')).AvailableFreeSpace -lt (2*$cert.DatabaseBytes + 2GB)) { throw 'Insufficient room for candidate, export and headroom.' }
$stamp=(Get-Date -Format 'yyyyMMdd-HHmmss')+'-'+[Guid]::NewGuid().ToString('N').Substring(0,8)
$root=Join-Path $attemptRoot "candidate-start-$stamp"
if (Test-Path -LiteralPath $root) { throw 'Candidate attempt directory already exists.' }
Assert-CheckOffline
Write-CheckJson $marker ([ordered]@{
    StartedAt=(Get-Date).ToString('o');Candidate=$root;Scope='One isolated startup/export/graceful-stop batch'
    OriginalSHA256=$expectedHash;Port=3307;NoForcedStop=$true;NoManualRepair=$true;NoWorkingChanges=$true
})
New-Item -ItemType Directory -Path $root | Out-Null
foreach ($dir in @('tmp','logs','export')) {New-Item -ItemType Directory -Path (Join-Path $root $dir) | Out-Null}
$logs=Join-Path $root 'logs'
$data=Join-Path $root 'data'
$errorLog=Join-Path $logs 'server-error.log'
$candidate=$null; $context=$null; $halt=$null; $stopIssue=$null; $shutdown=$null
$ready=$false; $exportComplete=$false; $inventoryComplete=$false; $iniHash=$null; $launchRecorded=$false
try {
    Assert-ContextDirectories $root
    foreach ($dir in @('tmp','logs','export')) {Assert-ContextDirectories (Join-Path $root $dir)}
    $ini=Write-P2Ini $root
    $iniHash=(Get-FileHash -LiteralPath $ini -Algorithm SHA256).Hash
    Write-Host 'Checking the complete candidate option set in help mode before copying or starting.'
    $helpOut=Join-Path $logs 'server-help.stdout.log'; $helpErr=Join-Path $logs 'server-help.stderr.log'
    $helpRun=Invoke-BoundedCheck $server @("--defaults-file=$ini",'--verbose','--help') $root $helpOut $helpErr 15000
    Assert-P2Help ([IO.File]::ReadAllText($helpOut)) ([IO.File]::ReadAllText($helpErr)) $helpRun.ExitCode $helpRun.TimedOut $root
    # Confirm client switches using --help only; no connection or live SQL in these calls.
    $exportPath=Join-Path $root 'export\atikha_finance.sql'
    $clientHelp=@(
        @{Name='mysql';Exe=$mysql;Args=@(Get-P2ClientArguments 'SELECT 1')+@('--help')},
        @{Name='dump';Exe=$dump;Args=@(Get-P2DumpArguments (Join-Path $logs 'dump-help-empty.sql'))+@('--help')},
        @{Name='admin';Exe=$admin;Args=@('--no-defaults','--protocol=TCP','--host=127.0.0.1','--port=3307','--user=root','--connect-timeout=3','--shutdown-timeout=30','--help')}
    )
    foreach ($spec in $clientHelp) {
        $out=Join-Path $logs ($spec.Name+'-help.stdout.log'); $err=Join-Path $logs ($spec.Name+'-help.stderr.log')
        $run=Invoke-BoundedCheck $spec.Exe $spec.Args $root $out $err 15000
        if ($run.TimedOut -or $run.ExitCode -ne 0 -or [IO.File]::ReadAllText($err).Trim().Length) { throw 'Client option compatibility failed.' }
    }
    if (Test-Path -LiteralPath $exportPath) { throw 'Help created an export destination unexpectedly. Stop without launching.' }
    if ((Get-Item -LiteralPath (Join-Path $logs 'dump-help-empty.sql')).Length -ne 0) { throw 'Unexpected dump-help output content.' }
    Assert-CheckOffline
    Write-Host 'Copying the complete preserved data to one new disposable candidate.'
    Copy-ContextData $preserved $data (Join-Path $logs 'copy.log')
    $fresh=Get-CheckTree $data
    if ($fresh.TreeSHA256 -ne $expectedHash) { throw 'Candidate copy hash mismatch. No launch.' }
    Write-CheckJson (Join-Path $root 'candidate-before.json') $fresh
    foreach ($file in @('ibdata1','ib_logfile0','ib_logfile1','aria_log_control')) {
        if (-not (Test-Path -LiteralPath (Join-Path $data $file) -PathType Leaf)) { throw 'Required shared engine file missing.' }
    }
    if ((Get-Item -LiteralPath (Join-Path $data 'ib_logfile0')).Length -ne 48MB -or
        (Get-Item -LiteralPath (Join-Path $data 'ib_logfile1')).Length -ne 48MB) { throw 'Redo geometry changed. No launch.' }
    Assert-CheckOffline
    Assert-ContextDirectories $data
    foreach ($path in $pins.Keys) {if ((Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash -ne $pins[$path]) {throw 'Prelaunch binary/configuration drift.'}}
    if ((Get-FileHash -LiteralPath $ini -Algorithm SHA256).Hash -ne $iniHash) { throw 'Candidate configuration changed.' }
    Write-CheckJson (Join-Path $root 'launch-attempt.json') ([ordered]@{
        RequestedAt=(Get-Date).ToString('o');Executable=$server;Arguments=@("--defaults-file=$ini")
        Ini=$ini;IniSHA256=$iniHash;Data=$data;WorkingDirectory=$root;ReadinessSeconds=60
        NormalRecoveryWrites='Candidate only';NoManualRepair=$true;NoForcedStop=$true
    })
    $launchRecorded=$true
    Write-Host 'Starting the disposable candidate once on 127.0.0.1:3307; waiting up to 60 seconds for readiness.'
    $candidate=Start-Process -FilePath $server -ArgumentList @(ConvertTo-ContextNativeArgument "--defaults-file=$ini") -WorkingDirectory $root `
        -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $logs 'server.stdout.log') -RedirectStandardError (Join-Path $logs 'server.stderr.log')
    $null=$candidate.Handle
    $ticks=$candidate.StartTime.ToUniversalTime().Ticks
    $context=[pscustomobject]@{Process=$candidate;Server=$server;Ini=$ini;Ticks=$ticks;Mysql=$mysql;Logs=$logs;Root=$root}
    Write-CheckJson (Join-Path $root 'launched-process.json') ([ordered]@{
        ProcessId=$candidate.Id;CreationTicksUTC=$ticks;Executable=$server;Ini=$ini;IniSHA256=$iniHash
    })
    $watch=[Diagnostics.Stopwatch]::StartNew()
    $identitySql='SELECT @@port,@@datadir,@@pid_file,VERSION(),@@global.read_only,@@global.innodb_force_recovery,@@global.event_scheduler,@@global.log_bin,@@global.general_log,@@global.slow_query_log;'
    while ($watch.Elapsed.TotalSeconds -lt 60) {
        Assert-P2Process $candidate $server $ini $ticks
        if (Get-P2StartupIssue $errorLog) { throw 'Candidate startup log reports a warning/error. Stop for private review.' }
        $body=Read-P2Log $errorLog
        if ($body -match '(?i)ready for connections') {
            $remaining=[Math]::Min(10000,[Math]::Max(1,60000-[int]$watch.ElapsedMilliseconds))
            $identity=Invoke-P2Sql $context 'identity' $identitySql $remaining
            Assert-P2Identity $identity $root $candidate.Id
            $ready=$true
            break
        }
        Start-Sleep -Milliseconds 500
    }
    if (-not $ready) { throw 'Candidate did not reach authenticated readiness within 60 seconds. No retry.' }
    Write-Host 'Authenticated candidate identity matched. Verifying effective paths before logical export.'
    $varsSql="SHOW GLOBAL VARIABLES WHERE Variable_name IN ('datadir','tmpdir','slave_load_tmpdir','aria_log_dir_path','innodb_data_home_dir','innodb_log_group_home_dir','innodb_undo_directory','pid_file','socket','log_error','general_log_file','slow_query_log_file','relay_log','relay_log_index','relay_log_info_file','secure_file_priv','innodb_data_file_path','innodb_log_file_size','innodb_log_files_in_group','innodb_page_size','lower_case_table_names','innodb_buffer_pool_filename','aria_recover_options','myisam_recover_options','general_log','slow_query_log','named_pipe','bind_address','innodb_read_only','skip_networking');"
    $variables=Invoke-P2Sql $context 'effective-variables' $varsSql
    Assert-P2Variables $variables $root
    $inventorySql="SELECT SCHEMA_NAME FROM information_schema.SCHEMATA ORDER BY SCHEMA_NAME; SELECT TABLE_SCHEMA,TABLE_NAME,TABLE_TYPE,COALESCE(ENGINE,'VIEW') FROM information_schema.TABLES WHERE TABLE_SCHEMA='atikha_finance' ORDER BY TABLE_NAME;"
    $null=Invoke-P2Sql $context 'inventory-before-export' $inventorySql
    Assert-P2Process $candidate $server $ini $ticks -RequireListener
    Write-Host 'Creating a private current-application logical export before table checks; no financial writes.'
    $dumpRun=Invoke-BoundedCheck $dump @(Get-P2DumpArguments $exportPath) $root (Join-Path $logs 'dump.stdout.log') (Join-Path $logs 'dump.stderr.log') 120000
    Write-CheckJson (Join-Path $logs 'dump-result.json') $dumpRun
    Assert-P2Dump $exportPath ([IO.File]::ReadAllText((Join-Path $logs 'dump.stderr.log'))) $dumpRun.ExitCode $dumpRun.TimedOut
    Assert-P2Process $candidate $server $ini $ticks -RequireListener
    $exportComplete=$true
    Write-CheckJson (Join-Path $root 'export-manifest.json') ([ordered]@{
        CreatedAt=(Get-Date).ToString('o');Path=$exportPath;SHA256=(Get-FileHash -LiteralPath $exportPath).Hash
        Bytes=(Get-Item -LiteralPath $exportPath).Length;Database='atikha_finance';Includes='Data/views/triggers/routines/events'
        Restored=$false;FinancialIntegrityVerified=$false;NoForceOption=$true
    })
    $metadataSql="SELECT ROUTINE_SCHEMA,ROUTINE_NAME,ROUTINE_TYPE,DEFINER FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA='atikha_finance' ORDER BY ROUTINE_NAME; SELECT TRIGGER_SCHEMA,TRIGGER_NAME,EVENT_OBJECT_TABLE,DEFINER FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='atikha_finance' ORDER BY TRIGGER_NAME; SELECT EVENT_SCHEMA,EVENT_NAME,STATUS,DEFINER FROM information_schema.EVENTS WHERE EVENT_SCHEMA='atikha_finance' ORDER BY EVENT_NAME; SELECT User,Host,plugin FROM mysql.user ORDER BY User,Host; SHOW GRANTS FOR CURRENT_USER();"
    $null=Invoke-P2Sql $context 'objects-accounts-current-grants' $metadataSql
    $inventoryComplete=$true
    if (Get-P2StartupIssue $errorLog) { throw 'Candidate log needs review after export/metadata capture.' }
} catch { $halt=$_.Exception.Message }

# No server Kill/TerminateProcess operation exists here. Graceful stop is attempted once if safely identified.
if ($null -ne $candidate) {
    try {
        Write-Host 'Checking candidate exit or attempting its one authenticated graceful shutdown.'
        if ($null -eq $context) { throw 'Candidate launch identity was not fully captured. No automatic shutdown permitted.' }
        $shutdown=Stop-P2Gracefully $context $admin
    } catch {$stopIssue=$_.Exception.Message}
}
$candidateRunning=$false
if ($null -ne $candidate) {$candidate.Refresh();$candidateRunning=-not $candidate.HasExited}
Write-CheckJson (Join-Path $root 'results.json') ([ordered]@{
    CapturedAt=(Get-Date).ToString('o');LaunchRecorded=$launchRecorded;AuthenticatedReady=$ready
    ExportCompleted=$exportComplete;MetadataCompleted=$inventoryComplete;HaltReason=$halt;Shutdown=$shutdown
    ShutdownIssue=$stopIssue;CandidateStillRunning=$candidateRunning;NoForcedStop=$true;NoManualRepair=$true;NoCutover=$true
})
Write-Host 'Rehashing the five protected original/earlier trees. Candidate recovery bytes are retained separately.'
$protectedUnchanged=$true
foreach ($protected in $protectedRoots) {
    try {
        Assert-ContextDirectories $protected
        if ((Get-CheckTree $protected).TreeSHA256 -ne $expectedHash) {$protectedUnchanged=$false}
    } catch {$protectedUnchanged=$false}
}
Write-CheckJson (Join-Path $root 'preservation-after.json') ([ordered]@{
    CapturedAt=(Get-Date).ToString('o');ProtectedFiveTreesUnchanged=$protectedUnchanged;ExpectedSHA256=$expectedHash
    CandidateMayHaveRecoveryWrites=$launchRecorded;CandidateStillRunning=$candidateRunning;NoCutover=$true;NoManualRepair=$true
})
if (-not $protectedUnchanged) { throw "A protected original/copy could not be verified unchanged. Stop; preserve all files. Evidence: $root" }
if (-not $candidateRunning) {
    Assert-CheckOffline
    if (Test-Path -LiteralPath $data) {Write-CheckJson (Join-Path $root 'candidate-after.json') (Get-CheckTree $data)}
}
if ($null -eq $halt -and $null -eq $stopIssue -and $ready -and $exportComplete -and $inventoryComplete -and
    $null -ne $shutdown -and $shutdown.State -eq 'gracefully-stopped' -and -not $candidateRunning) {
    Write-Host 'PHASE2A_CANDIDATE_COMPLETE. Identity/export verified and candidate gracefully stopped; database acceptance remains separate.'
} else {
    Write-Host 'PHASE2A_STOPPED_FOR_REVIEW. No retry, forced stop or repair is permitted.'
    if ($null -ne $halt) {Write-Host $halt}
    if ($null -ne $stopIssue) {Write-Host $stopIssue}
    if ($candidateRunning) {Write-Host "Candidate PID $($candidate.Id) remains running. Do not move/copy/reset its files or start XAMPP."}
}
Write-Host "Private Phase 2-A evidence: $root"
Write-Host 'Keep XAMPP MySQL and Apache stopped. Send this terminal result; do not rerun or share private exports/logs.'

