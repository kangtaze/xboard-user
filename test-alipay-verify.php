<?php
// Alipay verify debug test
$pubkey = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEApkVEndZe4qQy66sp2oSemUWtr6r0EYm+l6JqSgS4wFfy5oXwYd9QPieTad1HMS3zbD3q6dQlfoJSOFfV0/JXyHkeLiiLTqGysd7hGQimikSd/llc/m6UHDDclcrVstr0SUIE3DhzZZEg/ocG1GyY2n8Z61/wvPxz0uz1FZt/Mkbd3tsrPtknBSekBmNzNFZATlWIZlVPGqPtSwDAnMXEjGLt/HYobporcD9yFubGt1sOhkH6O/mAt2Ru/2Ru/4321z41hnv/Nt+fk+GNbHMr4Ucj/ObPC/P9HTGh6887qFhPdvCvmOZy+6FNIfYscNhw+gyx6ZrX/WX4+qIDES2eTwIDAQAB';

// Test 1: Can we load the key?
$res = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($pubkey, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
$key = openssl_get_publickey($res);
echo "Public key loaded: " . ($key ? "YES" : "NO - " . openssl_error_string()) . "\n";

// Test 2: Verify with the actual notify data from Alipay
// This is the exact data Alipay sent us
$sign = "K7apfbmYhmaJ51XFN21lcquOJs0D/gM6Y7oL79u+d9oVZTx7NJ2fS2qKsVeda5R9NizN5rnl0+0WeNuCfPPkAypCa/4tnwKyLxzKmpvVB0Jf8cJhuerB9HCHJWn5+P4bljv4tKtf3prfRVaP/lRyAEemlQXiAdhz2ylAoBvwOAaUFCNKijg20JBvOQiHE0g/O9m/hC4C1nqXYZi+GWgW9RUwABVdiy7MePeX6MR19w/nNpL+mONofDo1Q0FP9O8rZwXqVhUo7va/VqCvpLcnu1yy0wLNet9GmBKDweFii65wsB+NFccgB/vHmPD89C79qGJJaoQVVP7Rz7OqzoUCTg==";

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

// Method A: Without URL encoding
$queryA = http_build_query($params);
echo "\n--- Method A: http_build_query ---\n";
echo $queryA . "\n";
$resultA = openssl_verify($queryA, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
echo "Result A: " . ($resultA === 1 ? "SUCCESS" : "FAIL (code=$resultA)") . "\n";
if ($resultA !== 1) echo "OpenSSL error: " . openssl_error_string() . "\n";

// Method B: Manual key=value (no URL encoding)
$queryParts = [];
foreach ($params as $k => $v) {
    $queryParts[] = $k . '=' . $v;
}
$queryB = implode('&', $queryParts);
echo "\n--- Method B: Manual key=value ---\n";
echo $queryB . "\n";
$resultB = openssl_verify($queryB, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
echo "Result B: " . ($resultB === 1 ? "SUCCESS" : "FAIL (code=$resultB)") . "\n";
if ($resultB !== 1) echo "OpenSSL error: " . openssl_error_string() . "\n";

// Method C: URL encode values manually
$queryParts = [];
foreach ($params as $k => $v) {
    $queryParts[] = $k . '=' . urlencode($v);
}
$queryC = implode('&', $queryParts);
echo "\n--- Method C: urlencode values ---\n";
echo $queryC . "\n";
$resultC = openssl_verify($queryC, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
echo "Result C: " . ($resultC === 1 ? "SUCCESS" : "FAIL (code=$resultC)") . "\n";
if ($resultC !== 1) echo "OpenSSL error: " . openssl_error_string() . "\n";

openssl_free_key($key);
