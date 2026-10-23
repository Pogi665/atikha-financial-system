<?php
/** Pure legacy rendering assertion: no PDO, database fixture, provider or dashboard route. */
require_once __DIR__.'/../includes/dashboard_reporting.php';
ob_start();
dashboard_render_reporting(null, null, '2026-09-30');
$html = ob_get_clean();
if (!str_contains($html, '2026-09-30 · Configured cash') || str_contains($html, 'Â') || str_contains($html, '�')) {
    throw new RuntimeException('Legacy reporting separator must render as the intended UTF-8 middle dot.');
}
echo "PASS: legacy helper renders the intended UTF-8 middle dot; no dashboard activation or database/provider call\n";
