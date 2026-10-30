<?php
/** Serialize the existing authorized full-result model; no independent queries. */
function report_export_binary(array $model,string $format)
{
    require_once dirname(__DIR__).'/vendor/autoload.php';
    $rows=0;foreach($model['sections'] as $section)$rows+=count($section['rows']);
    if($rows>($format==='pdf'?5000:50000))throw new ReportingProblem('This file is too large for a single '.strtoupper($format).' export. Choose a narrower period or use the full CSV export.',422);
    if($format==='pdf'){
        require_once __DIR__.'/report_export_pdf_view.php';
        $options=new Dompdf\Options();$options->set('isRemoteEnabled',false);$options->set('isPhpEnabled',false);$options->set('isJavascriptEnabled',false);$options->set('tempDir',sys_get_temp_dir());$options->set('chroot',__DIR__);
        $pdf=new Dompdf\Dompdf($options);$pdf->setPaper('A4','landscape');$pdf->loadHtml(report_export_pdf_html($model),'UTF-8');$pdf->render();$pdf->getCanvas()->page_text(730,565,'Page {PAGE_NUM} / {PAGE_COUNT}',null,8,[.3,.4,.45]);$bytes=$pdf->output();
    }elseif($format==='xlsx'){
        $xlsx=new Shuchkin\SimpleXLSXGen();$raw=static fn($v)=>"\0".(string)$v;
        $meta=[[$raw('Atikha Finance'),$raw($model['title'])],[$raw('Generated'),$raw($model['generated_at'])]];
        foreach($model['metadata'] as $label=>$value)if($label!=='Text protection')$meta[]=[$raw($label),$raw($value)];
        $meta[]=[$raw('Money precision'),$raw('Amounts beyond safe spreadsheet numeric precision are preserved as exact decimal text.')];$xlsx->addSheet($meta,'Report Information');
        foreach($model['sections'] as $index=>$section){
            $rows=[array_map(static fn($col)=>"\0".$col[1],$section['columns'])];
            $cell=static function($value,string $kind)use($raw){
                if($value===null)return $raw('—');
                if($kind==='money'){
                    $decimal=accounting_decimal(accounting_cents((string)$value));$digits=strlen(ltrim(str_replace(['.','-'],'',$decimal),'0'));
                    return $digits<=14?(float)$decimal:$raw($decimal);
                }
                return $raw($value);
            };
            foreach($section['rows'] as $row){$out=[];foreach($section['columns'] as [$key,$label,$kind])$out[]=$cell($row[$key]??null,$kind);$rows[]=$out;}
            if($section['totals']){$total=[];foreach($section['columns'] as $i=>[$key,$label,$kind])$total[]=$i===0?$raw('Total'):(isset($section['totals'][$key])?$cell($section['totals'][$key],$kind):$raw(''));$rows[]=$total;}
            $name=str_replace(['\\','/',':','?','*','[',']'],' ',$section['title']);$name=mb_substr(trim($name),0,23).' '.($index+1);$xlsx->addSheet($rows,$name);
        }
        $bytes=(string)$xlsx;
    }else throw new ReportingProblem('Choose a supported report format.',400);
    if(!is_string($bytes)||strlen($bytes)<100)throw new ReportingProblem('The report file could not be completed.',503);
    $stream=tmpfile();if($stream===false||fwrite($stream,$bytes)!==strlen($bytes)){if(is_resource($stream))fclose($stream);throw new ReportingProblem('Private export storage is unavailable.',503);}rewind($stream);return $stream;
}
