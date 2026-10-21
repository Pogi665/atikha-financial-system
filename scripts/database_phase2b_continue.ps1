# One separately authorized continuation from the cleanly stopped Phase 2-B copy.
# Previous helpers, attempt markers, protected data and export are never edited/reused.
[CmdletBinding()]
param([switch]$ContinueIsolatedValidationRestore)
$ErrorActionPreference='Stop'
if(-not $ContinueIsolatedValidationRestore){throw 'No action taken. Specify -ContinueIsolatedValidationRestore for the authorized fresh-copy continuation.'}

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

function Assert-P2BContinuationGate([object]$Result,[object]$Stop,[object]$Probe,[object[]]$Checks,[object]$Before,[object]$After){
    if($Result.Passed -ne $false -or $Result.HaltReason -cne 'Isolated table/integrity/evidence probe needs review. Stop without repair.' -or
        $Result.ShutdownIssue -or $Result.ValidationPassed -ne $false -or $Result.RestoreInspected -ne $false -or $Result.ExactApplicationRestoreMatched -ne $false -or
        $Result.ProtectedSevenTreesAndUploadsUnchanged -ne $true -or $Result.AllServersStopped -ne $true -or $Result.CandidateStillRunning -ne $false -or
        $Result.NoManualRepair -ne $true -or $Result.NoForcedStop -ne $true -or $Result.NoWorkingChanges -ne $true -or $Result.NoMigration -ne $true -or $Result.NoCutover -ne $true -or
        $Result.ExportSHA256 -cne 'E6C603BAFF6038977A177AD97D2A10F4F0D5D55A21381372B22E287E80DBE22A'){
        throw 'Earlier Phase 2-B result differs from the reviewed stopped attempt.'
    }
    if($Stop.State -cne 'gracefully-stopped' -or $null -eq $Stop.ExitCode -or $Stop.ExitCode -ne 0 -or
        $null -eq $Stop.Client.ExitCode -or $Stop.Client.ExitCode -ne 0 -or $Stop.Client.TimedOut -ne $false){throw 'Prior candidate graceful stop is not verified.'}
    if($Probe.passed -ne $false -or $Probe.writes_performed -ne $false -or $Probe.failure -cne 'CHECK TABLE needs review; no repair attempted.'){
        throw 'Earlier probe failure does not match reviewed table-status stop.'
    }
    if($Checks.Count -ne 51 -or @($Checks | Select-Object -ExpandProperty object -Unique).Count -ne 51){throw 'Earlier serial check inventory differs.'}
    for($i=0;$i -lt 50;$i++){
        $c=$Checks[$i];$r=@($c.result)
        $prefix=if($i -lt 34){'`atikha_finance`.'}else{'`mysql`.'}
        if(-not ([string]$c.object).StartsWith($prefix,[StringComparison]::Ordinal) -or $r.Count -ne 1 -or
            $r[0].Msg_type -cne 'status' -or $r[0].Msg_text -cne 'OK' -or $r[0].Op -cne 'check'){
            throw 'A previous application/system check was not clean OK.'
        }
    }
    $last=$Checks[50];$r=@($last.result)
    if($last.object -cne '`mysql`.`proc`' -or $r.Count -ne 2 -or
        $r[0].Table -cne 'mysql.proc' -or $r[0].Op -cne 'check' -or $r[0].Msg_type -cne 'warning' -or
        $r[0].Msg_text -cne "1 client is using or hasn't closed the table properly" -or
        $r[1].Table -cne 'mysql.proc' -or $r[1].Op -cne 'check' -or $r[1].Msg_type -cne 'status' -or $r[1].Msg_text -cne 'OK'){
        throw 'Earlier mysql.proc result is not the exact reviewed warning plus OK.'
    }
    if($Before.TreeSHA256 -cne 'D0DA68893E42300F3E6CB6B1384887FF8C76D745C7FB5B753EB59A4E71FD680E' -or $Before.FileCount -ne 2767 -or
        $After.TreeSHA256 -cne '4B3AA2146106026AC60F8368B52AACCE251570E44694232ABA308A4426B385E4' -or $After.FileCount -ne 2767){
        throw 'Earlier before/after candidate manifests differ from the reviewed state.'
    }
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
$marker=Join-Path $attemptRoot 'evidence\phase2b-continuation-attempt.json'
if($env:MYSQL_PWD -or $env:MYSQL_HOME -or $env:MARIADB_HOME){throw 'Unexpected database credential/defaults environment. Stop without displaying it.'}
Assert-CheckOffline
Assert-ContextDirectories $attemptRoot
Assert-ContextDirectories (Join-Path $attemptRoot 'evidence')
if(Test-Path -LiteralPath $marker){throw 'Phase 2-B continuation already has an attempt marker. No repeat.'}
$priorResult=[IO.File]::ReadAllText((Join-Path $prior 'results.json'))|ConvertFrom-Json
$priorPreserved=[IO.File]::ReadAllText((Join-Path $prior 'preservation-after.json'))|ConvertFrom-Json
if(-not $priorResult.AuthenticatedReady -or -not $priorResult.ExportCompleted -or -not $priorResult.MetadataCompleted -or
    $priorResult.HaltReason -or $priorResult.ShutdownIssue -or $priorResult.CandidateStillRunning -or $priorResult.Shutdown.State -ne 'gracefully-stopped' -or
    -not $priorPreserved.ProtectedSixTreesUnchanged){throw 'Successful stopped Phase 2-A evidence missing.'}
$stoppedResult=[IO.File]::ReadAllText((Join-Path $stoppedAttempt 'results.json'))|ConvertFrom-Json
$stoppedStop=[IO.File]::ReadAllText((Join-Path $stoppedValidation 'shutdown-result.json'))|ConvertFrom-Json
$stoppedProbe=[IO.File]::ReadAllText((Join-Path $stoppedValidation 'logs\inspection.json'))|ConvertFrom-Json
$stoppedChecks=@([IO.File]::ReadAllLines((Join-Path $stoppedValidation 'logs\table-checks.jsonl'))|ForEach-Object{$_|ConvertFrom-Json})
$stoppedBefore=[IO.File]::ReadAllText((Join-Path $stoppedValidation 'candidate-before.json'))|ConvertFrom-Json
$stoppedAfter=[IO.File]::ReadAllText((Join-Path $stoppedValidation 'candidate-after.json'))|ConvertFrom-Json
Assert-P2BContinuationGate $stoppedResult $stoppedStop $stoppedProbe $stoppedChecks $stoppedBefore $stoppedAfter
if(Test-Path -LiteralPath (Join-Path $stoppedAttempt 'restore')){throw 'Unexpected restore in the reviewed prior attempt.'}
foreach($path in $pins.Keys){Assert-P2BPin $path $pins[$path]}
Assert-P2BSourceManifest $sourceManifest $workspace
Assert-P2BPin $export $exportHash
if((Get-Item -LiteralPath $export).Length -ne 123247){throw 'Export size changed.'}
Write-Host 'Verifying eight protected database trees and both protected upload trees. Keep XAMPP stopped.'
Assert-P2BProtected $protected
$uploadHash='FF2E87E9C44B4ADF9385EB8748AAAB56B569738FBADD6D148C84A967FFC328BE'
foreach($path in @($uploads,(Join-Path $workspace 'uploads'))){
    Assert-ContextDirectories $path
    if((Get-CheckTree $path).TreeSHA256 -ne $uploadHash){throw 'Protected uploads changed. Stop for review.'}
}
if(([IO.DriveInfo]::new('C:\')).AvailableFreeSpace -lt (2GB+3*412226547)){throw 'Insufficient capacity for two copies/evidence/headroom.'}
Initialize-P2CandidateIdentity
$stamp=(Get-Date -Format 'yyyyMMdd-HHmmss')+'-'+[Guid]::NewGuid().ToString('N').Substring(0,8)
$root=Join-Path $attemptRoot "phase2b-$stamp"
if(Test-Path -LiteralPath $root){throw 'Phase 2-B destination already exists.'}
Assert-CheckOffline
Write-CheckJson $marker ([ordered]@{At=(Get-Date).ToString('o');Root=$root;ProtectedTreeCount=8;ExportSHA256=$exportHash;
    Source=$source;SourceSHA256=$sourceHash;PriorAttempt=$stoppedAttempt;Scope='One separately authorized fresh-copy validation and application restore continuation';NoWorkingChanges=$true;NoManualRepair=$true;NoForcedStop=$true})
New-Item -ItemType Directory -Path $root|Out-Null
$activeProcess=$null;$activeContext=$null;$halt=$null;$shutdownIssue=$null;$validation=$null;$restored=$null;$equal=$false
try {
    Write-Host 'Starting a new isolated validation copy; checking system/application tables, accounting and preserved attachments.'
    $ctx=Start-P2BCopy (Join-Path $root 'validation') $source $sourceHash
    $validation=Invoke-P2BProbe $ctx
    Close-P2BCopy
    Assert-P2BProtected $protected
    Write-Host 'Starting a second disposable copy; replacing ONLY its application schema with the pinned logical export.'
    $ctx=Start-P2BCopy (Join-Path $root 'restore') $source $sourceHash
    Invoke-P2BImport $ctx $export $exportHash
    $restored=Invoke-P2BProbe $ctx
    Assert-P2BEqual $validation.snapshot $restored.snapshot
    Assert-P2BEqual $validation.evidence $restored.evidence
    $equal=$true
    Close-P2BCopy
} catch {$halt=$_.Exception.Message}
# Always attempt one guarded graceful stop if a candidate remains; never retry a shutdown already attempted.
if($null -ne $activeProcess){
    try{
        if($null -eq $activeContext -or (Test-Path -LiteralPath (Join-Path $activeContext.Root 'graceful-shutdown-attempt.json'))){throw 'No additional shutdown request permitted; inspect candidate state.'}
        Close-P2BCopy
    } catch {$shutdownIssue=$_.Exception.Message}
}
$running=$false
if($null -ne $activeProcess){$activeProcess.Refresh();$running=-not $activeProcess.HasExited}
Write-Host 'Verifying all eight protected database trees, original export and both upload trees remain unchanged.'
$unchanged=$true
try {
    Assert-P2BProtected $protected
    Assert-P2BPin $export $exportHash
    foreach($path in @($uploads,(Join-Path $workspace 'uploads'))){if((Get-CheckTree $path).TreeSHA256 -ne $uploadHash){throw 'Protected uploads changed.'}}
    foreach($path in $pins.Keys){Assert-P2BPin $path $pins[$path]}
    Assert-P2BSourceManifest $sourceManifest $workspace
} catch {$unchanged=$false}
$offline=$false
if(-not $running){try{Assert-CheckOffline;$offline=$true}catch{$shutdownIssue=$_.Exception.Message}}
$passed=$null -eq $halt -and $null -eq $shutdownIssue -and $equal -and $unchanged -and $offline
Write-CheckJson (Join-Path $root 'results.json') ([ordered]@{At=(Get-Date).ToString('o');Passed=$passed;HaltReason=$halt;ShutdownIssue=$shutdownIssue;
    ValidationPassed=($null -ne $validation -and $validation.passed);RestoreInspected=($null -ne $restored -and $restored.passed);ExactApplicationRestoreMatched=$equal;
    ProtectedEightTreesAndUploadsUnchanged=$unchanged;AllServersStopped=$offline;CandidateStillRunning=$running;ExportSHA256=$exportHash;
    NoManualRepair=$true;NoForcedStop=$true;NoWorkingChanges=$true;NoMigration=$true;NoCutover=$true;WorkingAcceptance=$false;U3IntegrationRun=$false})
if($passed){Write-Host 'PHASE2B_CONTINUATION_COMPLETE. Isolated inspection/restore matched; working restart/acceptance remains separate.'}
else{
    Write-Host 'PHASE2B_CONTINUATION_STOPPED_FOR_REVIEW. No repair, retry, force-stop or working restart permitted.'
    if($halt){Write-Host $halt};if($shutdownIssue){Write-Host $shutdownIssue}
    if($running){Write-Host "Candidate PID $($activeProcess.Id) remains running. Do not touch its files or start XAMPP."}
}
Write-Host "Private Phase 2-B continuation evidence: $root"
Write-Host 'Keep XAMPP MySQL and Apache stopped. Send the terminal result; do not rerun or share private exports/logs.'
