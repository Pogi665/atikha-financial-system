<?php
require_once __DIR__.'/accounting_query.php';
function dashboard_kpi_series(PDO $pdo,?array $months=null): array
{
    $months??=accounting_months(6);
    if(count($months)!==6){throw new InvalidArgumentException('Six completed month keys required.');}
    return accounting_monthly($pdo,$months);
}
