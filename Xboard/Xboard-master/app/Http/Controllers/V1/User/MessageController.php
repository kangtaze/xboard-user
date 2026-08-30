<?php

// 用途：用户端消息中心 API（fetch 分页拉取 + markRead 标记已读）
// 调用方：APP 端 lib/xboard/api_service.dart getMessages / markMessageRead
// 被调用方：MessageService
// 关联记录：Task #17 PR-B1

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Services\MessageService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    protected MessageService $messageService;

    public function __construct(MessageService $messageService)
    {
        $this->messageService = $messageService;
    }

    /**
     * GET /api/v1/user/message/fetch
     * Query: ?type=notice|ticket|order|refund (可选) &page=1
     */
    public function fetch(Request $request)
    {
        $request->validate([
            'type' => 'nullable|string|in:notice,ticket,order,refund',
            'page' => 'nullable|integer|min:1',
        ]);

        $result = $this->messageService->getMessages(
            $request->user()->id,
            $request->input('type'),
            (int) $request->input('page', 1)
        );

        return $this->success([
            'total' => $result['total'],
            'list' => $result['list'],
        ]);
    }

    /**
     * POST /api/v1/user/message/markRead
     * Body: { id: int }
     */
    public function markRead(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
        ]);

        $this->messageService->markRead(
            $request->user()->id,
            (int) $request->input('id')
        );

        return $this->success(true);
    }
}