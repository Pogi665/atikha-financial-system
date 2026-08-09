$ErrorActionPreference = 'Stop'
$base = 'http://localhost/Financial%20Management%20System'
$s = New-Object Microsoft.PowerShell.Commands.WebRequestSession

function Req($Uri, $Method = 'GET', $Body = $null) {
    $p = @{ Uri = $Uri; Method = $Method; WebSession = $s; UseBasicParsing = $true; TimeoutSec = 90 }
    if ($Body) { $p.Body = $Body }
    try {
        $r = Invoke-WebRequest @p
        return [pscustomobject]@{ Status = [int]$r.StatusCode; Content = $r.Content }
    } catch {
        $resp = $_.Exception.Response
        if (-not $resp) { throw }
        $body = ''
        try { $body = (New-Object System.IO.StreamReader($resp.GetResponseStream())).ReadToEnd() } catch { }
        return [pscustomobject]@{ Status = [int]$resp.StatusCode; Content = $body }
    }
}

Req "$base/auth.php" 'POST' @{ email = 'staff@atikha.local'; password = 'password123' } | Out-Null
$ocr = Req "$base/ocr_expense.php"
$t = ([regex]'name="csrf_token" value="([^"]+)"').Match($ocr.Content).Groups[1].Value

$r = Req "$base/ocr_extract.php" 'POST' @{ csrf_token = $t }
Write-Host "STATUS: $($r.Status)"
Write-Host "BODY: $($r.Content)"
