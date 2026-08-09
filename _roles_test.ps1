$ErrorActionPreference = 'Stop'
$base = 'http://localhost/Financial%20Management%20System'
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
$fundId = $args[0]
$deleteId = $args[1]

function NewJar($name) {
    $p = Join-Path $PSScriptRoot "_cookies_$name.txt"
    if (Test-Path $p) { Remove-Item $p -Force }
    return $p
}

Write-Host '=== Admin: edit a project code (audit diff) ==='
$jar = NewJar 'admin'
curl.exe -s -c $jar -b $jar -o NUL -X POST "$base/auth.php" -d "email=admin@atikha.local&password=password123"
$page = (curl.exe -s -c $jar -b $jar "$base/funds.php") -join "`n"
$token = ([regex]'name="csrf_token" value="([^"]+)"').Match($page).Groups[1].Value

Write-Host ("admin reaches funds.php   : {0}" -f ($page -match 'Log Incoming Funds'))
Write-Host ("admin sees Delete control : {0}" -f ($page -match 'name="action" value="delete"'))
Write-Host ("edit modal has project    : {0}" -f ($page -match 'id="edit-project"'))

$r = (curl.exe -s -i -o NUL -D - -c $jar -b $jar -X POST "$base/funds.php" `
    -d "action=update" -d "csrf_token=$token" -d "fund_id=$fundId" `
    -d "source_donor=VERIFY-EDITED" -d "category=Donation" -d "project_code=ATK-2026-REVISED" `
    -d "amount=1234.56" -d "date_received=2026-08-09") -join "`n"
Write-Host ($r -split "`n" | Where-Object { $_ -match '^Location' })

Write-Host ''
Write-Host '=== Admin: delete a fund ==='
$d = (curl.exe -s -i -o NUL -D - -c $jar -b $jar -X POST "$base/funds.php" `
    -d "action=delete" -d "csrf_token=$token" -d "fund_id=$deleteId") -join "`n"
Write-Host ($d -split "`n" | Where-Object { $_ -match '^Location' })

Write-Host ''
Write-Host '=== Management lockout ==='
& $mysql -u root -e "UPDATE atikha_finance.Users SET Role='Management' WHERE UserID=2;" 2>&1 | Out-Null
try {
    $jarM = NewJar 'mgmt'
    curl.exe -s -c $jarM -b $jarM -o NUL -X POST "$base/auth.php" -d "email=staff@atikha.local&password=password123"

    $dash = (curl.exe -s -c $jarM -b $jarM "$base/dashboard.php") -join "`n"
    Write-Host ("dashboard renders for Management  : {0}" -f ($dash -match 'Signed in as'))
    Write-Host ("Incoming Funds link hidden        : {0}" -f (-not ($dash -match 'href="funds\.php"')))
    Write-Host ("Scan Receipt link hidden          : {0}" -f (-not ($dash -match 'href="ocr_expense\.php"')))
    Write-Host ("Expenses link still shown         : {0}" -f ($dash -match 'href="expenses\.php"'))

    foreach ($p in @('expenses.php', 'reports.php')) {
        $c = (curl.exe -s -c $jarM -b $jarM "$base/$p") -join "`n"
        Write-Host ("{0}: workspace links hidden      : {1}" -f $p, (-not ($c -match 'href="funds\.php"') -and -not ($c -match 'href="ocr_expense\.php"')))
    }

    $f = (curl.exe -s -w "`nSTATUS:%{http_code}" -c $jarM -b $jarM "$base/funds.php") -join "`n"
    Write-Host ("funds.php status                  : {0}" -f ([regex]'STATUS:(\d+)').Match($f).Groups[1].Value)
    Write-Host ("funds.php shows Access Denied     : {0}" -f ($f -match 'Access Denied'))
    Write-Host ("refusal names the roles           : {0}" -f ($f -match 'Accounting/Administrative Staff and System Administrator'))

    $o = (curl.exe -s -w "`nSTATUS:%{http_code}" -c $jarM -b $jarM "$base/ocr_expense.php") -join "`n"
    Write-Host ("ocr_expense.php status            : {0}" -f ([regex]'STATUS:(\d+)').Match($o).Groups[1].Value)
    Write-Host ("ocr_expense.php Access Denied     : {0}" -f ($o -match 'Access Denied'))

    $e = (curl.exe -s -w "`nSTATUS:%{http_code}" -c $jarM -b $jarM -X POST "$base/ocr_extract.php" -F "csrf_token=x") -join "`n"
    Write-Host ("ocr_extract.php status            : {0}" -f ([regex]'STATUS:(\d+)').Match($e).Groups[1].Value)
    Write-Host ("ocr_extract.php JSON refusal      : {0}" -f ($e -match 'restricted to Staff and Administrators'))

    # Management must not be able to POST a fund even by guessing the endpoint
    $p = (curl.exe -s -w "`nSTATUS:%{http_code}" -c $jarM -b $jarM -X POST "$base/funds.php" `
        -d "action=create" -d "csrf_token=x" -d "source_donor=SNEAKY" -d "category=Donation" `
        -d "amount=1" -d "date_received=2026-08-09") -join "`n"
    Write-Host ("direct POST blocked               : {0}" -f ([regex]'STATUS:(\d+)').Match($p).Groups[1].Value)
} finally {
    & $mysql -u root -e "UPDATE atikha_finance.Users SET Role='Staff' WHERE UserID=2;" 2>&1 | Out-Null
    $restored = & $mysql -u root -N -e "SELECT Role FROM atikha_finance.Users WHERE UserID=2;"
    Write-Host ''
    Write-Host "test account role restored to: $restored"
}
