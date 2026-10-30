<?php
/** Pure advisory statistics. Inputs/outputs are signed integer centavos. */
function planning_round(int $numerator,int $denominator): int
{
    if($denominator<=0)throw new InvalidArgumentException('Invalid statistical divisor.');
    $whole=intdiv($numerator,$denominator);$rem=$numerator%$denominator;
    return $whole+(abs($rem)*2>=$denominator?($numerator<0?-1:1):0);
}
function planning_scale(int $value,int $coefficient): int
{
    $out=0;for($i=0;$i<abs($coefficient);$i++)$out=accounting_add($out,$coefficient<0?-$value:$value);return $out;
}
function planning_predict(array $series,string $method): int
{
    if(!$series)throw new InvalidArgumentException('History is unavailable.');
    if($method==='last')return $series[count($series)-1];
    $weights=$method==='trend6'?[-5,-2,1,4,7,10]:($method==='weighted3'?[1,2,3]:[1,1,1]);
    if(count($series)<count($weights))throw new InvalidArgumentException('Insufficient complete history.');
    // Divide before weighting, retaining exact signed remainders. This avoids an
    // overflowing intermediate numerator when a large final mean is valid.
    $denominator=array_sum($weights);$whole=$remainder=0;
    foreach(array_slice($series,-count($weights)) as $i=>$value){$whole=accounting_add($whole,planning_scale(intdiv($value,$denominator),$weights[$i]));$remainder=accounting_add($remainder,planning_scale($value%$denominator,$weights[$i]));}
    $whole=accounting_add($whole,intdiv($remainder,$denominator));$remainder%=$denominator;$comparison=abs($remainder)*2;
    if($comparison>$denominator||($comparison===$denominator&&($whole===0||($whole>0&&$remainder>0)||($whole<0&&$remainder<0))))$whole=accounting_add($whole,$remainder<0?-1:1);
    return $whole;
}
function planning_estimate(array $series): array
{
    $labels=['last'=>'Last complete month','mean3'=>'3-month moving average','weighted3'=>'Weighted 3-month average','trend6'=>'6-month linear trend'];$n=count($series);
    foreach($series as $value)if(!is_int($value))throw new InvalidArgumentException('Use exact centavos for recognized expenses.');
    if($n<3)return ['state'=>'insufficient','amount'=>null,'method'=>null,'method_label'=>'Insufficient history','mae'=>null,'folds'=>0,'observations'=>$n];
    $method='mean3';$error=null;$folds=0;$scores=[];
    if($n>=6){$methods=$n>=9?array_keys($labels):['last','mean3','weighted3'];$start=max($n>=9?6:3,$n-6);$folds=$n-$start;
        foreach($methods as $candidate){$loss=0;for($i=$start;$i<$n;$i++){$predicted=planning_predict(array_slice($series,0,$i),$candidate);$loss=accounting_add($loss,abs(accounting_add($predicted,-$series[$i])));}$scores[$candidate]=$loss;}
        $best=min($scores);foreach($methods as $candidate)if($scores[$candidate]===$best){$method=$candidate;break;}$error=planning_round($best,$folds);
    }
    $amount=planning_predict($series,$method);$note='';$peak=max(array_map('abs',$series));
    if($method==='trend6'&&$peak<=intdiv(PHP_INT_MAX,20)&&abs($amount)>max(100,planning_scale($peak,20))){$method='mean3';$amount=planning_predict($series,$method);$error=null;$folds=0;$note='The trend extrapolation was unstable; the 3-month baseline is shown.';}
    return ['state'=>$amount<0?'credit_dominated':'available','amount'=>$amount,'method'=>$method,'method_label'=>$labels[$method],'mae'=>$error,'folds'=>$folds,'observations'=>$n,'note'=>$note,'scores'=>$scores];
}
