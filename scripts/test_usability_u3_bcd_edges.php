<?php
/** Extended integration boundaries, on its own new complete-schema synthetic clone. */
require __DIR__.'/test_usability_u3_bcd.php';
cli_require(($o['gate']??'')==='all','Run all internal gates before integration boundaries.');
$current='integration';$checks[$current]=0;
function bcd_edge_release(PDO $p,int $uid,array $c,string $amount):array{$v=bcd_payload($c,$amount);$v['party_id']=(string)$c['employee2'];$d=bcd_save($p,$uid,'advance_release',$v);return bcd_post($p,$uid,$d,advance_review($p,$uid,bcd_r(bcd_identity($d))));}
try{
    $released=bcd_edge_release($p,$uid,$c,'200.00');$aid=$released['advance_id'];
    $cash=bcd_save($p,$uid,'advance_liquidation',bcd_liquidation($c,[bcd_line('cash_cost',$c['bank'],'100.00')]),$aid);
    bcd_reject($p,fn()=>advance_review($p,$uid,bcd_r(bcd_identity($cash))),'Liquidation cannot move cash');
    $liability=bcd_liquidation($c,[bcd_line('cost',$c['training'],'100.00'),bcd_line('tax',$c['tax'],'','10.00')]);$ld=bcd_save($p,$uid,'advance_liquidation',$liability,$aid);$ld=bcd_support($p,$uid,$ld,'100.00',[['client_id'=>'cost','amount'=>'100.00']]);
    bcd_reject($p,fn()=>advance_review($p,$uid,bcd_r(bcd_identity($ld))),'Unexplained liability credit');
    $excess=bcd_save($p,$uid,'advance_liquidation',bcd_liquidation($c,[bcd_line('cost',$c['training'],'201.00')]),$aid);$excess=bcd_support($p,$uid,$excess,'201.00',[['client_id'=>'cost','amount'=>'201.00']]);bcd_reject($p,fn()=>advance_review($p,$uid,bcd_r(bcd_identity($excess))),'Fully supported excess claim');
    $jobs=[];
    for($i=0;$i<2;$i++){
        $d=bcd_save($p,$uid,'advance_liquidation',bcd_liquidation($c,[bcd_line('cost',$c['training'],'200.00')]),$aid);$d=bcd_support($p,$uid,$d,'200.00',[['client_id'=>'cost','amount'=>'200.00']]);$r=advance_review($p,$uid,bcd_r(bcd_identity($d)));$request=bcd_r(bcd_identity($d)+['payload'=>$d['payload'],'review_token'=>$r['token']]);
        $worker=dirname($path).'/u3-race-'.$i.'.json';file_put_contents($worker,workspace_json(['receipt_root'=>RECEIPT_UPLOAD_DIR,'session'=>$_SESSION,'post'=>$request]));$jobs[]=[$worker,$request];
    }
    $running=[];
    foreach($jobs as [$worker]){$pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/test_stage2.php','--database='.$db,'--worker='.$worker],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);cli_require(is_resource($proc),'Race worker launch failed.');fclose($pipes[0]);$running[]=[$proc,$pipes];}
    $out=[];foreach($running as [$proc,$pipes]){$body=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);cli_require(proc_close($proc)===0&&$error==='','Race worker failed.');$out[]=json_decode($body,true,64,JSON_THROW_ON_ERROR);}
    bcd_check(count(array_filter($out,fn($r)=>$r['ok']))===1&&advance_detail($p,$uid,$aid,journal_today())['outstanding']==='0.00','Actual concurrent settlements consume remaining balance once');
    $historical=bcd_edge_release($p,$uid,$c,'25.00');$p->exec('UPDATE parties SET is_active=0 WHERE id='.(int)$c['employee2']);$p->exec('UPDATE projects SET is_active=0 WHERE id='.(int)$c['project']);
    $d=bcd_save($p,$uid,'advance_liquidation',bcd_liquidation($c,[bcd_line('cost',$c['training'],'25.00','',$c['project'])]),$historical['advance_id']);$d=bcd_support($p,$uid,$d,'25.00',[['client_id'=>'cost','amount'=>'25.00']]);$r=advance_review($p,$uid,bcd_r(bcd_identity($d)));bcd_post($p,$uid,$d,$r);
    bcd_check(advance_detail($p,$uid,$historical['advance_id'],journal_today())['status']==='settled','Inactive original employee/project remain settleable through historical references');
    $p->exec('UPDATE parties SET is_active=1 WHERE id='.(int)$c['employee2']);$p->exec('UPDATE projects SET is_active=1 WHERE id='.(int)$c['project']);
    $d=bcd_save($p,$uid,'advance_return',array_replace(bcd_payload($c,'1.00'),['party_id'=>'','control_account_id'=>'']),$aid);
    $other=(int)$p->query("SELECT UserID FROM Users WHERE Email='other@example.invalid'")->fetchColumn();$_SESSION=['UserID'=>$other,'Role'=>'Admin'];bcd_reject($p,fn()=>advance_draft_public($p,$other,(int)$d['id']),'Another administrator cannot read private draft');
    $manager=(int)$p->query("SELECT UserID FROM Users WHERE Email='management@example.invalid'")->fetchColumn();$_SESSION=['UserID'=>$manager,'Role'=>'Admin'];bcd_reject($p,fn()=>advance_save($p,$manager,bcd_r([])),'Management database identity cannot be forged into writer');$_SESSION=['UserID'=>$uid,'Role'=>'Admin'];
    $state=advance_state($p,journal_today());bcd_check(count(array_filter($state['reconciliation'],fn($r)=>!$r['ok']))===0,'All control accounts reconcile after integration writes');
    bcd_check((int)$p->query('SELECT COUNT(*) FROM (SELECT journal_entry_id FROM journal_entry_lines GROUP BY journal_entry_id HAVING SUM(debit_amount)<>SUM(credit_amount)) x')->fetchColumn()===0,'Every journal remains exactly balanced');
    echo 'Integration boundary checks: '.$checks[$current]."\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);}
