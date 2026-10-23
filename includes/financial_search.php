<?php
/** Port of local DataTables2.3.8 smart literal search, not a SQL wildcard/regex API. */
function financial_search_normalize(string $text,bool $includeOriginal=false):string
{
    static $map=null;if($map===null){$data=json_decode(file_get_contents(__DIR__.'/../assets/vendor/unicode-search.json'),true,8,JSON_THROW_ON_ERROR);$map=$data['nfd'];}$nfd=strtr($text,$map);$changed=strlen(mb_convert_encoding($nfd,'UTF-16LE','UTF-8'))!==strlen(mb_convert_encoding($text,'UTF-16LE','UTF-8'));return $changed?($includeOriginal?$text.' ':'').preg_replace('/[\x{0300}-\x{036f}]/u','',$nfd):$nfd;
}
function financial_search_history(string $query,array $fields):bool
{
    $q=mb_strtolower(financial_search_normalize($query),'UTF-8');$hay=[];foreach($fields as $field){$s=financial_search_normalize((string)$field,true);if(str_contains($s,'&'))$s=html_entity_decode(strip_tags($s),ENT_QUOTES|ENT_HTML5,'UTF-8');$hay[]=str_replace(["\r","\n","\u{2028}"],'',$s);}$hay=mb_strtolower(implode('  ',$hay),'UTF-8');preg_match_all('/!?["\x{201c}][^"\x{201d}]+["\x{201d}]|[^ ]+/u',$q,$parts);
    foreach($parts[0] as $term){$negative=str_starts_with($term,'!');if($negative)$term=substr($term,1);if(str_starts_with($term,'"')&&str_ends_with($term,'"'))$term=substr($term,1,-1);$term=str_replace('"','',$term);if($negative){if(mb_strlen($term)>1&&str_contains($hay,$term))return false;}elseif(!str_contains($hay,$term))return false;}return true;
}
function financial_search_compare(string $a,string $b):int{return strcmp(mb_convert_encoding(mb_strtolower($a,'UTF-8'),'UTF-16BE','UTF-8'),mb_convert_encoding(mb_strtolower($b,'UTF-8'),'UTF-16BE','UTF-8'));}
