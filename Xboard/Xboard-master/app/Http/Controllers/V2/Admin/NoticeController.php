<?php

namespace App\Http\Controllers\V2\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NoticeSave;
use App\Models\Notice;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NoticeController extends Controller
{
    public function fetch(Request $request)
    {
        return $this->success(
            Notice::orderBy('sort', 'ASC')
                ->orderBy('id', 'DESC')
                ->get()
        );
    }

    public function save(NoticeSave $request)
    {
        $data = $request->only([
            'title',
            'content',
            'img_url',
            'tags',
            'show',
            'popup'
        ]);
        if (!$request->input('id')) {
            if (!Notice::create($data)) {
                return $this->fail([500, '保存失败']);
            }
            // 公告全员推送（Task #17 PR-B1，仅 show=true 时；异步 Job 不阻塞 admin save）
            if (!empty($data['show'])) {
                $noticeTags = [];
                if (!empty($data['tags'])) {
                    $noticeTags = is_array($data['tags']) ? $data['tags'] : [$data['tags']];
                }
                if (!empty($data['popup'])) {
                    $noticeTags[] = \App\Models\UserMessage::TAG_POPUP;
                }
                try {
                    (new MessageService())->pushNoticeToAll(
                        $data['title'],
                        $data['content'],
                        null,
                        $noticeTags ?: null
                    );
                } catch (\Throwable $e) {
                    \Log::error('[Message] notice push failed: ' . $e->getMessage());
                }
            }
        } else {
            try {
                Notice::find($request->input('id'))->update($data);
            } catch (\Exception $e) {
                return $this->fail([500, '保存失败']);
            }
        }
        return $this->success(true);
    }



    public function show(Request $request)
    {
        if (empty($request->input('id'))) {
            return $this->fail([500, '公告ID不能为空']);
        }
        $notice = Notice::find($request->input('id'));
        if (!$notice) {
            return $this->fail([400202, '公告不存在']);
        }
        $notice->show = $notice->show ? 0 : 1;
        if (!$notice->save()) {
            return $this->fail([500, '保存失败']);
        }

        return $this->success(true);
    }

    public function drop(Request $request)
    {
        if (empty($request->input('id'))) {
            return $this->fail([422, '公告ID不能为空']);
        }
        $notice = Notice::find($request->input('id'));
        if (!$notice) {
            return $this->fail([400202, '公告不存在']);
        }
        if (!$notice->delete()) {
            return $this->fail([500, '删除失败']);
        }
        return $this->success(true);
    }

    public function sort(Request $request)
    {
        $params = $request->validate([
            'ids' => 'required|array'
        ]);

        try {
            DB::beginTransaction();
            foreach ($params['ids'] as $k => $v) {
                $notice = Notice::findOrFail($v);
                $notice->update(['sort' => $k + 1]);
            }
            DB::commit();
            return $this->success(true);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error($e);
            return $this->fail([500, '排序保存失败']);
        }
    }
}
