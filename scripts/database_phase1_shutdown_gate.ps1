# Manual Phase 1, first gate only. No forced server stop, DB copy, check or repair.
$ErrorActionPreference = 'Stop'

$serverPath = 'C:\xampp\mysql\bin\mysqld.exe'
$adminPath = 'C:\xampp\mysql\bin\mysqladmin.exe'
$iniPath = 'C:\xampp\mysql\bin\my.ini'
$expectedServerId = 20024
$expectedStart = [DateTimeOffset]::FromUnixTimeMilliseconds(1792429885946).UtcDateTime

function Assert-ApacheStopped {
    if (@(Get-Process -Name httpd -ErrorAction SilentlyContinue).Count) {
        throw 'Apache is running. Stop Apache only through XAMPP, then stop here and report.'
    }
}

Assert-ApacheStopped
if ($env:MYSQL_PWD) { throw 'Unexpected MYSQL_PWD setting. Stop without displaying it.' }
$servers = @(Get-Process -Name mysqld,mariadbd -ErrorAction SilentlyContinue)
if ($servers.Count -ne 1 -or $servers[0].Id -ne $expectedServerId) {
    throw 'The database process changed or is absent. Do not start/stop anything; report this result.'
}
$serverProcess = $servers[0]
if ($serverProcess.Path -ine $serverPath -or
    [Math]::Abs(($serverProcess.StartTime.ToUniversalTime() - $expectedStart).TotalSeconds) -gt 1) {
    throw 'Server executable/start-time identity is different or inaccessible. Stop and report.'
}
$serverCim = Get-CimInstance Win32_Process -Filter "ProcessId=$expectedServerId"
$parentCim = Get-CimInstance Win32_Process -Filter "ProcessId=$($serverCim.ParentProcessId)"
if ($parentCim.Name -ine 'xampp-control.exe' -or
    (Get-Content -LiteralPath 'C:\xampp\mysql\data\mysql.pid' -Raw).Trim() -ne "$expectedServerId") {
    throw 'XAMPP parent or PID-file identity mismatch. Stop and report.'
}
$listeners = @(Get-NetTCPConnection -State Listen -ErrorAction Stop |
    Where-Object { $_.LocalPort -in 3306,3307 })
if (-not $listeners.Count -or
    @($listeners | Where-Object { $_.LocalPort -ne 3306 -or $_.OwningProcess -ne $expectedServerId }).Count) {
    throw 'Unexpected database listener. Stop and report.'
}
if ((Get-FileHash -LiteralPath $serverPath -Algorithm SHA256).Hash -ne
    'EE6380DB7277E0A4EBE0FF255EDD846EB3B4EE604804CF5DB3BDDE1BE44B7E8A' -or
    (Get-FileHash -LiteralPath $iniPath -Algorithm SHA256).Hash -ne
    '268B38BBA89E1FD30E3DC9EDBB5AA70BA30A97912D98CAFC2D5283119D06E3A0') {
    throw 'Server binary or configuration changed. Stop and report.'
}

$stamp = (Get-Date -Format 'yyyyMMdd-HHmmss') + '-' + [Guid]::NewGuid().ToString('N').Substring(0,8)
$phase1Root = Join-Path 'C:\xampp\mysql\recovery-exports' "diagnostic-$stamp"
if (Test-Path -LiteralPath $phase1Root) { throw 'Evidence destination exists. Stop.' }
$phase1Evidence = Join-Path $phase1Root 'evidence'
New-Item -ItemType Directory -Path $phase1Evidence | Out-Null
[ordered]@{
    CapturedAt=(Get-Date).ToString('o'); ProcessId=$expectedServerId
    StartTimeUTC=$serverProcess.StartTime.ToUniversalTime().ToString('o')
    Executable=$serverProcess.Path; Parent=$parentCim.Name
    Branch='graceful-only'; NoDatabaseCopyYet=$true
} | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $phase1Evidence 'identity.json') -Encoding UTF8
Copy-Item -LiteralPath 'C:\xampp\mysql\data\mysql_error.log' -Destination (Join-Path $phase1Evidence 'mysql_error.before-shutdown.log')
Write-Host "Private evidence: $phase1Evidence"

$adminArguments = @('--no-defaults','--protocol=TCP','--host=127.0.0.1',
    '--port=3306','--user=root','--connect-timeout=5','--shutdown-timeout=30','shutdown')
# Recheck the captured process handle immediately before the authenticated request.
if ($serverProcess.HasExited) { throw 'Server exited before the request. Stop and report.' }
$shutdownClient = Start-Process -FilePath $adminPath -ArgumentList $adminArguments `
    -WindowStyle Hidden -PassThru `
    -RedirectStandardOutput (Join-Path $phase1Evidence 'shutdown.stdout.log') `
    -RedirectStandardError (Join-Path $phase1Evidence 'shutdown.stderr.log')
if (-not $shutdownClient.WaitForExit(15000)) {
    # Only this newly launched client is cancelled, never mysqld or a process tree.
    $shutdownClient.Kill()
    $shutdownClient.WaitForExit()
    throw "Shutdown client timed out; only the client was cancelled. No server force-stop or copy. Evidence: $phase1Evidence"
}
$shutdownClient.WaitForExit()
if ($shutdownClient.ExitCode -ne 0) {
    Get-Content -LiteralPath (Join-Path $phase1Evidence 'shutdown.stderr.log')
    throw "Graceful shutdown failed. Do not retry, force-stop or copy database files. Evidence: $phase1Evidence"
}
if (-not $serverProcess.WaitForExit(60000)) {
    throw 'Server did not exit within 60 seconds. Do not force-stop or copy files. Stop and report.'
}
Assert-ApacheStopped
if (@(Get-Process -Name mysqld,mariadbd -ErrorAction SilentlyContinue).Count -or
    @(Get-NetTCPConnection -State Listen -ErrorAction Stop |
        Where-Object { $_.LocalPort -in 3306,3307 }).Count) {
    throw 'A database process/listener remains. No copying allowed. Stop and report.'
}
[ordered]@{ VerifiedStoppedAt=(Get-Date).ToString('o'); Graceful=$true; NoDatabaseCopyYet=$true } |
    ConvertTo-Json | Set-Content -LiteralPath (Join-Path $phase1Evidence 'stopped.json') -Encoding UTF8
Write-Host 'STOPPED_AND_VERIFIED. Leave MySQL and Apache stopped. No database copy/check/repair has run.'
Write-Host "Send this result and the private evidence directory path: $phase1Evidence"
