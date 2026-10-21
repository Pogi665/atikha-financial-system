# Function-only preparation tests. Extract this function's AST; never execute recovery operational mains.
function Invoke-P2BPreparationTests([string]$Workspace,[string]$TestRoot){
    $ErrorActionPreference='Stop'
    foreach($file in @('database_phase2a_candidate_corrected.ps1','database_phase2b_validate_restore.ps1')){
        $tok=$null;$errors=$null
        $ast=[Management.Automation.Language.Parser]::ParseFile((Join-Path $Workspace "scripts\$file"),[ref]$tok,[ref]$errors)
        if($errors.Count){throw 'Production helper parse failed.'}
        foreach($node in $ast.EndBlock.Statements){if($node -is [Management.Automation.Language.FunctionDefinitionAst]){. ([scriptblock]::Create($node.Extent.Text))}}
    }
    $checks=New-Object 'System.Collections.Generic.List[string]'
    function Check([string]$Name,[bool]$Condition){if(-not $Condition){throw "FAILED: $Name"};$checks.Add($Name)}
    function Reject([string]$Name,[scriptblock]$Action){$blocked=$false;try{&$Action|Out-Null}catch{$blocked=$true};Check $Name $blocked}
    if(Test-Path -LiteralPath $TestRoot){throw 'Test root must be fresh.'}
    if(-not ([IO.Path]::GetFullPath($TestRoot)).StartsWith((Join-Path $Workspace '.migration-private\'),[StringComparison]::OrdinalIgnoreCase)){throw 'Tests stay in private workspace.'}
    New-Item -ItemType Directory -Path $TestRoot|Out-Null
    $file=Join-Path $TestRoot 'synthetic.txt';[IO.File]::WriteAllText($file,'one')
    $hash=(Get-FileHash -LiteralPath $file).Hash
    Assert-P2BPin $file $hash;Check 'matching synthetic pin accepted' $true
    Reject 'changed synthetic input rejected' {Assert-P2BPin $file ('0'*64)}
    Reject 'missing synthetic input rejected' {Assert-P2BPin (Join-Path $TestRoot 'missing') $hash}
    Assert-P2BSourceManifest (Join-Path $Workspace 'scripts\database_phase2b_source_manifest.json') $Workspace
    Check 'current pinned probe and includes accepted' $true
    $badManifest=Join-Path $TestRoot 'bad-manifest.json'
    [IO.File]::WriteAllText($badManifest,'{"Format":"wrong","Files":[]}')
    Reject 'wrong source manifest rejected' {Assert-P2BSourceManifest $badManifest $Workspace}
    $treeRoot=Join-Path $TestRoot 'tree';New-Item -ItemType Directory -Path $treeRoot|Out-Null
    [IO.File]::WriteAllText((Join-Path $treeRoot 'a.txt'),'synthetic')
    $tree=Get-CheckTree $treeRoot
    $spec=@([pscustomobject]@{Path=$treeRoot;Hash=$tree.TreeSHA256;Count=1})
    Assert-P2BProtected $spec;Check 'synthetic protected tree accepted' $true
    [IO.File]::WriteAllText((Join-Path $treeRoot 'a.txt'),'changed')
    Reject 'protected byte change rejected' {Assert-P2BProtected $spec}
    $linkedRoot=Join-Path $TestRoot 'external-link';New-Item -ItemType Directory -Path $linkedRoot|Out-Null
    [IO.File]::WriteAllText((Join-Path $linkedRoot 'x.isl'),'external synthetic link')
    $linked=Get-CheckTree $linkedRoot
    Reject 'tablespace link rejected' {Assert-P2BProtected @([pscustomobject]@{Path=$linkedRoot;Hash=$linked.TreeSHA256;Count=1})}
    $good=[pscustomobject]@{passed=$true;writes_performed=$false;working_database_accessed=$false;stable_before_after_checks=$true;checked_objects=3;integrity_problems=@();snapshot=@{tables=@{a=1}};evidence=@{files=@{}}}
    Assert-P2BReport $good;Check 'complete synthetic inspection accepted' $true
    $good.passed=$false;Reject 'failed inspection rejected' {Assert-P2BReport $good};$good.passed=$true
    $good.writes_performed=$true;Reject 'probe write claim rejected' {Assert-P2BReport $good};$good.writes_performed=$false
    $good.working_database_accessed=$true;Reject 'working access claim rejected' {Assert-P2BReport $good};$good.working_database_accessed=$false
    $good.stable_before_after_checks=$false;Reject 'unstable snapshot rejected' {Assert-P2BReport $good};$good.stable_before_after_checks=$true
    $good.checked_objects=0;Reject 'zero table checks rejected' {Assert-P2BReport $good};$good.checked_objects=3
    $good.integrity_problems=@('mismatch');Reject 'accounting problem rejected' {Assert-P2BReport $good};$good.integrity_problems=@()
    $same=[pscustomobject]@{rows='2';sha='abc';schema='CREATE a';nullable=$null}
    Assert-P2BEqual $same $same;Check 'deterministic equality accepted' $true
    Reject 'restore count mismatch rejected' {Assert-P2BEqual $same ([pscustomobject]@{rows='1';sha='abc';schema='CREATE a';nullable=$null})}
    Reject 'restore byte mismatch rejected' {Assert-P2BEqual $same ([pscustomobject]@{rows='2';sha='def';schema='CREATE a';nullable=$null})}
    Reject 'restore schema mismatch rejected' {Assert-P2BEqual $same ([pscustomobject]@{rows='2';sha='abc';schema='CREATE b';nullable=$null})}
    Reject 'restore null mismatch rejected' {Assert-P2BEqual $same ([pscustomobject]@{rows='2';sha='abc';schema='CREATE a';nullable=''})}
    $prefix='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\'
    Assert-P2BRestoreContext ([pscustomobject]@{Root=$prefix+'phase2b-20261020-200000-abcdef01\restore'})
    Check 'isolated restore root accepted' $true
    foreach($bad in @('C:\xampp\mysql\data',$prefix+'phase2b-20261020-200000-abcdef01\validation',
        'C:\other\phase2b-20261020-200000-abcdef01\restore',$prefix+'candidate-corrected-20261020-181135-b09d90ba',
        $prefix+'phase2b-20261020-200000-abcdef01\restore\..\validation')){
        Reject ('unsafe restore root rejected: '+$bad) {Assert-P2BRestoreContext ([pscustomobject]@{Root=$bad})}
    }
    $argsImport=@(Get-P2BImportArguments)
    Check 'import transport fixed' ($argsImport[0] -eq '--no-defaults' -and $argsImport -contains '--port=3307' -and $argsImport -contains '--host=127.0.0.1' -and $argsImport -contains '--protocol=TCP')
    Check 'import has no force/reconnect/local file bypass' ($argsImport -notcontains '--force' -and $argsImport -contains '--skip-reconnect' -and $argsImport -contains '--binary-mode' -and -not ($argsImport -match '^--local-infile'))
    # Installed compatibility only: help never connects, imports, creates a server datadir or opens the real export.
    $clientRun=Invoke-BoundedCheck 'C:\xampp\mysql\bin\mysql.exe' ($argsImport+@('--help')) $TestRoot (Join-Path $TestRoot 'mysql-help.out') (Join-Path $TestRoot 'mysql-help.err') 15000
    Check 'installed import client option help accepted' (-not $clientRun.TimedOut -and $clientRun.ExitCode -eq 0 -and [IO.File]::ReadAllText((Join-Path $TestRoot 'mysql-help.err')).Trim().Length -eq 0)
    $candidate=Join-Path $TestRoot 'help-only';New-Item -ItemType Directory -Path $candidate|Out-Null
    foreach($dir in @('logs','tmp','export')){New-Item -ItemType Directory -Path (Join-Path $candidate $dir)|Out-Null}
    $ini=Write-P2Ini $candidate
    $serverRun=Invoke-BoundedCheck 'C:\xampp\mysql\bin\mysqld.exe' @("--defaults-file=$ini",'--verbose','--help') $candidate (Join-Path $candidate 'help.out') (Join-Path $candidate 'help.err') 15000
    Assert-P2Help ([IO.File]::ReadAllText((Join-Path $candidate 'help.out'))) ([IO.File]::ReadAllText((Join-Path $candidate 'help.err'))) $serverRun.ExitCode $serverRun.TimedOut $candidate
    Check 'installed full candidate options accepted in help only' $true
    Check 'help did not initialize data' (-not (Test-Path -LiteralPath (Join-Path $candidate 'data')))
    Initialize-P2CandidateIdentity
    $emptyCim=@([pscustomobject]@{ProcessId=4321;ExecutablePath=$null;CommandLine=$null})
    $listener=@([pscustomobject]@{LocalPort=3307;LocalAddress='127.0.0.1';OwningProcess=4321})
    Assert-P2ProcessInventory $emptyCim $listener 4321 'C:\xampp\mysql\bin\mysqld.exe' $ini $true
    Check 'absent CIM metadata retains separate mandatory native guard' $true
    Reject 'wrong listener owner rejected' {Assert-P2ProcessInventory $emptyCim $listener 1234 'C:\xampp\mysql\bin\mysqld.exe' $ini $true}
    $listener[0].LocalAddress='0.0.0.0';Reject 'non-loopback listener rejected' {Assert-P2ProcessInventory $emptyCim $listener 4321 'C:\xampp\mysql\bin\mysqld.exe' $ini $true}
    $settings=Get-P2Settings $candidate
    Check 'settings preserve grants recovery and read only' ($settings['read-only'] -eq '1' -and $settings['innodb-force-recovery'] -eq '0' -and $settings['skip-grant-tables'] -eq '0')
    Check 'automatic repair and events disabled' ($settings['aria-recover-options'] -eq 'OFF' -and $settings['myisam-recover-options'] -eq 'OFF' -and $settings['event-scheduler'] -eq 'OFF')
    # Exercise the exact stdin/timeout wrapper with harmless PowerShell clients, never mysql/import SQL.
    $input=Join-Path $TestRoot 'synthetic-input.txt';[IO.File]::WriteAllText($input,"synthetic import text`r`nsecond line`r`n")
    $inputHash=(Get-FileHash -LiteralPath $input).Hash
    $shellExe=Join-Path $PSHOME 'powershell.exe'
    $out=Join-Path $TestRoot 'stdin.out';$err=Join-Path $TestRoot 'stdin.err'
    $echo=Invoke-P2BInputClient $shellExe @('-NoProfile','-NonInteractive','-Command','[Console]::Out.Write([Console]::In.ReadToEnd())') $input $TestRoot $out $err 10000
    Check 'input client receives exact synthetic stdin' ($echo.ExitCode -eq 0 -and -not $echo.TimedOut -and [IO.File]::ReadAllText($out) -ceq [IO.File]::ReadAllText($input))
    Check 'input wrapper preserves input bytes' ((Get-FileHash -LiteralPath $input).Hash -eq $inputHash)
    Reject 'input wrapper refuses evidence overwrite' {Invoke-P2BInputClient $shellExe @('-NoProfile','-NonInteractive','-Command','exit 0') $input $TestRoot $out $err 10000}
    $failed=Invoke-P2BInputClient $shellExe @('-NoProfile','-NonInteractive','-Command','exit 9') $input $TestRoot (Join-Path $TestRoot 'nonzero.out') (Join-Path $TestRoot 'nonzero.err') 10000
    Check 'input wrapper captures nonzero exit' ($failed.ExitCode -eq 9 -and -not $failed.TimedOut)
    $timed=Invoke-P2BInputClient $shellExe @('-NoProfile','-NonInteractive','-Command','Start-Sleep -Seconds 20') $input $TestRoot (Join-Path $TestRoot 'timeout.out') (Join-Path $TestRoot 'timeout.err') 500
    Check 'input wrapper cancels only timed out harmless child' ($timed.TimedOut -and -not (Get-Process -Id $timed.ProcessId -ErrorAction SilentlyContinue))
    Write-CheckJson (Join-Path $TestRoot 'results.json') ([ordered]@{Passed=$checks.Count;Checks=@($checks);DatabaseServerStarted=$false;SQLExecuted=$false;ImportExecuted=$false;SyntheticAndHelpOnly=$true})
    [pscustomobject]@{Passed=$checks.Count;Evidence=$TestRoot;DatabaseServerStarted=$false;SQLExecuted=$false;ImportExecuted=$false}
}
