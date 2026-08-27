<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * GeoIpService — IP → 国家/省/市/运营商 反查
 *
 * 用途:
 * - 方案 B1 admin 用户详情: 显示用户 IP 的真实地理位置 + 运营商
 * - 钩子点:
 *   - App\Services\AuthService::generateAuthData (登录时写 country 到 Sanctum token)
 *   - App\Http\Middleware\LogSubscriptionAccess (拉订阅时反查)
 *   - App\Http\Controllers\V2\Admin\UserController::transformUserData (列表展示)
 *
 * 底层库:
 * - zoujingli/ip2region (composer.json 已 require)
 * - 数据文件: /www/vendor/zoujingli/ip2region/ip2region.xdb (10.6 MB)
 * - 精度: 中国大陆全中文 (国家|省|市|ISP), 海外全英文
 * - 性能: 全内存查询 ~10µs
 *
 * 缓存策略:
 * - Laravel Cache 24h TTL (ip2region 数据每周二/四更新一次, 24h 平衡性能与新鲜度)
 * - 缓存 key: "geoip:{ip_int}"
 *
 * 失败降级:
 * - IPv6 / 无效 IP / 库异常 → 返回全空数组, 不抛异常
 * - 海外 IP → 部分字段为空 (ip2region 海外精度有限)
 */
class GeoIpService
{
    /**
     * IP 反查, 返回结构化地理信息.
     *
     * @param string $ip IPv4 或 IPv6 字符串
     * @return array{country: string, province: string, city: string, isp: string}
     */
    public function lookup(string $ip): array
    {
        $empty = ['country' => '', 'province' => '', 'city' => '', 'isp' => ''];

        // 仅支持 IPv4 (现网 ip2region.xdb 是 IPv4 only)
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $empty;
        }

        // ip2long 处理异常 IP (虽然 FILTER_VALIDATE_IP 已过滤)
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return $empty;
        }

        $cacheKey = 'geoip:' . $ipLong;

        return Cache::remember($cacheKey, 86400, function () use ($ip, $empty) {
            try {
                // Ip2Region 是全局类 (无 namespace, 在 /www/vendor/zoujingli/ip2region/Ip2Region.php)
                // 必须用 `\Ip2Region` 前导反斜杠强制全局命名空间 (本类在 App\Services 命名空间下)
                $region = (new \Ip2Region())->memorySearch($ip);
                // memorySearch 返回 array: ['city_id' => 0, 'region' => '国家|0|省|市|ISP' 或 '0|0|0|0|0']
                // v15.18: 修正 off-by-one — 实际 5 段, parts[1] 是区域标记位(总为0), 应读 parts[2/3/4]
                // 实测: 114.114.114.114 → "中国|0|江苏省|南京市|0", 8.8.8.8 → "美国|0|0|0|Level3"
                $parts = explode('|', $region['region'] ?? '');

                // ip2region 用 "0" 表示无数据, 标准化为空
                $clean = static function ($v): string {
                    $v = trim((string) $v);
                    return ($v === '' || $v === '0') ? '' : $v;
                };

                return [
                    'country' => $clean($parts[0] ?? ''),     // 国家
                    'province' => $clean($parts[2] ?? ''),    // 省 (跳过 parts[1] = 0)
                    'city' => $clean($parts[3] ?? ''),        // 市
                    'isp' => $clean($parts[4] ?? ''),         // ISP (电信/移动/联通/Level3)
                ];
            } catch (\Throwable $e) {
                Log::warning('GeoIpService lookup failed', [
                    'ip' => $ip,
                    'err' => $e->getMessage(),
                ]);
                return $empty;
            }
        });
    }

    /**
     * 便捷方法: 从 int IP (v2_user.last_login_ip / register_ip) 反查.
     * 自动 long2ip.
     */
    public function lookupFromInt(int $ipInt): array
    {
        if ($ipInt === 0) {
            return ['country' => '', 'province' => '', 'city' => '', 'isp' => ''];
        }
        return $this->lookup(long2ip($ipInt));
    }
}
