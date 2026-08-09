$ErrorActionPreference = 'Stop'
$base = 'http://localhost/Financial%20Management%20System'
$jar = Join-Path $PSScriptRoot '_cookies.txt'
if (Test-Path $jar) { Remove-Item $jar -Force }

# 1. Sign in as Staff
curl.exe -s -c $jar -b $jar -o NUL -X POST "$base/auth.php" -d "email=staff@atikha.local&password=password123"

# 2. Grab a CSRF token from the workspace page
$page = curl.exe -s -c $jar -b $jar "$base/ocr_expense.php"
$token = ([regex]'name="csrf_token" value="([^"]+)"').Match(($page -join "`n")).Groups[1].Value
Write-Host "csrf token length: $($token.Length)"

# 3. Missing-file guard
Write-Host ''
Write-Host '--- no file attached ---'
curl.exe -s -c $jar -b $jar -X POST "$base/ocr_extract.php" -F "csrf_token=$token"
Write-Host ''

# 4. Non-image guard
Write-Host ''
Write-Host '--- non-image file ---'
Set-Content -Path '_not_an_image.txt' -Value 'this is definitely not a receipt' -NoNewline
curl.exe -s -c $jar -b $jar -X POST "$base/ocr_extract.php" -F "csrf_token=$token" -F "receipt_image=@_not_an_image.txt"
Write-Host ''

# 5. The real thing
Write-Host ''
Write-Host '--- real receipt image ---'
curl.exe -s -c $jar -b $jar -X POST "$base/ocr_extract.php" -F "csrf_token=$token" -F "receipt_image=@_test_receipt.png;type=image/png"
Write-Host ''
