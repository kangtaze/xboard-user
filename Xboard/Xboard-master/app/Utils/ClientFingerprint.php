<?php

namespace App\Utils;

/**
 * ClientFingerprint — UA 字符串 → 客户端指纹解析
 *
 * 用途:
 * - 区分用户在用什么客户端: VP92 自研 APP / 小火箭 / Clash / v2ray / 浏览器 / 未知
 * - 用于:
 *   - v2_subscription_log.client_type (拉订阅记录)
 *   - v2_user.last_login_client (admin 列表展示)
 *
 * 设计:
 * - 纯正则, 不依赖第三方库 (Mobile_Detect 等太重)
 * - 覆盖 VP92 实际场景中可能遇到的客户端
 * - 失败优雅降级到 'unknown'
 *
 * 注意:
 * - 客户端 UA 可被伪造 (小火箭可改), 仅作辅助参考
 * - 浏览器访问 /dashboard 也会记录 (type=browser)
 */
class ClientFingerprint
{
    /**
     * 解析 UA 字符串.
     *
     * @param string $ua HTTP User-Agent
     * @return array{type: string, version: string, os: string}
     *   - type: 客户端类型标识 (vp92-android / vp92-ios / shadowrocket / clash / ...)
     *   - version: 版本号字符串 (无版本时为空)
     *   - os: 操作系统 (Android / iOS / Windows / macOS / Linux / Multi / "")
     */
    public static function parse(string $ua): array
    {
        $ua = trim($ua);
        $empty = ['type' => 'unknown', 'version' => '', 'os' => ''];

        if ($ua === '') {
            return $empty;
        }

        // 1. VP92 自研 APP (user_agent 格式: "VP92-Android/v1.2.6" 或 "VP92-IOS/v1.0.0")
        if (preg_match('#^VP92-([A-Za-z]+)/v?([\d.]+)$#', $ua, $m)) {
            $platform = strtolower($m[1]);
            $osFromPlatform = match (strtoupper($m[1])) {
                'Android' => 'Android',
                'IOS', 'IPHONEOS' => 'iOS',
                'MAC', 'MACOS' => 'macOS',
                'WINDOWS', 'WIN' => 'Windows',
                default => $m[1],
            };
            return [
                'type' => 'vp92-' . $platform,
                'version' => $m[2],
                'os' => $osFromPlatform,
            ];
        }

        // 2. 已知代理客户端 (按优先级匹配)
        $patterns = [
            // iOS
            ['regex' => '#^Shadowrocket/([\d.]+)#i', 'type' => 'shadowrocket', 'os' => 'iOS'],
            ['regex' => '#^Quantumult[\s%20]?X/?([\d.]*)$#i', 'type' => 'quantumult-x', 'os' => 'iOS'],
            ['regex' => '#^Surge/([\d.]+)#i', 'type' => 'surge', 'os' => 'iOS/macOS'],
            ['regex' => '#^Stash/?([\d.]*)$#i', 'type' => 'stash', 'os' => 'iOS/macOS'],
            ['regex' => '#^Loon/?([\d.]*)$#i', 'type' => 'loon', 'os' => 'iOS'],

            // Android
            ['regex' => '#^v2rayNG/?([\d.]*)$#i', 'type' => 'v2rayng', 'os' => 'Android'],
            ['regex' => '#^NekoBox(?:forAndroid)?/?([\d.]*)$#i', 'type' => 'nekobox', 'os' => 'Android'],
            ['regex' => '#^sing-box/?([\d.]*)$#i', 'type' => 'sing-box', 'os' => 'Android'],
            ['regex' => '#^Clash(?:forAndroid|FA)?/?([\d.]*)$#i', 'type' => 'clash-android', 'os' => 'Android'],

            // Desktop
            ['regex' => '#^(?:Clash[\s-]for[\s-]Windows|CFW)/?([\d.]*)$#i', 'type' => 'clash-windows', 'os' => 'Windows'],
            ['regex' => '#^ClashX(?:Pro)?/?([\d.]*)$#i', 'type' => 'clashx', 'os' => 'macOS'],
            ['regex' => '#^clash-verge(?:-rev)?/?([\d.]*)$#i', 'type' => 'clash-verge', 'os' => 'Desktop'],
            ['regex' => '#^mihomo/?([\d.]*)$#i', 'type' => 'mihomo', 'os' => 'Desktop'],
            ['regex' => '#^v2rayN/?([\d.]*)$#i', 'type' => 'v2rayn', 'os' => 'Windows'],
            ['regex' => '#^Hiddify(?:Next|App)?/?([\d.]*)$#i', 'type' => 'hiddify', 'os' => 'Multi'],
            ['regex' => '#^Netch/?([\d.]*)$#i', 'type' => 'netch', 'os' => 'Windows'],
        ];

        foreach ($patterns as $p) {
            if (preg_match($p['regex'], $ua, $m)) {
                return [
                    'type' => $p['type'],
                    'version' => $m[1] ?? '',
                    'os' => $p['os'],
                ];
            }
        }

        // 3. 浏览器 (Mozilla 开头, 推断 OS)
        if (str_starts_with($ua, 'Mozilla/')) {
            $os = 'Linux';
            if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) {
                $os = 'iOS';
            } elseif (str_contains($ua, 'Android')) {
                $os = 'Android';
            } elseif (str_contains($ua, 'Macintosh')) {
                $os = 'macOS';
            } elseif (str_contains($ua, 'Windows')) {
                $os = 'Windows';
            }
            // 简化: 不解析 browser 版本 (避免过度工程)
            return ['type' => 'browser', 'version' => '', 'os' => $os];
        }

        // 4. 命令行 / 未知
        return $empty;
    }
}
