# Read from the console without echo. Only the calling PHP process receives stdout.
$ErrorActionPreference = 'Stop'
if ([Console]::IsInputRedirected) { throw 'An interactive console is required.' }
$secret = New-Object System.Security.SecureString
try {
    while ($true) {
        $key = [Console]::ReadKey($true)
        if ($key.Key -eq 'Enter') { break }
        if ($key.Key -eq 'Backspace') {
            if ($secret.Length -gt 0) { $secret.RemoveAt($secret.Length - 1) }
        } elseif (-not [char]::IsControl($key.KeyChar)) { $secret.AppendChar($key.KeyChar) }
    }
    $ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
    try { [Console]::Out.Write([Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }
} finally { $secret.Dispose() }
