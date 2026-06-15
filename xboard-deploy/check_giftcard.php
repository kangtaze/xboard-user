<?php
// Read and extract key parts of GiftCardCode model
$file = '/www/app/Models/GiftCardCode.php';
$content = file_get_contents($file);

// Find status constants
preg_match('/STATUS_.*=.*\d.*;/s', $content, $m);
echo "=== STATUS CONSTANTS ===\n";
echo $m[0] ?? "NOT FOUND\n";

// Find isAvailable
preg_match('/public function isAvailable.*?\n    \}/s', $content, $m);
echo "\n=== isAvailable ===\n";
echo $m[0] ?? "NOT FOUND\n";

// Find markAsUsed
preg_match('/public function markAsUsed.*?\n    \}/s', $content, $m);
echo "\n=== markAsUsed ===\n";
echo $m[0] ?? "NOT FOUND\n";

// Find checkUserEligibility in GiftCardService
$sfile = '/www/app/Services/GiftCardService.php';
$scontent = file_get_contents($sfile);
preg_match('/public function checkUserEligibility.*?\n    \}/s', $scontent, $m);
echo "\n=== checkUserEligibility ===\n";
echo $m[0] ?? "NOT FOUND\n";
