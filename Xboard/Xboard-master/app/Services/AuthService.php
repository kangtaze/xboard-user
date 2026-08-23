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
        // Create a new Sanctum token with device info
        $token = $this->user->createToken(
            Str::random(20), // token name (device identifier)
            ['*'], // abilities
            now()->addYear() // expiration
        );

        // Store device info when a request is available (i.e. login flow).
        if ($request) {
            $accessToken = $token->accessToken;
            $ua = $request->userAgent() ?? '';
            $parsed = UserAgentParser::parse($ua);

            $accessToken->ip = $request->ip();
            $accessToken->os = $parsed['os'];
            $accessToken->device = $parsed['device'];
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

    public function getSessions(): array
    {
        return $this->user->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($t) => [
                'id' => (int) $t->id,
                'ip' => $t->ip ?? '',
                'os' => $t->os ?? '',
                'device' => $t->device ?? '',
                'browser' => $t->browser ?? '',
                'country' => $t->country ?? '',
                'last_active_at' => $t->last_used_at ? $t->last_used_at->timestamp : 0,
                'created_at' => $t->created_at->timestamp,
            ])
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
