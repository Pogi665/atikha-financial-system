$ErrorActionPreference = 'Stop'
$base = 'http://localhost/Financial%20Management%20System'
$jar = Join-Path $PSScriptRoot '_cookies.txt'
$receiptId = $args[0]

# Load the review page for this receipt (the no-JS / failed-save render path)
$page = (curl.exe -s -c $jar -b $jar "$base/ocr_expense.php?receipt=$receiptId") -join "`n"
$token = ([regex]'name="csrf_token" value="([^"]+)"').Match($page).Groups[1].Value

Write-Host '--- server-rendered review state ---'
Write-Host ("preview image bound : {0}" -f ($page -match 'id="preview"[\s\S]{0,200}src="uploads/receipts/'))
Write-Host ("payee prefilled     : {0}" -f ($page -match 'id="payee"[\s\S]{0,300}value="MANILA HARDWARE SUPPLY"'))
Write-Host ("amount prefilled    : {0}" -f ($page -match 'id="amount"[\s\S]{0,400}value="3830.40"'))
Write-Host ("date prefilled      : {0}" -f ($page -match 'id="date_incurred"[\s\S]{0,400}value="2026-03-14"'))
Write-Host ("confidence badge    : {0}" -f ($page -match '98% confidence'))
Write-Host ("form state visible  : {0}" -f ($page -match 'id="state-form" class=" p-6'))
Write-Host ("idle state hidden   : {0}" -f ($page -match 'id="state-idle" class="hidden'))

# Reject an invalid amount first
Write-Host ''
Write-Host '--- server-side validation on save ---'
$bad = (curl.exe -s -c $jar -b $jar -X POST "$base/ocr_expense.php" `
    -d "action=save" -d "csrf_token=$token" -d "receipt_id=$receiptId" `
    -d "payee=Test" -d "category=Equipment" -d "amount=-5" -d "date_incurred=2026-03-14") -join "`n"
Write-Host ("negative amount refused : {0}" -f ($bad -match 'Please fill in all fields with valid values'))

# Now the real save
Write-Host ''
Write-Host '--- confirm and save ---'
$hdrs = (curl.exe -s -i -o NUL -D - -c $jar -b $jar -X POST "$base/ocr_expense.php" `
    -d "action=save" -d "csrf_token=$token" -d "receipt_id=$receiptId" `
    -d "payee=MANILA HARDWARE SUPPLY" -d "category=Equipment" -d "amount=3830.40" -d "date_incurred=2026-03-14") -join "`n"
Write-Host ($hdrs -split "`n" | Where-Object { $_ -match '^(HTTP|Location)' })
