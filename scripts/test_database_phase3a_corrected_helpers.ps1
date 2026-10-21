# Function-only synthetic/helper tests. No operational recovery main or real repair runs.
function Invoke-P3CPreparationTests([string]$Workspace,[string]$TestRoot){
    $ErrorActionPreference='Stop'
    foreach($file in @('database_phase2a_candidate_corrected.ps1','database_phase2b_validate_restore.ps1','database_phase3a_corrected_trial.ps1')){
        $tokens=$null;$errors=$null
        $ast=[Management.Automation.Language.Parser]::ParseFile((Join-Path $Workspace "scripts\$file"),[ref]$tokens,[ref]$errors)
        if($errors.Count){throw 'Helper parse failed.'}
        foreach($node in $ast.EndBlock.Statements){if($node -is [Management.Automation.Language.FunctionDefinitionAst]){. ([scriptblock]::Create($node.Extent.Text))}}
    }
    if(Test-Path -LiteralPath $TestRoot){throw 'New test destination required.'}
    if(-not ([IO.Path]::GetFullPath($TestRoot)).StartsWith((Join-Path $Workspace '.migration-private\'),[StringComparison]::OrdinalIgnoreCase)){throw 'Synthetic files must stay in private workspace.'}
    New-Item -ItemType Directory -Path $TestRoot|Out-Null
    $checks=New-Object 'System.Collections.Generic.List[string]'
    function Check([string]$Name,[bool]$Ok){if(-not $Ok){throw "FAILED: $Name"};$checks.Add($Name)}
    function Reject([string]$Name,[scriptblock]$Action){$bad=$false;try{&$Action|Out-Null}catch{$bad=$true};Check $Name $bad}
    function Clone([object]$Value){$Value|ConvertTo-Json -Depth 20 -Compress|ConvertFrom-Json}
    $prior='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase2b-20261020-185120-15e57a02'
    $r=[IO.File]::ReadAllText((Join-Path $prior 'results.json'))|ConvertFrom-Json
    $stop=[IO.File]::ReadAllText((Join-Path $prior 'validation\shutdown-result.json'))|ConvertFrom-Json
    $rows=@([IO.File]::ReadAllLines((Join-Path $prior 'validation\logs\table-checks.jsonl'))|ForEach-Object{$_|ConvertFrom-Json})
    Assert-P3Prior $r $stop $rows;Check 'actual stopped corruption evidence accepted' $true
    foreach($key in @('ProtectedEightTreesAndUploadsUnchanged','AllServersStopped','NoManualRepair','NoForcedStop','NoWorkingChanges','NoMigration','NoCutover')){
        $bad=Clone $r;$bad.$key=$false;Reject ('previous protection rejected: '+$key) {Assert-P3Prior $bad $stop $rows}
    }
    $bad=Clone $r;$bad.RestoreInspected=$true;Reject 'unexpected restore rejected' {Assert-P3Prior $bad $stop $rows}
    $bad=Clone $r;$bad.CandidateStillRunning=$true;Reject 'live previous candidate rejected' {Assert-P3Prior $bad $stop $rows}
    $bad=Clone $stop;$bad.ExitCode=1;Reject 'abnormal previous shutdown rejected' {Assert-P3Prior $r $bad $rows}
    Reject 'missing prior check rejected' {Assert-P3Prior $r $stop @($rows[0..55])}
    $bad=Clone $rows;$bad[56].object='`mysql`.`proc`';Reject 'other repair target rejected' {Assert-P3Prior $r $stop $bad}
    $bad=Clone $rows;$bad[56].result[0].Msg_text='different error';Reject 'other index error rejected' {Assert-P3Prior $r $stop $bad}
    $bad=Clone $rows;$bad[0].result[0].Msg_type='warning';Reject 'earlier warning rejected' {Assert-P3Prior $r $stop $bad}
    $root='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase2b-20990101-000000-'+[Guid]::NewGuid().ToString('N').Substring(0,8)+'\index-trial'
    Assert-P3TrialRoot $root;Check 'fresh offline trial role accepted' $true
    foreach($path in @('C:\xampp\mysql\data',($root.Replace('index-trial','validation')),($root+'\..\restore'),'C:\elsewhere\phase2b-20990101-000000-abcdef12\index-trial')){
        Reject ('unsafe repair role rejected: '+$path) {Get-P3RepairArguments $path}
    }
    Reject 'repair role newline rejected' {Get-P3RepairArguments ($root+"`n")}
    $argsRepair=@(Get-P3RepairArguments $root)
    Check 'one recover and one quick only' (@($argsRepair|Where-Object{$_ -ceq '--recover'}).Count -eq 1 -and @($argsRepair|Where-Object{$_ -ceq '--quick'}).Count -eq 1)
    Check 'no defaults no force no logging bypass' ($argsRepair[0] -ceq '--no-defaults' -and $argsRepair -contains '--require-control-file' -and $argsRepair -contains '--skip-transaction-log' -and -not ($argsRepair -match '^--(force|ignore-control-file|safe-recover|zerofill|extend-check)'))
    Check 'single fixed index target' ($argsRepair.Count -eq 9 -and $argsRepair[-1] -ceq (Join-Path $root 'data\mysql\tables_priv.MAI'))
    $repairHelp=Invoke-BoundedCheck 'C:\xampp\mysql\bin\aria_chk.exe' ($argsRepair+@('--help')) $TestRoot (Join-Path $TestRoot 'aria-help.out') (Join-Path $TestRoot 'aria-help.err') 15000
    $help=[IO.File]::ReadAllText((Join-Path $TestRoot 'aria-help.out'));$helpErr=[IO.File]::ReadAllText((Join-Path $TestRoot 'aria-help.err'))
    Assert-P3RepairHelp $help $helpErr $repairHelp $root;Check 'installed entire repair option set help accepted' $true
    Check 'help did not create hypothetical target' (-not (Test-Path -LiteralPath $root))
    Reject 'help wrong control handling rejected' {Assert-P3RepairHelp ($help.Replace('require-control-file              TRUE','require-control-file              FALSE')) $helpErr $repairHelp $root}
    Reject 'help error rejected' {Assert-P3RepairHelp $help 'warning' $repairHelp $root}
    Reject 'help nonzero rejected' {Assert-P3RepairHelp $help '' ([pscustomobject]@{TimedOut=$false;ExitCode=1}) $root}
    $ok=[pscustomobject]@{ExitCode=0;TimedOut=$false}
    $progress="- recovering (with sort) Aria-table 'synthetic'`r`nData records: 3`r`n- Fixing index 1`r`n- Fixing index 2`r`nState updated`r`n"
    Assert-P3RepairResult $progress '' $ok 3;Check 'known repair progress requires same count' $true
    Assert-P3RepairResult ($progress.Replace('with sort','with keycache')) '' $ok 3;Check 'quick keycache algorithm accepted without retry' $true
    foreach($word in @('Warning','Error','duplicate','skipped','lost','aborted','retrying','zerofill')){
        Reject ('repair diagnostic rejected: '+$word) {Assert-P3RepairResult ($progress+"`n"+$word) '' $ok 3}
    }
    Reject 'repair stderr rejected' {Assert-P3RepairResult $progress 'unexpected' $ok 3}
    Reject 'repair timeout rejected' {Assert-P3RepairResult $progress '' ([pscustomobject]@{ExitCode=0;TimedOut=$true}) 3}
    Reject 'repair nonzero rejected' {Assert-P3RepairResult $progress '' ([pscustomobject]@{ExitCode=1;TimedOut=$false}) 3}
    Reject 'repair count loss rejected' {Assert-P3RepairResult $progress '' $ok 4}
    Reject 'no recovery progress rejected' {Assert-P3RepairResult 'nothing done' '' $ok 3}
    Reject 'ambiguous multiple counts rejected' {Assert-P3RepairResult ($progress+"`nData records: 2`n") '' $ok 3}
    $data=Join-Path $TestRoot 'synthetic-data';New-Item -ItemType Directory -Path (Join-Path $data 'mysql')|Out-Null
    foreach($name in @('tables_priv.MAI','tables_priv.MAD','tables_priv.frm')){[IO.File]::WriteAllText((Join-Path $data ('mysql\'+$name)),'before-'+$name)}
    [IO.File]::WriteAllText((Join-Path $data 'aria_log_control'),'unchanged-control')
    $before=Get-CheckTree $data
    [IO.File]::WriteAllText((Join-Path $data 'mysql\tables_priv.MAI'),'synthetic-rebuilt-index')
    $after=Get-CheckTree $data
    Assert-P3IndexOnlyDelta $before $after;Check 'only target index delta accepted' $true
    Reject 'unchanged target rejected' {Assert-P3IndexOnlyDelta $before $before}
    [IO.File]::WriteAllText((Join-Path $data 'mysql\tables_priv.MAD'),'changed-data')
    Reject 'data rewrite rejected' {Assert-P3IndexOnlyDelta $before (Get-CheckTree $data)}
    [IO.File]::WriteAllText((Join-Path $data 'mysql\tables_priv.MAD'),'before-tables_priv.MAD')
    [IO.File]::WriteAllText((Join-Path $data 'aria_log_control'),'changed-control')
    Reject 'control/log write rejected' {Assert-P3IndexOnlyDelta $before (Get-CheckTree $data)}
    [IO.File]::WriteAllText((Join-Path $data 'aria_log_control'),'unchanged-control')
    [IO.File]::WriteAllText((Join-Path $data 'unexpected.tmp'),'extra')
    Reject 'leftover extra database file rejected' {Assert-P3IndexOnlyDelta $before (Get-CheckTree $data)}
    $cap=[pscustomobject]@{passed=$true;mode='capture';writes_performed=$false;working_database_accessed=$false;stable_baseline=$true;no_table_checks=$true;
        baseline=[pscustomobject]@{snapshot=@{tables=@{a=1}};tables_priv_scan=[pscustomobject]@{access='HANDLER_TABLE_SCAN';index=$null;rows=3;eof=$true;counter_proof=$true}}}
    Assert-P3CCaptureReport $cap;Check 'complete full scan baseline accepted' $true
    $bad=Clone $cap;$bad.baseline.tables_priv_scan.access='index';Reject 'indexed baseline rejected' {Assert-P3CCaptureReport $bad}
    $bad=Clone $cap;$bad.no_table_checks=$false;Reject 'checks before baseline rejected' {Assert-P3CCaptureReport $bad}
    $bad=Clone $cap;$bad.stable_baseline=$false;Reject 'unstable baseline rejected' {Assert-P3CCaptureReport $bad}
    $bad=Clone $cap;$bad.writes_performed=$true;Reject 'baseline write claim rejected' {Assert-P3CCaptureReport $bad}
    $bad=Clone $cap;$bad.baseline.tables_priv_scan.rows=$null;Reject 'missing baseline count rejected' {Assert-P3CCaptureReport $bad}
    $bad=Clone $cap;$bad.baseline.tables_priv_scan.rows=-1;Reject 'negative baseline count rejected' {Assert-P3CCaptureReport $bad}
    $bad=Clone $cap;$bad.baseline.tables_priv_scan.PSObject.Properties.Remove('index');Reject 'missing explicit index null rejected' {Assert-P3CCaptureReport $bad}

    foreach($key in @('eof','counter_proof')){$bad=Clone $cap;$bad.baseline.tables_priv_scan.$key=$false;Reject ('missing direct scan proof: '+$key) {Assert-P3CCaptureReport $bad}}
    $bad=Clone $cap;$bad.baseline.tables_priv_scan.access='ALL';Reject 'old optimizer-only baseline rejected' {Assert-P3CCaptureReport $bad}
    $d='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase3a-plan-20261020-195003-f2761503'
    $r=[IO.File]::ReadAllText((Join-Path $d 'results.json'))|ConvertFrom-Json
    $st=[IO.File]::ReadAllText((Join-Path $d 'diagnostic\shutdown-result.json'))|ConvertFrom-Json
    $rp=[IO.File]::ReadAllText((Join-Path $d 'diagnostic\logs\plan-diagnostic.json'))|ConvertFrom-Json
    $failed=[IO.File]::ReadAllText('C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase2b-20261020-192336-6a4bea4a\results.json')|ConvertFrom-Json
    Assert-P3CPrevious $r $st $rp $failed;Check 'actual successful plan diagnostic gate accepted' $true
    foreach($key in @('Passed','DiagnosticCompleted','ProtectedTenTreesUploadsExportUnchanged','AllServersStopped','NoRepair','NoTableChecks','NoImport','NoFinancialRows','NoWorkingChanges','NoForcedStop','NoMigration','NoCutover')){
        $bad=Clone $r;$bad.$key=$false;Reject ('diagnostic gate requires: '+$key) {Assert-P3CPrevious $bad $st $rp $failed}
    }
    $bad=Clone $failed;$bad.RepairAttempted=$true;Reject 'previous repair attempt cannot be retried' {Assert-P3CPrevious $r $st $rp $bad}
    $bad=Clone $st;$bad.ExitCode=1;Reject 'diagnostic abnormal exit blocks trial' {Assert-P3CPrevious $r $bad $rp $failed}
    $bad=Clone $rp;$bad.explain[0].type='range';Reject 'different observed plan needs review' {Assert-P3CPrevious $r $st $bad $failed}

    Write-CheckJson (Join-Path $TestRoot 'results.json') ([ordered]@{Passed=$checks.Count;Checks=@($checks);DatabaseServerStarted=$false;SQLExecuted=$false;RealRepairRun=$false;InstalledHelpAndSyntheticOnly=$true})
    [pscustomobject]@{Passed=$checks.Count;Evidence=$TestRoot;DatabaseServerStarted=$false;SQLExecuted=$false;RealRepairRun=$false}
}
