# Legacy entry point: shared isolated route suite replaces live credentials/cookie files.
param([Parameter(Mandatory=$true)][ValidatePattern('^atikha_test_[a-z0-9_]+$')][string]$Database)
$ErrorActionPreference = 'Stop'
& python (Join-Path $PSScriptRoot 'scripts/test_http.py') "--database=$Database"
if ($LASTEXITCODE -ne 0) { throw 'Isolated HTTP regression suite failed.' }
