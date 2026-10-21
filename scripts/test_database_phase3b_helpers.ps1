# Import this function AST only; preparation never invokes any recovery operational main.
function Invoke-P3BPreparationTests([string]$Workspace,[string]$TestRoot){
    $ErrorActionPreference='Stop'
    foreach($file in @('database_phase2a_candidate_corrected.ps1','database_phase2b_validate_restore.ps1','database_phase3a_corrected_trial.ps1','database_phase3b_normalize.ps1')){
        $tokens=$null;$errors=$null
        $ast=[Management.Automation.Language.Parser]::ParseFile((Join-Path $Workspace "scripts\$file"),[ref]$tokens,[ref]$errors)
        if($errors.Count){throw 'Helper parse failed.'}
        foreach($node in $ast.EndBlock.Statements){if($node -is [Management.Automation.Language.FunctionDefinitionAst]){. ([scriptblock]::Create($node.Extent.Text))}}
    }
    if(Test-Path -LiteralPath $TestRoot){throw 'New private test root required.'}
    if(-not ([IO.Path]::GetFullPath($TestRoot)).StartsWith((Join-Path $Workspace '.migration-private\'),[StringComparison]::OrdinalIgnoreCase)){throw 'Synthetic files must stay in private workspace.'}
    New-Item -ItemType Directory -Path $TestRoot|Out-Null
    $checks=New-Object 'System.Collections.Generic.List[string]'
    function Check([string]$Name,[bool]$Ok){if(-not $Ok){throw "FAILED: $Name"};$checks.Add($Name)}
    function Reject([string]$Name,[scriptblock]$Action){$bad=$false;try{&$Action|Out-Null}catch{$bad=$true};Check $Name $bad}
    function Clone([object]$Value){$Value|ConvertTo-Json -Depth 30 -Compress|ConvertFrom-Json}
    $role='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase2b-20990101-000000-abcdef12\normalization'
    $arguments=@(Get-P3BArguments $role)
    Check 'explicit defaults/control/logging options' ($arguments[0] -ceq '--no-defaults' -and $arguments -contains '--require-control-file' -and $arguments -contains '--skip-transaction-log')
    Check 'single zerofill no other action' ($arguments.Count -eq 8 -and @($arguments|Where-Object{$_ -ceq '--zerofill'}).Count -eq 1 -and -not ($arguments -match '^--(recover|quick|force|ignore-control|safe|check|extend|zerofill-keep|silent|wait)'))
    Check 'fixed target and contained paths' ($arguments[-1] -ceq (Join-Path $role 'data\mysql\tables_priv.MAI') -and $arguments -contains ('--datadir='+(Join-Path $role 'data')) -and $arguments -contains ('--logdir='+(Join-Path $role 'data')) -and $arguments -contains ('--tmpdir='+(Join-Path $role 'tmp')))
    foreach($path in @('C:\xampp\mysql\data',$role.Replace('normalization','validation'),$role.Replace('normalization','restore'),$role.Replace('normalization','index-trial'),$role.Replace('normalization','capture'),($role+'\..\validation'),($role+"`n"),'C:\elsewhere\normalization')){Reject 'unsafe offline role rejected' {Get-P3BArguments $path}}
    $ok=[pscustomobject]@{ExitCode=0;TimedOut=$false}
    $text="- Zerofilling index for Aria-table '$($arguments[-1])'`r`n- Zerofilling data  for Aria-table '$($arguments[-1])'`r`nState updated`r`n"
    Assert-P3BResult $text '' $ok $role;Check 'source-audited complete progress accepted' $true
    foreach($word in @('Warning','Error','duplicate','skipped','lost','aborted','retrying','recovered','unexpected')){Reject ('unknown/diagnostic output rejected '+$word) {Assert-P3BResult ($text+$word) '' $ok $role}}
    Reject 'wrong-target progress rejected' {Assert-P3BResult ($text.Replace('tables_priv','db')) '' $ok $role}
    Reject 'partial success rejected' {Assert-P3BResult "State updated`n" '' $ok $role}
    Reject 'stderr rejected' {Assert-P3BResult $text 'warning' $ok $role}
    Reject 'timeout rejected' {Assert-P3BResult $text '' ([pscustomobject]@{TimedOut=$true;ExitCode=0}) $role}
    Reject 'nonzero exit rejected' {Assert-P3BResult $text '' ([pscustomobject]@{TimedOut=$false;ExitCode=1}) $role}
    Reject 'missing exit rejected' {Assert-P3BResult $text '' ([pscustomobject]@{TimedOut=$false;ExitCode=$null}) $role}
    $data=Join-Path $TestRoot 'synthetic-data';New-Item -ItemType Directory -Path (Join-Path $data 'mysql')|Out-Null
    foreach($name in @('tables_priv.MAI','tables_priv.MAD','tables_priv.frm')){[IO.File]::WriteAllText((Join-Path $data ('mysql\'+$name)),'0000')}
    [IO.File]::WriteAllText((Join-Path $data 'aria_log_control'),'0000');[IO.File]::WriteAllText((Join-Path $data 'aria_log.00000001'),'0000')
    $before=Get-CheckTree $data
    [IO.File]::WriteAllText((Join-Path $data 'mysql\tables_priv.MAI'),'1111');$indexOnly=Get-CheckTree $data
    Assert-P3BDelta $before $indexOnly;Check 'target index-only delta accepted' $true
    [IO.File]::WriteAllText((Join-Path $data 'mysql\tables_priv.MAD'),'1111');$both=Get-CheckTree $data
    Assert-P3BDelta $before $both;Check 'target MAI plus MAD byte delta accepted' $true
    Reject 'unchanged operation rejected' {Assert-P3BDelta $before $before}
    foreach($file in @('mysql\tables_priv.frm','aria_log_control','aria_log.00000001')){
        [IO.File]::WriteAllText((Join-Path $data $file),'1111');Reject ('non-target write rejected '+$file) {Assert-P3BDelta $before (Get-CheckTree $data)};[IO.File]::WriteAllText((Join-Path $data $file),'0000')
    }
    [IO.File]::WriteAllText((Join-Path $data 'mysql\tables_priv.MAD'),'longer');Reject 'target file length change rejected' {Assert-P3BDelta $before (Get-CheckTree $data)}
    [IO.File]::WriteAllText((Join-Path $data 'mysql\tables_priv.MAD'),'1111')
    [IO.File]::WriteAllText((Join-Path $data 'extra'),'0000');Reject 'extra database file rejected' {Assert-P3BDelta $before (Get-CheckTree $data)}
    $bad=Clone $both;$bad.Entries=@($bad.Entries|Where-Object{$_.Path -cne 'mysql\tables_priv.MAD'});Reject 'deleted data file rejected' {Assert-P3BDelta $before $bad}
    $bad=Clone $before;$bad.Entries+=@($bad.Entries[0]);Reject 'duplicate manifest path rejected' {Assert-P3BDelta $bad $both}
    $prior='C:\xampp\mysql\recovery-exports\diagnostic-20261020-032731-25c55e2d\phase2b-20261021-001938-c6362668'
    $result=[IO.File]::ReadAllText((Join-Path $prior 'results.json'))|ConvertFrom-Json
    $cap=[IO.File]::ReadAllText((Join-Path $prior 'capture\shutdown-result.json'))|ConvertFrom-Json
    $val=[IO.File]::ReadAllText((Join-Path $prior 'validation\shutdown-result.json'))|ConvertFrom-Json
    $rows=@([IO.File]::ReadAllLines((Join-Path $prior 'validation\logs\table-checks.jsonl'))|ForEach-Object{$_|ConvertFrom-Json})
    Assert-P3BPrevious $result $cap $val $rows;Check 'actual prior moved-state failure accepted' $true
    foreach($key in @('CaptureCompleted','RepairAttempted','OfflineIndexOnlyDeltaVerified','ProtectedElevenTreesUploadsExportAndArtifactsUnchanged','AllServersStopped','NoWorkingChanges','NoForcedStop','NoMigration','NoCutover')){
        $bad=Clone $result;$bad.$key=$false;Reject ('missing prior proof rejected '+$key) {Assert-P3BPrevious $bad $cap $val $rows}
    }
    foreach($key in @('Passed','ValidationPassed','RestorePassed','ExactApplicationRestoreMatched','CandidateStillRunning','WorkingAcceptance','U3IntegrationRun')){
        $bad=Clone $result;$bad.$key=$true;Reject ('unexpected prior progression rejected '+$key) {Assert-P3BPrevious $bad $cap $val $rows}
    }
    $bad=Clone $rows;$bad[56].result[0].Msg_text='different';Reject 'different prior fault rejected' {Assert-P3BPrevious $result $cap $val $bad}
    $bad=Clone $rows;$bad[0].result[0].Msg_type='warning';Reject 'earlier prior warning rejected' {Assert-P3BPrevious $result $cap $val $bad}
    $bad=Clone $cap;$bad.Client.TimedOut=$true;Reject 'prior capture timeout rejected' {Assert-P3BPrevious $result $bad $val $rows}
    $bad=Clone $val;$bad.ExitCode=1;Reject 'prior validation abnormal stop rejected' {Assert-P3BPrevious $result $cap $bad $rows}
    $help=Invoke-BoundedCheck 'C:\xampp\mysql\bin\aria_chk.exe' ($arguments+@('--help')) $TestRoot (Join-Path $TestRoot 'help.out') (Join-Path $TestRoot 'help.err') 15000
    Assert-P3RepairHelp ([IO.File]::ReadAllText((Join-Path $TestRoot 'help.out'))) ([IO.File]::ReadAllText((Join-Path $TestRoot 'help.err'))) $help $role
    Check 'installed exact normalization option set accepted in help only' $true
    Check 'help did not create target directory' (-not (Test-Path -LiteralPath $role))
    # Exercise the new bridge to the immutable original baseline using only a fake reader.
    $script:originalBaseline=Join-Path $TestRoot 'original-baseline.json'
    [IO.File]::WriteAllText($script:originalBaseline,'synthetic-original')
    $script:originalBaselineHash=(Get-FileHash -LiteralPath $script:originalBaseline).Hash
    $script:originalBaselineReport=[pscustomobject]@{baseline=[pscustomobject]@{snapshot=[pscustomobject]@{grant='unchanged'};tables_priv_scan=[pscustomobject]@{rows=1;sha256='original'}}}
    $script:fakeRead=[pscustomobject]@{baseline=(Clone $script:originalBaselineReport.baseline)}
    function Invoke-P3CProbe([object]$Context,[string]$Mode,[string]$Baseline=''){return $script:fakeRead}
    $actual=Invoke-P3BProbe ([pscustomobject]@{}) capture
    Check 'matching original capture accepted by new bridge' ($actual.baseline.tables_priv_scan.sha256 -ceq 'original')
    $script:fakeRead.baseline.snapshot.grant='changed'
    Reject 'original grant mismatch stops before normalization' {Invoke-P3BProbe ([pscustomobject]@{}) capture}
    $script:fakeRead.baseline=Clone $script:originalBaselineReport.baseline
    $script:fakeRead.baseline.tables_priv_scan.rows=0
    Reject 'original full-scan count mismatch stops before normalization' {Invoke-P3BProbe ([pscustomobject]@{}) capture}
    $script:fakeRead.baseline=Clone $script:originalBaselineReport.baseline
    [IO.File]::WriteAllText($script:originalBaseline,'tampered-original')
    Reject 'original baseline file tampering blocks new bridge' {Invoke-P3BProbe ([pscustomobject]@{}) capture}
    # Native-call double verifies argument boundaries in before/after orchestration.
    # All output goes to this synthetic test folder; no real decoder/tool is invoked.
    $script:pins=@{};$script:phase3Probe=Join-Path $Workspace 'scripts\database_phase3b_probe.php'
    $headerRole=Join-Path $TestRoot 'header-double';New-Item -ItemType Directory -Path (Join-Path $headerRole 'logs')|Out-Null
    function Get-P3BArguments([string]$Root){return @('synthetic-role-already-tested')}
    function Invoke-BoundedCheck([string]$Executable,[string[]]$Arguments,[string]$WorkingDirectory,[string]$Stdout,[string]$Stderr,[int]$TimeoutMs){
        $script:headerArguments=$Arguments
        $outputs=@($Arguments|Where-Object{$_.StartsWith('--output=')})
        if($outputs.Count -ne 1){throw 'Metadata output argument missing or joined.'}
        [IO.File]::WriteAllText($outputs[0].Substring(9),'{"rows":1}')
        [IO.File]::WriteAllText($Stdout,'');[IO.File]::WriteAllText($Stderr,'')
        [pscustomobject]@{ExitCode=0;TimedOut=$false}
    }
    $script:php='synthetic-php-never-executed'
    $header=Invoke-P3BHeader $headerRole before 1
    Check 'before header wrapper uses three separate arguments' ($script:headerArguments.Count -eq 3 -and $header.rows -eq 1)
    $header=Invoke-P3BHeader $headerRole after 1
    Check 'after header wrapper uses five separate arguments' ($script:headerArguments.Count -eq 5)
    Check 'after header wrapper passes exact original before file' ($script:headerArguments[3] -ceq ('--before='+(Join-Path $headerRole 'logs\header-before.json')))
    Check 'after header wrapper passes exact full-scan row count' ($script:headerArguments[4] -ceq '--rows=1')
    Reject 'unknown header stage blocks tool invocation' {Invoke-P3BHeader $headerRole unknown 1}
    [IO.File]::WriteAllText((Join-Path $headerRole 'logs\header-before.json'),'changed-before')
    Reject 'before-header tampering blocks tool invocation' {Invoke-P3BHeader $headerRole after 1}
    Write-CheckJson (Join-Path $TestRoot 'results.json') ([ordered]@{Passed=$checks.Count;Checks=@($checks);SQLExecuted=$false;ServerStarted=$false;NormalizationRun=$false;InstalledHelpAndSyntheticOnly=$true})
    [pscustomobject]@{Passed=$checks.Count;Evidence=$TestRoot;SQLExecuted=$false;ServerStarted=$false;NormalizationRun=$false}
}
