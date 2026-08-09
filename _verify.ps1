$ErrorActionPreference = 'Stop'
$base = 'http://localhost/Financial%20Management%20System'
$script:s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$script:fails = 0

function Show($label, $ok, $detail) {
    if ($ok) { $mark = 'PASS' } else { $mark = 'FAIL'; $script:fails++ }
    if ($detail) { Write-Host ("[{0}] {1} -- {2}" -f $mark, $label, $detail) }
    else { Write-Host ("[{0}] {1}" -f $mark, $label) }
}

function Req($Uri, $Method = 'GET', $Body = $null) {
    $p = @{ Uri = $Uri; Method = $Method; WebSession = $script:s; UseBasicParsing = $true; TimeoutSec = 90 }
    if ($Body) { $p.Body = $Body }
    try {
        $r = Invoke-WebRequest @p
        return [pscustomobject]@{ Status = [int]$r.StatusCode; Content = $r.Content }
    } catch {
        $resp = $_.Exception.Response
        if (-not $resp) { throw }
        $body = ''
        try {
            $sr = New-Object System.IO.StreamReader($resp.GetResponseStream())
            $body = $sr.ReadToEnd()
        } catch { }
        return [pscustomobject]@{ Status = [int]$resp.StatusCode; Content = $body }
    }
}

Write-Host '=== Staff session ==='
$auth = Req "$base/auth.php" 'POST' @{ email = 'staff@atikha.local'; password = 'password123' }
Show 'staff login succeeds' ($auth.Status -eq 200 -and $auth.Content -match 'Signed in as') "HTTP $($auth.Status)"

$funds = Req "$base/funds.php"
Show 'funds.php reachable by Staff' ($funds.Status -eq 200) "HTTP $($funds.Status)"
Show 'entry form present' ($funds.Content -match 'Log Incoming Funds')
Show 'verification panel present' ($funds.Content -match 'Recently Logged')
Show 'Project Code field present' ($funds.Content -match 'name="project_code"')
Show 'emerald primary action' ($funds.Content -match 'bg-emerald-600 hover:bg-emerald-700')
Show 'bright workspace background' ($funds.Content -match 'body class="min-h-screen min-w-\[1024px\] bg-slate-50"')
Show 'dark sidebar retained' ($funds.Content -match 'aside class="fixed inset-y-0 left-0 w-64 bg-slate-800')

$fToken = ([regex]'name="csrf_token" value="([^"]+)"').Match($funds.Content).Groups[1].Value
Show 'funds.php issues a CSRF token' ($fToken.Length -gt 0) "len=$($fToken.Length)"

$stamp = 'VERIFY-' + (Get-Random -Maximum 999999)
$create = Req "$base/funds.php" 'POST' @{ action = 'create'; csrf_token = $fToken; source_donor = $stamp;
    category = 'Donation'; project_code = 'ATK-2026-99'; amount = '1234.56'; date_received = '2026-08-09' }
Show 'create redirects to the success view' ($create.Content -match 'Incoming fund saved successfully') "HTTP $($create.Status)"
Show 'new record listed' ($create.Content -match [regex]::Escape($stamp))
Show 'project code rendered' ($create.Content -match 'ATK-2026-99')

$create2 = Req "$base/funds.php" 'POST' @{ action = 'create'; csrf_token = $fToken; source_donor = "$stamp-NOPROJ";
    category = 'Donation'; project_code = ''; amount = '10.00'; date_received = '2026-08-09' }
Show 'create without a project code succeeds' ($create2.Content -match 'Incoming fund saved successfully')

$bad = Req "$base/funds.php" 'POST' @{ action = 'create'; csrf_token = 'bogus'; source_donor = 'X';
    category = 'Donation'; project_code = 'Y'; amount = '5'; date_received = '2026-08-09' }
Show 'stale CSRF token is refused' ($bad.Content -match 'Your session expired')

Show 'Staff sees no Delete control' (-not ($funds.Content -match 'name="action" value="delete"'))

Write-Host ''
Write-Host '=== Scan Receipt workspace ==='
$ocr = Req "$base/ocr_expense.php"
Show 'ocr_expense.php reachable by Staff' ($ocr.Status -eq 200) "HTTP $($ocr.Status)"
Show 'drag-and-drop zone present' ($ocr.Content -match 'id="dropzone"')
Show 'idle state present' ($ocr.Content -match 'id="state-idle"')
Show 'loading state present' ($ocr.Content -match 'id="state-loading"')
Show 'extraction form present' ($ocr.Content -match 'id="save-form"')
Show 'preview pane present' ($ocr.Content -match 'id="preview-shell"')
Show 'AJAX endpoint wired' ($ocr.Content -match 'ocr_extract\.php')
Show 'no-JS upload fallback retained' ($ocr.Content -match 'name="action" value="upload"')
Show 'save handler retained' ($ocr.Content -match 'name="action" value="save"')
Show 'discard handler retained' ($ocr.Content -match 'name="action" value="discard"')

Write-Host ''
Write-Host '=== ocr_extract.php guards ==='
$g1 = Req "$base/ocr_extract.php"
Show 'GET rejected with 405' ($g1.Status -eq 405) "HTTP $($g1.Status)"
$g2 = Req "$base/ocr_extract.php" 'POST' @{ csrf_token = 'bogus' }
Show 'bad CSRF rejected with 400' ($g2.Status -eq 400) "HTTP $($g2.Status)"

$oToken = ([regex]'name="csrf_token" value="([^"]+)"').Match($ocr.Content).Groups[1].Value
$g3 = Req "$base/ocr_extract.php" 'POST' @{ csrf_token = $oToken }
Show 'missing file rejected with 400' ($g3.Status -eq 400) "HTTP $($g3.Status)"
Show 'missing file returns a helpful message' ($g3.Content -match 'receipt image')

$saved = $script:s
$script:s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$g4 = Req "$base/ocr_extract.php" 'POST' @{ csrf_token = 'x' }
Show 'anonymous caller rejected with 401' ($g4.Status -eq 401) "HTTP $($g4.Status)"
$script:s = $saved

Write-Host ''
Write-Host '=== Other pages still render ==='
foreach ($page in @('dashboard.php', 'expenses.php', 'reports.php')) {
    $r = Req "$base/$page"
    Show "$page renders for Staff" ($r.Status -eq 200) "HTTP $($r.Status)"
}

Write-Host ''
Write-Host "FAILURES: $script:fails"
Write-Host "marker: $stamp"
