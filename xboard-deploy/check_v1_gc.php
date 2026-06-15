<?php
$file = '/www/app/Http/Controllers/V1/User/GiftCardController.php';
$content = file_get_contents($file);
echo substr($content, 0, 3000);
