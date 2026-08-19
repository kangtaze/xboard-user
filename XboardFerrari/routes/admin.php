<?php

use Illuminate\Support\Facades\Route;
use XboardFerrari\Http\Controllers\Admin\EdgetunnelController;
use App\Http\Controllers\V2\Admin\CfWorkerController;

Route::prefix('edgetunnel')->group(function () {
    Route::get('/subscriptions', [EdgetunnelController::class, 'getSubscriptions']);
    Route::post('/subscription/save', [EdgetunnelController::class, 'saveSubscription']);
    Route::post('/subscription/drop', [EdgetunnelController::class, 'dropSubscription']);
    Route::post('/subscription/sync', [EdgetunnelController::class, 'syncSubscription']);
    Route::post('/subscription/preview', [EdgetunnelController::class, 'previewInjection']);

    Route::get('/nodes', [EdgetunnelController::class, 'getNodes']);
    Route::post('/node/update', [EdgetunnelController::class, 'updateNode']);
    Route::post('/node/test-latency', [EdgetunnelController::class, 'testLatency']);
    Route::post('/node/batch-test', [EdgetunnelController::class, 'batchTestLatency']);

    Route::get('/servers', [EdgetunnelController::class, 'getServers']);
    Route::post('/server/save', [EdgetunnelController::class, 'saveServer']);
    Route::post('/server/update', [EdgetunnelController::class, 'updateServer']);
    Route::post('/server/drop', [EdgetunnelController::class, 'dropServer']);

    Route::get('/users', [EdgetunnelController::class, 'getUsers']);
    Route::post('/users/batch-group', [EdgetunnelController::class, 'batchUpdateUserGroup']);
});

Route::prefix('cf-worker')->group(function () {
    Route::get('/list', [CfWorkerController::class, 'list']);
    Route::post('/save', [CfWorkerController::class, 'save']);
    Route::post('/deploy', [CfWorkerController::class, 'deploy']);
    Route::post('/redeploy', [CfWorkerController::class, 'redeploy']);
    Route::post('/delete', [CfWorkerController::class, 'delete']);
    Route::post('/sync-users', [CfWorkerController::class, 'syncUsers']);
    Route::post('/sync-all', [CfWorkerController::class, 'syncAll']);
    Route::post('/upload-template', [CfWorkerController::class, 'uploadTemplate']);
});
