# Function-only synthetic tests; never invokes the cutover main, a database or server.
function Invoke-P4PreparationTests([string]$Workspace,[string]$TestRoot){
    $ErrorActionPreference='Stop'
    foreach($file in @('database_phase2a_candidate_corrected.ps1','database_phase2b_validate_restore.ps1','database_phase3b_complete.ps1','database_phase4_cutover.ps1')){
        $tokens=$null;$errors=$null;$ast=[Management.Automation.Language.Parser]::ParseFile((Join-Path $Workspace "scripts\$file"),[ref]$tokens,[ref]$errors);if($errors.Count){throw 'Parse failure.'}
        foreach($n in $ast.EndBlock.Statements){if($n -is [Management.Automation.Language.FunctionDefinitionAst] -and $n.Name -ne 'Get-P2Settings'){. ([scriptblock]::Create($n.Extent.Text))}elseif($n.Name -eq 'Get-P2Settings' -and $file -eq 'database_phase2a_candidate_corrected.ps1'){. ([scriptblock]::Create($n.Extent.Text.Replace('function Get-P2Settings(','function Get-P4TestBaseSettings(')));$base=${function:Get-P4TestBaseSettings}}}
    }
    if(Test-Path -LiteralPath $TestRoot){throw 'Fresh synthetic directory required.'}
    if(-not ([IO.Path]::GetFullPath($TestRoot)).StartsWith((Join-Path $Workspace '.migration-private\'),[StringComparison]::OrdinalIgnoreCase)){throw 'Synthetic path outside workspace private storage.'}
    New-Item -ItemType Directory -Path $TestRoot|Out-Null
    $checks=New-Object 'System.Collections.Generic.List[string]'
    function Check([string]$Name,[bool]$Ok){if(-not $Ok){throw "FAILED: $Name"};$checks.Add($Name)}
    function Reject([string]$Name,[scriptblock]$Action){$bad=$false;try{&$Action|Out-Null}catch{$bad=$true};Check $Name $bad}
    function Clone([object]$Value){$Value|ConvertTo-Json -Depth 30 -Compress|ConvertFrom-Json}
    $prior='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase2b-20261021-021041-339e0626'
    $r=[IO.File]::ReadAllText((Join-Path $prior 'results.json'))|ConvertFrom-Json
    $reports=@();$stops=@();foreach($role in @('validation','restore')){$reports+=([IO.File]::ReadAllText((Join-Path $prior "$role\logs\inspection.json"))|ConvertFrom-Json);$stops+=([IO.File]::ReadAllText((Join-Path $prior "$role\shutdown-result.json"))|ConvertFrom-Json)}
    Assert-P4Prior $r $reports $stops;Check 'actual complete rehearsal accepted' $true
    foreach($k in @('Passed','RehearsalFilenamesRecovered','ValidationPassed','RestorePassed','ExactApplicationRestoreMatched','ProtectedSeventeenDatabaseTreesAndTwoUploadsUnchanged','AllServersStopped','NoDatabaseRepair','NoWorkingChanges','NoForcedStop','NoMigration','NoCutover')){
        $bad=Clone $r;$bad.$k=$false;Reject ('missing predecessor proof '+$k) {Assert-P4Prior $bad $reports $stops}
    }
    $bad=Clone $r;$bad.CandidateStillRunning=$true;Reject 'running predecessor rejected' {Assert-P4Prior $bad $reports $stops}
    $bad=Clone $reports;$bad[0].checked_objects=64;Reject 'partial predecessor checks rejected' {Assert-P4Prior $r $bad $stops}
    $bad=Clone $stops;$bad[0].Client.TimedOut=$true;Reject 'unclean prior shutdown rejected' {Assert-P4Prior $r $reports $bad}
    $data='C:\xampp\mysql\data';$stage='C:\xampp\mysql\data-recovered-stage-20990101-010101-01234567';$archive='C:\xampp\mysql\data-before-recovery-20990101-010101-01234567';$root='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase4-20990101-010101-01234567'
    Assert-P4Paths $data $stage $archive $root;Check 'exact fresh production path shape accepted without moves' $true
    Reject 'arbitrary working path rejected' {Assert-P4Paths $TestRoot $stage $archive $root}
    Reject 'nested staging path rejected' {Assert-P4Paths $data ($data+'\data-recovered-stage-20990101-010101-01234567') $archive $root}
    Reject 'arbitrary archive rejected' {Assert-P4Paths $data $stage 'C:\xampp\mysql\backup' $root}
    Reject 'outside evidence root rejected' {Assert-P4Paths $data $stage $archive $TestRoot}
    $settings=Get-P4Settings $root (&$base $root)
    Check 'normal port selected' ($settings.port -ceq '3306')
    foreach($k in @('datadir','aria-log-dir-path','innodb-data-home-dir','innodb-log-group-home-dir','innodb-undo-directory')){Check ('working path '+$k) ($settings[$k] -ceq 'C:/xampp/mysql/data')}
    foreach($k in @('read-only','skip-slave-start')){Check ('safety enabled '+$k) ($settings[$k] -ceq '1')}
    foreach($k in @('aria-recover-options','myisam-recover-options','event-scheduler')){Check ('automatic work disabled '+$k) ($settings[$k] -ceq 'OFF')}
    Check 'no grants bypass' ($settings['skip-grant-tables'] -ceq '0')
    Check 'no force recovery' ($settings['innodb-force-recovery'] -ceq '0')
    Check 'runtime files stay in evidence root' ($settings['pid-file'] -ceq ($root.Replace('\','/')+'/candidate.pid') -and $settings['log-error'].StartsWith($root.Replace('\','/')))
    $argsSql=@(Get-P4ClientArguments 'SELECT 1');Check 'client uses no-defaults and exact working port' ($argsSql.Count -eq 11 -and $argsSql[0] -ceq '--no-defaults' -and $argsSql -contains '--port=3306' -and $argsSql[-1] -ceq '--execute=SELECT 1')
    $script:probe='C:\private app\probe.php';$script:manifest='C:\private manifest.json';$script:manifestHash='A'*64
    $args=@(Get-P4ProbeArguments 'C:\private root' 'C:\private output.json' '123');Check 'separate probe arguments' ($args.Count -eq 10 -and $args[-1] -ceq '--pid=123' -and $args[-3] -ceq '--manifest=C:\private manifest.json')
    $args=@(Get-P4ProbeArguments 'C:\private root' 'C:\private output.json');Check 'file recovery distinct from inspection' ($args[-1] -ceq '--recover-files')
    Check 'providers disabled' ($args -contains 'allow_url_fopen=0' -and $args -contains 'disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail')
    $pidroot=Join-Path $TestRoot 'identity';New-Item -ItemType Directory -Path $pidroot|Out-Null;[IO.File]::WriteAllText((Join-Path $pidroot 'candidate.pid'),'123')
    $values=@('3306','C:/xampp/mysql/data/',($pidroot.Replace('\','/')+'/candidate.pid'),'10.4.32-MariaDB','1','0','OFF','0','0','0')
    Assert-P4Identity ($values -join "`t") $pidroot 123;Check 'working SQL identity accepted' $true
    foreach($i in @(0,1,2,3,4,5,6,7,8,9)){$bad=@($values);$bad[$i]='wrong';Reject ('wrong SQL identity field '+$i) {Assert-P4Identity ($bad -join "`t") $pidroot 123}}
    Reject 'wrong SQL PID rejected' {Assert-P4Identity ($values -join "`t") $pidroot 124}
    $servers=@([pscustomobject]@{ProcessId=123;ExecutablePath=$null;CommandLine=$null});$listeners=@([pscustomobject]@{LocalPort=3306;LocalAddress='127.0.0.1';OwningProcess=123})
    Assert-P4Inventory $servers $listeners 123 'server.exe' 'ini' $true;Check 'only exact port owner accepted' $true
    $bad=Clone $listeners;$bad[0].LocalPort=3307;Reject 'old candidate port rejected' {Assert-P4Inventory $servers $bad 123 'server.exe' 'ini' $true}
    $bad=Clone $listeners;$bad[0].LocalAddress='0.0.0.0';Reject 'nonloopback rejected' {Assert-P4Inventory $servers $bad 123 'server.exe' 'ini' $true}
    $bad=Clone $listeners;$bad[0].OwningProcess=124;Reject 'different listener owner rejected' {Assert-P4Inventory $servers $bad 123 'server.exe' 'ini' $true}
    Reject 'extra server rejected' {Assert-P4Inventory @($servers[0],$servers[0]) $listeners 123 'server.exe' 'ini' $true}
    Reject 'missing listener rejected' {Assert-P4Inventory $servers @() 123 'server.exe' 'ini' $true}
    $good=Clone $reports[0];$good.working_database_accessed=$true;$good|Add-Member -NotePropertyName baseline_matches_original_before_after -NotePropertyValue $true;$good|Add-Member -NotePropertyName evidence_matches_rehearsal -NotePropertyValue $true
    Assert-P4Report $good;Check 'full working report accepted' $true
    foreach($k in @('passed','working_database_accessed','baseline_matches_original_before_after','evidence_matches_rehearsal')){$bad=Clone $good;$bad.$k=$false;Reject ('missing working proof '+$k) {Assert-P4Report $bad}}
    $bad=Clone $good;$bad.writes_performed=$true;Reject 'data writes rejected' {Assert-P4Report $bad}
    $bad=Clone $good;$bad.checked_objects=64;Reject 'partial working checks rejected' {Assert-P4Report $bad}
    $bad=Clone $good;@($bad.foreign_keys.PSObject.Properties)[0].Value='1';Reject 'working orphan rejected' {Assert-P4Report $bad}
    $bad=Clone $good;$bad.integrity_problems=@('failure');Reject 'working integrity error rejected' {Assert-P4Report $bad}
    $out=[ordered]@{Passed=$checks.Count;Checks=@($checks);OperationalMainExecuted=$false;SQLExecuted=$false;WorkingFilesChanged=$false}
    Write-CheckJson (Join-Path $TestRoot 'results.json') $out;Write-Output ("PHASE4_HELPER_TESTS_PASSED: "+$checks.Count)
}
