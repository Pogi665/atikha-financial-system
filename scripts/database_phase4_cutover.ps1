# One guarded whole-directory promotion. Leaves recovered MySQL and Apache STOPPED.
[CmdletBinding()]
param([switch]$PromoteVerifiedRecovery)
$ErrorActionPreference='Stop'
if(-not $PromoteVerifiedRecovery){throw 'No action taken. Specify -PromoteVerifiedRecovery for the documented working cutover.'}

function Assert-P4Paths([string]$Data,[string]$Stage,[string]$Archive,[string]$Root,[switch]$StageReady){
    if((Normalize-P2Path $Data) -ine 'C:\xampp\mysql\data' -or
       (Normalize-P2Path ([IO.Path]::GetDirectoryName($Stage))) -ine 'C:\xampp\mysql' -or
       (Normalize-P2Path ([IO.Path]::GetDirectoryName($Archive))) -ine 'C:\xampp\mysql' -or
       [IO.Path]::GetFileName($Stage) -cnotmatch '^data-recovered-stage-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}$' -or
       [IO.Path]::GetFileName($Archive) -cnotmatch '^data-before-recovery-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}$' -or
       (Normalize-P2Path $Root) -notmatch '^C:\\xampp\\mysql\\recovery-exports\\diagnostic-20261020-032731-25c55e2d\\phase4-[0-9]{8}-[0-9]{6}-[a-f0-9]{8}$'){
        throw 'Cutover paths outside the approved locations.'
    }
    Assert-ContextDirectories ([IO.Path]::GetDirectoryName($Data))
    if(Test-Path -LiteralPath $Archive){throw 'Cutover archive already exists. No overwrite.'}
    if($StageReady){Assert-ContextDirectories $Stage}else{if(Test-Path -LiteralPath $Stage){throw 'Cutover stage already exists. No overwrite.'}}
}
function Assert-P4Prior([object]$Result,[object[]]$Reports,[object[]]$Stops){
    foreach($k in @('Passed','RehearsalFilenamesRecovered','ValidationPassed','RestorePassed','ExactApplicationRestoreMatched','ProtectedSeventeenDatabaseTreesAndTwoUploadsUnchanged','AllServersStopped','NoDatabaseRepair','NoWorkingChanges','NoForcedStop','NoMigration','NoCutover')){
        if($Result.$k -ne $true){throw 'Completed rehearsal proof missing.'}
    }
    if($Result.HaltReason -or $Result.ShutdownIssue -or $Result.CandidateStillRunning -ne $false -or $Reports.Count -ne 2 -or $Stops.Count -ne 2){throw 'Prior rehearsal needs review.'}
    foreach($r in $Reports){Assert-P3DReport $r}
    Assert-P2BEqual $Reports[0].snapshot $Reports[1].snapshot;Assert-P2BEqual $Reports[0].evidence $Reports[1].evidence
    Assert-P2BEqual $Reports[0].tables_priv_scan $Reports[1].tables_priv_scan
    foreach($s in $Stops){if($s.State -cne 'gracefully-stopped' -or $null -eq $s.ExitCode -or $s.ExitCode -ne 0 -or $null -eq $s.Client.ExitCode -or $s.Client.ExitCode -ne 0 -or $s.Client.TimedOut -ne $false){throw 'Prior clean stop required.'}}
}
function Get-P4Settings([string]$Root,[object]$Settings){
    $Settings.port='3306'
    foreach($k in @('datadir','aria-log-dir-path','innodb-data-home-dir','innodb-log-group-home-dir','innodb-undo-directory')){$Settings[$k]='C:/xampp/mysql/data'}
    foreach($k in @('relay-log-info-file','master-info-file','log-tc')){$Settings[$k]='C:/xampp/mysql/data/'+@{'relay-log-info-file'='relay-log.info';'master-info-file'='master.info';'log-tc'='tc.log'}[$k]}
    return $Settings
}
function Get-P4ClientArguments([string]$Sql){
    @('--no-defaults','--protocol=TCP','--host=127.0.0.1','--port=3306','--user=root','--connect-timeout=3','--default-character-set=utf8mb4','--batch','--raw','--skip-column-names',"--execute=$Sql")
}
function Assert-P4Help([string]$Output,[string]$Errors,[int]$ExitCode,[bool]$TimedOut,[string]$Root,[string]$EmptyData){
    # Help may inspect mysql.plugin. Override only its datadir to an empty private directory.
    # Verify that override first; the normal settings audit still checks every other option.
    $map=Get-P2HelpMap $Output
    if(-not $map.ContainsKey('datadir') -or (Normalize-P2Path $map.datadir) -ine (Normalize-P2Path $EmptyData)){throw 'Help did not use the empty private data directory.'}
    $rows=@($Output -split "`r?`n");$count=0
    for($i=0;$i -lt $rows.Count;$i++){if($rows[$i] -cmatch '^datadir\s{2,}'){$rows[$i]='datadir                           C:/xampp/mysql/data';$count++}}
    if($count -ne 1){throw 'Ambiguous help datadir output.'}
    Assert-P2Help ($rows -join "`r`n") $Errors $ExitCode $TimedOut $Root
}
function Assert-P4Inventory([object[]]$Servers,[object[]]$Listeners,[int]$ExpectedId,[string]$Exe,[string]$Ini,[bool]$RequireListener){
    if($Servers.Count -ne 1 -or $Servers[0].ProcessId -ne $ExpectedId){throw 'Unexpected working server inventory.'}
    if($Servers[0].ExecutablePath -and (Normalize-P2Path $Servers[0].ExecutablePath) -ine (Normalize-P2Path $Exe)){throw 'Wrong working server executable.'}
    if($Servers[0].CommandLine){Assert-P2Command ([string]$Servers[0].CommandLine) $Exe $Ini}
    if(@($Listeners|Where-Object {$_.LocalPort -ne 3306 -or $_.LocalAddress -cne '127.0.0.1' -or $_.OwningProcess -ne $ExpectedId}).Count -or ($RequireListener -and $Listeners.Count -ne 1)){throw 'Working listener identity differs.'}
}
function Assert-P4Identity([string]$Output,[string]$Root,[int]$ExpectedId){
    $rows=@($Output.Trim() -split "`r?`n");$v=@($rows[0] -split "`t")
    if($rows.Count -ne 1 -or $v.Count -ne 10 -or $v[0] -cne '3306' -or (Normalize-P2Path $v[1]) -ine 'C:\xampp\mysql\data' -or
       (Normalize-P2Path $v[2]) -ine (Join-Path $Root 'candidate.pid') -or $v[3] -notmatch '^10\.4\.32-MariaDB' -or $v[4] -cne '1' -or $v[5] -cne '0' -or $v[6] -ine 'OFF' -or $v[7] -cne '0' -or $v[8] -cne '0' -or $v[9] -cne '0'){
        throw 'Authenticated working recovery identity differs.'
    }
    if([IO.File]::ReadAllText((Join-Path $Root 'candidate.pid')).Trim() -cne [string]$ExpectedId){throw 'Working PID file differs.'}
}
function Get-P4ProbeArguments([string]$Root,[string]$Output,[string]$PidValue=''){
    $a=@('-d','allow_url_fopen=0','-d','disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail',
        $script:probe,"--root=$Root","--output=$Output","--manifest=$script:manifest","--manifest-sha=$script:manifestHash")
    if($PidValue){$a+="--pid=$PidValue"}else{$a+='--recover-files'}
    return $a
}
function Assert-P4Report([object]$Report){
    if($Report.passed -ne $true -or $Report.working_database_accessed -ne $true -or $Report.writes_performed -ne $false -or
       $Report.baseline_matches_original_before_after -ne $true -or $Report.evidence_matches_rehearsal -ne $true -or $Report.checked_objects -ne 65 -or
       @($Report.foreign_keys.PSObject.Properties).Count -ne 70 -or @($Report.foreign_keys.PSObject.Properties|Where-Object {$_.Value -cne '0'}).Count -or
       $null -eq $Report.integrity_problems -or $Report.integrity_problems.Count -ne 0){throw 'Working inspection did not fully pass.'}
}
function Stop-P4Gracefully([object]$Context,[string]$Admin){
    $Context.Process.Refresh();if($Context.Process.HasExited){throw 'Working server exited unexpectedly; inspect before further action.'}
    Assert-P2Process $Context.Process $Context.Server $Context.Ini $Context.Ticks $Context.Target -RequireListener -EvidenceDirectory $Context.Logs
    $mark=Join-Path $Context.Root 'graceful-shutdown-attempt.json'
    if(Test-Path -LiteralPath $mark){throw 'Shutdown already attempted. No retry.'}
    $argsAdmin=@('--no-defaults','--protocol=TCP','--host=127.0.0.1','--port=3306','--user=root','--connect-timeout=3','--shutdown-timeout=30','shutdown')
    Write-CheckJson $mark ([ordered]@{At=(Get-Date).ToString('o');ProcessId=$Context.Process.Id;CreationTicks=$Context.Ticks})
    $run=Invoke-BoundedCheck $Admin $argsAdmin $Context.Root (Join-Path $Context.Logs 'shutdown.stdout.log') (Join-Path $Context.Logs 'shutdown.stderr.log') 15000
    if($run.TimedOut -or $run.ExitCode -ne 0 -or [IO.File]::ReadAllText((Join-Path $Context.Logs 'shutdown.stderr.log')).Trim().Length){throw 'Working graceful shutdown failed. No force/retry.'}
    if(-not $Context.Process.WaitForExit(60000)){throw 'Working exit deadline reached. No force-stop.'}
    $Context.Process.WaitForExit();if($null -eq $Context.Process.ExitCode -or $Context.Process.ExitCode -ne 0){throw 'Abnormal working server exit.'}
    return [pscustomobject]@{State='gracefully-stopped';ExitCode=$Context.Process.ExitCode;Client=$run}
}

# Import pinned function definitions only. No predecessor operational main executes.
$imports=@{'database_phase2a_candidate_corrected.ps1'='E78CFCAA9979E091B1D0E28E6974D27F496937FAAAE420D233456AD31145067B';'database_phase2b_validate_restore.ps1'='F6FB16745C53E618ECE316F75BF1520AA98B1DBBBD7BDDEEE54DCC4E7D9D3744';'database_phase3b_complete.ps1'='582B1F49B064A164FF0DF5DF46FB63849A9BA4844CACF965BE247DFBB6138FCA'}
foreach($file in @('database_phase2a_candidate_corrected.ps1','database_phase2b_validate_restore.ps1','database_phase3b_complete.ps1')){
    $path=Join-Path $PSScriptRoot $file;if((Get-FileHash -LiteralPath $path).Hash -cne $imports[$file]){throw 'Predecessor helper changed.'}
    $tokens=$null;$errors=$null;$ast=[Management.Automation.Language.Parser]::ParseFile($path,[ref]$tokens,[ref]$errors);if($errors.Count){throw 'Predecessor parse failure.'}
    foreach($node in $ast.EndBlock.Statements){if($node -is [Management.Automation.Language.FunctionDefinitionAst]){. ([scriptblock]::Create($node.Extent.Text))}}
}
$script:P4BaseSettings=${function:Get-P2Settings}
function Get-P2Settings([string]$Root){Get-P4Settings $Root (& $script:P4BaseSettings $Root)}
Set-Item Function:Get-P2ClientArguments ${function:Get-P4ClientArguments}
Set-Item Function:Assert-P2ProcessInventory ${function:Assert-P4Inventory}
Set-Item Function:Assert-P2Identity ${function:Assert-P4Identity}
$workspace=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$manifest=Join-Path $workspace '.migration-private\phase4-cutover-inputs.json'
$manifestHash='DF2725B0033B493CAA373959B82C893F79C08C57C73B584797CA0387DB2C2F1B'
Assert-P2BPin $manifest $manifestHash;$spec=[IO.File]::ReadAllText($manifest)|ConvertFrom-Json
if($spec.Format -cne 'phase4-cutover-v1' -or $spec.Protected.Count -ne 22 -or $spec.Recoveries.Count -ne 3){throw 'Cutover specification incomplete.'}
$pins=@{};foreach($e in $spec.Pins.PSObject.Properties){$pins[$e.Name]=$e.Value;Assert-P2BPin $e.Name $e.Value}
$sourceManifest=Join-Path $PSScriptRoot 'database_phase2b_source_manifest.json';Assert-P2BSourceManifest $sourceManifest $workspace
$server='C:\xampp\mysql\bin\mysqld.exe';$mysql='C:\xampp\mysql\bin\mysql.exe';$admin='C:\xampp\mysql\bin\mysqladmin.exe';$php='C:\xampp\php\php.exe';$probe=Join-Path $PSScriptRoot 'database_phase4_probe.php'
$protected=@($spec.Protected|ForEach-Object {[pscustomobject]@{Path=$_.Root;Hash=$_.SHA256;Count=$_.Files}})
if(@($protected.Path|Select-Object -Unique).Count -ne 22){throw 'Duplicate protected sources.'}
$prior=$spec.CompletionRoot
$reports=@([IO.File]::ReadAllText((Join-Path $prior 'validation\logs\inspection.json'))|ConvertFrom-Json;[IO.File]::ReadAllText((Join-Path $prior 'restore\logs\inspection.json'))|ConvertFrom-Json)
$stops=@([IO.File]::ReadAllText((Join-Path $prior 'validation\shutdown-result.json'))|ConvertFrom-Json;[IO.File]::ReadAllText((Join-Path $prior 'restore\shutdown-result.json'))|ConvertFrom-Json)
Assert-P4Prior ([IO.File]::ReadAllText((Join-Path $prior 'results.json'))|ConvertFrom-Json) $reports $stops
if((Test-Path Env:MYSQL_PWD) -or (Test-Path Env:MYSQL_HOME) -or (Test-Path Env:MARIADB_HOME) -or (Test-Path Env:MYSQL_TEST_LOGIN_FILE)){throw 'Unexpected database client environment.'}
Assert-CheckOffline
$stamp=(Get-Date -Format 'yyyyMMdd-HHmmss')+'-'+[Guid]::NewGuid().ToString('N').Substring(0,8)
$root=Join-Path $spec.RecoveryBase ('phase4-'+$stamp);$stage='C:\xampp\mysql\data-recovered-stage-'+$stamp;$archive='C:\xampp\mysql\data-before-recovery-'+$stamp
$data=$spec.WorkingData;$uploads=$spec.WorkingUploads;$marker=Join-Path $spec.RecoveryBase 'evidence\phase4-cutover-attempt.json'
Assert-P4Paths $data $stage $archive $root;if(Test-Path -LiteralPath $marker){throw 'Cutover marker exists. No retry.'}
Write-Host 'Verifying all protected data, original uploads, completed rehearsal and source/tool/configuration pins. Keep XAMPP stopped.'
Assert-P2BProtected $protected
Assert-P3DUploadDelta ([IO.File]::ReadAllText((Join-Path $prior 'logs\uploads-before.json'))|ConvertFrom-Json) (Get-CheckTree $spec.RehearsalUploads) $spec.Recoveries
if((Get-CheckTree $data).TreeSHA256 -cne $spec.OriginalWorkingHash){throw 'Working database changed.'}
$bytes=(@((Get-CheckTree $spec.Source).Entries|Where-Object {$_.Kind -ceq 'F'}|Measure-Object Bytes -Sum)[0]).Sum
if(([IO.DriveInfo]::new('C:\')).AvailableFreeSpace -lt (2*$bytes+2GB)){throw 'Insufficient staging/headroom capacity.'}
New-Item -ItemType Directory -Path $root|Out-Null;foreach($d in @('logs','tmp','export','help-empty-data')){New-Item -ItemType Directory -Path (Join-Path $root $d)|Out-Null}
Write-CheckJson $marker ([ordered]@{At=(Get-Date).ToString('o');Root=$root;WorkingData=$data;Stage=$stage;Archive=$archive;OriginalHash=$spec.OriginalWorkingHash;Source=$spec.Source;SourceHash=$spec.SourceHash;NoAutomaticRollback=$true})
$promoted=$false;$oldMoved=$false;$filesAdded=$false;$verified=$false;$stopped=$false;$halt=$null;$shutdownIssue=$null;$ctx=$null;$process=$null
try {
    Write-Host 'Staging a complete verified recovered directory; preserving the original directory for rollback.'
    Copy-ContextData $spec.Source $stage (Join-Path $root 'logs\stage-copy.log')
    if((Get-CheckTree $stage).TreeSHA256 -cne $spec.SourceHash){throw 'Staging copy differs.'}
    $ini=Write-P2Ini $root;$iniHash=(Get-FileHash -LiteralPath $ini).Hash
    $emptyData=Join-Path $root 'help-empty-data'
    $help=Invoke-BoundedCheck $server @("--defaults-file=$ini","--datadir=$emptyData",'--verbose','--help') $root (Join-Path $root 'logs\help.stdout.log') (Join-Path $root 'logs\help.stderr.log') 15000
    Assert-P4Help ([IO.File]::ReadAllText((Join-Path $root 'logs\help.stdout.log'))) ([IO.File]::ReadAllText((Join-Path $root 'logs\help.stderr.log'))) $help.ExitCode $help.TimedOut $root $emptyData
    Assert-CheckOffline
    $stageInputs=@($protected|Where-Object {(Normalize-P2Path $_.Path) -ieq (Normalize-P2Path $data) -or (Normalize-P2Path $_.Path) -ieq (Normalize-P2Path $spec.Source) -or (Normalize-P2Path $_.Path) -ieq (Normalize-P2Path $uploads)})
    if($stageInputs.Count -ne 3){throw 'Staging input inventory incomplete.'};Assert-P2BProtected $stageInputs
    Write-Host 'Recovering exactly three verified receipt filenames in working uploads; existing files cannot be overwritten.'
    $before=Get-CheckTree $uploads;Write-CheckJson (Join-Path $root 'logs\uploads-before.json') $before
    $fileRun=Invoke-BoundedCheck $php @(Get-P4ProbeArguments $root (Join-Path $root 'logs\filename-recovery.json')) $root (Join-Path $root 'logs\files.stdout.log') (Join-Path $root 'logs\files.stderr.log') 30000
    Write-CheckJson (Join-Path $root 'logs\files-result.json') $fileRun
    if($fileRun.TimedOut -or $fileRun.ExitCode -ne 0 -or [IO.File]::ReadAllText((Join-Path $root 'logs\files.stderr.log')).Trim().Length){throw 'Working file recovery stopped.'}
    $fileReport=[IO.File]::ReadAllText((Join-Path $root 'logs\filename-recovery.json'))|ConvertFrom-Json
    if($fileReport.passed -ne $true -or $fileReport.file_recovery_passed -ne $true -or $fileReport.recovered_count -ne 3 -or $fileReport.working_database_accessed -ne $false){throw 'File recovery report incomplete.'}
    $after=Get-CheckTree $uploads;Assert-P3DUploadDelta $before $after $spec.Recoveries;Write-CheckJson (Join-Path $root 'logs\uploads-after.json') $after;$uploadHash=$after.TreeSHA256;$filesAdded=$true
    Assert-CheckOffline;Assert-P4Paths $data $stage $archive $root -StageReady
} catch {$halt=$_.Exception.Message}
# Promotion is deliberately separate from preparation; every filesystem target is revalidated first.
if(-not $halt){try{
    if((Get-CheckTree $data).TreeSHA256 -cne $spec.OriginalWorkingHash -or (Get-CheckTree $stage).TreeSHA256 -cne $spec.SourceHash -or (Test-Path -LiteralPath $archive)){throw 'Promotion preflight drift.'}
    Assert-CheckOffline;Assert-ContextDirectories $data;Assert-ContextDirectories $stage;Assert-ContextDirectories ([IO.Path]::GetDirectoryName($archive))
    Move-Item -LiteralPath $data -Destination $archive;$oldMoved=$true
    Write-CheckJson (Join-Path $root 'original-moved.json') ([ordered]@{Archive=$archive;SHA256=$spec.OriginalWorkingHash})
    Move-Item -LiteralPath $stage -Destination $data;$promoted=$true
    Write-CheckJson (Join-Path $root 'promoted.json') ([ordered]@{Data=$data;SourceSHA256=$spec.SourceHash})
    if((Get-CheckTree $data).TreeSHA256 -cne $spec.SourceHash -or (Get-CheckTree $archive).TreeSHA256 -cne $spec.OriginalWorkingHash){throw 'Promotion byte verification failed.'}
    foreach($p in $pins.Keys){Assert-P2BPin $p $pins[$p]};Assert-P2BPin $ini $iniHash;Assert-CheckOffline
    Initialize-P2CandidateIdentity
    Write-Host 'Starting recovered MySQL once on loopback port 3306 with Apache off, read-only/event/repair safeguards and full original-baseline checks.'
    Write-CheckJson (Join-Path $root 'launch-attempt.json') ([ordered]@{Executable=$server;Ini=$ini;IniHash=$iniHash})
    $process=Start-Process -FilePath $server -ArgumentList @(ConvertTo-ContextNativeArgument "--defaults-file=$ini") -WorkingDirectory $root -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $root 'logs\server.stdout.log') -RedirectStandardError (Join-Path $root 'logs\server.stderr.log')
    $null=$process.Handle;$ticks=$process.StartTime.ToUniversalTime().Ticks;$target=New-Object AtikhaP2CandidateIdentity -ArgumentList $process.Id,$server,$ticks
    $ctx=[pscustomobject]@{Process=$process;Target=$target;Server=$server;Ini=$ini;Ticks=$ticks;Mysql=$mysql;Root=$root;Logs=(Join-Path $root 'logs')}
    Write-CheckJson (Join-Path $root 'launched-process.json') ([ordered]@{ProcessId=$process.Id;CreationTicksUTC=$ticks;Ini=$ini})
    $watch=[Diagnostics.Stopwatch]::StartNew();$ready=$false
    while($watch.ElapsedMilliseconds -lt 60000){
        Assert-P2Process $process $server $ini $ticks $target -EvidenceDirectory $ctx.Logs
        if(Get-P2StartupIssue (Join-Path $ctx.Logs 'server-error.log')){throw 'Working startup warning/error. No retry.'}
        if((Read-P2Log (Join-Path $ctx.Logs 'server-error.log')) -match '(?i)ready for connections'){$ready=$true;break};Start-Sleep -Milliseconds 500
    }
    if(-not $ready){throw 'Working readiness deadline reached.'}
    $identity=Invoke-P2Sql $ctx 'identity' 'SELECT @@port,@@datadir,@@pid_file,VERSION(),@@global.read_only,@@global.innodb_force_recovery,@@global.event_scheduler,@@global.log_bin,@@global.general_log,@@global.slow_query_log;'
    Assert-P4Identity $identity $root $process.Id
    $vars="SHOW GLOBAL VARIABLES WHERE Variable_name IN ('datadir','tmpdir','slave_load_tmpdir','aria_log_dir_path','innodb_data_home_dir','innodb_log_group_home_dir','innodb_undo_directory','pid_file','socket','log_error','general_log_file','slow_query_log_file','relay_log','relay_log_index','relay_log_info_file','secure_file_priv','innodb_data_file_path','innodb_log_file_size','innodb_log_files_in_group','innodb_page_size','lower_case_table_names','innodb_buffer_pool_filename','aria_recover_options','myisam_recover_options','general_log','slow_query_log','named_pipe','bind_address','innodb_read_only','skip_networking');"
    Assert-P2Variables (Invoke-P2Sql $ctx 'effective-variables' $vars) $root
    $run=Invoke-BoundedCheck $php @(Get-P4ProbeArguments $root (Join-Path $ctx.Logs 'inspection.json') ([string]$process.Id)) $root (Join-Path $ctx.Logs 'probe.stdout.log') (Join-Path $ctx.Logs 'probe.stderr.log') 180000
    Write-CheckJson (Join-Path $ctx.Logs 'probe-result.json') $run
    if($run.TimedOut -or $run.ExitCode -ne 0 -or [IO.File]::ReadAllText((Join-Path $ctx.Logs 'probe.stderr.log')).Trim().Length){throw 'Working baseline/integrity/evidence inspection needs review.'}
    Assert-P2Process $process $server $ini $ticks $target -RequireListener -EvidenceDirectory $ctx.Logs
    Assert-P4Report ([IO.File]::ReadAllText((Join-Path $ctx.Logs 'inspection.json'))|ConvertFrom-Json)
    if(Get-P2StartupIssue (Join-Path $ctx.Logs 'server-error.log')){throw 'Working server log needs review.'}
    $verified=$true
}catch{$halt=$_.Exception.Message}finally{
    if($null -ne $ctx){try{$s=Stop-P4Gracefully $ctx $admin;Write-CheckJson (Join-Path $root 'shutdown-result.json') $s;$stopped=$true;$ctx.Target.Dispose()}catch{$shutdownIssue=$_.Exception.Message}}
}}
$unchanged=$false;$offline=$false
try{
    Assert-CheckOffline;$offline=$true
    $remaining=@($protected|Where-Object {(Normalize-P2Path $_.Path) -ine (Normalize-P2Path $data) -and (Normalize-P2Path $_.Path) -ine (Normalize-P2Path $uploads)})
    Assert-P2BProtected $remaining
    $originalNow=if($oldMoved){$archive}else{$data};if((Get-CheckTree $originalNow).TreeSHA256 -cne $spec.OriginalWorkingHash){throw 'Original database bytes changed.'}
    if($filesAdded -and (Get-CheckTree $uploads).TreeSHA256 -cne $uploadHash){throw 'Recovered uploads drifted.'}
    foreach($p in $pins.Keys){Assert-P2BPin $p $pins[$p]};Assert-P2BSourceManifest $sourceManifest $workspace
    if($promoted){Write-CheckJson (Join-Path $root 'working-data-after.json') (Get-CheckTree $data)}
    $unchanged=$true
}catch{if(-not $halt){$halt=$_.Exception.Message}}
$passed=$promoted -and $verified -and $stopped -and $unchanged -and $offline -and -not $halt -and -not $shutdownIssue
Write-CheckJson (Join-Path $root 'results.json') ([ordered]@{At=(Get-Date).ToString('o');Passed=$passed;HaltReason=$halt;ShutdownIssue=$shutdownIssue;OldDirectoryArchived=$oldMoved;RecoveredDirectoryPromoted=$promoted;ThreeWorkingReceiptNamesRecovered=$filesAdded;WorkingBaselineIntegrityEvidencePassed=$verified;GracefullyStopped=$stopped;OriginalAndProtectedSourcesUnchanged=$unchanged;AllServersStopped=$offline;NoMigration=$true;NoSQLImport=$true;NoTableRepair=$true;NoForcedStop=$true;NoConfigurationChange=$true;ApplicationLoginTested=$false;U3IntegrationRun=$false;Archive=$archive})
if($passed){Write-Host 'PHASE4_WORKING_RECOVERY_VERIFIED. Recovered directory is in place; full baseline/checks passed; MySQL and Apache are stopped. Startup/login acceptance remains separate.'}else{Write-Host 'PHASE4_STOPPED_FOR_REVIEW. Do not retry, force-stop, restore folders or start XAMPP.';Write-Host $halt;Write-Host $shutdownIssue}
Write-Host "Private cutover evidence: $root";Write-Host 'Keep XAMPP stopped and send the terminal result. Do not rerun.'
