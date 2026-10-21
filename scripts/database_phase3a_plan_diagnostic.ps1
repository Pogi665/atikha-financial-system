# One authorized metadata-only diagnostic; never repairs or imports.
[CmdletBinding()]
param([switch]$CaptureIsolatedPlan)
$ErrorActionPreference='Stop'
if(-not $CaptureIsolatedPlan){throw 'No action. Specify -CaptureIsolatedPlan for the authorized diagnostic-only batch.'}

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



function Assert-P3DPrevious([object]$Result,[object]$Stop,[object]$Probe){
    if($Result.Passed -ne $false -or $Result.HaltReason -cne 'Phase 3-A private probe needs review; no further repair/import.' -or $Result.ShutdownIssue -or
       $Result.CaptureCompleted -ne $false -or $Result.RepairAttempted -ne $false -or $Result.ValidationPassed -ne $false -or $Result.RestorePassed -ne $false -or
       $Result.ProtectedNineTreesUploadsExportAndArtifactsUnchanged -ne $true -or $Result.AllServersStopped -ne $true -or $Result.CandidateStillRunning -ne $false -or
       $Result.NoWorkingChanges -ne $true -or $Result.NoForcedStop -ne $true -or $Result.NoMigration -ne $true -or $Result.NoCutover -ne $true -or
       $Probe.passed -ne $false -or $Probe.mode -cne 'capture' -or $Probe.working_database_accessed -ne $false -or $Probe.writes_performed -ne $false -or
       $Probe.failure -cne 'Permission baseline does not prove a full table scan.' -or $Stop.State -cne 'gracefully-stopped' -or
       $null -eq $Stop.ExitCode -or $Stop.ExitCode -ne 0 -or $null -eq $Stop.Client.ExitCode -or $Stop.Client.ExitCode -ne 0 -or $Stop.Client.TimedOut -ne $false){throw 'Previous diagnostic gate differs; no new startup.'}
}
function Assert-P3DRoot([string]$Root){
    if($Root -match '[\r\n]'){throw 'Invalid diagnostic path.'}
    $prefix='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\'
    $p=Normalize-P2Path $Root
    if(-not $p.StartsWith($prefix,[StringComparison]::OrdinalIgnoreCase) -or $p.Substring($prefix.Length) -cnotmatch '^phase3a-plan-\d{8}-\d{6}-[a-f0-9]{8}\\diagnostic\z'){throw 'Not the fresh diagnostic role.'}
}
function Assert-P3DReport([object]$Report){
    if($Report.passed -ne $true -or $Report.mode -cne 'plan-diagnostic' -or $Report.diagnostic_complete -ne $true -or $Report.working_database_accessed -ne $false -or
       $Report.writes_performed -ne $false -or $Report.financial_rows_read -ne $false -or $Report.permission_row_select_executed -ne $false -or
       $Report.table_checks_performed -ne $false -or $Report.repair_performed -ne $false -or $Report.import_performed -ne $false -or
       $Report.scan_sql -cne 'SELECT * FROM mysql.tables_priv IGNORE INDEX (`PRIMARY`,`Grantor`)' -or $null -eq $Report.explain -or $null -eq $Report.indexes -or
       $Report.existing_scan_guard_accepted -isnot [bool]){throw 'Plan diagnostic incomplete or outside scope.'}
}
function Invoke-P3DProbe([object]$Context){
    Assert-P3DRoot $Context.Root
    foreach($path in $script:pins.Keys){Assert-P2BPin $path $script:pins[$path]}
    Assert-P2BSourceManifest $script:sourceManifest $script:workspace
    Assert-P2BPin $Context.Ini $Context.IniHash
    Assert-P2Process $Context.Process $Context.Server $Context.Ini $Context.Ticks $Context.Target -RequireListener -EvidenceDirectory $Context.Logs
    $output=Join-Path $Context.Logs 'plan-diagnostic.json'
    $argsProbe=@('-d','allow_url_fopen=0','-d','disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail',
        $script:planProbe,"--root=$($Context.Root)","--pid=$($Context.Process.Id)","--output=$output")
    $run=Invoke-BoundedCheck $script:php $argsProbe $Context.Root (Join-Path $Context.Logs 'plan.stdout.log') (Join-Path $Context.Logs 'plan.stderr.log') 45000
    Write-CheckJson (Join-Path $Context.Logs 'plan-result.json') $run
    if($run.TimedOut -or $run.ExitCode -ne 0 -or ([IO.File]::ReadAllText((Join-Path $Context.Logs 'plan.stderr.log'))).Trim().Length){throw 'Diagnostic probe failed. No retry.'}
    Assert-P2Process $Context.Process $Context.Server $Context.Ini $Context.Ticks $Context.Target -RequireListener -EvidenceDirectory $Context.Logs
    if(Get-P2StartupIssue (Join-Path $Context.Logs 'server-error.log')){throw 'Diagnostic server warning/error needs review.'}
    $report=[IO.File]::ReadAllText($output)|ConvertFrom-Json
    Assert-P3DReport $report
    foreach($file in @('indexes.json','query-plan.json')){if(-not (Test-Path -LiteralPath (Join-Path $Context.Logs $file))){throw 'Actual diagnostic observations were not saved.'}}
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

$last=Join-Path $attemptRoot 'phase2b-20261020-192336-6a4bea4a'
$protected += [pscustomobject]@{Path=(Join-Path $last 'capture\data');Hash='B850F25EFFA63989D09898835FFF1CF62312BEC319472750BE04CA5230ED3010';Count=2767}
$planProbe=Join-Path $PSScriptRoot 'database_phase3a_plan_probe.php'
$pins[(Join-Path $PSScriptRoot 'database_phase3a_probe.php')]='B2AF34FAA83A80391044D29720847143B411653197346ED29CC8E70D1AC0081E'
$pins[$planProbe]='DEFDA95ABC052E9CC502E529B7CD78028D3BB5A587F354AF925CB60A4E3B6629'

$marker=Join-Path $attemptRoot 'evidence\phase3a-plan-diagnostic-attempt.json'
if($env:MYSQL_PWD -or $env:MYSQL_HOME -or $env:MARIADB_HOME){throw 'Unexpected credential/defaults environment. Stop without displaying it.'}
Assert-CheckOffline
Assert-ContextDirectories $attemptRoot
Assert-ContextDirectories (Join-Path $attemptRoot 'evidence')
if(Test-Path -LiteralPath $marker){throw 'Diagnostic attempt marker already exists. No repeat.'}
Assert-P3DPrevious ([IO.File]::ReadAllText((Join-Path $last 'results.json'))|ConvertFrom-Json) `
    ([IO.File]::ReadAllText((Join-Path $last 'capture\shutdown-result.json'))|ConvertFrom-Json) `
    ([IO.File]::ReadAllText((Join-Path $last 'capture\logs\capture.json'))|ConvertFrom-Json)
foreach($file in @('capture\logs\baseline-first.json','index-trial','validation','restore')){if(Test-Path -LiteralPath (Join-Path $last $file)){throw 'Unexpected progress in prior trial.'}}
foreach($path in $pins.Keys){Assert-P2BPin $path $pins[$path]}
Assert-P2BSourceManifest $sourceManifest $workspace
Assert-P2BPin $export $exportHash
Write-Host 'Verifying ten protected database trees and both upload trees. Keep XAMPP MySQL and Apache stopped.'
Assert-P2BProtected $protected
$uploadHash='FF2E87E9C44B4ADF9385EB8748AAAB56B569738FBADD6D148C84A967FFC328BE'
foreach($path in @($uploads,(Join-Path $workspace 'uploads'))){Assert-ContextDirectories $path;if((Get-CheckTree $path).TreeSHA256 -cne $uploadHash){throw 'Protected upload drift.'}}
if(([IO.DriveInfo]::new('C:\')).AvailableFreeSpace -lt (2GB+412226547)){throw 'Insufficient space for fresh diagnostic copy.'}
Initialize-P2CandidateIdentity
$stamp=(Get-Date -Format 'yyyyMMdd-HHmmss')+'-'+[Guid]::NewGuid().ToString('N').Substring(0,8)
$root=Join-Path $attemptRoot "phase3a-plan-$stamp"
if(Test-Path -LiteralPath $root){throw 'Fresh diagnostic destination exists.'}
Assert-P3DRoot (Join-Path $root 'diagnostic')
Assert-CheckOffline
Write-CheckJson $marker ([ordered]@{At=(Get-Date).ToString('o');Root=$root;Source=$source;SourceSHA256=$sourceHash;ProtectedTreeCount=10;NoRepair=$true;NoTableChecks=$true;NoImport=$true;NoFinancialRows=$true})
New-Item -ItemType Directory -Path $root|Out-Null
$activeProcess=$null;$activeContext=$null;$halt=$null;$shutdownIssue=$null;$report=$null
try{
    Write-Host 'Starting one fresh diagnostic-only copy on 127.0.0.1:3307; saving SHOW INDEX and EXPLAIN before evaluation.'
    $ctx=Start-P2BCopy (Join-Path $root 'diagnostic') $source $sourceHash
    $report=Invoke-P3DProbe $ctx
    Close-P2BCopy
}catch{$halt=$_.Exception.Message}
if($null -ne $activeProcess){
    try{
        if($null -eq $activeContext -or (Test-Path -LiteralPath (Join-Path $activeContext.Root 'graceful-shutdown-attempt.json'))){throw 'No repeated shutdown request permitted.'}
        Close-P2BCopy
    }catch{$shutdownIssue=$_.Exception.Message}
}
$running=$false;if($null -ne $activeProcess){$activeProcess.Refresh();$running=-not $activeProcess.HasExited}
Write-Host 'Verifying protected source trees, uploads/export and pinned code remain unchanged.'
$unchanged=$true
try{
    Assert-P2BProtected $protected
    Assert-P2BPin $export $exportHash
    foreach($path in @($uploads,(Join-Path $workspace 'uploads'))){if((Get-CheckTree $path).TreeSHA256 -cne $uploadHash){throw 'Upload drift.'}}
    foreach($path in $pins.Keys){Assert-P2BPin $path $pins[$path]}
    Assert-P2BSourceManifest $sourceManifest $workspace
}catch{$unchanged=$false}
$offline=$false;if(-not $running){try{Assert-CheckOffline;$offline=$true}catch{$shutdownIssue=$_.Exception.Message}}
$passed=$null -eq $halt -and $null -eq $shutdownIssue -and $null -ne $report -and $report.passed -and $unchanged -and $offline
Write-CheckJson (Join-Path $root 'results.json') ([ordered]@{At=(Get-Date).ToString('o');Passed=$passed;HaltReason=$halt;ShutdownIssue=$shutdownIssue;DiagnosticCompleted=($null -ne $report -and $report.passed);
    ProtectedTenTreesUploadsExportUnchanged=$unchanged;AllServersStopped=$offline;CandidateStillRunning=$running;NoRepair=$true;NoTableChecks=$true;NoImport=$true;NoFinancialRows=$true;
    NoWorkingChanges=$true;NoForcedStop=$true;NoMigration=$true;NoCutover=$true;U3IntegrationRun=$false})
if($passed){Write-Host 'PHASE3A_PLAN_DIAGNOSTIC_COMPLETE. Actual plan saved and candidate gracefully stopped; repair remains unperformed.'}
else{Write-Host 'PHASE3A_PLAN_DIAGNOSTIC_STOPPED_FOR_REVIEW. No retry, repair, force-stop or XAMPP startup.';if($halt){Write-Host $halt};if($shutdownIssue){Write-Host $shutdownIssue}}
if($running){Write-Host "Candidate PID $($activeProcess.Id) remains running. Do not touch its files or start XAMPP."}
Write-Host "Private plan diagnostic evidence: $root"
Write-Host 'Keep XAMPP stopped. Send the terminal result; do not rerun or share private logs/exports.'
