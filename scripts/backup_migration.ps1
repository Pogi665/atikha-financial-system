param(
    [Parameter(Mandatory=$true)][ValidatePattern('^atikha_test_[a-z0-9_]+$')][string]$CopyDatabase,
    [Parameter(Mandatory=$true)][string]$Directory
)
$ErrorActionPreference = 'Stop'
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
$dump = 'C:\xampp\mysql\bin\mysqldump.exe'
$php = 'C:\xampp\php\php.exe'
$dbHost = if ($env:ATIKHA_DB_HOST) { $env:ATIKHA_DB_HOST } else { '127.0.0.1' }
$dbUser = if ($env:ATIKHA_DB_USER) { $env:ATIKHA_DB_USER } else { 'root' }
$previousPassword = $env:MYSQL_PWD
try {
    $env:MYSQL_PWD = $env:ATIKHA_DB_PASSWORD
    $exists = & $mysql -h $dbHost -u $dbUser -N -e "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$CopyDatabase'"
    if ($LASTEXITCODE -ne 0 -or $exists -ne '0') { throw 'Copy database exists or lookup failed; choose a new disposable name.' }
    if (Test-Path -LiteralPath $Directory) { throw 'Backup directory already exists; choose a new path.' }
    New-Item -ItemType Directory -Path $Directory | Out-Null
    $backupPath = (Resolve-Path -LiteralPath $Directory).Path
    & $dump -h $dbHost -u $dbUser --single-transaction --routines --events --triggers --hex-blob --default-character-set=utf8mb4 "--result-file=$backupPath\database.sql" atikha_finance
    if ($LASTEXITCODE -ne 0) { throw 'Database export failed.' }
    Copy-Item -LiteralPath (Join-Path $PSScriptRoot '..\uploads') -Destination (Join-Path $backupPath 'uploads') -Recurse
    & $mysql -h $dbHost -u $dbUser -e "CREATE DATABASE $CopyDatabase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    if ($LASTEXITCODE -ne 0) { throw 'Copy database creation failed.' }
    $previousEncoding = $OutputEncoding
    try {
        $OutputEncoding = New-Object System.Text.UTF8Encoding $false
        [IO.File]::ReadAllText((Join-Path $backupPath 'database.sql')) | & $mysql -h $dbHost -u $dbUser --default-character-set=utf8mb4 $CopyDatabase
        if ($LASTEXITCODE -ne 0) { throw 'Restoration failed.' }
    } finally { $OutputEncoding = $previousEncoding }
    & $php (Join-Path $PSScriptRoot 'backup_verify.php') --database=atikha_finance "--copy-database=$CopyDatabase" "--directory=$backupPath"
    if ($LASTEXITCODE -ne 0) { throw 'Restoration verification failed.' }
    Write-Output "Verified manifest: $backupPath\manifest.json"
} finally { $env:MYSQL_PWD = $previousPassword }
