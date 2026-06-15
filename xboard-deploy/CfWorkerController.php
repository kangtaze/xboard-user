<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\CfWorker;
use App\Services\CfDeployService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CfWorkerController extends Controller
{
    private CfDeployService $deployService;

    public function __construct(CfDeployService $deployService)
    {
        $this->deployService = $deployService;
    }

    public function list(Request $request)
    {
        $workers = CfWorker::orderBy('id', 'desc')->get()->map(function ($w) {
            return [
                'id' => $w->id,
                'name' => $w->name,
                'cf_account_id' => $w->cf_account_id,
                'cf_zone_id' => $w->cf_zone_id,
                'domain' => $w->domain,
                'worker_name' => $w->worker_name,
                'xboard_node_id' => $w->xboard_node_id,
                'status' => $w->status,
                'status_text' => $w->status_text,
                'error_msg' => $w->error_msg,
                'deployed_at' => $w->deployed_at?->toDateTimeString(),
                'created_at' => $w->created_at?->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $workers
        ]);
    }

    public function save(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'cf_account_id' => 'required|string|max:100',
            'cf_api_token' => 'required|string|max:200',
            'cf_zone_id' => 'required|string|max:100',
            'domain' => 'required|string|max:200',
        ]);

        $params = $request->only(['name', 'cf_account_id', 'cf_api_token', 'cf_zone_id', 'domain']);

        if ($request->input('id')) {
            $worker = CfWorker::findOrFail($request->input('id'));
            // Don't overwrite token if not provided on edit
            if ($params['cf_api_token'] === '******') {
                unset($params['cf_api_token']);
            }
            $worker->update($params);
        } else {
            $params['worker_name'] = 'edgetunnel-' . Str::random(8);
            $params['status'] = CfWorker::STATUS_PENDING;
            $worker = CfWorker::create($params);
        }

        return response()->json([
            'data' => true
        ]);
    }

    public function deploy(Request $request)
    {
        $worker = CfWorker::findOrFail($request->input('id'));

        if ($worker->status === CfWorker::STATUS_DEPLOYED) {
            return response()->json(['message' => '该账号已部署，请使用重新部署'], 400);
        }

        $success = $this->deployService->deploy($worker);

        return response()->json([
            'data' => $success,
            'message' => $success ? '部署成功' : '部署失败: ' . $worker->fresh()->error_msg
        ]);
    }

    public function redeploy(Request $request)
    {
        $worker = CfWorker::findOrFail($request->input('id'));

        if (!$worker->kv_namespace_id) {
            return response()->json(['message' => '请先执行完整部署'], 400);
        }

        $success = $this->deployService->redeploy($worker);

        return response()->json([
            'data' => $success,
            'message' => $success ? '重新部署成功' : '重新部署失败: ' . $worker->fresh()->error_msg
        ]);
    }

    public function delete(Request $request)
    {
        $worker = CfWorker::findOrFail($request->input('id'));
        $cleanCf = $request->input('clean_cf', true);

        $this->deployService->destroy($worker, $cleanCf);

        return response()->json([
            'data' => true
        ]);
    }

    public function uploadTemplate(Request $request)
    {
        $request->validate([
            'file' => 'required|file'
        ]);

        $content = file_get_contents($request->file('file')->getRealPath());
        $path = storage_path('app/worker-template.js');
        file_put_contents($path, $content);

        return response()->json([
            'data' => true,
            'message' => 'Worker模板已更新 (' . strlen($content) . ' bytes)'
        ]);
    }
}
