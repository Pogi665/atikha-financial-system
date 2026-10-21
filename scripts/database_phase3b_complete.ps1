# Finish the authorized isolated validation/restore; never repeat normalization or touch working data.
[CmdletBinding()]
param([switch]$CompleteIsolatedValidationRestore)
$ErrorActionPreference='Stop'
if(-not $CompleteIsolatedValidationRestore){throw 'No action taken. Specify -CompleteIsolatedValidationRestore for the guarded continuation.'}
$functionSources=@{
    'database_phase2a_candidate_corrected.ps1'='E78CFCAA9979E091B1D0E28E6974D27F496937FAAAE420D233456AD31145067B'
    'database_phase2b_validate_restore.ps1'='F6FB16745C53E618ECE316F75BF1520AA98B1DBBBD7BDDEEE54DCC4E7D9D3744'
}
foreach($file in @('database_phase2a_candidate_corrected.ps1','database_phase2b_validate_restore.ps1')){
    $path=Join-Path $PSScriptRoot $file
    if((Get-FileHash -LiteralPath $path).Hash -cne $functionSources[$file]){throw 'Pinned earlier helper changed.'}
    $tokens=$null;$errors=$null;$ast=[Management.Automation.Language.Parser]::ParseFile($path,[ref]$tokens,[ref]$errors)
    if($errors.Count){throw 'Earlier helper parse failure.'}
    foreach($node in $ast.EndBlock.Statements){if($node -is [Management.Automation.Language.FunctionDefinitionAst]){. ([scriptblock]::Create($node.Extent.Text))}}
}
function Assert-P3DPrior([object]$Result,[object]$Report,[object[]]$Stops,[object[]]$Checks){
    foreach($key in @('CaptureCompleted','NormalizationAttempted','OfflineTargetOnlyDeltaAndHeaderVerified','ProtectedFourteenTreesUploadsExportAndArtifactsUnchanged','AllServersStopped','NoWorkingChanges','NoForcedStop','NoMigration','NoCutover')){
        if($Result.$key -ne $true){throw 'Required predecessor proof missing.'}
    }
    foreach($key in @('Passed','ValidationPassed','RestorePassed','ExactApplicationRestoreMatched','CandidateStillRunning','WorkingAcceptance','U3IntegrationRun')){if($Result.$key -ne $false){throw 'Unexpected predecessor progression.'}}
    if($Result.ShutdownIssue -or $Result.HaltReason -cne 'Phase 3-A private probe needs review; no further repair/import.' -or $Result.TrialRepairTarget -cne 'mysql.tables_priv metadata only (MAI/MAD)'){throw 'Predecessor result differs.'}
    if($Stops.Count -ne 2){throw 'Both predecessor stops required.'}
    foreach($stop in $Stops){if($stop.State -cne 'gracefully-stopped' -or $null -eq $stop.ExitCode -or $stop.ExitCode -ne 0 -or $null -eq $stop.Client.ExitCode -or $stop.Client.ExitCode -ne 0 -or $stop.Client.TimedOut -ne $false){throw 'Predecessor did not stop normally.'}}
    if($Report.passed -ne $false -or $Report.writes_performed -ne $false -or $Report.working_database_accessed -ne $false -or $Report.mode -cne 'inspect' -or $Report.baseline_matched_before_checks -ne $true -or
        $Report.checked_objects -ne 65 -or $Report.integrity_problems.Count -ne 0 -or $Report.failure -cne 'Missing or escaping preserved attachment.' -or
        @($Report.foreign_keys.PSObject.Properties).Count -ne 70 -or @($Report.foreign_keys.PSObject.Properties|Where-Object {$_.Value -cne '0'}).Count){throw 'Predecessor failure/integrity evidence differs.'}
    if($Checks.Count -ne 65 -or @($Checks|Select-Object -ExpandProperty object -Unique).Count -ne 65 -or
        @($Checks|Where-Object {$_.object.StartsWith('`atikha_finance`.')}).Count -ne 34 -or @($Checks|Where-Object {$_.object.StartsWith('`mysql`.')}).Count -ne 31){throw 'Predecessor check inventory differs.'}
    foreach($check in $Checks){$rows=@($check.result);if($rows.Count -ne 1 -or $rows[0].Msg_type -cne 'status' -or $rows[0].Msg_text -cne 'OK'){throw 'Predecessor check was not clean.'}}
}
function Assert-P3DUploadDelta([object]$Before,[object]$After,[object[]]$Recoveries){
    if($Recoveries.Count -ne 3){throw 'Exactly three recovery specifications required.'}
    $expected=@{};$old=@{};$new=@{}
    foreach($r in $Recoveries){
        if($r.PublicPath -cnotmatch '^uploads/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_. ()-]+\z' -or @($r.PublicPath.Split('/')|Where-Object {$_ -ceq '..'}).Count){throw 'Unsafe recovered filename.'}
        $path=$r.PublicPath.Substring(8).Replace('/','\')
        if($expected.ContainsKey($path) -or @($r.Hashes).Count -lt 1){throw 'Duplicate/unproven recovery.'};$expected[$path]=$r
    }
    foreach($e in $Before.Entries){if($old.ContainsKey($e.Path)){throw 'Duplicate before entry.'};$old[$e.Path]=$e}
    foreach($e in $After.Entries){if($new.ContainsKey($e.Path)){throw 'Duplicate after entry.'};$new[$e.Path]=$e}
    if($new.Count -ne $old.Count+3 -or $After.FileCount -ne $Before.FileCount+3){throw 'Unexpected upload additions/removals.'}
    foreach($key in $old.Keys){
        if(-not $new.ContainsKey($key) -or $expected.ContainsKey($key) -or $old[$key].Kind -cne $new[$key].Kind -or $old[$key].Bytes -ne $new[$key].Bytes -or $old[$key].SHA256 -cne $new[$key].SHA256){throw 'Existing uploads changed or destination already existed.'}
    }
    foreach($key in $new.Keys){
        if($old.ContainsKey($key)){continue}
        if(-not $expected.ContainsKey($key) -or $new[$key].Kind -cne 'F' -or $new[$key].Bytes -ne [long]$expected[$key].Bytes){throw 'Unexpected recovery file/type/size.'}
        foreach($hash in $expected[$key].Hashes){if($new[$key].SHA256 -ine $hash){throw 'Recovered bytes do not match every recorded hash.'}}
    }
}
function Get-P3DArguments([object]$Context,[string]$Output){
    @('-d','allow_url_fopen=0','-d','disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail',
        $script:probe,"--root=$($Context.Root)","--pid=$($Context.Process.Id)","--output=$Output","--uploads=$script:uploads",
        "--baseline=$script:baseline","--manifest=$script:completionManifest","--manifest-sha=$script:completionManifestHash")
}
function Get-P3DRecoveryArguments([string]$Root,[string]$Uploads,[string]$Output){
    @($script:probe,'--prepare-files',"--root=$Root","--uploads=$Uploads","--output=$Output","--manifest=$script:completionManifest","--manifest-sha=$script:completionManifestHash")
}
function Assert-P3DRecoveryReport([object]$Report){
    if($Report.passed -ne $true -or $Report.sql_executed -ne $false -or $Report.working_database_accessed -ne $false -or $Report.passed_recovery -ne $true -or $Report.recovered_count -ne 3 -or @($Report.files).Count -ne 3){throw 'Filename recovery did not fully pass.'}
}
function Assert-P3DReport([object]$Report){
    Assert-P2BReport $Report
    if($Report.mode -cne 'inspect' -or $Report.baseline_matched_before_checks -ne $true -or $Report.baseline_matched_after_checks -ne $true -or $Report.receipt_metadata_matches_pinned_export -ne $true -or
        $Report.checked_objects -ne 65 -or @($Report.foreign_keys.PSObject.Properties).Count -ne 70 -or @($Report.foreign_keys.PSObject.Properties|Where-Object {$_.Value -cne '0'}).Count){throw 'Full baseline/receipt metadata/integrity verification incomplete.'}
}
function Invoke-P3DProbe([object]$Context){
    foreach($path in $script:pins.Keys){Assert-P2BPin $path $script:pins[$path]}
    Assert-P2BSourceManifest $script:sourceManifest $script:workspace
    Assert-P2BPin $Context.Ini $Context.IniHash
    if((Get-CheckTree $script:uploads).TreeSHA256 -cne $script:rehearsalHash){throw 'Rehearsal uploads drifted.'}
    Assert-P2Process $Context.Process $Context.Server $Context.Ini $Context.Ticks $Context.Target -RequireListener -EvidenceDirectory $Context.Logs
    $output=Join-Path $Context.Logs 'inspection.json'
    $run=Invoke-BoundedCheck $script:php @(Get-P3DArguments $Context $output) $Context.Root (Join-Path $Context.Logs 'probe.stdout.log') (Join-Path $Context.Logs 'probe.stderr.log') 180000
    Write-CheckJson (Join-Path $Context.Logs 'probe-result.json') $run
    if($run.TimedOut -or $run.ExitCode -ne 0 -or [IO.File]::ReadAllText((Join-Path $Context.Logs 'probe.stderr.log')).Trim().Length){throw 'Completion inspection needs review; no further operation.'}
    Assert-P2Process $Context.Process $Context.Server $Context.Ini $Context.Ticks $Context.Target -RequireListener -EvidenceDirectory $Context.Logs
    if(Get-P2StartupIssue (Join-Path $Context.Logs 'server-error.log')){throw 'Server log needs review.'}
    if((Get-CheckTree $script:uploads).TreeSHA256 -cne $script:rehearsalHash){throw 'Inspection changed rehearsal files.'}
    $report=[IO.File]::ReadAllText($output)|ConvertFrom-Json;Assert-P3DReport $report;return $report
}

$workspace=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$attemptRoot='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d'
$completionManifest=Join-Path $workspace '.migration-private\phase3b-completion-inputs.json'
$completionManifestHash='E48A737DD82A574A10714394CAA6BDAB7592E265227FFE296BC335BE811CD4F1'
Assert-P2BPin $completionManifest $completionManifestHash
$spec=[IO.File]::ReadAllText($completionManifest)|ConvertFrom-Json
if($spec.Format -cne 'phase3b-completion-inputs-v1' -or $spec.Protected.Count -ne 19 -or $spec.Recoveries.Count -ne 3 -or $spec.ReceiptMetadata.Count -ne 4){throw 'Incomplete continuation specification.'}
$protected=@($spec.Protected|ForEach-Object {[pscustomobject]@{Path=$_.Root;Hash=$_.SHA256;Count=$_.Files}})
if(@($protected.Path|Select-Object -Unique).Count -ne 19){throw 'Duplicate protected source.'}
$source=$spec.Source;$sourceHash=$spec.SourceHash;$originalUploads=$spec.OriginalUploads
if((Normalize-P2Path $source) -ine (Join-Path $attemptRoot 'phase2b-20261021-012006-549712af\normalization\data')){throw 'Only the immutable normalized offline source is allowed.'}
$baseline=$spec.Baseline;$export=$spec.Export;$exportHash=$spec.ExportHash
$sourceManifest=Join-Path $PSScriptRoot 'database_phase2b_source_manifest.json'
$server='C:\xampp\mysql\bin\mysqld.exe';$mysql='C:\xampp\mysql\bin\mysql.exe';$admin='C:\xampp\mysql\bin\mysqladmin.exe';$php='C:\xampp\php\php.exe'
$probe=Join-Path $PSScriptRoot 'database_phase3b_complete_probe.php'
$pins=@{
    $server='EE6380DB7277E0A4EBE0FF255EDD846EB3B4EE604804CF5DB3BDDE1BE44B7E8A'
    $mysql='A60D3CBC24984E3C1C5DEE3726ABC685EC6FFF58BCE3FBD763F9261C652FB73D'
    $admin='5D28FB1F6FAAF93604F1A46FB5D1133832EF94E90CC9D227ACFF7ADD12DEAA7C'
    $php='4043721B2D6A3D0E68D13B3DAEAB0A2C842B9E054EC4098B80E1AEAB6A8456F3'
    'C:\xampp\mysql\bin\my.ini'='268B38BBA89E1FD30E3DC9EDBB5AA70BA30A97912D98CAFC2D5283119D06E3A0'
    $probe='FD1850303AD19850248CA8093413E37F407566491B5A434ABE274EC5B1B745A4'
    $completionManifest=$completionManifestHash
    $baseline=$spec.BaselineHash
    $export=$exportHash
}
foreach($file in $functionSources.Keys){$pins[(Join-Path $PSScriptRoot $file)]=$functionSources[$file]}
foreach($entry in $spec.Pins.PSObject.Properties){$pins[$entry.Name]=$entry.Value}
foreach($path in $pins.Keys){Assert-P2BPin $path $pins[$path]}
Assert-P2BSourceManifest $sourceManifest $workspace
$prior=$spec.Prior
$checks=@([IO.File]::ReadAllLines((Join-Path $prior 'validation\logs\table-checks.jsonl'))|ForEach-Object {$_|ConvertFrom-Json})
$stops=@([IO.File]::ReadAllText((Join-Path $prior 'capture\shutdown-result.json'))|ConvertFrom-Json;[IO.File]::ReadAllText((Join-Path $prior 'validation\shutdown-result.json'))|ConvertFrom-Json)
Assert-P3DPrior ([IO.File]::ReadAllText((Join-Path $prior 'results.json'))|ConvertFrom-Json) ([IO.File]::ReadAllText((Join-Path $prior 'validation\logs\inspection.json'))|ConvertFrom-Json) $stops $checks
if(Test-Path -LiteralPath (Join-Path $prior 'restore')){throw 'Unexpected earlier restore.'}
if($env:MYSQL_PWD -or $env:MYSQL_HOME -or $env:MARIADB_HOME){throw 'Unexpected credential/defaults environment; stop without displaying it.'}
Assert-CheckOffline
$marker=Join-Path $attemptRoot 'evidence\phase3b-completion-attempt.json'
Assert-ContextDirectories $attemptRoot;Assert-ContextDirectories (Split-Path -Parent $marker)
if(Test-Path -LiteralPath $marker){throw 'Continuation already attempted; no retry.'}
Write-Host 'Verifying seventeen protected database trees, both original upload trees, export and source pins. Keep XAMPP stopped.'
Assert-P2BProtected $protected
if(([IO.DriveInfo]::new('C:\')).AvailableFreeSpace -lt (2GB+3*412226547)){throw 'Insufficient capacity for fresh validation/restore and headroom.'}
$stamp=(Get-Date -Format 'yyyyMMdd-HHmmss')+'-'+[Guid]::NewGuid().ToString('N').Substring(0,8)
$root=Join-Path $attemptRoot "phase2b-$stamp"
if(Test-Path -LiteralPath $root){throw 'Fresh continuation root already exists.'}
Assert-CheckOffline
Write-CheckJson $marker ([ordered]@{At=(Get-Date).ToString('o');Root=$root;ProtectedDatabaseTrees=17;OriginalUploadTrees=2;ReceiptFilenameRecoveries=3;NoDatabaseRepair=$true;NoWorkingChanges=$true})
New-Item -ItemType Directory -Path $root|Out-Null
foreach($name in @('logs','rehearsal')){New-Item -ItemType Directory -Path (Join-Path $root $name)|Out-Null}
$activeProcess=$null;$activeContext=$null;$halt=$null;$shutdownIssue=$null;$validation=$null;$restored=$null;$recovered=$false;$equal=$false;$rehearsalHash=$null;$artifactPins=@()
try{
    $uploads=Join-Path $root 'rehearsal\uploads'
    Copy-ContextData $originalUploads $uploads (Join-Path $root 'logs\upload-copy.log')
    $before=Get-CheckTree $uploads
    if($before.TreeSHA256 -cne $spec.UploadHash){throw 'Fresh rehearsal uploads differ from originals.'}
    Write-CheckJson (Join-Path $root 'logs\uploads-before.json') $before
    Write-Host 'Recovering exactly three missing filenames in fresh rehearsal uploads from recorded-hash/size/MIME verified source bytes.'
    $fileArgs=@(Get-P3DRecoveryArguments $root $uploads (Join-Path $root 'logs\filename-recovery.json'))
    Assert-CheckOffline
    $run=Invoke-BoundedCheck $php $fileArgs $root (Join-Path $root 'logs\filename-recovery.out') (Join-Path $root 'logs\filename-recovery.err') 60000
    Write-CheckJson (Join-Path $root 'logs\filename-recovery-result.json') $run
    if($run.TimedOut -or $run.ExitCode -ne 0 -or [IO.File]::ReadAllText((Join-Path $root 'logs\filename-recovery.err')).Trim().Length){throw 'Filename recovery needs review; do not start validation.'}
    $recovery=[IO.File]::ReadAllText((Join-Path $root 'logs\filename-recovery.json'))|ConvertFrom-Json
    Assert-P3DRecoveryReport $recovery
    $after=Get-CheckTree $uploads;Write-CheckJson (Join-Path $root 'logs\uploads-after.json') $after
    Assert-P3DUploadDelta $before $after @($spec.Recoveries)
    $rehearsalHash=$after.TreeSHA256;$artifactPins += [pscustomobject]@{Path=$uploads;Hash=$after.TreeSHA256;Count=$after.FileCount};$recovered=$true
    Assert-P2BProtected $protected
    foreach($path in $pins.Keys){Assert-P2BPin $path $pins[$path]}
    Initialize-P2CandidateIdentity
    Write-Host 'Starting a fresh normalized-source validation copy; full baseline, tables, accounting and all attachment checks are required.'
    $ctx=Start-P2BCopy (Join-Path $root 'validation') $source $sourceHash
    $validation=Invoke-P3DProbe $ctx;Close-P2BCopy
    $validationData=Join-Path $root 'validation\data';$tree=Get-CheckTree $validationData
    $artifactPins += [pscustomobject]@{Path=$validationData;Hash=$tree.TreeSHA256;Count=$tree.FileCount}
    Assert-P2BProtected $protected;Assert-P2BProtected $artifactPins
    Write-Host 'Starting separate application restoration rehearsal from the original pinned export; no database repair.'
    $ctx=Start-P2BCopy (Join-Path $root 'restore') $validationData $tree.TreeSHA256
    Invoke-P2BImport $ctx $export $exportHash
    $restored=Invoke-P3DProbe $ctx
    Assert-P2BEqual $validation.snapshot $restored.snapshot;Assert-P2BEqual $validation.evidence $restored.evidence;Assert-P2BEqual $validation.tables_priv_scan $restored.tables_priv_scan
    $equal=$true;Close-P2BCopy
    $restoreData=Join-Path $root 'restore\data';$tree=Get-CheckTree $restoreData
    $artifactPins += [pscustomobject]@{Path=$restoreData;Hash=$tree.TreeSHA256;Count=$tree.FileCount}
}catch{$halt=$_.Exception.Message}
if($null -ne $activeProcess){try{if($null -eq $activeContext -or (Test-Path -LiteralPath (Join-Path $activeContext.Root 'graceful-shutdown-attempt.json'))){throw 'No additional shutdown request permitted.'};Close-P2BCopy}catch{$shutdownIssue=$_.Exception.Message}}
$running=$false;if($null -ne $activeProcess){$activeProcess.Refresh();$running=-not $activeProcess.HasExited}
Write-Host 'Rechecking all protected sources, original uploads/export/baseline, recovered rehearsal files and completed artifacts.'
$unchanged=$true
try{Assert-P2BProtected $protected;if($artifactPins.Count){Assert-P2BProtected $artifactPins};foreach($path in $pins.Keys){Assert-P2BPin $path $pins[$path]};Assert-P2BSourceManifest $sourceManifest $workspace}catch{$unchanged=$false}
$offline=$false;if(-not $running){try{Assert-CheckOffline;$offline=$true}catch{$shutdownIssue=$_.Exception.Message}}
$passed=$null -eq $halt -and $null -eq $shutdownIssue -and $recovered -and $equal -and $unchanged -and $offline
Write-CheckJson (Join-Path $root 'results.json') ([ordered]@{At=(Get-Date).ToString('o');Passed=$passed;HaltReason=$halt;ShutdownIssue=$shutdownIssue;RehearsalFilenamesRecovered=$recovered;
    ValidationPassed=($null -ne $validation -and $validation.passed);RestorePassed=($null -ne $restored -and $restored.passed);ExactApplicationRestoreMatched=$equal;
    ProtectedSeventeenDatabaseTreesAndTwoUploadsUnchanged=$unchanged;AllServersStopped=$offline;CandidateStillRunning=$running;NoDatabaseRepair=$true;NoWorkingChanges=$true;NoForcedStop=$true;NoMigration=$true;NoCutover=$true;WorkingAcceptance=$false;U3IntegrationRun=$false})
if($passed){Write-Host 'PHASE3B_VALIDATION_RESTORE_COMPLETE. Isolated filename recovery, full validation and original-export restoration verified; working acceptance remains separate.'}
else{Write-Host 'PHASE3B_COMPLETION_STOPPED_FOR_REVIEW. Do not retry, repair, force-stop or restart XAMPP.';if($halt){Write-Host $halt};if($shutdownIssue){Write-Host $shutdownIssue};if($running){Write-Host "Candidate PID $($activeProcess.Id) remains running. Do not touch its files."}}
Write-Host "Private completion evidence: $root"
Write-Host 'Keep XAMPP MySQL and Apache stopped. Send the terminal result; do not rerun or share private logs/exports.'
