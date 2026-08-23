<?php

namespace App\Utils;

/**
 * Lightweight User-Agent parser tailored to the strings the VP92 client sends:
 *   "VP92/v1.2.2 clash-verge Platform/android"
 *   "VP92/v1.2.2 clash-verge Platform/windows"
 *   "VP92/v1.2.2 clash-verge Platform/macos"
 *   "VP92/v1.2.2 clash-verge Platform/linux"
 *
 * It also handles common browser User-Agents (e.g. admin panel login from a
 * real browser) so the active-sessions list does not show empty rows for the
 * web dashboard. Output is intentionally coarse — only OS / device / browser
 * — because that is all the Flutter UI needs.
 */
class UserAgentParser
{
    /**
     * @return array{os: string, device: string, browser: string}
     */
    public static function parse(?string $userAgent): array
    {
        $ua = (string) $userAgent;
        if ($ua === '') {
            return ['os' => 'Unknown', 'device' => 'Unknown', 'browser' => 'Unknown'];
        }

        $lower = strtolower($ua);

        // ---- VP92 Flutter client (the case we care about most) ----
        if (str_contains($lower, 'vp92/')) {
            // Pull the "Platform/<token>" segment first.
            $platform = 'Unknown';
            if (preg_match('/platform\/([a-z0-9_\-]+)/i', $ua, $m)) {
                $platform = $m[1];
            }

            $os = match (strtolower($platform)) {
                'android' => 'Android',
                'ios' => 'iOS',
                'macos' => 'macOS',
                'windows' => 'Windows',
                'linux' => 'Linux',
                'web' => 'Web',
                'fuchsia' => 'Fuchsia',
                default => ucfirst($platform),
            };

            return [
                'os' => $os,
                // The Flutter app currently does not report a device model, but
                // keep the column so we can populate it from a future header
                // without another migration.
                'device' => $os === 'Android' || $os === 'iOS' ? 'Mobile' : 'Desktop',
                'browser' => 'VP92',
            ];
        }

        // ---- Generic browser fallbacks (admin / web dashboard) ----
        $browser = 'Unknown';
        if (str_contains($lower, 'edg/')) {
            $browser = 'Edge';
        } elseif (str_contains($lower, 'opr/') || str_contains($lower, 'opera')) {
            $browser = 'Opera';
        } elseif (str_contains($lower, 'chrome/') && !str_contains($lower, 'chromium')) {
            $browser = 'Chrome';
        } elseif (str_contains($lower, 'firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($lower, 'safari/') && !str_contains($lower, 'chrome')) {
            $browser = 'Safari';
        } elseif (str_contains($lower, 'curl/')) {
            $browser = 'curl';
        } elseif (str_contains($lower, 'postman')) {
            $browser = 'Postman';
        }

        $os = 'Unknown';
        $device = 'Desktop';
        if (str_contains($lower, 'windows')) {
            $os = 'Windows';
        } elseif (str_contains($lower, 'mac os x') || str_contains($lower, 'macintosh')) {
            $os = 'macOS';
        } elseif (str_contains($lower, 'android')) {
            $os = 'Android';
            $device = 'Mobile';
        } elseif (str_contains($lower, 'iphone') || str_contains($lower, 'ipad')) {
            $os = 'iOS';
            $device = str_contains($lower, 'ipad') ? 'Tablet' : 'Mobile';
        } elseif (str_contains($lower, 'linux')) {
            $os = 'Linux';
        }

        return ['os' => $os, 'device' => $device, 'browser' => $browser];
    }
}
