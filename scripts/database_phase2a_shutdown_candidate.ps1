# Authorized manual continuation: one verified candidate graceful shutdown only; no startup/export/repair.
[CmdletBinding()]
param([switch]$GracefullyStopVerifiedCandidate)
$ErrorActionPreference = 'Stop'
if (-not $GracefullyStopVerifiedCandidate) { throw 'No action taken. Specify -GracefullyStopVerifiedCandidate for the shutdown-only continuation after explicit approval.' }

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

function Normalize-P2Path([string]$Path) {
    [IO.Path]::GetFullPath($Path.Replace('/','\')).TrimEnd('\')
}

function Initialize-P2ShutdownIdentity {
    if ('AtikhaP2ShutdownIdentity' -as [type]) {return}
    Add-Type -TypeDefinition @'
using System; using System.Text; using System.Runtime.InteropServices; using System.ComponentModel;
public sealed class AtikhaP2ShutdownIdentity : IDisposable {
 private IntPtr handle;
 public readonly int Id; public readonly string Image; public readonly long CreationTicks;
 [DllImport("kernel32.dll",SetLastError=true)] static extern IntPtr OpenProcess(uint access,bool inherit,int id);
 [DllImport("kernel32.dll",CharSet=CharSet.Unicode,SetLastError=true)] static extern bool QueryFullProcessImageName(IntPtr process,uint flags,StringBuilder path,ref uint size);
 [DllImport("kernel32.dll",SetLastError=true)] static extern bool GetProcessTimes(IntPtr process,out long c,out long e,out long k,out long u);
 [DllImport("kernel32.dll",SetLastError=true)] static extern bool GetExitCodeProcess(IntPtr process,out uint code);
 [DllImport("kernel32.dll")] static extern uint WaitForSingleObject(IntPtr process,uint timeout);
 [DllImport("kernel32.dll")] static extern bool CloseHandle(IntPtr process);
 [DllImport("shell32.dll",CharSet=CharSet.Unicode,SetLastError=true)] static extern IntPtr CommandLineToArgvW(string command,out int count);
 [DllImport("kernel32.dll")] static extern IntPtr LocalFree(IntPtr memory);
 public AtikhaP2ShutdownIdentity(int id,string image,long creation) {
  Id=id;Image=image;CreationTicks=creation;
  handle=OpenProcess(0x00101000,false,id); // SYNCHRONIZE | QUERY_LIMITED_INFORMATION; no termination rights.
  if(handle==IntPtr.Zero) throw new Win32Exception(Marshal.GetLastWin32Error());
  try {Verify();}catch{Dispose();throw;}
 }
 public void Verify() {
  if(handle==IntPtr.Zero || WaitForSingleObject(handle,0)!=258)throw new InvalidOperationException("Held candidate is absent/exited.");
  var path=new StringBuilder(32768);uint size=32768;long c,e,k,u;
  if(!QueryFullProcessImageName(handle,0,path,ref size)||!GetProcessTimes(handle,out c,out e,out k,out u))throw new Win32Exception(Marshal.GetLastWin32Error());
  if(!String.Equals(path.ToString(),Image,StringComparison.OrdinalIgnoreCase)||DateTime.FromFileTimeUtc(c).Ticks!=CreationTicks)
   throw new InvalidOperationException("Exact process image/creation mismatch.");
 }
 public bool HasExited() {if(handle==IntPtr.Zero)throw new InvalidOperationException("Handle closed.");return WaitForSingleObject(handle,0)==0;}
 public bool WaitForExit(uint timeout) {if(handle==IntPtr.Zero)throw new InvalidOperationException("Handle closed.");return WaitForSingleObject(handle,timeout)==0;}
 public uint ExitCode() {uint code;if(!HasExited()||!GetExitCodeProcess(handle,out code))throw new InvalidOperationException("Exit not verified.");return code;}
 public static string[] SplitCommand(string command) {
  int count;IntPtr memory=CommandLineToArgvW(command,out count);
  if(memory==IntPtr.Zero)throw new Win32Exception(Marshal.GetLastWin32Error());
  try {var args=new string[count];for(int i=0;i<count;i++)args[i]=Marshal.PtrToStringUni(Marshal.ReadIntPtr(memory,i*IntPtr.Size));return args;}finally{LocalFree(memory);}
 }
 public void Dispose() {if(handle!=IntPtr.Zero){CloseHandle(handle);handle=IntPtr.Zero;}}
}
'@
}

function Assert-P2ShutdownCommand([string]$Command,[string]$Exe,[string]$Ini) {
    $parts=[AtikhaP2ShutdownIdentity]::SplitCommand($Command)
    if ($parts.Count -ne 2 -or (Normalize-P2Path $parts[0]) -ine (Normalize-P2Path $Exe) -or
        -not $parts[1].StartsWith('--defaults-file=',[StringComparison]::OrdinalIgnoreCase) -or
        (Normalize-P2Path $parts[1].Substring(16)) -ine (Normalize-P2Path $Ini)) {
        throw 'Available command-line tokens do not match the single explicit candidate option file.'
    }
}

function Assert-P2ShutdownInventory([object[]]$Servers,[object[]]$Listeners,[int]$ExpectedId,[string]$Exe,[string]$Ini) {
    if ($Servers.Count -ne 1 -or $Servers[0].ProcessId -ne $ExpectedId -or
        $Listeners.Count -ne 1 -or $Listeners[0].LocalPort -ne 3307 -or $Listeners[0].LocalAddress -ne '127.0.0.1' -or $Listeners[0].OwningProcess -ne $ExpectedId) {
        throw 'Server/listener ownership differs. No SQL or shutdown permitted.'
    }
    if ($Servers[0].ExecutablePath -and (Normalize-P2Path $Servers[0].ExecutablePath) -ine (Normalize-P2Path $Exe)) {throw 'Available CIM image differs.'}
    if ($Servers[0].CommandLine) {Assert-P2ShutdownCommand ([string]$Servers[0].CommandLine) $Exe $Ini}
}

function Assert-P2ShutdownTarget([object]$Target,[string]$Root,[string]$IniHash) {
    $Target.Verify()
    if (@(Get-Process -Name httpd,mysql,mysqladmin,mysqldump,aria_chk,myisamchk,aria_read_log -ErrorAction SilentlyContinue).Count) {throw 'Apache or a diagnostic client/checker is active.'}
    $servers=@(Get-CimInstance Win32_Process | Where-Object {$_.Name -in 'mysqld.exe','mariadbd.exe'})
    $listeners=@(Get-NetTCPConnection -State Listen -ErrorAction Stop | Where-Object {$_.LocalPort -in 3306,3307})
    Assert-P2ShutdownInventory $servers $listeners $Target.Id $Target.Image (Join-Path $Root 'candidate.ini')
    if ((Get-FileHash -LiteralPath (Join-Path $Root 'candidate.ini') -Algorithm SHA256).Hash -ne $IniHash -or
        [IO.File]::ReadAllText((Join-Path $Root 'candidate.pid')).Trim() -ne [string]$Target.Id) {throw 'Candidate configuration/PID-file changed.'}
}

function Assert-P2ShutdownSql([string]$Output,[string]$Root,[int]$ExpectedId) {
    $lines=@($Output.Trim() -split "`r?`n")
    if ($lines.Count -ne 1) {throw 'Unexpected candidate SQL identity shape.'}
    $v=@($lines[0] -split "`t")
    if ($v.Count -ne 6 -or $v[0] -ne '3307' -or (Normalize-P2Path $v[1]) -ine (Normalize-P2Path (Join-Path $Root 'data')) -or
        (Normalize-P2Path $v[2]) -ine (Normalize-P2Path (Join-Path $Root 'candidate.pid')) -or
        $v[3] -notmatch '^10\.4\.32-MariaDB' -or $v[4] -ne '1' -or $v[5] -ne '0' -or
        [IO.File]::ReadAllText((Join-Path $Root 'candidate.pid')).Trim() -ne [string]$ExpectedId) {throw 'SQL candidate identity/read-only/recovery settings differ.'}
}

$attemptRoot='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d'
$root=Join-Path $attemptRoot 'candidate-start-20261020-174040-e060c0a8'
$exe='C:\xampp\mysql\bin\mysqld.exe'
$mysql='C:\xampp\mysql\bin\mysql.exe'
$admin='C:\xampp\mysql\bin\mysqladmin.exe'
$expectedId=9436
$expectedTicks=639280860697780893L
$iniHash='BA22ED1A3B9F61BB3C648455557D32EEA61F24CC55160D67670308396B9130A3'
$treeHash='B95EAEAC7F2F6097D87EFA862D44418C474ACDF2C35B5A5CD7DA3726708F4FCD'
$marker=Join-Path $root 'shutdown-only-attempt.json'
if ($env:MYSQL_PWD -or $env:MYSQL_HOME -or $env:MARIADB_HOME) {throw 'Unexpected database environment; stop without displaying it.'}
Assert-ContextDirectories $root
Assert-ContextDirectories (Join-Path $root 'logs')
if (Test-Path -LiteralPath $marker) {throw 'Shutdown-only continuation is already recorded; no retry.'}
$saved=[IO.File]::ReadAllText((Join-Path $root 'launched-process.json')) | ConvertFrom-Json
$result=[IO.File]::ReadAllText((Join-Path $root 'results.json')) | ConvertFrom-Json
$preservation=[IO.File]::ReadAllText((Join-Path $root 'preservation-after.json')) | ConvertFrom-Json
if ($saved.ProcessId -ne $expectedId -or $saved.CreationTicksUTC -ne $expectedTicks -or $saved.Executable -ine $exe -or
    $saved.IniSHA256 -ne $iniHash -or $saved.Ini -ine (Join-Path $root 'candidate.ini') -or -not $result.LaunchRecorded -or
    $result.AuthenticatedReady -or $result.ExportCompleted -or $null -ne $result.Shutdown -or -not $result.CandidateStillRunning -or
    $result.HaltReason -ne 'Candidate command/process inventory differs from the captured launch.' -or
    -not $preservation.ProtectedFiveTreesUnchanged -or $preservation.ExpectedSHA256 -ne $treeHash) {throw 'Saved failed-attempt evidence differs.'}
$pins=@{
    $exe='EE6380DB7277E0A4EBE0FF255EDD846EB3B4EE604804CF5DB3BDDE1BE44B7E8A'
    $mysql='A60D3CBC24984E3C1C5DEE3726ABC685EC6FFF58BCE3FBD763F9261C652FB73D'
    $admin='5D28FB1F6FAAF93604F1A46FB5D1133832EF94E90CC9D227ACFF7ADD12DEAA7C'
}
foreach ($path in $pins.Keys) {if ((Get-FileHash -LiteralPath $path).Hash -ne $pins[$path]) {throw 'Pinned binary changed.'}}
Initialize-P2ShutdownIdentity
$target=New-Object AtikhaP2ShutdownIdentity -ArgumentList $expectedId,$exe,$expectedTicks
$halt=$null;$stopped=$false;$sqlVerified=$false;$shutdownRequested=$false
try {
    Assert-P2ShutdownTarget $target $root $iniHash
    Write-CheckJson $marker ([ordered]@{
        StartedAt=(Get-Date).ToString('o');Scope='One table-free identity query then one graceful shutdown only'
        ProcessId=$expectedId;CreationTicksUTC=$expectedTicks;Executable=$exe;IniSHA256=$iniHash
        NoStartup=$true;NoExport=$true;NoForcedStop=$true;NoRepair=$true
    })
    Write-Host 'Revalidating the existing candidate with one bounded table-free identity query.'
    $argsSql=@('--no-defaults','--protocol=TCP','--host=127.0.0.1','--port=3307','--user=root',
        '--connect-timeout=3','--batch','--raw','--skip-column-names',
        '--execute=SELECT @@port,@@datadir,@@pid_file,VERSION(),@@global.read_only,@@global.innodb_force_recovery;')
    $out=Join-Path $root 'logs\shutdown-only-identity.stdout.log';$err=Join-Path $root 'logs\shutdown-only-identity.stderr.log'
    $run=Invoke-BoundedCheck $mysql $argsSql $root $out $err 10000
    if ($run.TimedOut -or $run.ExitCode -ne 0 -or [IO.File]::ReadAllText($err).Trim().Length) {throw 'Identity query failed. No shutdown/force-stop/retry.'}
    Assert-P2ShutdownSql ([IO.File]::ReadAllText($out)) $root $expectedId
    $sqlVerified=$true
    Assert-P2ShutdownTarget $target $root $iniHash
    Write-CheckJson (Join-Path $root 'shutdown-only-request.json') ([ordered]@{
        RequestedAt=(Get-Date).ToString('o');ProcessId=$expectedId;SqlIdentityVerified=$true;NoForce=$true
    })
    Write-Host 'Requesting one authenticated graceful shutdown of the verified port-3307 candidate.'
    $shutdownRequested=$true
    $argsAdmin=@('--no-defaults','--protocol=TCP','--host=127.0.0.1','--port=3307','--user=root',
        '--connect-timeout=3','--shutdown-timeout=30','shutdown')
    $out=Join-Path $root 'logs\shutdown-only-admin.stdout.log';$err=Join-Path $root 'logs\shutdown-only-admin.stderr.log'
    $run=Invoke-BoundedCheck $admin $argsAdmin $root $out $err 15000
    if ($run.TimedOut -or $run.ExitCode -ne 0 -or [IO.File]::ReadAllText($err).Trim().Length) {throw 'Graceful request failed. Leave candidate files untouched; no forced stop.'}
    if (-not $target.WaitForExit(60000)) {throw 'Candidate exit not confirmed. No forced stop or retry.'}
    if ($target.ExitCode() -ne 0) {throw 'Candidate exited abnormally. Preserve evidence.'}
    Assert-CheckOffline
    $stopped=$true
} catch {$halt=$_.Exception.Message}
$stillRunning=-not $target.HasExited()
$target.Dispose()
Write-CheckJson (Join-Path $root 'shutdown-only-result.json') ([ordered]@{
    CapturedAt=(Get-Date).ToString('o');SqlIdentityVerified=$sqlVerified;ShutdownRequested=$shutdownRequested
    GracefullyStopped=$stopped;CandidateStillRunning=$stillRunning;HaltReason=$halt
    NoStartup=$true;NoExport=$true;NoForcedStop=$true;NoRepair=$true
})
if ($stopped) {
    Write-Host 'Candidate exited. Verifying the five protected original/earlier trees without copying anything.'
    foreach ($protected in @('C:\xampp\mysql\data',(Join-Path $attemptRoot 'preserved\data'),(Join-Path $attemptRoot 'examine\data'),
        (Join-Path $attemptRoot 'check-context-20261020-043052-9c47421f\data'),(Join-Path $attemptRoot 'log-review-20261020-044727-5f056f22\data'))) {
        Assert-ContextDirectories $protected
        if ((Get-CheckTree $protected).TreeSHA256 -ne $treeHash) {throw 'A protected tree changed. Stop and preserve all evidence.'}
    }
    Assert-CheckOffline
    Write-CheckJson (Join-Path $root 'shutdown-only-preservation.json') ([ordered]@{
        VerifiedAt=(Get-Date).ToString('o');ProtectedFiveTreesUnchanged=$true;TreeSHA256=$treeHash;AllServersStopped=$true
    })
    Write-Host 'PHASE2A_CANDIDATE_GRACEFULLY_STOPPED. Five protected trees are unchanged; no startup/export retry ran.'
} else {
    Write-Host 'PHASE2A_SHUTDOWN_ONLY_STOPPED_FOR_REVIEW.'
    Write-Host $halt
    if ($stillRunning) {Write-Host 'Candidate remains running. Do not force-stop it or touch its files.'}
}
Write-Host "Private evidence: $root"
Write-Host 'Keep XAMPP stopped. Send the terminal result; do not rerun.'

