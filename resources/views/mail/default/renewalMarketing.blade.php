{{-- 续费营销邮件 — 官方企业化风格（基于 updateNotice / vip4-announcement-email / welcome 标杆样式）--}}
@php
    // 默认值兜底（防止模板变量未传）
    $planName = $plan['name'] ?? 'VIP1';
    $planMonthly = $plan['monthly'] ?? '19.90';
    $planQuarterly = $plan['quarterly'] ?? '56';
    $planTraffic = $plan['traffic'] ?? '600GB';
    $planSpeed = $plan['speed'] ?? '1000Mbps';
    $expiredDate = $expired_date ?? date('Y年m月d日');
    $daysExpired = $days_expired ?? 0;

    // 续费赠送福利（按 VIP 等级差异化）
    $bonusByPlan = [
        'VIP1' => ['gift' => '赠 5GB 体验流量', 'discount' => '立省 3 元'],
        'VIP2' => ['gift' => '赠 10GB 体验流量', 'discount' => '立省 6 元'],
        'VIP3' => ['gift' => '赠 20GB 体验流量', 'discount' => '立省 12 元'],
    ];
    $bonus = $bonusByPlan[$planName] ?? $bonusByPlan['VIP1'];

    // 推荐套餐（智能匹配：当前 VIP 等级 + 升一档推荐）
    $upgradeMap = [
        'VIP1' => ['recommended' => 'VIP1', 'reason' => '保持现有套餐'],
        'VIP2' => ['recommended' => 'VIP2', 'reason' => '保持现有套餐，流量翻倍更划算'],
        'VIP3' => ['recommended' => 'VIP3', 'reason' => '保持现有套餐'],
    ];
    $recommend = $upgradeMap[$planName] ?? $upgradeMap['VIP1'];
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
</head>
<body style="margin:0;padding:0;background:#f8f9fa;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;-webkit-font-smoothing:antialiased">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fa;padding:40px 16px">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%">

    {{-- Logo 头部 --}}
    <tr>
        <td style="text-align:center;padding:0 0 32px">
            <img src="https://vp92vip.com/img/logo.svg" alt="VP92极速加速器" style="height:28px">
            <p style="margin:12px 0 0;color:#1a1a2e;font-size:11px;letter-spacing:4px;text-transform:uppercase;font-weight:500">VP92 TURBO · GLOBAL ACCELERATION NETWORK</p>
        </td>
    </tr>

    {{-- 主卡片 --}}
    <tr>
        <td>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:2px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08)">

                {{-- 顶部 Banner --}}
                <tr>
                    <td style="background:#0f3460;padding:56px 40px 48px;text-align:center">
                        <p style="margin:0 0 18px;color:rgba(255,255,255,0.5);font-size:11px;letter-spacing:3px;text-transform:uppercase;font-weight:500">RENEWAL · 服务到期通知</p>
                        <h1 style="margin:0;color:#ffffff;font-size:28px;font-weight:300;letter-spacing:0.5px;line-height:1.4">您的 <span style="font-weight:700">{{ $planName }}</span> 服务已到期<br><span style="font-weight:700;font-size:24px">续费即享专属福利</span></h1>
                        <p style="margin:18px 0 0;color:rgba(255,255,255,0.5);font-size:13px;letter-spacing:0.3px">已到期 {{ $daysExpired }} 天 · 立即恢复畅享全球网络加速</p>
                    </td>
                </tr>
                <tr><td style="background:#e94560;height:3px"></td></tr>

                {{-- 主体内容 --}}
                <tr>
                    <td style="padding:48px 40px 32px">

                        {{-- 引导语 --}}
                        <p style="margin:0 0 36px;color:#333;font-size:14px;line-height:1.95">尊敬的用户：<br>感谢您一直以来对 <strong style="color:#0f3460">VP92 极速加速器</strong>的信任与支持。我们注意到您的 <strong style="color:#0f3460">{{ $planName }}</strong> 服务已于 <strong style="color:#e94560">{{ $expiredDate }}</strong> 到期。为避免影响您的正常使用，我们为您准备了<strong style="color:#0f3460">专属续费福利</strong>，请尽快完成续费。</p>

                        {{-- 区块 01：当前账号状态 --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee;margin-bottom:28px">
                            <tr><td style="background:#fafbfc;padding:14px 24px;border-bottom:1px solid #eee">
                                <span style="color:#0f3460;font-size:13px;font-weight:600;letter-spacing:1.5px">01 / 当前账号状态</span>
                            </td></tr>
                            <tr><td style="padding:24px 24px 8px">
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr><td style="padding:8px 0;color:#333;font-size:14px;line-height:1.7"><span style="color:#0f3460;margin-right:10px;font-weight:700">●</span> 当前套餐：<strong style="color:#1a1a2e">{{ $planName }}</strong>（{{ $planTraffic }} / {{ $planSpeed }}）</td></tr>
                                    <tr><td style="padding:8px 0;color:#333;font-size:14px;line-height:1.7"><span style="color:#0f3460;margin-right:10px;font-weight:700">●</span> 到期日期：<strong style="color:#e94560">{{ $expiredDate }}</strong>（已过期 {{ $daysExpired }} 天）</td></tr>
                                    <tr><td style="padding:8px 0;color:#333;font-size:14px;line-height:1.7"><span style="color:#0f3460;margin-right:10px;font-weight:700">●</span> 账号状态：已暂停服务，恢复连接需续费</td></tr>
                                    <tr><td style="padding:8px 0 16px;color:#333;font-size:14px;line-height:1.7"><span style="color:#0f3460;margin-right:10px;font-weight:700">●</span> 数据保留：账号信息与配置将保留 <strong style="color:#0f3460">30 天</strong>，期间续费立即恢复</td></tr>
                                </table>
                            </td></tr>
                        </table>

                        {{-- 区块 02：续费方案（套餐对比） --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #0f3460;margin-bottom:28px">
                            <tr>
                                <td style="background:linear-gradient(135deg,#0f3460 0%,#1a1a2e 100%);padding:14px 24px">
                                    <span style="color:#ffffff;font-size:13px;font-weight:600;letter-spacing:1.5px">02 / 续费方案 · 按月/季度灵活选择</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:24px 24px 8px;background:#fafbfc">
                                    {{-- 月付方案 --}}
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #eee;margin-bottom:14px">
                                        <tr>
                                            <td width="40%" style="padding:20px 16px;border-right:1px solid #eee;vertical-align:middle;text-align:center;background:#fafbfc">
                                                <p style="margin:0 0 4px;color:#999;font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:500">MONTHLY</p>
                                                <p style="margin:0;color:#0f3460;font-size:22px;font-weight:700;letter-spacing:0.5px">¥{{ $planMonthly }}</p>
                                                <p style="margin:4px 0 0;color:#999;font-size:11px">/ 月</p>
                                            </td>
                                            <td style="padding:18px 20px;vertical-align:middle">
                                                <p style="margin:0 0 6px;color:#1a1a2e;font-size:14px;font-weight:600">月付方案</p>
                                                <p style="margin:0;color:#666;font-size:12px;line-height:1.7">{{ $planTraffic }} 流量 · {{ $planSpeed }} 带宽<br>灵活按月续费，无长期承诺</p>
                                            </td>
                                        </tr>
                                    </table>
                                    {{-- 季度方案（推荐）--}}
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;border:2px solid #0f3460;margin-bottom:14px">
                                        <tr>
                                            <td width="40%" style="padding:20px 16px;border-right:1px solid #0f3460;vertical-align:middle;text-align:center;background:#0f3460">
                                                <p style="margin:0 0 4px;color:rgba(255,255,255,0.5);font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:500">QUARTERLY · 推荐</p>
                                                <p style="margin:0;color:#ffffff;font-size:22px;font-weight:700;letter-spacing:0.5px">¥{{ $planQuarterly }}</p>
                                                <p style="margin:4px 0 0;color:rgba(255,255,255,0.6);font-size:11px">/ 季（约 3 个月）</p>
                                            </td>
                                            <td style="padding:18px 20px;vertical-align:middle;background:#fff8e7">
                                                <p style="margin:0 0 6px;color:#1a1a2e;font-size:14px;font-weight:600">季度方案 <span style="display:inline-block;background:#e94560;color:#fff;font-size:10px;padding:2px 6px;border-radius:2px;letter-spacing:1px;margin-left:4px;font-weight:700">省 {{ $bonus['discount'] }}</span></p>
                                                <p style="margin:0;color:#666;font-size:12px;line-height:1.7">{{ $planTraffic }} 流量 · {{ $planSpeed }} 带宽<br>相当于月付 ¥{{ number_format($planQuarterly / 3, 2) }}/月，<strong style="color:#e94560">立省 {{ $bonus['discount'] }}</strong></p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:0 24px 20px;background:#fafbfc">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fdf3f4;border-left:3px solid #e94560">
                                        <tr><td style="padding:12px 16px">
                                            <span style="color:#a33;font-size:12px;line-height:1.7">🎁 <strong style="color:#e94560">限时专享福利</strong>：本邮件专属续费，再赠 <strong style="color:#e94560">{{ $bonus['gift'] }}</strong>（续费后系统自动发放到您的账户）</span>
                                        </td></tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        {{-- 区块 03：3 步续费 --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee;margin-bottom:28px">
                            <tr><td style="background:#fafbfc;padding:14px 24px;border-bottom:1px solid #eee">
                                <span style="color:#0f3460;font-size:13px;font-weight:600;letter-spacing:1.5px">03 / 3 步完成续费</span>
                            </td></tr>
                            <tr><td style="padding:24px">

                                {{-- 步骤 1 --}}
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px">
                                    <tr>
                                        <td width="36" valign="top" style="padding-top:2px">
                                            <table role="presentation" width="28" height="28" cellpadding="0" cellspacing="0"><tr><td style="background:#0f3460;border-radius:50%;text-align:center;color:#ffffff;font-size:13px;font-weight:700;line-height:28px;font-family:'Helvetica Neue',Arial,sans-serif">1</td></tr></table>
                                        </td>
                                        <td style="padding:2px 0 2px 12px;color:#333;font-size:14px;line-height:1.7">
                                            <strong style="color:#1a1a2e">登录账户</strong><br>
                                            <span style="color:#666;font-size:13px">访问 vp92vip.com，使用注册邮箱与密码登录 Dashboard</span>
                                        </td>
                                    </tr>
                                </table>

                                {{-- 步骤 2 --}}
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px">
                                    <tr>
                                        <td width="36" valign="top" style="padding-top:2px">
                                            <table role="presentation" width="28" height="28" cellpadding="0" cellspacing="0"><tr><td style="background:#0f3460;border-radius:50%;text-align:center;color:#ffffff;font-size:13px;font-weight:700;line-height:28px;font-family:'Helvetica Neue',Arial,sans-serif">2</td></tr></table>
                                        </td>
                                        <td style="padding:2px 0 2px 12px;color:#333;font-size:14px;line-height:1.7">
                                            <strong style="color:#1a1a2e">选择套餐</strong><br>
                                            <span style="color:#666;font-size:13px">进入「购买套餐」页，选择 {{ $planName }} 月付或季度方案</span>
                                        </td>
                                    </tr>
                                </table>

                                {{-- 步骤 3 --}}
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td width="36" valign="top" style="padding-top:2px">
                                            <table role="presentation" width="28" height="28" cellpadding="0" cellspacing="0"><tr><td style="background:#e94560;border-radius:50%;text-align:center;color:#ffffff;font-size:13px;font-weight:700;line-height:28px;font-family:'Helvetica Neue',Arial,sans-serif">3</td></tr></table>
                                        </td>
                                        <td style="padding:2px 0 2px 12px;color:#333;font-size:14px;line-height:1.7">
                                            <strong style="color:#1a1a2e">支付完成，立即恢复</strong><br>
                                            <span style="color:#666;font-size:13px">支持支付宝 / 礼品卡 / 海外信用卡，付款后 <strong style="color:#e94560">秒到账</strong>，无需重新登录</span>
                                        </td>
                                    </tr>
                                </table>

                            </td></tr>
                        </table>

                        {{-- 区块 04：为什么选择 VP92 --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee;margin-bottom:32px">
                            <tr><td style="background:#fafbfc;padding:14px 24px;border-bottom:1px solid #eee">
                                <span style="color:#0f3460;font-size:13px;font-weight:600;letter-spacing:1.5px">04 / 为什么全球用户选择 VP92 极速</span>
                            </td></tr>
                            <tr><td style="padding:24px 24px 8px">
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr><td style="padding:8px 0;color:#333;font-size:14px;line-height:1.7"><span style="color:#0f3460;margin-right:10px;font-weight:700">●</span> <strong style="color:#0f3460">全球骨干节点</strong> · 50+ 国家地区，{{ $planSpeed }} 带宽秒开 4K 视频</td></tr>
                                    <tr><td style="padding:8px 0;color:#333;font-size:14px;line-height:1.7"><span style="color:#0f3460;margin-right:10px;font-weight:700">●</span> <strong style="color:#0f3460">智能调度引擎</strong> · 实时路径优化，自动选择最低延迟链路</td></tr>
                                    <tr><td style="padding:8px 0;color:#333;font-size:14px;line-height:1.7"><span style="color:#0f3460;margin-right:10px;font-weight:700">●</span> <strong style="color:#0f3460">AES-256 加密</strong> · 银行级加密传输，无日志记录政策</td></tr>
                                    <tr><td style="padding:8px 0 16px;color:#333;font-size:14px;line-height:1.7"><span style="color:#0f3460;margin-right:10px;font-weight:700">●</span> <strong style="color:#0f3460">7×24 客服</strong> · Telegram / AI 智能客服秒级响应</td></tr>
                                </table>
                            </td></tr>
                        </table>

                        {{-- CTA 主按钮 --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px">
                            <tr><td align="center" style="padding:8px 0 12px">
                                <a href="https://vp92vip.com/#/renew" style="display:inline-block;background:#e94560;color:#ffffff;text-decoration:none;padding:16px 64px;font-size:15px;font-weight:700;letter-spacing:3px">立即续费 · 恢复服务</a>
                            </td></tr>
                            <tr><td style="text-align:center;padding:6px 0 0">
                                <span style="color:#999;font-size:12px">续费后系统自动恢复，</span><span style="color:#999;font-size:12px">无需重新配置客户端</span>
                            </td></tr>
                            <tr><td style="text-align:center;padding:14px 0 0">
                                <span style="color:#999;font-size:12px">遇到问题？</span>
                                <a href="https://t.me/VP92jisu_bot" style="color:#0f3460;font-size:12px;text-decoration:none;font-weight:600">AI 智能客服</a>
                                <span style="color:#ddd;margin:0 6px">|</span>
                                <a href="https://t.me/vp92_01" style="color:#0f3460;font-size:12px;text-decoration:none;font-weight:600">人工客服 @vp92_01</a>
                            </td></tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- Footer --}}
    <tr>
        <td style="padding:32px 0 0">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="border-top:1px solid #eee;padding-top:24px;text-align:center">
                        <p style="margin:0 0 12px;color:#999;font-size:12px;line-height:1.8">如需帮助，请联系我们的客服团队</p>
                        <p style="margin:0 0 4px">
                            <a href="https://t.me/VP92jisu_bot" style="color:#0f3460;font-size:12px;text-decoration:none;font-weight:500">AI 智能客服</a>
                            <span style="color:#ddd;margin:0 8px">|</span>
                            <a href="https://t.me/vp92_01" style="color:#0f3460;font-size:12px;text-decoration:none;font-weight:500">人工客服</a>
                            <span style="color:#ddd;margin:0 8px">|</span>
                            <a href="https://vp92vip.com" style="color:#0f3460;font-size:12px;text-decoration:none;font-weight:500">官方网站</a>
                        </p>
                        <p style="margin:16px 0 0;color:#bbb;font-size:11px">© {{ date('Y') }} VP92 极速加速器 · 保留所有权利</p>
                        <p style="margin:4px 0 0;color:#ccc;font-size:10px">此邮件由系统自动发送，请勿直接回复。</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</td></tr>
</table>
</body>
</html>