<?php
$file = '/www/app/Models/GiftCardCode.php';
$content = file_get_contents($file);

// Fix isAvailable to check STATUS_USED
$old = 'if (in_array($this->status, [self::STATUS_EXPIRED, self::STATUS_DISABLED])) {';
$new = 'if (in_array($this->status, [self::STATUS_USED, self::STATUS_EXPIRED, self::STATUS_DISABLED])) {';

$content = str_replace($old, $new, $content);

if ($content === file_get_contents($file)) {
    echo "ERROR: Pattern not found or already fixed\n";
    exit(1);
}

file_put_contents($file, $content);
echo "DONE - Added STATUS_USED check to isAvailable()\n";

// Verify
$check = file_get_contents($file);
if (strpos($check, 'self::STATUS_USED, self::STATUS_EXPIRED') !== false) {
    echo "VERIFIED: Fix applied successfully\n";
} else {
    echo "WARNING: Fix may not have been applied correctly\n";
}
