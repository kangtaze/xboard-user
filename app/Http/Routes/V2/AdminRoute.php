<?php
namespace App\Http\Routes\V2;

use App\Http\Controllers\V2\Admin\ConfigController;
use App\Http\Controllers\V2\Admin\MailTemplateController;
use App\Http\Controllers\V2\Admin\PlanController;
use App\Http\Controllers\V2\Admin\Server\GroupController;
use App\Http\Controllers\V2\Admin\Server\RouteController;
use App\Http\Controllers\V2\Admin\Server\ManageController;
use App\Http\Controllers\V2\Admin\Server\MachineController;
use App\Http\Controllers\V2\Admin\OrderController;
use App\Http\Controllers\V2\Admin\UserController;
use App\Http\Controllers\V2\Admin\StatController;
use App\Http\Controllers\V2\Admin\NoticeController;
use App\Http\Controllers\V2\Admin\TicketController;
use App\Http\Controllers\V2\Admin\CouponController;
use App\Http\Controllers\V2\Admin\GiftCardController;
use App\Http\Controllers\V2\Admin\KnowledgeController;
use App\Http\Controllers\V2\Admin\PaymentController;
use App\Http\Controllers\V2\Admin\SystemController;
use App\Http\Controllers\V2\Admin\ThemeController;
use App\Http\Controllers\V2\Admin\CfWorkerController;
use App\Http\Controllers\V2\Admin\TrafficResetController;
use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AdminRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key')))),
            'middleware' => ['admin', 'log'],
        ], function ($router) {
            // Config
            $router->group([
                'prefix' => 'config'
            ], function ($router) {
                $router->get('/fetch', [ConfigController::class, 'fetch']);
                $router->post('/save', [ConfigController::class, 'save']);
                $router->get('/getEmailTemplate', [ConfigController::class, 'getEmailTemplate']);
                $router->get('/getThemeTemplate', [ConfigController::class, 'getThemeTemplate']);
                $router->post('/setTelegramWebhook', [ConfigController::class, 'setTelegramWebhook']);
                $router->post('/testSendMail', [ConfigController::class, 'testSendMail']);
            });

            // Mail Templates
            $router->group([
                'prefix' => 'mail/template'
            ], function ($router) {
                $router->get('/list', [MailTemplateController::class, 'list']);
                $router->get('/get', [MailTemplateController::class, 'get']);
                $router->post('/save', [MailTemplateController::class, 'save']);
                $router->post('/reset', [MailTemplateController::class, 'reset']);
                $router->post('/test', [MailTemplateController::class, 'test']);
            });

            // Plan
            $router->group([
                'prefix' => 'plan'
            ], function ($router) {
                $router->get('/fetch', [PlanController::class, 'fetch']);
                $router->post('/save', [PlanController::class, 'save']);
                $router->post('/drop', [PlanController::class, 'drop']);
                $router->post('/update', [PlanController::class, 'update']);
                $router->post('/sort', [PlanController::class, 'sort']);
            });

            // Server
            $router->group([
                'prefix' => 'server/group'
            ], function ($router) {
                $router->get('/fetch', [GroupController::class, 'fetch']);
                $router->post('/save', [GroupController::class, 'save']);
                $router->post('/drop', [GroupController::class, 'drop']);
            });
            $router->group([
                'prefix' => 'server/route'
            ], function ($router) {
                $router->get('/fetch', [RouteController::class, 'fetch']);
                $router->post('/save', [RouteController::class, 'save']);
                $router->post('/drop', [RouteController::class, 'drop']);
            });
            // 节点管理接口
            $router->group([
                'prefix' => 'server/manage'
            ], function ($router) {
                $router->get('/getNodes', [ManageController::class, 'getNodes']);
                $router->post('/update', [ManageController::class, 'update']);
                $router->post('/save', [ManageController::class, 'save']);
                $router->post('/drop', [ManageController::class, 'drop']);
                $router->post('/copy', [ManageController::class, 'copy']);
                $router->post('/sort', [ManageController::class, 'sort']);
                $router->post('/batchDelete', [ManageController::class, 'batchDelete']);
                $router->post('/batchUpdate', [ManageController::class, 'batchUpdate']);
                $router->post('/resetTraffic', [ManageController::class, 'resetTraffic']);
                $router->post('/batchResetTraffic', [ManageController::class, 'batchResetTraffic']);
                $router->get('/generateEchKey', [ManageController::class, 'generateEchKey']);
            });

            // 机器管理接口
            $router->group([
                'prefix' => 'server/machine'
            ], function ($router) {
                $router->get('/fetch', [MachineController::class, 'fetch']);
                $router->post('/save', [MachineController::class, 'save']);
                $router->post('/drop', [MachineController::class, 'drop']);
                $router->post('/resetToken', [MachineController::class, 'resetToken']);
                $router->get('/getToken', [MachineController::class, 'getToken']);
                $router->get('/installCommand', [MachineController::class, 'installCommand']);
                $router->get('/nodes', [MachineController::class, 'nodes']);
                $router->get('/history', [MachineController::class, 'history']);
            });

            // Order
            $router->group([
                'prefix' => 'order'
            ], function ($router) {
                $router->any('/fetch', [OrderController::class, 'fetch']);
                $router->post('/update', [OrderController::class, 'update']);
                $router->post('/assign', [OrderController::class, 'assign']);
                $router->post('/paid', [OrderController::class, 'paid']);
                $router->post('/cancel', [OrderController::class, 'cancel']);
                $router->post('/destroy', [OrderController::class, 'destroy']);
                $router->post('/detail', [OrderController::class, 'detail']);
            });

            // User
            $router->group([
                'prefix' => 'user'
            ], function ($router) {
                $router->any('/fetch', [UserController::class, 'fetch']);
                $router->post('/update', [UserController::class, 'update']);
                $router->get('/getUserInfoById', [UserController::class, 'getUserInfoById']);
                $router->post('/generate', [UserController::class, 'generate']);
                $router->post('/dumpCSV', [UserController::class, 'dumpCSV']);
                $router->post('/sendMail', [UserController::class, 'sendMail']);
                $router->post('/ban', [UserController::class, 'ban']);
                $router->post('/resetSecret', [UserController::class, 'resetSecret']);
                $router->post('/setInviteUser', [UserController::class, 'setInviteUser']);
                $router->post('/destroy', [UserController::class, 'destroy']);
            });

            // Stat
            $router->group([
                'prefix' => 'stat'
            ], function ($router) {
                $router->get('/getOverride', [StatController::class, 'getOverride']);
                $router->get('/getStats', [StatController::class, 'getStats']);
                $router->get('/getServerLastRank', [StatController::class, 'getServerLastRank']);
                $router->get('/getServerYesterdayRank', [StatController::class, 'getServerYesterdayRank']);
                $router->get('/getOrder', [StatController::class, 'getOrder']);
                $router->any('/getStatUser', [StatController::class, 'getStatUser']);
                $router->get('/getRanking', [StatController::class, 'getRanking']);
                $router->get('/getStatRecord', [StatController::class, 'getStatRecord']);
                $router->get('/getTrafficRank', [StatController::class, 'getTrafficRank']);
            });

            // Notice
            $router->group([
                'prefix' => 'notice'
            ], function ($router) {
                $router->get('/fetch', [NoticeController::class, 'fetch']);
                $router->post('/save', [NoticeController::class, 'save']);
                $router->post('/update', [NoticeController::class, 'update']);
                $router->post('/drop', [NoticeController::class, 'drop']);
                $router->post('/show', [NoticeController::class, 'show']);
                $router->post('/sort', [NoticeController::class, 'sort']);
            });

            // Ticket
            $router->group([
                'prefix' => 'ticket'
            ], function ($router) {
                $router->any('/fetch', [TicketController::class, 'fetch']);
                $router->post('/reply', [TicketController::class, 'reply']);
                $router->post('/close', [TicketController::class, 'close']);
            });

            // Coupon
            $router->group([
                'prefix' => 'coupon'
            ], function ($router) {
                $router->any('/fetch', [CouponController::class, 'fetch']);
                $router->post('/generate', [CouponController::class, 'generate']);
                $router->post('/drop', [CouponController::class, 'drop']);
                $router->post('/show', [CouponController::class, 'show']);
                $router->post('/update', [CouponController::class, 'update']);
            });

            // Gift Card
            $router->group([
                'prefix' => 'gift-card'
            ], function ($router) {
                // Template management
                $router->any('/templates', [GiftCardController::class, 'templates']);
                $router->post('/create-template', [GiftCardController::class, 'createTemplate']);
                $router->post('/update-template', [GiftCardController::class, 'updateTemplate']);
                $router->post('/delete-template', [GiftCardController::class, 'deleteTemplate']);

                // Code management
                $router->post('/generate-codes', [GiftCardController::class, 'generateCodes']);
                $router->any('/codes', [GiftCardController::class, 'codes']);
                $router->post('/toggle-code', [GiftCardController::class, 'toggleCode']);
                $router->get('/export-codes', [GiftCardController::class, 'exportCodes']);
                $router->post('/update-code', [GiftCardController::class, 'updateCode']);
                $router->post('/delete-code', [GiftCardController::class, 'deleteCode']);

                // Usage records
                $router->any('/usages', [GiftCardController::class, 'usages']);

                // Statistics
                $router->any('/statistics', [GiftCardController::class, 'statistics']);
                $router->get('/types', [GiftCardController::class, 'types']);
            });

            // Knowledge
            $router->group([
                'prefix' => 'knowledge'
            ], function ($router) {
                $router->get('/fetch', [KnowledgeController::class, 'fetch']);
                $router->get('/getCategory', [KnowledgeController::class, 'getCategory']);
                $router->post('/save', [KnowledgeController::class, 'save']);
                $router->post('/show', [KnowledgeController::class, 'show']);
                $router->post('/drop', [KnowledgeController::class, 'drop']);
                $router->post('/sort', [KnowledgeController::class, 'sort']);
            });

            // Payment  
            $router->group([
                'prefix' => 'payment'
            ], function ($router) {
                $router->get('/fetch', [PaymentController::class, 'fetch']);
                $router->get('/getPaymentMethods', [PaymentController::class, 'getPaymentMethods']);
                $router->post('/getPaymentForm', [PaymentController::class, 'getPaymentForm']);
                $router->post('/save', [PaymentController::class, 'save']);
                $router->post('/drop', [PaymentController::class, 'drop']);
                $router->post('/show', [PaymentController::class, 'show']);
                $router->post('/sort', [PaymentController::class, 'sort']);
            });

            // System
            $router->group([
                'prefix' => 'system'
            ], function ($router) {
                $router->get('/getSystemStatus', [SystemController::class, 'getSystemStatus']);
                $router->get('/getQueueStats', [SystemController::class, 'getQueueStats']);
                $router->get('/getQueueWorkload', [SystemController::class, 'getQueueWorkload']);
                $router->get('/getQueueMasters', '\\Laravel\\Horizon\\Http\\Controllers\\MasterSupervisorController@index');
                $router->get('/getHorizonFailedJobs', [SystemController::class, 'getHorizonFailedJobs']);
                $router->any('/getAuditLog', [SystemController::class, 'getAuditLog']);
            });

            // Update
            // $router->group([
            //     'prefix' => 'update'
            // ], function ($router) {
            //     $router->get('/check', [UpdateController::class, 'checkUpdate']);
            //     $router->post('/execute', [UpdateController::class, 'executeUpdate']);
            // });

            // Theme
            $router->group([
                'prefix' => 'theme'
            ], function ($router) {
                $router->get('/getThemes', [ThemeController::class, 'getThemes']);
                $router->post('/upload', [ThemeController::class, 'upload']);
                $router->post('/delete', [ThemeController::class, 'delete']);
                $router->post("/switch", [ThemeController::class, "switchTheme"]);
                $router->post('/saveThemeConfig', [ThemeController::class, 'saveThemeConfig']);
                $router->post('/getThemeConfig', [ThemeController::class, 'getThemeConfig']);
            });

            // Plugin
            $router->group([
                'prefix' => 'plugin'
            ], function ($router) {
                $router->get('/types', [\App\Http\Controllers\V2\Admin\PluginController::class, 'types']);
                $router->get('/getPlugins', [\App\Http\Controllers\V2\Admin\PluginController::class, 'index']);
                $router->post('/upload', [\App\Http\Controllers\V2\Admin\PluginController::class, 'upload']);
                $router->post('/delete', [\App\Http\Controllers\V2\Admin\PluginController::class, 'delete']);
                $router->post('install', [\App\Http\Controllers\V2\Admin\PluginController::class, 'install']);
                $router->post('uninstall', [\App\Http\Controllers\V2\Admin\PluginController::class, 'uninstall']);
                $router->post('enable', [\App\Http\Controllers\V2\Admin\PluginController::class, 'enable']);
                $router->post('disable', [\App\Http\Controllers\V2\Admin\PluginController::class, 'disable']);
                $router->get('config', [\App\Http\Controllers\V2\Admin\PluginController::class, 'getConfig']);
                $router->post('config', [\App\Http\Controllers\V2\Admin\PluginController::class, 'updateConfig']);
                $router->post('upgrade', [\App\Http\Controllers\V2\Admin\PluginController::class, 'upgrade']);
            });

            // 流量重置管理
            $router->group([
                'prefix' => 'traffic-reset'
            ], function ($router) {
                $router->get('logs', [TrafficResetController::class, 'logs']);
                $router->get('stats', [TrafficResetController::class, 'stats']);
                $router->get('user/{userId}/history', [TrafficResetController::class, 'userHistory']);
                $router->post('reset-user', [TrafficResetController::class, 'resetUser']);
            });

            // 注意：edgetunnel/cf-worker 的 GET 路由已挪到 group 外（见下方第二个 group），
            // 因为要让"未登录时渲染登录表单"而不是返回 403 JSON。
            // 它们仍然挂在 secure_path 前缀下，跟其他 admin API 路径一致。

            // CF Worker Management
            $router->group([
                'prefix' => 'cf-worker'
            ], function ($router) {
                $router->get('/list', [CfWorkerController::class, 'list']);
                $router->post('/save', [CfWorkerController::class, 'save']);
                $router->post('/deploy', [CfWorkerController::class, 'deploy']);
                $router->post('/delete', [CfWorkerController::class, 'delete']);
                $router->post('/redeploy', [CfWorkerController::class, 'redeploy']);
                $router->get('/status', [CfWorkerController::class, 'status']);
            });

            // Edgetunnel API
            $router->group([
                'prefix' => 'edgetunnel'
            ], function ($router) {
                $router->get('/subscriptions', [\XboardFerrari\Http\Controllers\Admin\EdgetunnelController::class, 'getSubscriptions']);
                $router->post('/subscription/save', [\XboardFerrari\Http\Controllers\Admin\EdgetunnelController::class, 'saveSubscription']);
                $router->post('/subscription/drop', [\XboardFerrari\Http\Controllers\Admin\EdgetunnelController::class, 'dropSubscription']);
                $router->post('/subscription/sync', [\XboardFerrari\Http\Controllers\Admin\EdgetunnelController::class, 'syncSubscription']);
                $router->post('/subscription/preview', [\XboardFerrari\Http\Controllers\Admin\EdgetunnelController::class, 'previewInjection']);
                $router->get('/nodes', [\XboardFerrari\Http\Controllers\Admin\EdgetunnelController::class, 'getNodes']);
                $router->post('/node/update', [\XboardFerrari\Http\Controllers\Admin\EdgetunnelController::class, 'updateNode']);
                $router->post('/node/test-latency', [\XboardFerrari\Http\Controllers\Admin\EdgetunnelController::class, 'testLatency']);
                $router->post('/node/batch-test', [\XboardFerrari\Http\Controllers\Admin\EdgetunnelController::class, 'batchTestLatency']);
                $router->get("/users", [\XboardFerrari\Http\Controllers\Admin\EdgetunnelController::class, "getUsers"]);
                $router->post("/users/batch-group", [\XboardFerrari\Http\Controllers\Admin\EdgetunnelController::class, "batchUpdateUserGroup"]);
                $router->get("/servers", [\XboardFerrari\Http\Controllers\Admin\EdgetunnelController::class, "getServers"]);
                $router->post("/server/save", [\XboardFerrari\Http\Controllers\Admin\EdgetunnelController::class, "saveServer"]);
                $router->post("/server/update", [\XboardFerrari\Http\Controllers\Admin\EdgetunnelController::class, "updateServer"]);
                $router->post("/server/drop", [\XboardFerrari\Http\Controllers\Admin\EdgetunnelController::class, "dropServer"]);
            });
        });

        // ========== edgetunnel/cf-worker 页面路由（不挂 admin middleware） ==========
        // 目的：浏览器直接打开 /{SECURE_PATH}/edgetunnel 或 /cf-worker 时，
        //      如果没 Sanctum auth（既无 Authorization 头也无 _t query param），
        //      渲染一个轻量"token 桥接页"（admin.bridge）：
        //        - 浏览器 JS 读 localStorage['XBOARD_ACCESS_TOKEN']（SPA 登录后写的）
        //        - 有 token：跳到同 URL + ?_t={token}，再回到本路由的 _t 分支
        //        - 没 token：跳到 /{SECURE_PATH}#/sign-in?redirect={target}
        //      这样既不需要额外的 login.blade.php，也兼容 SPA 单一登录入口的设计。
        //      _t query param 服务端会剥 "Bearer " 前缀后调 findToken 验证。
        $securePath = admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key'))));
        $title = admin_setting('app_name', 'XBoard');
        $router->group([
            'prefix' => $securePath,
            'middleware' => ['log'],
        ], function ($router) use ($securePath, $title) {
            $bridgeView = function ($blade) use ($securePath, $title) {
                $target = "/{$securePath}/{$blade}";
                return response()->view('admin.bridge', [
                    'title' => $title,
                    'target_path' => $target,
                    'spa_signin_url' => "/{$securePath}#/sign-in",
                    'message' => "正在跳转到 {$blade} 管理...",
                ])->header('Cache-Control', 'no-store, no-cache, must-revalidate')
                  ->header('Pragma', 'no-cache');
            };

            $router->get('/edgetunnel', function () use ($securePath, $title, $bridgeView) {
                $tokenParam = request()->query('_t');
                if ($tokenParam) {
                    try {
                        // SPA 在 localStorage 里存的是 "Bearer xxx"；findToken 需要裸 token
                        $rawToken = preg_replace('/^Bearer\s+/i', '', $tokenParam);
                        $pat = PersonalAccessToken::findToken($rawToken);
                        if ($pat && $pat->tokenable && $pat->tokenable->is_admin) {
                            Auth::guard('sanctum')->setUser($pat->tokenable);
                        }
                    } catch (\Throwable $e) {}
                }
                $user = Auth::guard('sanctum')->user();
                if (!$user || !$user->is_admin) {
                    return $bridgeView('edgetunnel');
                }
                return response()->view('admin.edgetunnel', [
                    'title' => $title,
                    'secure_path' => $securePath,
                ])->header('Cache-Control', 'no-store, no-cache, must-revalidate')
                  ->header('Pragma', 'no-cache');
            });
            $router->get('/cf-worker', function () use ($securePath, $title, $bridgeView) {
                $tokenParam = request()->query('_t');
                if ($tokenParam) {
                    try {
                        $rawToken = preg_replace('/^Bearer\s+/i', '', $tokenParam);
                        $pat = PersonalAccessToken::findToken($rawToken);
                        if ($pat && $pat->tokenable && $pat->tokenable->is_admin) {
                            Auth::guard('sanctum')->setUser($pat->tokenable);
                        }
                    } catch (\Throwable $e) {}
                }
                $user = Auth::guard('sanctum')->user();
                if (!$user || !$user->is_admin) {
                    return $bridgeView('cf-worker');
                }
                return response()->view('admin.cf-worker', [
                    'title' => $title,
                    'secure_path' => $securePath,
                ])->header('Cache-Control', 'no-store, no-cache, must-revalidate')
                  ->header('Pragma', 'no-cache');
            });
        });

    }
}
