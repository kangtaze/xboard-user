<?php
// Test with the CORRECT Alipay public key
$pubkey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAncjiqE0DUiPZHmVgpZncwDSULDLSXOLtaZwUL2E5meYw02Cj5lhphLOK+k7ordmm8I8sgbAiYraM9aOASW6g3aVpkL4nMgM71Vqp3LRMS5atnFl5KOoVbZiCQa7/y1FktMwl5GkqQpR5BtO192cdObeVzMlmKeczfZepe47kKiQUkVgoiNWUYDGezR2w/097Xe6eKmQTOKg8uQRK9pa30r5LJorMJyav+HYGLpIcyoGgtlIx0co43jK02lkgzkkAGxUrTsUJiaN/U82orPm5P3qZEm/E0z98JxiQT5W6fOHcKZy8z7ZSZHuWtOnz/GTxtS8RAzHPABOpVnpHXYcgXwIDAQAB';

$sign = "K7apfbmYhmaJ51XFN21lcquOJs0D/gM6Y7oL79u+d9oVZTx7NJ2fS2qKsVeda5R9NizN5rnl0+0WeNuCfPPkAypCa/4tnwKyLxzKmpvVB0Jf8cJhuerB9HCHJWn5+P4bljv4tKtf3prfRVaP/lRyAEemlQXiAdhz2ylAoBvwOAaUFCNKijg20JBvOQiHE0g/O9m/hC4C1nqXYZi+GWgW9RUwABVdiy7MePeX6MR19w/nNpL+mONofDo1Q0FP9O8rZwXqVhUo7va/VqCvpLcnu1yy0wLNet9GmBKDweFii65wsB+NFccgB/vHmPD89C79qGJJaoQVVP7Rz7OqzoUCTg==";

$res = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($pubkey, 64, "\n", true) . "\n-----END PUBLIC KEY-----";

// All params except sign and sign_type, sorted
$params = [
    'app_id' => '2021006163638039',
    'auth_app_id' => '2021006163638039',
    'buyer_id' => '2088102990032752',
    'buyer_pay_amount' => '1.01',
    'charset' => 'UTF-8',
    'fund_bill_list' => '[{"amount":"1.01","fundChannel":"ALIPAYACCOUNT"}]',
    'gmt_create' => '2026-06-22 01:00:45',
    'gmt_payment' => '2026-06-22 01:00:49',
    'invoice_amount' => '1.01',
    'notify_id' => '2026062201222010050032751404606758',
    'notify_time' => '2026-06-22 01:00:50',
    'notify_type' => 'trade_status_sync',
    'out_trade_no' => '2026062201063425153439201',
    'point_amount' => '0.00',
    'receipt_amount' => '1.01',
    'seller_id' => '2088442562574564',
    'subject' => '会员充值',
    'total_amount' => '1.01',
    'trade_no' => '2026062222001432751413822384',
    'trade_status' => 'TRADE_SUCCESS',
    'version' => '1.0',
];

ksort($params);

// Method A: http_build_query (URL encodes values)
$queryA = http_build_query($params);
$resultA = openssl_verify($queryA, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
echo "Method A (http_build_query): " . ($resultA === 1 ? "SUCCESS" : "FAIL") . "\n";

// Method B: Manual key=value (no encoding)
$queryB = implode('&', array_map(fn($k,$v) => "$k=$v", array_keys($params), $params));
$resultB = openssl_verify($queryB, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
echo "Method B (raw key=value): " . ($resultB === 1 ? "SUCCESS" : "FAIL") . "\n";

// Method C: urlencode values manually
$queryC = implode('&', array_map(fn($k,$v) => "$k=" . urlencode($v), array_keys($params), $params));
$resultC = openssl_verify($queryC, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
echo "Method C (urlencode values): " . ($resultC === 1 ? "SUCCESS" : "FAIL") . "\n";
