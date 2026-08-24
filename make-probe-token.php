<?php
// 生成一个真实可用的 Sanctum token 并直接调用控制器验证 HTTP 链路
$user = \App\Models\User::where('email', 'kangtazezege@gmail.com')->first();
$tokenResult = $user->createToken('debug-probe', ['*'], now()->addHour());
$parts = explode('|', $tokenResult->plainTextToken);
$bearer = $parts[1] ?? $parts[0];
echo "PROBE_BEARER: {$bearer}\n";
