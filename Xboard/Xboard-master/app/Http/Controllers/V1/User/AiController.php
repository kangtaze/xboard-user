<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Services\AiChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * AI 客服控制器
 *
 * 处理 AI 聊天请求，需要用户登录态
 */
class AiController extends Controller
{
    private AiChatService $aiChatService;

    public function __construct(AiChatService $aiChatService)
    {
        $this->aiChatService = $aiChatService;
    }

    /**
     * AI 聊天对话
     *
     * POST /api/v1/user/ai/chat
     *
     * @param Request $request
     * @return JsonResponse
     *
     * Request Body:
     *   message: string (必填) 用户消息，最大 500 字符
     *   history: array (可选) 对话历史 [{role: "user"|"assistant", content: string}]
     */
    public function chat(Request $request): JsonResponse
    {
        $message = $request->input('message');
        $history = $request->input('history', []);

        // 验证消息
        if (empty($message) || !is_string($message)) {
            return $this->fail([400, __('消息不能为空')]);
        }

        // 长度限制
        if (mb_strlen($message) > 500) {
            return $this->fail([400, __('消息长度不能超过500字')]);
        }

        // 验证历史格式
        if (!is_array($history)) {
            return $this->fail([400, __('无效的对话历史格式')]);
        }

        foreach ($history as $item) {
            if (!isset($item['role']) || !isset($item['content'])) {
                return $this->fail([400, __('无效的对话历史格式')]);
            }
            if (!in_array($item['role'], ['user', 'assistant'])) {
                return $this->fail([400, __('无效的对话角色')]);
            }
        }

        // 限制历史条数
        if (count($history) > 20) {
            $history = array_slice($history, -20);
        }

        $userId = $request->user()->id;

        Log::info('AI chat request', [
            'user_id' => $userId,
            'message_length' => mb_strlen($message),
            'history_count' => count($history)
        ]);

        $result = $this->aiChatService->chat($userId, $message, $history);

        return $this->success($result);
    }
}