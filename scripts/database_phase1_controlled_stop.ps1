# Manual, explicitly authorized exception only. Never starts/copies/repairs MariaDB.
[CmdletBinding()]
param([switch]$ExecuteAuthorizedStop)
$ErrorActionPreference = 'Stop'
if (-not $ExecuteAuthorizedStop) {
    throw 'No action taken. This helper requires -ExecuteAuthorizedStop and the explicit user authorization recorded in project status.'
}

$evidence = 'C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\evidence'
$identityFile = Join-Path $evidence 'identity.json'
$attemptFile = Join-Path $evidence 'controlled-stop-attempt.json'
$stoppedFile = Join-Path $evidence 'stopped.json'
$serverPath = 'C:\xampp\mysql\bin\mysqld.exe'
$iniPath = 'C:\xampp\mysql\bin\my.ini'
$expectedId = 20024
if ((Test-Path -LiteralPath $attemptFile) -or (Test-Path -LiteralPath $stoppedFile)) {
    throw 'A stop attempt/result is already recorded. Do not repeat. Stop and report.'
}
$identity = Get-Content -LiteralPath $identityFile -Raw -Encoding UTF8 | ConvertFrom-Json
if ($identity.ProcessId -ne $expectedId -or $identity.Executable -ine $serverPath -or
    $identity.Branch -ne 'graceful-only' -or $identity.Parent -ine 'xampp-control.exe') {
    throw 'Prior identity evidence mismatch. No action taken.'
}
$priorFailure = Get-Content -LiteralPath (Join-Path $evidence 'shutdown.stderr.log') -Raw
if ($priorFailure -notmatch 'handshake: reading initial communication packet') {
    throw 'The expected graceful connection failure is not recorded. No action taken.'
}

function Assert-QuietClients {
    if (@(Get-Process -Name httpd,mysql,mysqldump,mysqladmin -ErrorAction SilentlyContinue).Count) {
        throw 'Apache or a database client is running. No action taken; close clients/stop Apache only and report.'
    }
}
function Assert-ServerOwnership {
    Assert-QuietClients
    $servers = @(Get-CimInstance Win32_Process | Where-Object { $_.Name -in 'mysqld.exe','mariadbd.exe' })
    if ($servers.Count -ne 1 -or $servers[0].ProcessId -ne $expectedId) {
        throw 'Database process changed or is absent. Do not start/stop anything. Report this result.'
    }
    $parent = Get-CimInstance Win32_Process -Filter "ProcessId=$($servers[0].ParentProcessId)"
    if ($parent.Name -ine 'xampp-control.exe' -or
        (Get-Content -LiteralPath 'C:\xampp\mysql\data\mysql.pid' -Raw).Trim() -ne "$expectedId") {
        throw 'Parent or PID-file mismatch. No action taken.'
    }
    $listeners = @(Get-NetTCPConnection -State Listen -ErrorAction Stop |
        Where-Object { $_.LocalPort -in 3306,3307 })
    if (-not $listeners.Count -or
        @($listeners | Where-Object { $_.LocalPort -ne 3306 -or $_.OwningProcess -ne $expectedId }).Count) {
        throw 'Listener identity changed. No action taken.'
    }
}
Assert-ServerOwnership
if ((Get-FileHash -LiteralPath $serverPath -Algorithm SHA256).Hash -ne
    'EE6380DB7277E0A4EBE0FF255EDD846EB3B4EE604804CF5DB3BDDE1BE44B7E8A' -or
    (Get-FileHash -LiteralPath $iniPath -Algorithm SHA256).Hash -ne
    '268B38BBA89E1FD30E3DC9EDBB5AA70BA30A97912D98CAFC2D5283119D06E3A0') {
    throw 'Binary/configuration hash mismatch. No action taken.'
}

if (-not ('AtikhaControlledStopTarget' -as [type])) {
Add-Type -TypeDefinition @'
using System;
using System.Text;
using System.ComponentModel;
using System.Runtime.InteropServices;
public sealed class AtikhaControlledStopTarget : IDisposable {
    private IntPtr handle;
    private bool attempted;
    [DllImport("kernel32.dll", SetLastError=true)] private static extern IntPtr OpenProcess(uint access,bool inherit,int id);
    [DllImport("kernel32.dll", CharSet=CharSet.Unicode, SetLastError=true)] private static extern bool QueryFullProcessImageName(IntPtr process,uint flags,StringBuilder path,ref uint size);
    [DllImport("kernel32.dll", SetLastError=true)] private static extern bool GetProcessTimes(IntPtr process,out long creation,out long exit,out long kernel,out long user);
    [DllImport("kernel32.dll", SetLastError=true)] private static extern bool TerminateProcess(IntPtr process,uint exitCode);
    [DllImport("kernel32.dll", SetLastError=true)] private static extern uint WaitForSingleObject(IntPtr handle,uint milliseconds);
    [DllImport("kernel32.dll")] private static extern bool CloseHandle(IntPtr handle);
    public AtikhaControlledStopTarget(int id,string expectedPath,long expectedUtcTicks) {
        // Hold exactly this process object across verification and termination.
        handle=OpenProcess(0x00101001,false,id); // SYNCHRONIZE | QUERY_LIMITED_INFORMATION | TERMINATE
        if(handle==IntPtr.Zero) throw new Win32Exception(Marshal.GetLastWin32Error());
        try {
            var path=new StringBuilder(32768);uint length=32768;
            if(!QueryFullProcessImageName(handle,0,path,ref length)) throw new Win32Exception(Marshal.GetLastWin32Error());
            long creation,exit,kernel,user;
            if(!GetProcessTimes(handle,out creation,out exit,out kernel,out user)) throw new Win32Exception(Marshal.GetLastWin32Error());
            if(!String.Equals(path.ToString(),expectedPath,StringComparison.OrdinalIgnoreCase) ||
                DateTime.FromFileTimeUtc(creation).Ticks!=expectedUtcTicks)
                throw new InvalidOperationException("Process image or exact creation time mismatch. No termination.");
            if(WaitForSingleObject(handle,0)!=258) throw new InvalidOperationException("Target already exited or cannot be verified live.");
        } catch {Dispose();throw;}
    }
    public void StopOnce() {
        if(attempted || handle==IntPtr.Zero) throw new InvalidOperationException("No second termination attempt allowed.");
        if(WaitForSingleObject(handle,0)!=258) throw new InvalidOperationException("Target is no longer verified live. No termination.");
        attempted=true;
        if(!TerminateProcess(handle,1)) throw new Win32Exception(Marshal.GetLastWin32Error());
        uint result=WaitForSingleObject(handle,60000);
        if(result!=0) throw new InvalidOperationException("Process exit not confirmed within deadline. Stop and report; do not copy or retry.");
    }
    public void Dispose() {if(handle!=IntPtr.Zero){CloseHandle(handle);handle=IntPtr.Zero;}}
}
'@
}
$startTicks = [DateTimeOffset]::Parse([string]$identity.StartTimeUTC).UtcDateTime.Ticks
$target = New-Object AtikhaControlledStopTarget -ArgumentList $expectedId,$serverPath,$startTicks
try {
    Copy-Item -LiteralPath 'C:\xampp\mysql\data\mysql_error.log' `
        -Destination (Join-Path $evidence 'mysql_error.before-controlled-stop.log')
    Assert-ServerOwnership
    # Record the attempt before the single call. An error never authorizes a retry.
    $record = [ordered]@{
        AttemptedAt=(Get-Date).ToString('o'); ProcessId=$expectedId
        StartTimeUTC=$identity.StartTimeUTC; Executable=$serverPath
        Method='One explicitly authorized controlled TerminateProcess call on a verified held handle'
        Graceful=$false; NoDatabaseCopyYet=$true
    } | ConvertTo-Json
    $attemptBytes = [Text.Encoding]::UTF8.GetBytes($record)
    $attemptStream = [IO.File]::Open($attemptFile,[IO.FileMode]::CreateNew,[IO.FileAccess]::Write,[IO.FileShare]::None)
    try {$attemptStream.Write($attemptBytes,0,$attemptBytes.Length);$attemptStream.Flush($true)} finally {$attemptStream.Dispose()}
    $target.StopOnce()
} finally {
    $target.Dispose()
}

# Confirm there was no restart or other database process before allowing the next gate.
Assert-QuietClients
if (@(Get-CimInstance Win32_Process | Where-Object { $_.Name -in 'mysqld.exe','mariadbd.exe' }).Count -or
    @(Get-NetTCPConnection -State Listen -ErrorAction Stop |
        Where-Object { $_.LocalPort -in 3306,3307 }).Count) {
    throw 'A database process/listener remains or restarted. Do not stop another process or copy files. Report this result.'
}
[ordered]@{
    VerifiedStoppedAt=(Get-Date).ToString('o'); ProcessId=$expectedId
    Graceful=$false; Method='Explicitly authorized controlled forced stop'
    NoDatabaseCopyYet=$true; NoRepair=$true; NoRestart=$true
} | ConvertTo-Json | Set-Content -LiteralPath $stoppedFile -Encoding UTF8
Write-Host 'CONTROLLED_STOP_VERIFIED. MySQL and Apache are stopped. No copy, check, repair or restart ran.'
Write-Host "Private evidence: $evidence"
Write-Host 'Leave both servers stopped and send the terminal result before offline preservation.'
