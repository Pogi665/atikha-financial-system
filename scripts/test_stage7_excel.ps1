param()
$ErrorActionPreference = 'Stop'
$taskRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$privateRoot = (Resolve-Path (Join-Path $taskRoot '.migration-private')).Path
$pointer = Get-Content -LiteralPath (Join-Path $privateRoot 'stages4-7-current.json') -Raw | ConvertFrom-Json
$fixturePath = (Resolve-Path -LiteralPath $pointer.g5_fixture).Path
if (-not $fixturePath.StartsWith($privateRoot + [IO.Path]::DirectorySeparatorChar)) { throw 'Private disposable fixture required' }
$runPath = [IO.Path]::GetDirectoryName($fixturePath)
$csvPath = (Resolve-Path -LiteralPath (Join-Path $runPath 'stage7-consumer.csv')).Path
$csvHash = (Get-FileHash -LiteralPath $csvPath -Algorithm SHA256).Hash.ToLowerInvariant()
$expectedRows = @(Import-Csv -LiteralPath $csvPath -Encoding UTF8)
$excel = $null; $book = $null; $range = $null
try {
    $excel = New-Object -ComObject Excel.Application
    $excel.Visible = $false
    $excel.DisplayAlerts = $false
    $excel.EnableEvents = $false
    $excel.AskToUpdateLinks = $false
    $excel.AutomationSecurity = 3
    $missing = [Type]::Missing
    # First import of the UTF8 comma/doublequote export; no macro, link update or save.
    $excel.Workbooks.OpenText($csvPath,65001,1,1,1,$false,$false,$false,$true)
    $book = $excel.ActiveWorkbook
    $range = $book.Worksheets.Item(1).UsedRange
    $formulaCount = 0; $numericCount = 0; $literalCount = 0; $exactTextCount = 0
    for ($rowIndex = 2; $rowIndex -le $range.Rows.Count; $rowIndex++) {
        $textCell = $range.Cells.Item($rowIndex,1)
        $moneyCell = $range.Cells.Item($rowIndex,2)
        try {
            if ($textCell.HasFormula -eq $true -or $moneyCell.HasFormula -eq $true) { $formulaCount++ }
            if ($textCell.Value2 -is [string]) {
                $literalCount++
                $expected = [string]@($expectedRows[$rowIndex-2].PSObject.Properties)[0].Value
                $actualText = ([string]$textCell.Value2).Replace("`r`n","`n")
                $expected = $expected.Replace("`r`n","`n")
                if ($actualText -eq $expected -or $actualText -eq ($expected -replace "^'",'')) { $exactTextCount++ }
            }
            if ($moneyCell.Value2 -is [double]) { $numericCount++ }
        } finally {
            [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($textCell)
            [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($moneyCell)
        }
    }
    if ($formulaCount -ne 0 -or $numericCount -ne ($range.Rows.Count - 1) -or $literalCount -ne ($range.Rows.Count - 1)) { throw 'Excel first-import text/numeric/formula check failed' }
    $links = $book.LinkSources(1)
    if ($null -ne $links) { throw 'Unexpected external workbook links' }
    $last = $range.Cells.Item($range.Rows.Count,2)
    try { $precisionObserved = ([double]$last.Value2).ToString('G17',[Globalization.CultureInfo]::InvariantCulture) }
    finally { [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($last) }
    $result = [ordered]@{ passed=$true; consumer='Microsoft Excel'; version=[string]$excel.Version; build=[string]$excel.Build; workflow='First OpenText UTF8 comma/doublequote import; macros/events/link updates disabled; read then close without saving'; rows=($range.Rows.Count-1); text_cells=$literalCount; numeric_money_cells=$numericCount; formula_cells=$formulaCount; external_links=0; precision_probe_csv='90071992547409.91'; precision_probe_excel=$precisionObserved; exact_text_cells=$exactTextCount; csv_sha256=$csvHash; limitation='Excel numeric precision and arbitrary later saves/imports are not exact CSV roundtrip guarantees'; saved=$false }
    $json = $result | ConvertTo-Json -Depth 6
    [IO.File]::WriteAllText((Join-Path $runPath 'stage7-excel-result.json'),$json,(New-Object Text.UTF8Encoding($false)))
    Write-Output $json
} finally {
    if ($null -ne $range) { [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($range) }
    if ($null -ne $book) { $book.Close($false); [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($book) }
    if ($null -ne $excel) { $excel.Quit(); [void][Runtime.InteropServices.Marshal]::FinalReleaseComObject($excel) }
}
