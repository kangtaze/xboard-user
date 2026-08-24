<?php

namespace App\Services;

use App\Models\User;
use App\Utils\UserAgentParser;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    private User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function generateAuthData(?Request $request = null): array
    {
        // Prefer a stable client-generated device id (X-Device-Id header) as the
        // token name — LoginService dedups same-device sessions by matching it.
        // Requests WITHOUT the header are web-dashboard logins: mark their token
        // name with a "web:" prefix so the device-limit count in LoginService can
        // skip them — users must always be able to open the web panel to copy
        // subscription links in whatever format their client needs.
        $deviceId = mb_substr(trim((string) $request?->header('X-Device-Id', '')), 0, 64);
        $isWebSession = $deviceId === '';

        // Create a new Sanctum token with device info
        $token = $this->user->createToken(
            $isWebSession ? 'web:' . Str::random(20) : $deviceId, // token name (device identifier)
            ['*'], // abilities
            now()->addYear() // expiration
        );

        // Store device info when a request is available (i.e. login flow).
        if ($request) {
            $accessToken = $token->accessToken;
            $ua = $request->userAgent() ?? '';
            $parsed = UserAgentParser::parse($ua);

            // #53: prefer the real device model reported by the client
            // (X-Device-Model header, e.g. "Xiaomi 23127PN0CC") over the
            // coarse UA-derived type ("Mobile"/"Desktop").
            $reportedModel = mb_substr(trim((string) $request->header('X-Device-Model', '')), 0, 64);

            $accessToken->ip = $request->ip();
            $accessToken->os = $parsed['os'];
            $accessToken->device = $reportedModel !== '' ? $reportedModel : ($isWebSession ? '网页登录' : $parsed['device']);
            $accessToken->browser = $parsed['browser'];
            // country is reserved for a future GeoIP lookup.
            $accessToken->country = '';
            $accessToken->save();
        }

        // Format token: remove ID prefix and add Bearer
        $tokenParts = explode('|', $token->plainTextToken);
        $formattedToken = 'Bearer ' . ($tokenParts[1] ?? $tokenParts[0]);

        return [
            'token' => $this->user->token,
            'auth_data' => $formattedToken,
            'is_admin' => $this->user->is_admin,
        ];
    }

    public function getSessions(?int $currentTokenId = null): array
    {
        return $this->user->tokens()
            // Sort by created_at DESC so the freshest login is at the top.
            // (Tokens start with last_used_at = null, so a last_used_at DESC
            // sort would push new tokens to the bottom where the user can't
            // see them — confusing for the device-info feature.)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($t) use ($currentTokenId) {
                return [
                    'id' => (int) $t->id,
                    'ip' => $t->ip ?? '',
                    'os' => $t->os ?? '',
                    'device' => $t->device ?? '',
                    'browser' => $t->browser ?? '',
                    'country' => $t->country ?? '',
                    'last_active_at' => $t->last_used_at ? $t->last_used_at->timestamp : 0,
                    'created_at' => $t->created_at->timestamp,
                    // is_current lets the Flutter app highlight the row the user
                    // is logged in from (matches the access token that issued
                    // the current /user/getActiveSession request). Strict int
                    // compare — Sanctum token ids are unsigned bigint.
                    'is_current' => $currentTokenId !== null && (int) $t->id === $currentTokenId,
                ];
            })
            ->values()
            ->toArray();
    }

    public function removeSession(string $sessionId): bool
    {
        $deleted = $this->user->tokens()->where('id', $sessionId)->delete();
        return $deleted > 0;
    }

    public function removeAllSessions(): bool
    {
        $this->user->tokens()->delete();
        return true;
    }

    public static function findUserByBearerToken(string $bearerToken): ?User
    {
        $token = str_replace('Bearer ', '', $bearerToken);

        $accessToken = PersonalAccessToken::findToken($token);

        $tokenable = $accessToken?->tokenable;

        return $tokenable instanceof User ? $tokenable : null;
    }

    /**
     * 解密认证数据
     *
     * @param string $authorization
     * @return array|null 用户数据或null
     */
    public static function decryptAuthData(string $authorization): ?array
    {
        $user = self::findUserByBearerToken($authorization);

        if (!$user) {
            return null;
        }

        return [
            'id' => $user->id,
            'email' => $user->email,
            'is_admin' => (bool)$user->is_admin,
            'is_staff' => (bool)$user->is_staff
        ];
    }
}
