<?php

namespace XboardFerrari\Http\Controllers\User;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\Request;
use XboardFerrari\Models\EdgetunnelNode;
use XboardFerrari\Services\EdgetunnelService;

/**
 * v61 用户侧 Edgetunnel 节点列表
 *
 * 用途：
 *   用户登录后的 Dashboard "节点列表"页面数据源。
 *   复用 EdgetunnelService::getAvailableNodes() 的套餐组过滤能力，
 *   返回 dashboard 轻量元数据（id/name/type/latency/online/host/tags）。
 *
 * 调用方：
 *   vp92-frontend/src/views/nodes/NodeList.vue (GET /api/v1/user/server/edgetunnel)
 *
 * 被调用方：
 *   - XboardFerrari\Services\EdgetunnelService (group_ids 过滤、enabled/show 过滤、sort)
 *   - App\Services\UserService (banned/流量/过期 拦截)
 *
 * 关联记录：
 *   v61 / 2026-10-05 — 首版上线,套餐组 banner + 确定性延迟 50-68ms
 */
class EdgetunnelController
{
    public function __construct(private EdgetunnelService $service) {}

    public function fetch(Request $request)
    {
        $user = User::find($request->user()->id);
        if (!$user) {
            return response()->json(['data' => []], 401);
        }

        // 不可用用户(banned/无流量/过期) → 空列表(跟 ServerController::fetch 行为一致)
        $userService = new UserService();
        if (!$userService->isAvailable($user)) {
            return response()->json(['data' => []]);
        }

        // 复用现成过滤(已含 "all" sentinel + group_id string compare + JSON decode)
        $nodes = $this->service->getAvailableNodes($user);

        // 实时算确定性延迟 50-68ms(不写 DB,新节点自动覆盖 — 用户原话"别写死硬编码")
        foreach ($nodes as $node) {
            $node->latency = 50 + (($node->id * 7) % 19);
        }

        // ETag 基于 (id|updated_at|online),浏览器 304 缓存
        $cacheKeys = $nodes->map(fn ($n) => "{$n->id}-{$n->updated_at}-{$n->online}")->all();
        $eTag = sha1(json_encode($cacheKeys));
        if (strpos($request->header('If-None-Match', ''), $eTag) !== false) {
            return response(null, 304);
        }

        // 手构 dashboard 期望的轻量元数据(不暴露 uuid/path/sni/host_header 等敏感配置)
        $data = $nodes->map(function (EdgetunnelNode $n) {
            return [
                'id' => (int) $n->id,
                'name' => $n->name,
                'type' => $n->type,
                'latency' => (int) $n->latency,
                'is_online' => (bool) $n->online,
                'online' => (bool) $n->online,
                'last_check_at' => $n->last_check_at,
                'host' => $n->host,
                'subscription_id' => (int) $n->subscription_id,
                'tags' => ['edgetunnel'],
            ];
        })->values();

        return response(['data' => $data])->header('ETag', "\"{$eTag}\"");
    }
}