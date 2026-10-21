# Function-only synthetic guard tests; no operational main, server, SQL or imports.
function Invoke-P2BContinuationPreparationTests([string]$Workspace,[string]$TestRoot){
    $ErrorActionPreference='Stop'
    foreach($file in @('database_phase2a_candidate_corrected.ps1','database_phase2b_validate_restore.ps1','database_phase2b_continue.ps1')){
        $tokens=$null;$errors=$null
        $ast=[Management.Automation.Language.Parser]::ParseFile((Join-Path $Workspace "scripts\$file"),[ref]$tokens,[ref]$errors)
        if($errors.Count){throw 'A helper does not parse.'}
        foreach($node in $ast.EndBlock.Statements){if($node -is [Management.Automation.Language.FunctionDefinitionAst]){. ([scriptblock]::Create($node.Extent.Text))}}
    }
    if(Test-Path -LiteralPath $TestRoot){throw 'Fresh synthetic test destination required.'}
    if(-not ([IO.Path]::GetFullPath($TestRoot)).StartsWith((Join-Path $Workspace '.migration-private\'),[StringComparison]::OrdinalIgnoreCase)){throw 'Test output must remain private in workspace.'}
    New-Item -ItemType Directory -Path $TestRoot|Out-Null
    $checks=New-Object 'System.Collections.Generic.List[string]'
    function Check([string]$Name,[bool]$Ok){if(-not $Ok){throw "FAILED: $Name"};$checks.Add($Name)}
    function Clone([object]$Value){$Value|ConvertTo-Json -Depth 20 -Compress|ConvertFrom-Json}
    function Reject([string]$Name,[scriptblock]$Action){$blocked=$false;try{&$Action|Out-Null}catch{$blocked=$true};Check $Name $blocked}
    $r=[pscustomobject]@{Passed=$false;HaltReason='Isolated table/integrity/evidence probe needs review. Stop without repair.';ShutdownIssue=$null;
        ValidationPassed=$false;RestoreInspected=$false;ExactApplicationRestoreMatched=$false;ProtectedSevenTreesAndUploadsUnchanged=$true;
        AllServersStopped=$true;CandidateStillRunning=$false;NoManualRepair=$true;NoForcedStop=$true;NoWorkingChanges=$true;NoMigration=$true;NoCutover=$true;
        ExportSHA256='E6C603BAFF6038977A177AD97D2A10F4F0D5D55A21381372B22E287E80DBE22A'}
    $stop=[pscustomobject]@{State='gracefully-stopped';ExitCode=0;Client=[pscustomobject]@{ExitCode=0;TimedOut=$false}}
    $probe=[pscustomobject]@{passed=$false;writes_performed=$false;failure='CHECK TABLE needs review; no repair attempted.'}
    $before=[pscustomobject]@{TreeSHA256='D0DA68893E42300F3E6CB6B1384887FF8C76D745C7FB5B753EB59A4E71FD680E';FileCount=2767}
    $after=[pscustomobject]@{TreeSHA256='4B3AA2146106026AC60F8368B52AACCE251570E44694232ABA308A4426B385E4';FileCount=2767}
    $rows=@(for($i=0;$i -lt 50;$i++){
        $schema=if($i -lt 34){'atikha_finance'}else{'mysql'}
        [pscustomobject]@{object=('`'+$schema+'`.`synthetic_'+$i+'`');result=@([pscustomobject]@{Table=($schema+'.synthetic_'+$i);Op='check';Msg_type='status';Msg_text='OK'})}
    })
    $rows += [pscustomobject]@{object='`mysql`.`proc`';result=@(
        [pscustomobject]@{Table='mysql.proc';Op='check';Msg_type='warning';Msg_text="1 client is using or hasn't closed the table properly"},
        [pscustomobject]@{Table='mysql.proc';Op='check';Msg_type='status';Msg_text='OK'})}
    Assert-P2BContinuationGate $r $stop $probe $rows $before $after
    Check 'exact reviewed failure state accepted' $true
    foreach($key in @('AllServersStopped','ProtectedSevenTreesAndUploadsUnchanged','NoManualRepair','NoForcedStop','NoWorkingChanges','NoMigration','NoCutover')){
        $bad=Clone $r;$bad.$key=$false
        Reject ('unsafe old result rejected: '+$key) {Assert-P2BContinuationGate $bad $stop $probe $rows $before $after}
    }
    foreach($key in @('Passed','ValidationPassed','RestoreInspected','ExactApplicationRestoreMatched','CandidateStillRunning')){
        $bad=Clone $r;$bad.$key=$true
        Reject ('unexpected previous progression rejected: '+$key) {Assert-P2BContinuationGate $bad $stop $probe $rows $before $after}
    }
    $bad=Clone $r;$bad.HaltReason='different failure';Reject 'different prior failure rejected' {Assert-P2BContinuationGate $bad $stop $probe $rows $before $after}
    $bad=Clone $r;$bad.ShutdownIssue='unknown';Reject 'shutdown issue rejected' {Assert-P2BContinuationGate $bad $stop $probe $rows $before $after}
    $bad=Clone $r;$bad.ExportSHA256='0'*64;Reject 'wrong old export rejected' {Assert-P2BContinuationGate $bad $stop $probe $rows $before $after}
    $bad=Clone $stop;$bad.State='already-exited';Reject 'no graceful state rejected' {Assert-P2BContinuationGate $r $bad $probe $rows $before $after}
    $bad=Clone $stop;$bad.ExitCode=1;Reject 'abnormal server exit rejected' {Assert-P2BContinuationGate $r $bad $probe $rows $before $after}
    $bad=Clone $stop;$bad.Client.TimedOut=$true;Reject 'shutdown timeout rejected' {Assert-P2BContinuationGate $r $bad $probe $rows $before $after}
    $bad=Clone $stop;$bad.Client.ExitCode=2;Reject 'shutdown client failure rejected' {Assert-P2BContinuationGate $r $bad $probe $rows $before $after}
    $bad=Clone $probe;$bad.failure='different';Reject 'different probe failure rejected' {Assert-P2BContinuationGate $r $stop $bad $rows $before $after}
    $bad=Clone $probe;$bad.writes_performed=$true;Reject 'probe write claim rejected' {Assert-P2BContinuationGate $r $stop $bad $rows $before $after}
    Reject 'missing check row rejected' {Assert-P2BContinuationGate $r $stop $probe @($rows[0..49]) $before $after}
    $bad=Clone $rows;$bad[2].object=$bad[1].object;Reject 'duplicate table result rejected' {Assert-P2BContinuationGate $r $stop $probe $bad $before $after}
    $bad=Clone $rows;$bad[1].object='`mysql`.`wrong`';Reject 'wrong prior application inventory rejected' {Assert-P2BContinuationGate $r $stop $probe $bad $before $after}
    $bad=Clone $rows;$bad[0].result[0].Msg_type='warning';Reject 'earlier clean check warning rejected' {Assert-P2BContinuationGate $r $stop $probe $bad $before $after}
    $bad=Clone $rows;$bad[50].result[0].Msg_text='Table is marked as crashed';Reject 'different last warning rejected' {Assert-P2BContinuationGate $r $stop $probe $bad $before $after}
    $bad=Clone $rows;$bad[50].result[1].Msg_text='Corrupt';Reject 'warning without final OK rejected' {Assert-P2BContinuationGate $r $stop $probe $bad $before $after}
    $bad=Clone $rows;$bad[50].object='`mysql`.`other`';Reject 'different stopped table rejected' {Assert-P2BContinuationGate $r $stop $probe $bad $before $after}
    $bad=Clone $before;$bad.TreeSHA256='0'*64;Reject 'wrong initial copy hash rejected' {Assert-P2BContinuationGate $r $stop $probe $rows $bad $after}
    $bad=Clone $after;$bad.TreeSHA256='0'*64;Reject 'wrong post-check source hash rejected' {Assert-P2BContinuationGate $r $stop $probe $rows $before $bad}
    $bad=Clone $after;$bad.FileCount=2766;Reject 'missing source file rejected' {Assert-P2BContinuationGate $r $stop $probe $rows $before $bad}
    # Exercise the gate against actual already-stopped saved results, with read-only file access only.
    $prior='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase2b-20261020-183602-8bee3d3b'
    $realR=[IO.File]::ReadAllText((Join-Path $prior 'results.json'))|ConvertFrom-Json
    $realStop=[IO.File]::ReadAllText((Join-Path $prior 'validation\shutdown-result.json'))|ConvertFrom-Json
    $realProbe=[IO.File]::ReadAllText((Join-Path $prior 'validation\logs\inspection.json'))|ConvertFrom-Json
    $realRows=@([IO.File]::ReadAllLines((Join-Path $prior 'validation\logs\table-checks.jsonl'))|ForEach-Object{$_|ConvertFrom-Json})
    $realBefore=[IO.File]::ReadAllText((Join-Path $prior 'validation\candidate-before.json'))|ConvertFrom-Json
    $realAfter=[IO.File]::ReadAllText((Join-Path $prior 'validation\candidate-after.json'))|ConvertFrom-Json
    Assert-P2BContinuationGate $realR $realStop $realProbe $realRows $realBefore $realAfter
    Check 'saved stopped-attempt evidence passes same gate' $true
    $oldHash=(Get-FileHash -LiteralPath (Join-Path $Workspace 'scripts\database_phase2b_validate_restore.ps1')).Hash
    Check 'original Phase 2-B helper retained exactly' ($oldHash -eq 'F6FB16745C53E618ECE316F75BF1520AA98B1DBBBD7BDDEEE54DCC4E7D9D3744')
    Check 'original probe retained exactly' ((Get-FileHash -LiteralPath (Join-Path $Workspace 'scripts\database_phase2b_probe.php')).Hash -eq 'C827303D62808315FE808BD269555733C29BC54A8D4AD17A363FB6E706375A63')
    Check 'original source manifest retained exactly' ((Get-FileHash -LiteralPath (Join-Path $Workspace 'scripts\database_phase2b_source_manifest.json')).Hash -eq '32924EB58FD5242BDCD14A36E2FEF22CD70F0A504EF924352FF0E4BB05877D51')
    Write-CheckJson (Join-Path $TestRoot 'results.json') ([ordered]@{Passed=$checks.Count;Checks=@($checks);DatabaseServerStarted=$false;SQLExecuted=$false;ImportExecuted=$false;ReadOnlySavedEvidenceInspected=$true})
    [pscustomobject]@{Passed=$checks.Count;Evidence=$TestRoot;DatabaseServerStarted=$false;SQLExecuted=$false;ImportExecuted=$false}
}
