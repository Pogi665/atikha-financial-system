<?php
/** Shared presentation only. Permissions and financial models remain in their existing services. */
function ui_escape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
/** Display only: keep machine dates and canonical financial payloads unchanged. */
function ui_date($value): string {
    if(!is_string($value)||!preg_match('/\A\d{4}-\d{2}-\d{2}\z/',$value))return 'Not recorded';
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
    return $d&&$d->format('Y-m-d')===$value?$d->format('M j, Y'):'Not recorded';
}
function ui_month($value): string {
    return is_string($value)&&preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/',$value)?(new DateTimeImmutable($value.'-01'))->format('F Y'):'Choose a month';
}
function ui_datetime($value, string $basis='utc'): string {
    if(!is_string($value)||trim($value)==='')return 'Not recorded';
    try{$d=new DateTimeImmutable($value,new DateTimeZone($basis==='utc'?'UTC':'Asia/Manila'));if($basis==='utc')$d=$d->setTimezone(new DateTimeZone('Asia/Manila'));return $d->format('M j, Y · g:i A');}catch(Throwable $e){return 'Not recorded';}
}
function ui_assets(): string {
    $root=dirname(__DIR__);
    return '<link rel="stylesheet" href="assets/css/sidebar.css?v='.filemtime($root.'/assets/css/sidebar.css').'"><link rel="stylesheet" href="assets/css/atikha_ui.css?v='.filemtime($root.'/assets/css/atikha_ui.css').'"><link rel="stylesheet" href="assets/css/atikha_workspaces.css?v='.filemtime($root.'/assets/css/atikha_workspaces.css').'"><link rel="stylesheet" href="assets/css/atikha_dashboard.css?v='.filemtime($root.'/assets/css/atikha_dashboard.css').'">';
}
function ui_icon(string $name): string {
    $paths=[
        'dashboard'=>'<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
        'paid'=>'<path d="M7 7h10v10M17 7 5 19"/><path d="M4 4h6M20 14v6h-6"/>',
        'received'=>'<path d="M7 7v10h10M7 17 19 5"/><path d="M4 10V4h6M14 20h6v-6"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'sidebar'=>'<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M9 4v16m6-11-3 3 3 3"/>',
        'book'=>'<path d="M12 5v16M3 5c3-2 6-2 9 0 3-2 6-2 9 0v14c-3-2-6-2-9 0-3-2-6-2-9 0z"/>',
        'email'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 6 9 7 9-7"/>',
        'review'=>'<rect x="5" y="4" width="14" height="17" rx="2"/><path d="m8 13 3 3 5-6M9 4V2h6v2"/>',
        'settings'=>'<path d="m12 3 3 2 3 1 1 4 2 2-2 3-1 3-4 1-2 2-3-2-3-1-1-4-2-2 2-3 1-3 4-1z"/><circle cx="12" cy="12" r="3"/>',
        'users'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3m1-16a3 3 0 0 1 0 6m3 10v-3a6 6 0 0 0-3-5"/>',
        'file'=>'<path d="M5 3h9l5 5v13H5zM14 3v6h5M8 13h8M8 17h6"/>',
        'search'=>'<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>',
        'scan'=>'<path d="M8 3H3v5m13-5h5v5M3 16v5h5m13-5v5h-5M7 12h10"/>',
        'chevron'=>'<path d="m9 5 7 7-7 7"/>',
        'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
        'lock'=>'<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    ];
    return '<svg class="ui-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['file']).'</svg>';
}
function ui_help(string $label,string $text): void {
    ?><details class="ui-help"><summary aria-label="<?= ui_escape($label) ?>">i</summary><p><?= ui_escape($text) ?></p></details><?php
}
function ui_badge(string $label,string $state='neutral'): string {
    $states=['neutral','success','pending','error','readonly'];
    return '<span class="ui-badge ui-badge-'.(in_array($state,$states,true)?$state:'neutral').'">'.($state==='readonly'?ui_icon('lock'):'<span aria-hidden="true">'.($state==='error'?'!':($state==='success'?'✓':'•')).'</span>').ui_escape($label).'</span>';
}
function ui_page_heading(string $title,string $description='',string $actions=''): void {
    ?><header class="ui-page-heading"><div><h1><?= ui_escape($title) ?></h1><?php if($description!==''): ?><p><?= ui_escape($description) ?></p><?php endif; ?></div><?php if($actions!==''): ?><div class="ui-actions"><?= $actions ?></div><?php endif; ?></header><?php
}
function ui_export_menu(string $url): string {
    $parts=parse_url($url);if(($parts['path']??'')!=='financial_export.php')throw new InvalidArgumentException('Use the authorized financial export route.');parse_str($parts['query']??'',$query);
    $html='<details class="ui-export-menu"><summary class="ui-button">Export / Print</summary><div>';
    foreach(['pdf'=>'Export PDF','xlsx'=>'Export Excel (.xlsx)','csv'=>'Export CSV','print'=>'Print / Save PDF'] as $format=>$label){$query['format']=$format;$url=ui_escape('financial_export.php?'.http_build_query($query));$html.=$format==='print'?'<button type="button" class="ui-button" data-report-print-source="'.$url.'">'.ui_escape($label).'</button>':'<a class="ui-button" href="'.$url.'">'.ui_escape($label).'</a>';}
    return $html.'</div></details>';
}
