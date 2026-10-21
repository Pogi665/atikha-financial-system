# Invoke function AST only. No operational recovery/diagnostic main is executed.
function Invoke-P3DPreparationTests([string]$Workspace,[string]$TestRoot){
    $ErrorActionPreference='Stop'
    foreach($file in @('database_phase2a_candidate_corrected.ps1','database_phase2b_validate_restore.ps1','database_phase3a_plan_diagnostic.ps1')){
        $tokens=$null;$errors=$null;$ast=[Management.Automation.Language.Parser]::ParseFile((Join-Path $Workspace "scripts\$file"),[ref]$tokens,[ref]$errors)
        if($errors.Count){throw 'Parse error.'}
        foreach($node in $ast.EndBlock.Statements){if($node -is [Management.Automation.Language.FunctionDefinitionAst]){. ([scriptblock]::Create($node.Extent.Text))}}
    }
    if(Test-Path -LiteralPath $TestRoot){throw 'Fresh test root required.'}
    if(-not ([IO.Path]::GetFullPath($TestRoot)).StartsWith((Join-Path $Workspace '.migration-private\'),[StringComparison]::OrdinalIgnoreCase)){throw 'Private workspace tests only.'}
    New-Item -ItemType Directory -Path $TestRoot|Out-Null
    $checks=New-Object 'System.Collections.Generic.List[string]'
    function Check([string]$Name,[bool]$Ok){if(-not $Ok){throw "FAILED: $Name"};$checks.Add($Name)}
    function Reject([string]$Name,[scriptblock]$Fn){$bad=$false;try{&$Fn|Out-Null}catch{$bad=$true};Check $Name $bad}
    function Clone([object]$Value){$Value|ConvertTo-Json -Depth 20 -Compress|ConvertFrom-Json}
    $last='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase2b-20261020-192336-6a4bea4a'
    $r=[IO.File]::ReadAllText((Join-Path $last 'results.json'))|ConvertFrom-Json
    $stop=[IO.File]::ReadAllText((Join-Path $last 'capture\shutdown-result.json'))|ConvertFrom-Json
    $probe=[IO.File]::ReadAllText((Join-Path $last 'capture\logs\capture.json'))|ConvertFrom-Json
    Assert-P3DPrevious $r $stop $probe;Check 'actual stopped pre-repair failure accepted' $true
    foreach($key in @('ProtectedNineTreesUploadsExportAndArtifactsUnchanged','AllServersStopped','NoWorkingChanges','NoForcedStop','NoMigration','NoCutover')){
        $bad=Clone $r;$bad.$key=$false;Reject ('unsafe previous state: '+$key) {Assert-P3DPrevious $bad $stop $probe}
    }
    foreach($key in @('Passed','CaptureCompleted','RepairAttempted','ValidationPassed','RestorePassed','CandidateStillRunning')){
        $bad=Clone $r;$bad.$key=$true;Reject ('unexpected previous progression: '+$key) {Assert-P3DPrevious $bad $stop $probe}
    }
    $bad=Clone $stop;$bad.ExitCode=1;Reject 'abnormal prior shutdown rejected' {Assert-P3DPrevious $r $bad $probe}
    $bad=Clone $stop;$bad.Client.TimedOut=$true;Reject 'prior shutdown timeout rejected' {Assert-P3DPrevious $r $bad $probe}
    $bad=Clone $probe;$bad.failure='different';Reject 'different probe failure rejected' {Assert-P3DPrevious $r $stop $bad}
    $root='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase3a-plan-20990101-000000-abcdef12\diagnostic'
    Assert-P3DRoot $root;Check 'new diagnostic role accepted' $true
    foreach($path in @('C:\xampp\mysql\data',$root.Replace('diagnostic','restore'),($root+'\..\restore'),($root+"`n"),($root+'\x'))){Reject 'wrong or unsafe role rejected' {Assert-P3DRoot $path}}
    $good=[pscustomobject]@{passed=$true;mode='plan-diagnostic';diagnostic_complete=$true;working_database_accessed=$false;writes_performed=$false;financial_rows_read=$false;
        permission_row_select_executed=$false;table_checks_performed=$false;repair_performed=$false;import_performed=$false;scan_sql='SELECT * FROM mysql.tables_priv IGNORE INDEX (`PRIMARY`,`Grantor`)';
        explain=@([pscustomobject]@{type='system';key=$null});indexes=@([pscustomobject]@{Key_name='PRIMARY'});existing_scan_guard_accepted=$false}
    Assert-P3DReport $good;Check 'diagnostic complete with rejected original plan' $true
    $accepted=Clone $good;$accepted.existing_scan_guard_accepted=$true;Assert-P3DReport $accepted;Check 'either actual plan verdict can be recorded' $true
    foreach($key in @('working_database_accessed','writes_performed','financial_rows_read','permission_row_select_executed','table_checks_performed','repair_performed','import_performed')){
        $bad=Clone $good;$bad.$key=$true;Reject ('forbidden diagnostic action: '+$key) {Assert-P3DReport $bad}
    }
    foreach($key in @('passed','diagnostic_complete')){$bad=Clone $good;$bad.$key=$false;Reject ('incomplete report: '+$key) {Assert-P3DReport $bad}}
    $bad=Clone $good;$bad.scan_sql='SELECT * FROM atikha_finance.journal_entries';Reject 'other query scope rejected' {Assert-P3DReport $bad}
    foreach($key in @('explain','indexes','existing_scan_guard_accepted')){$bad=Clone $good;$bad.$key=$null;Reject ('missing observation: '+$key) {Assert-P3DReport $bad}}
    # Structural safety boundary: the new operational main has no repair/import/check command invocation.
    $tokens=$null;$errors=$null;$main=[Management.Automation.Language.Parser]::ParseFile((Join-Path $Workspace 'scripts\database_phase3a_plan_diagnostic.ps1'),[ref]$tokens,[ref]$errors)
    $commands=@($main.FindAll({param($a)$a -is [Management.Automation.Language.CommandAst]},$true)|ForEach-Object{$_.GetCommandName()})
    Check 'new main never calls import or repair functions' (-not @($commands|Where-Object{$_ -in @('Invoke-P2BImport','Invoke-P2BProbe','Invoke-P3Probe','Get-P3RepairArguments','Repair-Database')}).Count)
    $p=[IO.File]::ReadAllText((Join-Path $Workspace 'scripts\database_phase3a_plan_probe.php'))
    Check 'new probe never calls row reader or table checks' ($p -notmatch 'p3a_capture\(|p3a_scan\(|p2b_snapshot\(|p2b_table_checks\(|correction_integrity\(')
    Write-CheckJson (Join-Path $TestRoot 'results.json') ([ordered]@{Passed=$checks.Count;Checks=@($checks);ServerStarted=$false;SQLExecuted=$false;RepairRun=$false;SyntheticOnly=$true})
    [pscustomobject]@{Passed=$checks.Count;Evidence=$TestRoot;ServerStarted=$false;SQLExecuted=$false}
}
