# One authorized Phase 3-A index-only trial; working data and all old copies stay untouched.
# Previous helpers, attempt markers, protected data and export are never edited/reused.
[CmdletBinding()]
param([switch]$RunCorrectedIndexTrial)
$ErrorActionPreference='Stop'
if(-not $RunCorrectedIndexTrial){throw 'No action taken. Specify -RunCorrectedIndexTrial for the authorized copied-index trial.'}

# Import reviewed function definitions only; never execute either earlier operational main.
$functionSources=@{
    'database_phase2a_candidate_corrected.ps1'='E78CFCAA9979E091B1D0E28E6974D27F496937FAAAE420D233456AD31145067B'
    'database_phase2b_validate_restore.ps1'='F6FB16745C53E618ECE316F75BF1520AA98B1DBBBD7BDDEEE54DCC4E7D9D3744'
}
foreach($file in @('database_phase2a_candidate_corrected.ps1','database_phase2b_validate_restore.ps1')){
    $path=Join-Path $PSScriptRoot $file
    if((Get-FileHash -LiteralPath $path).Hash -ne $functionSources[$file]){throw 'Pinned earlier function source changed. No continuation.'}
    $tokens=$null;$errors=$null
    $ast=[Management.Automation.Language.Parser]::ParseFile($path,[ref]$tokens,[ref]$errors)
    if($errors.Count){throw 'Earlier function source does not parse.'}
    foreach($node in $ast.EndBlock.Statements){
        if($node -is [Management.Automation.Language.FunctionDefinitionAst]){. ([scriptblock]::Create($node.Extent.Text))}
    }
}



function Assert-P3CPrevious([object]$Result,[object]$Stop,[object]$Report,[object]$Failed){
    if($Result.Passed -ne $true -or $Result.HaltReason -or $Result.ShutdownIssue -or $Result.DiagnosticCompleted -ne $true -or
        $Result.ProtectedTenTreesUploadsExportUnchanged -ne $true -or $Result.AllServersStopped -ne $true -or $Result.CandidateStillRunning -ne $false -or
        $Result.NoRepair -ne $true -or $Result.NoTableChecks -ne $true -or $Result.NoImport -ne $true -or $Result.NoFinancialRows -ne $true -or
        $Result.NoWorkingChanges -ne $true -or $Result.NoForcedStop -ne $true -or $Result.NoMigration -ne $true -or $Result.NoCutover -ne $true -or
        $Stop.State -cne 'gracefully-stopped' -or $null -eq $Stop.ExitCode -or $Stop.ExitCode -ne 0 -or $null -eq $Stop.Client.ExitCode -or $Stop.Client.ExitCode -ne 0 -or $Stop.Client.TimedOut -ne $false -or
        $Report.passed -ne $true -or $Report.mode -cne 'plan-diagnostic' -or $Report.existing_scan_guard_accepted -ne $false -or
        $Failed.RepairAttempted -ne $false -or $Failed.CaptureCompleted -ne $false -or $Failed.AllServersStopped -ne $true -or $Failed.ProtectedNineTreesUploadsExportAndArtifactsUnchanged -ne $true){throw 'Reviewed diagnostic/no-repair gate differs.'}
    $plan=@($Report.explain)
    if($plan.Count -ne 1 -or $plan[0].type -cne 'system' -or $plan[0].table -cne 'tables_priv' -or $null -ne $plan[0].key -or $plan[0].rows -cne '1' -or
        $Report.scan_sql -cne 'SELECT * FROM mysql.tables_priv IGNORE INDEX (`PRIMARY`,`Grantor`)'){throw 'Actual reviewed plan differs.'}
}

function Assert-P3Prior([object]$Result,[object]$Stop,[object[]]$Checks){
    if($Result.Passed -ne $false -or $Result.HaltReason -cne 'Isolated table/integrity/evidence probe needs review. Stop without repair.' -or $Result.ShutdownIssue -or
        $Result.ProtectedEightTreesAndUploadsUnchanged -ne $true -or $Result.AllServersStopped -ne $true -or $Result.CandidateStillRunning -ne $false -or
        $Result.RestoreInspected -ne $false -or $Result.NoManualRepair -ne $true -or $Result.NoForcedStop -ne $true -or $Result.NoWorkingChanges -ne $true -or
        $Result.NoMigration -ne $true -or $Result.NoCutover -ne $true -or $Stop.State -cne 'gracefully-stopped' -or
        $null -eq $Stop.ExitCode -or $Stop.ExitCode -ne 0 -or $null -eq $Stop.Client.ExitCode -or $Stop.Client.ExitCode -ne 0 -or $Stop.Client.TimedOut -ne $false){throw 'Latest stopped-attempt gate differs.'}
    if($Checks.Count -ne 57 -or @($Checks|Select-Object -ExpandProperty object -Unique).Count -ne 57){throw 'Previous check inventory differs.'}
    for($i=0;$i -lt 56;$i++){
        $prefix=if($i -lt 34){'`atikha_finance`.'}else{'`mysql`.'};$rows=@($Checks[$i].result)
        if(-not ([string]$Checks[$i].object).StartsWith($prefix,[StringComparison]::Ordinal) -or $rows.Count -ne 1 -or
            $rows[0].Msg_type -cne 'status' -or $rows[0].Msg_text -cne 'OK'){throw 'Earlier clean table checks differ.'}
    }
    $r=@($Checks[56].result)
    if($Checks[56].object -cne '`mysql`.`tables_priv`' -or $r.Count -ne 2 -or $r[0].Table -cne 'mysql.tables_priv' -or
        $r[1].Table -cne 'mysql.tables_priv' -or $r[0].Msg_type -cne 'error' -or $r[1].Msg_type -cne 'error' -or
        $r[0].Msg_text -cne 'Wrong base information on indexpage at page: 2' -or $r[1].Msg_text -cne 'Corrupt'){throw 'Wrong repair target or previous failure.'}
}
function Assert-P3TrialRoot([string]$Root){
    if($Root -match '[\r\n]'){throw 'Offline repair path contains a line break.'}
    $prefix='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\'
    $p=Normalize-P2Path $Root
    if(-not $p.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase) -or $p.Substring($prefix.Length) -cnotmatch '^phase2b-\d{8}-\d{6}-[a-f0-9]{8}\\index-trial\z'){throw 'Offline repair requires the new trial role only.'}
}
function Get-P3RepairArguments([string]$Root){
    Assert-P3TrialRoot $Root
    @('--no-defaults','--recover','--quick','--require-control-file','--skip-transaction-log',
      ('--datadir='+(Join-Path $Root 'data')),('--logdir='+(Join-Path $Root 'data')),('--tmpdir='+(Join-Path $Root 'tmp')),
      (Join-Path $Root 'data\mysql\tables_priv.MAI'))
}
function Assert-P3RepairHelp([string]$Text,[string]$Errors,[object]$Run,[string]$Root){
    if($Run.TimedOut -or $Run.ExitCode -ne 0 -or $Errors.Trim().Length){throw 'Offline repair option compatibility failed.'}
    $map=Get-P2HelpMap $Text
    foreach($key in @('datadir','logdir','tmpdir')){
        $wanted=Join-Path $Root $(if($key -eq 'tmpdir'){'tmp'}else{'data'})
        if(-not $map.ContainsKey($key) -or (Normalize-P2Path $map[$key]) -ine (Normalize-P2Path $wanted)){throw 'Offline tool path differs in help.'}
    }
    foreach($pair in @(@('require-control-file','TRUE'),@('ignore-control-file','FALSE'),@('transaction-log','FALSE'))){
        if($map[$pair[0]] -ine $pair[1]){throw 'Offline control/logging option differs.'}
    }
}
function Assert-P3RepairResult([string]$Text,[string]$Errors,[object]$Run,[long]$BaselineRows){
    if($Run.TimedOut -or $Run.ExitCode -ne 0 -or $Errors.Trim().Length){throw 'Index-only trial failed or needs private review. No fallback.'}
    if($Text -match '(?i)\b(error|warning|corrupt|duplicate|abort\w*|retry\w*|skipped|skipping|lost|zerofill|truncat\w*)\b' -or
        $Text -notmatch '(?m)^- recovering \(with (sort|keycache)\) Aria-table '){throw 'Unexpected index-repair diagnostic. Stop without fallback.'}
    $counts=[regex]::Matches($Text,'(?m)^Data records:\s*([0-9]+)\s*$')
    if($counts.Count -ne 1 -or [long]$counts[0].Groups[1].Value -ne $BaselineRows){throw 'Repair record count does not match explicit pre-repair scan.'}
}
function Assert-P3IndexOnlyDelta([object]$Before,[object]$After){
    $old=@{};$new=@{}
    foreach($e in $Before.Entries){if($old.ContainsKey($e.Path)){throw 'Duplicate before-manifest entry.'};$old[$e.Path]=$e}
    foreach($e in $After.Entries){if($new.ContainsKey($e.Path)){throw 'Duplicate after-manifest entry.'};$new[$e.Path]=$e}
    if($old.Count -ne $new.Count){throw 'Repair added/removed database files. No validation start.'}
    $target='mysql\tables_priv.MAI';$changed=$false
    foreach($key in $old.Keys){
        if(-not $new.ContainsKey($key) -or $old[$key].Kind -cne $new[$key].Kind){throw 'Repair changed database layout.'}
        if($key -ieq $target){
            if($old[$key].Kind -cne 'F'){throw 'Repair target is not a file.'}
            $changed=$old[$key].SHA256 -cne $new[$key].SHA256
        }elseif($old[$key].Bytes -ne $new[$key].Bytes -or $old[$key].SHA256 -cne $new[$key].SHA256){throw 'Repair changed a non-target file, data, schema or control bytes.'}
    }
    if(-not $changed){throw 'Target index did not change; no successful index rebuild established.'}
}
function Assert-P3CCaptureReport([object]$Report){
    if($Report.passed -ne $true -or $Report.mode -cne 'capture' -or $Report.writes_performed -ne $false -or $Report.working_database_accessed -ne $false -or
        $Report.stable_baseline -ne $true -or $Report.no_table_checks -ne $true -or $null -eq $Report.baseline.snapshot -or
        $Report.baseline.tables_priv_scan.access -cne 'HANDLER_TABLE_SCAN' -or $Report.baseline.tables_priv_scan.eof -ne $true -or $Report.baseline.tables_priv_scan.counter_proof -ne $true -or $Report.baseline.tables_priv_scan.PSObject.Properties.Name -notcontains 'index' -or
        $null -ne $Report.baseline.tables_priv_scan.index -or $null -eq $Report.baseline.tables_priv_scan.rows -or ([string]$Report.baseline.tables_priv_scan.rows) -notmatch '^[0-9]+\z'){throw 'Pre-repair private logical baseline is incomplete.'}
}
function Invoke-P3CProbe([object]$Context,[string]$Mode,[string]$Baseline=''){
    Assert-P2BSourceManifest $script:sourceManifest $script:workspace
    foreach($path in $script:pins.Keys){Assert-P2BPin $path $script:pins[$path]}
    Assert-P2BPin $Context.Ini $Context.IniHash
    Assert-P2Process $Context.Process $Context.Server $Context.Ini $Context.Ticks $Context.Target -RequireListener -EvidenceDirectory $Context.Logs
    if($Mode -notin 'capture','inspect'){throw 'Unsupported probe mode.'}
    $name=if($Mode -eq 'capture'){'capture'}else{'inspection'};$output=Join-Path $Context.Logs ($name+'.json')
    $argsProbe=@('-d','allow_url_fopen=0','-d','disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail',
        $script:phase3Probe,"--root=$($Context.Root)","--pid=$($Context.Process.Id)","--output=$output","--uploads=$script:uploads","--mode=$Mode")
    if($Mode -eq 'inspect'){Assert-P2BPin $Baseline $script:baselineHash;$argsProbe+="--baseline=$Baseline"}
    $run=Invoke-BoundedCheck $script:php $argsProbe $Context.Root (Join-Path $Context.Logs ($name+'.stdout.log')) (Join-Path $Context.Logs ($name+'.stderr.log')) 180000
    Write-CheckJson (Join-Path $Context.Logs ($name+'-result.json')) $run
    if($run.TimedOut -or $run.ExitCode -ne 0 -or [IO.File]::ReadAllText((Join-Path $Context.Logs ($name+'.stderr.log'))).Trim().Length){throw 'Phase 3-A private probe needs review; no further repair/import.'}
    Assert-P2Process $Context.Process $Context.Server $Context.Ini $Context.Ticks $Context.Target -RequireListener -EvidenceDirectory $Context.Logs
    if(Get-P2StartupIssue (Join-Path $Context.Logs 'server-error.log')){throw 'Candidate server log needs review.'}
    $report=[IO.File]::ReadAllText($output)|ConvertFrom-Json
    if($Mode -eq 'capture'){Assert-P3CCaptureReport $report}else{
        Assert-P2BReport $report
        if($report.baseline_matched_before_checks -ne $true -or $report.baseline_matched_after_checks -ne $true){throw 'Repair did not preserve complete original baseline.'}
    }
    $report
}

$attemptRoot='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d'
$prior=Join-Path $attemptRoot 'candidate-corrected-20261020-181135-b09d90ba'
$stoppedAttempt=Join-Path $attemptRoot 'phase2b-20261020-183602-8bee3d3b'
$stoppedValidation=Join-Path $stoppedAttempt 'validation'
$source=Join-Path $stoppedValidation 'data'
$sourceHash='4B3AA2146106026AC60F8368B52AACCE251570E44694232ABA308A4426B385E4'
$export=Join-Path $prior 'export\atikha_finance.sql'
$exportHash='E6C603BAFF6038977A177AD97D2A10F4F0D5D55A21381372B22E287E80DBE22A'
$uploads=Join-Path $attemptRoot 'preserved\storage\uploads'
$workspace=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$probe=Join-Path $PSScriptRoot 'database_phase2b_probe.php'
$sourceManifest=Join-Path $PSScriptRoot 'database_phase2b_source_manifest.json'
$server='C:\xampp\mysql\bin\mysqld.exe';$mysql='C:\xampp\mysql\bin\mysql.exe';$admin='C:\xampp\mysql\bin\mysqladmin.exe';$php='C:\xampp\php\php.exe'
$pins=@{
    $server='EE6380DB7277E0A4EBE0FF255EDD846EB3B4EE604804CF5DB3BDDE1BE44B7E8A'
    $mysql='A60D3CBC24984E3C1C5DEE3726ABC685EC6FFF58BCE3FBD763F9261C652FB73D'
    $admin='5D28FB1F6FAAF93604F1A46FB5D1133832EF94E90CC9D227ACFF7ADD12DEAA7C'
    'C:\xampp\mysql\bin\my.ini'='268B38BBA89E1FD30E3DC9EDBB5AA70BA30A97912D98CAFC2D5283119D06E3A0'
}
foreach($file in $functionSources.Keys){$pins[(Join-Path $PSScriptRoot $file)]=$functionSources[$file]}
# Pin PHP executable and read-only includes via the separately reviewed preparation manifest as well.
Assert-P2BPin $sourceManifest '32924EB58FD5242BDCD14A36E2FEF22CD70F0A504EF924352FF0E4BB05877D51'
$manifest=[IO.File]::ReadAllText($sourceManifest)|ConvertFrom-Json
$pins[$php]=$manifest.PhpSHA256
$protected=@()
foreach($path in @('C:\xampp\mysql\data',(Join-Path $attemptRoot 'preserved\data'),(Join-Path $attemptRoot 'examine\data'),
    (Join-Path $attemptRoot 'check-context-20261020-043052-9c47421f\data'),(Join-Path $attemptRoot 'log-review-20261020-044727-5f056f22\data'))){
    $protected += [pscustomobject]@{Path=$path;Hash='B95EAEAC7F2F6097D87EFA862D44418C474ACDF2C35B5A5CD7DA3726708F4FCD';Count=2768}
}
$protected += [pscustomobject]@{Path=(Join-Path $attemptRoot 'candidate-start-20261020-174040-e060c0a8\data');Hash='647FBDA5FFA69F1B756B87BD6BB0AB496F43BD822DDD65E205562B1AD3BE10A6';Count=2767}
$protected += [pscustomobject]@{Path=(Join-Path $prior 'data');Hash='D0DA68893E42300F3E6CB6B1384887FF8C76D745C7FB5B753EB59A4E71FD680E';Count=2767}
$protected += [pscustomobject]@{Path=$source;Hash=$sourceHash;Count=2767}
$latestAttempt=Join-Path $attemptRoot 'phase2b-20261020-185120-15e57a02'
$latestValidation=Join-Path $latestAttempt 'validation'
$protected += [pscustomobject]@{Path=(Join-Path $latestValidation 'data');Hash='55C7EF745BAF543B6E38615126BD0D7FF3CC69E2781B5755B5F880E91C881FB8';Count=2767}

$failedCapture=Join-Path $attemptRoot 'phase2b-20261020-192336-6a4bea4a'
$diagnostic=Join-Path $attemptRoot 'phase3a-plan-20261020-195003-f2761503'
foreach($path in @((Join-Path $failedCapture 'capture\data'),(Join-Path $diagnostic 'diagnostic\data'))){
    $protected += [pscustomobject]@{Path=$path;Hash='B850F25EFFA63989D09898835FFF1CF62312BEC319472750BE04CA5230ED3010';Count=2767}
}
Assert-P3CPrevious ([IO.File]::ReadAllText((Join-Path $diagnostic 'results.json'))|ConvertFrom-Json) `
    ([IO.File]::ReadAllText((Join-Path $diagnostic 'diagnostic\shutdown-result.json'))|ConvertFrom-Json) `
    ([IO.File]::ReadAllText((Join-Path $diagnostic 'diagnostic\logs\plan-diagnostic.json'))|ConvertFrom-Json) `
    ([IO.File]::ReadAllText((Join-Path $failedCapture 'results.json'))|ConvertFrom-Json)
foreach($name in @('index-trial','validation','restore','capture\logs\baseline-first.json')){if(Test-Path -LiteralPath (Join-Path $failedCapture $name)){throw 'Unexpected prior repair/baseline progression.'}}

$phase3Probe=Join-Path $PSScriptRoot 'database_phase3a_corrected_probe.php'
$aria='C:\xampp\mysql\bin\aria_chk.exe'
$pins[$aria]='B7CD8EBCECB13FEBA4FFFCF0B328FC2D826855B8A6F47D3D680354AE7875B20C'
$pins[$phase3Probe]='DE401BE3AFA080707390D41F1040433FD78C7F5FF720CE0E59C56D3EDCF122B4'
$pins[(Join-Path $PSScriptRoot 'database_phase3a_probe.php')]='B2AF34FAA83A80391044D29720847143B411653197346ED29CC8E70D1AC0081E'
$marker=Join-Path $attemptRoot 'evidence\phase3a-corrected-index-trial-attempt.json'
if($env:MYSQL_PWD -or $env:MYSQL_HOME -or $env:MARIADB_HOME){throw 'Unexpected database credential/defaults environment. Stop without displaying it.'}
Assert-CheckOffline
Assert-ContextDirectories $attemptRoot
Assert-ContextDirectories (Join-Path $attemptRoot 'evidence')
if(Test-Path -LiteralPath $marker){throw 'Phase 3-A has an existing attempt marker. No repeat.'}
$priorResult=[IO.File]::ReadAllText((Join-Path $prior 'results.json'))|ConvertFrom-Json
$priorPreserved=[IO.File]::ReadAllText((Join-Path $prior 'preservation-after.json'))|ConvertFrom-Json
if(-not $priorResult.AuthenticatedReady -or -not $priorResult.ExportCompleted -or -not $priorResult.MetadataCompleted -or
    $priorResult.HaltReason -or $priorResult.ShutdownIssue -or $priorResult.CandidateStillRunning -or $priorResult.Shutdown.State -ne 'gracefully-stopped' -or
    -not $priorPreserved.ProtectedSixTreesUnchanged){throw 'Successful stopped Phase 2-A evidence missing.'}

$latestResult=[IO.File]::ReadAllText((Join-Path $latestAttempt 'results.json'))|ConvertFrom-Json
$latestStop=[IO.File]::ReadAllText((Join-Path $latestValidation 'shutdown-result.json'))|ConvertFrom-Json
$latestChecks=@([IO.File]::ReadAllLines((Join-Path $latestValidation 'logs\table-checks.jsonl'))|ForEach-Object{$_|ConvertFrom-Json})
Assert-P3Prior $latestResult $latestStop $latestChecks
foreach($path in $pins.Keys){Assert-P2BPin $path $pins[$path]}
Assert-P2BSourceManifest $sourceManifest $workspace
Assert-P2BPin $export $exportHash
Write-Host 'Verifying eleven protected database trees, both upload trees and original export. Keep XAMPP stopped.'
Assert-P2BProtected $protected
$uploadHash='FF2E87E9C44B4ADF9385EB8748AAAB56B569738FBADD6D148C84A967FFC328BE'
foreach($path in @($uploads,(Join-Path $workspace 'uploads'))){if((Get-CheckTree $path).TreeSHA256 -ne $uploadHash){throw 'Protected uploads changed.'}}
if(([IO.DriveInfo]::new('C:\')).AvailableFreeSpace -lt (2GB+5*412226547)){throw 'Insufficient capacity for four fresh trees and headroom.'}
Initialize-P2CandidateIdentity
$stamp=(Get-Date -Format 'yyyyMMdd-HHmmss')+'-'+[Guid]::NewGuid().ToString('N').Substring(0,8)
$root=Join-Path $attemptRoot "phase2b-$stamp"
if(Test-Path -LiteralPath $root){throw 'New Phase 3-A attempt destination exists.'}
Assert-CheckOffline
Write-CheckJson $marker ([ordered]@{At=(Get-Date).ToString('o');Root=$root;ProtectedTreeCount=11;Source=$source;SourceSHA256=$sourceHash;
    Target='mysql.tables_priv index only';NoWorkingChanges=$true;NoForcedStop=$true;CaptureBeforeRepairRequired=$true;RepairInvocationLimit=1})
New-Item -ItemType Directory -Path $root|Out-Null
$activeProcess=$null;$activeContext=$null;$halt=$null;$shutdownIssue=$null
$capture=$null;$validation=$null;$restored=$null;$repairAttempted=$false;$indexOnly=$false;$equal=$false;$baselineHash=$null
$artifactPins=@()
try{
    Write-Host 'Starting a fresh capture copy; saving two stable logical snapshots with complete direct permission-table scans and counter proof. No CHECK or repair yet.'
    $ctx=Start-P2BCopy (Join-Path $root 'capture') $source $sourceHash
    $capture=Invoke-P3CProbe $ctx 'capture'
    $baseline=Join-Path $ctx.Logs 'capture.json';$baselineHash=(Get-FileHash -LiteralPath $baseline).Hash
    Close-P2BCopy
    $captureData=Join-Path $root 'capture\data';$captureTree=Get-CheckTree $captureData
    $artifactPins+=[pscustomobject]@{Path=$captureData;Hash=$captureTree.TreeSHA256;Count=$captureTree.FileCount}
    Assert-P2BProtected $protected
    Assert-CheckOffline
    $trial=Join-Path $root 'index-trial';New-Item -ItemType Directory -Path $trial|Out-Null
    foreach($dir in @('tmp','logs','before-target')){New-Item -ItemType Directory -Path (Join-Path $trial $dir)|Out-Null}
    $trialData=Join-Path $trial 'data'
    Copy-ContextData $captureData $trialData (Join-Path $trial 'logs\copy.log')
    $before=Get-CheckTree $trialData
    if($before.TreeSHA256 -ne $captureTree.TreeSHA256){throw 'Offline trial copy differs from captured stopped source.'}
    Write-CheckJson (Join-Path $trial 'before.json') $before
    foreach($ext in @('frm','MAI','MAD')){
        $file=Join-Path $trialData ('mysql\tables_priv.'+$ext);$backup=Join-Path $trial ('before-target\tables_priv.'+$ext)
        Copy-Item -LiteralPath $file -Destination $backup -ErrorAction Stop
        if((Get-FileHash -LiteralPath $file).Hash -ne (Get-FileHash -LiteralPath $backup).Hash){throw 'Target before-image backup differs.'}
    }
    Assert-P2BPin (Join-Path $trialData 'mysql\tables_priv.MAD') 'BFC7D21573F38A0EF3E8989A60457CF0F47D22A7D5720C585BF49879AC9A7FBF'
    Assert-P2BPin (Join-Path $trialData 'mysql\tables_priv.frm') '4057D360E62A8A93A56428BB62DF4AB30CA3F0110C8798CE0D103178F6A336BE'
    $argsRepair=@(Get-P3RepairArguments $trial)
    $help=Invoke-BoundedCheck $aria ($argsRepair+@('--help')) $trialData (Join-Path $trial 'logs\help.stdout.log') (Join-Path $trial 'logs\help.stderr.log') 15000
    Assert-P3RepairHelp ([IO.File]::ReadAllText((Join-Path $trial 'logs\help.stdout.log'))) ([IO.File]::ReadAllText((Join-Path $trial 'logs\help.stderr.log'))) $help $trial
    if((Get-CheckTree $trialData).TreeSHA256 -ne $before.TreeSHA256){throw 'Help unexpectedly changed trial data. No repair.'}
    Assert-CheckOffline
    foreach($path in $pins.Keys){Assert-P2BPin $path $pins[$path]}
    Assert-P2BPin $baseline $baselineHash
    Write-CheckJson (Join-Path $trial 'repair-attempt.json') ([ordered]@{At=(Get-Date).ToString('o');Tool=$aria;Arguments=$argsRepair;BaselineSHA256=$baselineHash;
        Rows=$capture.baseline.tables_priv_scan.rows;TimeoutSeconds=60;Invocation=1;NoForce=$true;QuickFlagCount=1;NoFallback=$true})
    $repairAttempted=$true
    Write-Host 'All servers are stopped. Attempting the single copied tables_priv index-only repair; no fallback.'
    $run=Invoke-BoundedCheck $aria $argsRepair $trialData (Join-Path $trial 'logs\repair.stdout.log') (Join-Path $trial 'logs\repair.stderr.log') 60000
    Write-CheckJson (Join-Path $trial 'repair-result.json') $run
    # Preserve/check the complete offline write scope even when the utility fails.
    Assert-CheckOffline
    $after=Get-CheckTree $trialData;Write-CheckJson (Join-Path $trial 'after.json') $after
    Assert-P3IndexOnlyDelta $before $after
    Assert-P3RepairResult ([IO.File]::ReadAllText((Join-Path $trial 'logs\repair.stdout.log'))) ([IO.File]::ReadAllText((Join-Path $trial 'logs\repair.stderr.log'))) $run ([long]$capture.baseline.tables_priv_scan.rows)
    $indexOnly=$true
    $artifactPins+=[pscustomobject]@{Path=$trialData;Hash=$after.TreeSHA256;Count=$after.FileCount}
    Write-Host 'Offline byte-scope gate passed. Starting a fresh copy of the trial for baseline, full integrity and file checks.'
    $ctx=Start-P2BCopy (Join-Path $root 'validation') $trialData $after.TreeSHA256
    $validation=Invoke-P3CProbe $ctx 'inspect' $baseline
    Close-P2BCopy
    $validationData=Join-Path $root 'validation\data';$validationTree=Get-CheckTree $validationData
    $artifactPins+=[pscustomobject]@{Path=$validationData;Hash=$validationTree.TreeSHA256;Count=$validationTree.FileCount}
    Assert-P2BProtected $protected
    Assert-P2BProtected $artifactPins
    Write-Host 'Starting the separate restore copy; replacing only its application schema from the original pinned export.'
    $ctx=Start-P2BCopy (Join-Path $root 'restore') $validationData $validationTree.TreeSHA256
    Invoke-P2BImport $ctx $export $exportHash
    $restored=Invoke-P3CProbe $ctx 'inspect' $baseline
    Assert-P2BEqual $validation.snapshot $restored.snapshot
    Assert-P2BEqual $validation.evidence $restored.evidence
    Assert-P2BEqual $validation.tables_priv_scan $restored.tables_priv_scan
    $equal=$true
    Close-P2BCopy
}catch{$halt=$_.Exception.Message}
if($null -ne $activeProcess){
    try{
        if($null -eq $activeContext -or (Test-Path -LiteralPath (Join-Path $activeContext.Root 'graceful-shutdown-attempt.json'))){throw 'No additional shutdown request permitted. Inspect stopped/running state.'}
        Close-P2BCopy
    }catch{$shutdownIssue=$_.Exception.Message}
}
$running=$false;if($null -ne $activeProcess){$activeProcess.Refresh();$running=-not $activeProcess.HasExited}
Write-Host 'Verifying original eleven sources, uploads/export, captured baseline and completed intermediate copies remain unchanged.'
$unchanged=$true
try{
    Assert-P2BProtected $protected
    if($artifactPins.Count){Assert-P2BProtected $artifactPins}
    Assert-P2BPin $export $exportHash
    if($baselineHash){Assert-P2BPin $baseline $baselineHash}
    foreach($path in @($uploads,(Join-Path $workspace 'uploads'))){if((Get-CheckTree $path).TreeSHA256 -ne $uploadHash){throw 'Protected upload drift.'}}
    foreach($path in $pins.Keys){Assert-P2BPin $path $pins[$path]}
    Assert-P2BSourceManifest $sourceManifest $workspace
}catch{$unchanged=$false}
$offline=$false;if(-not $running){try{Assert-CheckOffline;$offline=$true}catch{$shutdownIssue=$_.Exception.Message}}
$passed=$null -eq $halt -and $null -eq $shutdownIssue -and $indexOnly -and $equal -and $unchanged -and $offline
Write-CheckJson (Join-Path $root 'results.json') ([ordered]@{At=(Get-Date).ToString('o');Passed=$passed;HaltReason=$halt;ShutdownIssue=$shutdownIssue;
    CaptureCompleted=($null -ne $capture -and $capture.passed);RepairAttempted=$repairAttempted;OfflineIndexOnlyDeltaVerified=$indexOnly;
    ValidationPassed=($null -ne $validation -and $validation.passed);RestorePassed=($null -ne $restored -and $restored.passed);ExactApplicationRestoreMatched=$equal;
    ProtectedElevenTreesUploadsExportAndArtifactsUnchanged=$unchanged;AllServersStopped=$offline;CandidateStillRunning=$running;NoWorkingChanges=$true;
    NoForcedStop=$true;NoMigration=$true;NoCutover=$true;WorkingAcceptance=$false;U3IntegrationRun=$false;TrialRepairTarget='mysql.tables_priv index only'})
if($passed){Write-Host 'PHASE3A_CORRECTED_INDEX_TRIAL_COMPLETE. Copied index repair, baseline preservation and isolated application restore verified; working acceptance remains separate.'}
else{
    Write-Host 'PHASE3A_CORRECTED_STOPPED_FOR_REVIEW. No retry, fallback repair, force-stop or working restart permitted.'
    if($halt){Write-Host $halt};if($shutdownIssue){Write-Host $shutdownIssue}
    if($running){Write-Host "Candidate PID $($activeProcess.Id) remains running. Do not touch its files or start XAMPP."}
}
Write-Host "Private Phase 3-A evidence: $root"
Write-Host 'Keep XAMPP MySQL and Apache stopped. Send the terminal result; do not rerun or share private exports/logs.'
