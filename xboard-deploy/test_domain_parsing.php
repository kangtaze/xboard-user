<?php
// 测试节点名称解析逻辑
$testNames = [
    'SG_新加坡_02',
    'JP_日本_01',
    'US_美国_03',
    'HK_香港_05',
    'TW_台湾_10',
    'KR_韩国_01',
    'UK_英国_02',
    'DE_德国_01',
    'Invalid_Format',
    'SG_新加坡_02_Extra',
];

foreach ($testNames as $name) {
    if (preg_match('/^([A-Z]{2})[_\s].*?_(\d+)$/', $name, $matches)) {
        $region = strtolower($matches[1]);
        $number = (int)$matches[2];

        $validRegions = ['us', 'jp', 'sg', 'kr', 'hk', 'tw', 'uk', 'de', 'fr', 'ca', 'au', 'in', 'br', 'nl', 'ru', 'cn'];
        if (in_array($region, $validRegions)) {
            $domain = "{$region}{$number}.eogop1.ferrari.cc.cd";
            echo "✓ {$name} → {$domain}\n";
        } else {
            echo "✗ {$name} → Invalid region code: {$region}\n";
        }
    } else {
        echo "✗ {$name} → Cannot parse (fallback to IP mapping)\n";
    }
}
