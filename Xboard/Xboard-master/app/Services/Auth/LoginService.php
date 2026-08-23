<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Utils\UserAgentParser;
use App\Services\Plugin\HookManager;
use App\Utils\CacheKey;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LoginService
{
    /**
     * 处理用户登录
     *
     * @param string $email 用户邮箱
     * @param string $password 用户密码
     * @param Request|null $request 用于同设备识别（X-Device-Id 头 / IP+UA 指纹）
     * @return array [成功状态, 用户对象或错误信息]
     */
    public function login(string $email, string $password, ?Request $request = null): array
    {
        // 检查密码错误限制
        if ((int) admin_setting('password_limit_enable', true)) {
            $passwordErrorCount = (int) Cache::get(CacheKey::get('PASSWORD_ERROR_LIMIT', $email), 0);
            if ($passwordErrorCount >= (int) admin_setting('password_limit_count', 5)) {
                return [
                    false,
                    [
                        429,
                        __('There are too many password errors, please try again after :minute minutes.', [
                            'minute' => admin_setting('password_limit_expire', 60)
                        ])
                    ]
                ];
            }
        }

        // 查找用户
        $user = User::byEmail($email)->first();
        if (!$user) {
            return [false, [400, __('Incorrect email or password')]];
        }

        // 验证密码
        if (
            !Helper::multiPasswordVerify(
                $user->password_algo,
                $user->password_salt,
                $password,
                $user->password
            )
        ) {
            // 增加密码错误计数
            if ((int) admin_setting('password_limit_enable', true)) {
                $passwordErrorCount = (int) Cache::get(CacheKey::get('PASSWORD_ERROR_LIMIT', $email), 0);
                Cache::put(
                    CacheKey::get('PASSWORD_ERROR_LIMIT', $email),
                    (int) $passwordErrorCount + 1,
                    60 * (int) admin_setting('password_limit_expire', 60)
                );
            }
            return [false, [400, __('Incorrect email or password')]];
        }

        // 检查账户状态
        if ($user->banned) {
            return [false, [400, __('Your account has been suspended')]];
        }

        // B1: 同设备去重 —— 同一台设备的旧会话先撤销，避免反复登录产生多条会话占满名额。
        // 新版客户端带 X-Device-Id 头（持久随机 ID，存在 token name 列），识别最可靠；
        // 旧版客户端没有该头，退化为 IP + UA 指纹匹配（同 IP 且同系统/客户端才视为同一设备）。
        if ($request) {
            $deviceId = trim((string) $request->header('X-Device-Id', ''));
            if ($deviceId !== '') {
                $user->tokens()->where('name', $deviceId)->delete();
            } else {
                $ua = $request->userAgent() ?? '';
                if ($ua !== '') {
                    $parsed = UserAgentParser::parse($ua);
                    $user->tokens()
                        ->where('ip', (string) $request->ip())
                        ->where('os', $parsed['os'])
                        ->where('browser', $parsed['browser'])
                        ->delete();
                }
            }
        }

        // B2: 设备数量达到上限时直接拒绝登录，让用户自己管理会话
        // （不再自动踢最旧设备 —— 被踢设备的 token 全部失效，体验差且容易被误认为盗号）
        if ($user->device_limit > 0) {
            $activeCount = $user->tokens()->count();
            if ($activeCount >= $user->device_limit) {
                return [
                    false,
                    [
                        403,
                        '登录设备数已达上限，请在已登录设备的「账号 - 活跃会话」中移除不需要的设备后重试'
                    ]
                ];
            }
        }

        // 更新最后登录时间
        $user->last_login_at = time();
        $user->save();

        HookManager::call('user.login.after', $user);
        return [true, $user];
    }

    /**
     * 处理密码重置
     *
     * @param string $email 用户邮箱
     * @param string $emailCode 邮箱验证码
     * @param string $password 新密码
     * @return array [成功状态, 结果或错误信息]
     */
    public function resetPassword(string $email, string $emailCode, string $password): array
    {
        // 检查重置请求限制
        $forgetRequestLimitKey = CacheKey::get('FORGET_REQUEST_LIMIT', $email);
        $forgetRequestLimit = (int) Cache::get($forgetRequestLimitKey);
        if ($forgetRequestLimit >= 3) {
            return [false, [429, __('Reset failed, Please try again later')]];
        }

        // 验证邮箱验证码
        $cachedEmailCode = Cache::get(CacheKey::get('EMAIL_VERIFY_CODE', $email));
        if ($cachedEmailCode === null || !hash_equals((string) $cachedEmailCode, $emailCode)) {
            Cache::put($forgetRequestLimitKey, $forgetRequestLimit ? $forgetRequestLimit + 1 : 1, 300);
            return [false, [400, __('Incorrect email verification code')]];
        }

        // 查找用户
        $user = User::byEmail($email)->first();
        if (!$user) {
            return [false, [400, __('This email is not registered in the system')]];
        }

        // 更新密码
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->password_algo = NULL;
        $user->password_salt = NULL;

        if (!$user->save()) {
            return [false, [500, __('Reset failed')]];
        }

        HookManager::call('user.password.reset.after', $user);

        // 清除邮箱验证码
        Cache::forget(CacheKey::get('EMAIL_VERIFY_CODE', $email));

        return [true, true];
    }


    /**
     * 生成临时登录令牌和快速登录URL
     *
     * @param User $user 用户对象
     * @param string $redirect 重定向路径
     * @return string|null 快速登录URL
     */
    public function generateQuickLoginUrl(User $user, ?string $redirect = null): ?string
    {
        if (!$user || !$user->exists) {
            return null;
        }

        $code = Helper::guid();
        $key = CacheKey::get('TEMP_TOKEN', $code);

        Cache::put($key, $user->id, 60);

        $redirect = $redirect ?: 'dashboard';
        $loginRedirect = '/#/login?verify=' . $code . '&redirect=' . rawurlencode($redirect);

        if (admin_setting('app_url')) {
            $url = admin_setting('app_url') . $loginRedirect;
        } else {
            $url = url($loginRedirect);
        }

        return $url;
    }
}