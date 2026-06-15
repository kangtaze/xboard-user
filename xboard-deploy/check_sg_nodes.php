<?php
$content = file_get_contents('https://eogop1.ferrari.cc.cd/sub?token=4d2f1fd018b0e42cb277cb617f040099');
$decoded = base64_decode($content);

$lines = explode("\n", $decoded);
foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;

    // 解析vless
    if (preg_match('/vless:\/\/([^@]+)@([^:]+):(\d+)/', $line, $matches)) {
        $host = $matches[2];
        $port = $matches[3];

        // 获取fragment（节点名称）
        $fragment = '';
        if (strpos($line, '#') !== false) {
            $fragment = substr($line, strpos($line, '#') + 1);
            $fragment = urldecode($fragment);
        }

        echo "$fragment -> $host:$port\n";
    }
}
