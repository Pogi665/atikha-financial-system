# Function-only synthetic/predecessor checks. No recovery operational main runs.
function Invoke-P3DPreparationTests([string]$Workspace,[string]$TestRoot){
    $ErrorActionPreference='Stop'
    foreach($file in @('database_phase2a_candidate_corrected.ps1','database_phase2b_validate_restore.ps1','database_phase3b_complete.ps1')){
        $tokens=$null;$errors=$null;$ast=[Management.Automation.Language.Parser]::ParseFile((Join-Path $Workspace "scripts\$file"),[ref]$tokens,[ref]$errors)
        if($errors.Count){throw 'Helper parse failure.'}
        foreach($node in $ast.EndBlock.Statements){if($node -is [Management.Automation.Language.FunctionDefinitionAst]){. ([scriptblock]::Create($node.Extent.Text))}}
    }
    if(Test-Path -LiteralPath $TestRoot){throw 'New test root required.'}
    if(-not ([IO.Path]::GetFullPath($TestRoot)).StartsWith((Join-Path $Workspace '.migration-private\'),[StringComparison]::OrdinalIgnoreCase)){throw 'Synthetic test root must stay private.'}
    New-Item -ItemType Directory -Path $TestRoot|Out-Null
    $checks=New-Object 'System.Collections.Generic.List[string]'
    function Check([string]$Name,[bool]$Ok){if(-not $Ok){throw "FAILED: $Name"};$checks.Add($Name)}
    function Reject([string]$Name,[scriptblock]$Action){$bad=$false;try{&$Action|Out-Null}catch{$bad=$true};Check $Name $bad}
    function Clone([object]$Value){$Value|ConvertTo-Json -Depth 30 -Compress|ConvertFrom-Json}
    $prior='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase2b-20261021-012006-549712af'
    $result=[IO.File]::ReadAllText((Join-Path $prior 'results.json'))|ConvertFrom-Json
    $report=[IO.File]::ReadAllText((Join-Path $prior 'validation\logs\inspection.json'))|ConvertFrom-Json
    $stops=@([IO.File]::ReadAllText((Join-Path $prior 'capture\shutdown-result.json'))|ConvertFrom-Json;[IO.File]::ReadAllText((Join-Path $prior 'validation\shutdown-result.json'))|ConvertFrom-Json)
    $rows=@([IO.File]::ReadAllLines((Join-Path $prior 'validation\logs\table-checks.jsonl'))|ForEach-Object {$_|ConvertFrom-Json})
    Assert-P3DPrior $result $report $stops $rows;Check 'actual normalized predecessor accepted' $true
    foreach($key in @('CaptureCompleted','NormalizationAttempted','OfflineTargetOnlyDeltaAndHeaderVerified','ProtectedFourteenTreesUploadsExportAndArtifactsUnchanged','AllServersStopped','NoWorkingChanges','NoForcedStop','NoMigration','NoCutover')){
        $bad=Clone $result;$bad.$key=$false;Reject ('missing predecessor proof rejected '+$key) {Assert-P3DPrior $bad $report $stops $rows}
    }
    foreach($key in @('Passed','ValidationPassed','RestorePassed','ExactApplicationRestoreMatched','CandidateStillRunning','WorkingAcceptance','U3IntegrationRun')){
        $bad=Clone $result;$bad.$key=$true;Reject ('unexpected predecessor progression rejected '+$key) {Assert-P3DPrior $bad $report $stops $rows}
    }
    $bad=Clone $report;$bad.failure='different';Reject 'different stop reason rejected' {Assert-P3DPrior $result $bad $stops $rows}
    $bad=Clone $report;$bad.baseline_matched_before_checks=$false;Reject 'unproved predecessor baseline rejected' {Assert-P3DPrior $result $bad $stops $rows}
    $bad=Clone $report;@($bad.foreign_keys.PSObject.Properties)[0].Value='1';Reject 'orphan predecessor rejected' {Assert-P3DPrior $result $bad $stops $rows}
    $bad=Clone $report;$bad.integrity_problems=@('problem');Reject 'integrity predecessor rejected' {Assert-P3DPrior $result $bad $stops $rows}
    $bad=Clone $stops;$bad[0].ExitCode=1;Reject 'abnormal predecessor stop rejected' {Assert-P3DPrior $result $report $bad $rows}
    $bad=Clone $rows;$bad[0].result[0].Msg_type='warning';Reject 'prior table warning rejected' {Assert-P3DPrior $result $report $stops $bad}
    Reject 'missing prior check rejected' {Assert-P3DPrior $result $report $stops @($rows[0..63])}
    $data=Join-Path $TestRoot 'synthetic-uploads';New-Item -ItemType Directory -Path (Join-Path $data 'receipts')|Out-Null
    [IO.File]::WriteAllText((Join-Path $data 'receipts\keep'),'existing')
    $before=Get-CheckTree $data;$recoveries=@()
    for($i=1;$i -le 3;$i++){
        $file=Join-Path $data ('receipts\restored'+$i+'.png');[IO.File]::WriteAllText($file,'new-file-'+$i)
        $recoveries += [pscustomobject]@{PublicPath=('uploads/receipts/restored'+$i+'.png');Bytes=([IO.FileInfo]::new($file)).Length;Hashes=@((Get-FileHash -LiteralPath $file).Hash)}
    }
    $after=Get-CheckTree $data;Assert-P3DUploadDelta $before $after $recoveries;Check 'exactly three new files accepted' $true
    Reject 'unchanged tree rejected' {Assert-P3DUploadDelta $before $before $recoveries}
    $bad=Clone $recoveries;$bad[1].PublicPath=$bad[0].PublicPath;Reject 'duplicate target rejected' {Assert-P3DUploadDelta $before $after $bad}
    foreach($unsafe in @('uploads/receipts/../escape','outside/path','uploads/receipts/a:stream')){$bad=Clone $recoveries;$bad[0].PublicPath=$unsafe;Reject 'unsafe file scope rejected' {Assert-P3DUploadDelta $before $after $bad}}
    $bad=Clone $recoveries;$bad[0].Hashes=@();Reject 'missing recorded hash rejected' {Assert-P3DUploadDelta $before $after $bad}
    $bad=Clone $recoveries;$bad[0].Hashes+=@(('0'*64));Reject 'disagreeing recorded hash rejected' {Assert-P3DUploadDelta $before $after $bad}
    $bad=Clone $recoveries;$bad[0].Bytes=1;Reject 'wrong recovered size rejected' {Assert-P3DUploadDelta $before $after $bad}
    [IO.File]::WriteAllText((Join-Path $data 'receipts\keep'),'modified');Reject 'existing content change rejected' {Assert-P3DUploadDelta $before (Get-CheckTree $data) $recoveries}
    [IO.File]::WriteAllText((Join-Path $data 'receipts\keep'),'existing')
    [IO.File]::WriteAllText((Join-Path $data 'extra'),'unexpected');Reject 'fourth file rejected' {Assert-P3DUploadDelta $before (Get-CheckTree $data) $recoveries}
    $bad=Clone $after;$bad.Entries=@($bad.Entries|Where-Object {$_.Path -cne 'receipts\keep'});Reject 'removed original file rejected' {Assert-P3DUploadDelta $before $bad $recoveries}
    $bad=Clone $after;$bad.Entries+=@($bad.Entries[0]);Reject 'duplicate manifest entry rejected' {Assert-P3DUploadDelta $before $bad $recoveries}
    $script:probe='C:\private app\probe.php';$script:uploads='C:\private uploads';$script:baseline='C:\private original\baseline.json';$script:completionManifest='C:\private manifest\inputs.json';$script:completionManifestHash='A'*64
    $ctx=[pscustomobject]@{Root='C:\private app\validation';Process=[pscustomobject]@{Id=123}}
    $argsProbe=@(Get-P3DArguments $ctx 'C:\private app\validation\logs\inspection.json')
    Check 'inspection arguments remain separate' ($argsProbe.Count -eq 12 -and $argsProbe[-3] -ceq '--baseline=C:\private original\baseline.json' -and $argsProbe[-1] -ceq ('--manifest-sha='+('A'*64)))
    Check 'inspection blocks server-side providers' ($argsProbe -contains 'allow_url_fopen=0' -and $argsProbe -contains 'disable_functions=curl_exec,curl_multi_exec,fsockopen,pfsockopen,stream_socket_client,mail')
    $argsFiles=@(Get-P3DRecoveryArguments 'C:\private app' 'C:\private uploads' 'C:\private app\logs\filename-recovery.json')
    Check 'file recovery arguments remain separate' ($argsFiles.Count -eq 7 -and $argsFiles[1] -ceq '--prepare-files' -and $argsFiles[4] -ceq '--output=C:\private app\logs\filename-recovery.json' -and $argsFiles[5] -ceq '--manifest=C:\private manifest\inputs.json')
    $recovered=[pscustomobject]@{passed=$true;sql_executed=$false;working_database_accessed=$false;passed_recovery=$true;recovered_count=3;files=@(1,2,3)}
    Assert-P3DRecoveryReport $recovered;Check 'complete file recovery report accepted' $true
    foreach($key in @('passed','passed_recovery')){$bad=Clone $recovered;$bad.$key=$false;Reject 'incomplete recovery rejected' {Assert-P3DRecoveryReport $bad}}
    foreach($key in @('sql_executed','working_database_accessed')){$bad=Clone $recovered;$bad.$key=$true;Reject 'unexpected database action rejected' {Assert-P3DRecoveryReport $bad}}
    $bad=Clone $recovered;$bad.files=@(1,2);Reject 'partial recovery file list rejected' {Assert-P3DRecoveryReport $bad}
    $full=[pscustomobject]@{passed=$true;writes_performed=$false;working_database_accessed=$false;mode='inspect';baseline_matched_before_checks=$true;baseline_matched_after_checks=$true;receipt_metadata_matches_pinned_export=$true;stable_before_after_checks=$true;checked_objects=65;integrity_problems=@();snapshot=@{tables=@{original='same'}};evidence=@{files=@{receipt='same'}};foreign_keys=$report.foreign_keys}
    Assert-P3DReport $full;Check 'full final inspection report accepted' $true
    foreach($key in @('baseline_matched_before_checks','baseline_matched_after_checks','receipt_metadata_matches_pinned_export','stable_before_after_checks')){$bad=Clone $full;$bad.$key=$false;Reject ('missing final proof rejected '+$key) {Assert-P3DReport $bad}}
    $bad=Clone $full;$bad.checked_objects=64;Reject 'incomplete table inventory rejected' {Assert-P3DReport $bad}
    $bad=Clone $full;@($bad.foreign_keys.PSObject.Properties)[0].Value='1';Reject 'final orphan rejected' {Assert-P3DReport $bad}
    $source=[IO.File]::ReadAllText((Join-Path $Workspace 'scripts\database_phase3b_complete.ps1'))
    Check 'continuation invokes no repair utility/SQL' (-not ($source -match '(?i)aria_chk|--zerofill|--recover|REPAIR TABLE'))
    Check 'continuation uses new one-shot marker' ($source.Contains('phase3b-completion-attempt.json') -and -not $source.Contains('phase3b-normalization-attempt.json'))
    Write-CheckJson (Join-Path $TestRoot 'results.json') ([ordered]@{Passed=$checks.Count;Checks=@($checks);SQLExecuted=$false;ServerStarted=$false;RealReceiptRestored=$false;SyntheticFilesOnly=$true})
    [pscustomobject]@{Passed=$checks.Count;Evidence=$TestRoot;SQLExecuted=$false;ServerStarted=$false;RealReceiptRestored=$false}
}
