<?php
// Throwaway: renders a legible receipt image for the upload smoke test.
$w = 420;
$h = 560;
$im = imagecreatetruecolor($w, $h);
$white = imagecolorallocate($im, 255, 255, 255);
$black = imagecolorallocate($im, 20, 20, 20);
$grey = imagecolorallocate($im, 120, 120, 120);
imagefilledrectangle($im, 0, 0, $w, $h, $white);

$lines = [
    [30, 'MANILA HARDWARE SUPPLY'],
    [55, '123 Rizal Avenue, Quezon City'],
    [75, 'TIN 004-221-889-000'],
    [105, '--------------------------------'],
    [130, 'OFFICIAL RECEIPT'],
    [155, 'Date: 03/14/2026'],
    [180, '--------------------------------'],
    [205, 'Cement 40kg x 4        1,800.00'],
    [230, 'Steel bar 10mm x 6       960.00'],
    [255, 'Paint white 4L           540.00'],
    [280, 'Assorted nails           120.00'],
    [305, '--------------------------------'],
    [330, 'SUBTOTAL               3,420.00'],
    [355, 'VAT 12%                  410.40'],
    [385, 'TOTAL                  3,830.40'],
    [410, '--------------------------------'],
    [435, 'CASH                   4,000.00'],
    [460, 'CHANGE                   169.60'],
    [495, 'Thank you for your purchase!'],
];

foreach ($lines as [$y, $text]) {
    $color = ($y === 130 || $y === 385) ? $black : $grey;
    $font = ($y === 30 || $y === 130 || $y === 385) ? 5 : 3;
    imagestring($im, $font, 24, $y, $text, $color);
}

imagepng($im, __DIR__ . '/_test_receipt.png');
imagedestroy($im);
echo "written\n";
