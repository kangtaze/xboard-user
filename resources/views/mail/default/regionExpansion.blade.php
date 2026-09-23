{{-- 新地区上线营销邮件 — 官方企业化风格（基于 renewalMarketing / updateNotice 标杆样式）--}}
@php
    $expiredDate = $expired_date ?? date('Y年m月d日');
    $daysExpired = $days_expired ?? 0;
    $planName = $plan['name'] ?? 'VIP1';

    // 续费赠流量（按 VIP 等级差异化,继承 renewalMarketing 赠送体系）
    $bonusByPlan = [
        'VIP1' => ['gift' => '5GB', 'price' => '19.90'],
        'VIP2' => ['gift' => '10GB', 'price' => '29.90'],
        'VIP3' => ['gift' => '20GB', 'price' => '49.90'],
    ];
    $bonus = $bonusByPlan[$planName] ?? $bonusByPlan['VIP1'];

    // 5 个新地区卡片（按地理距离 + 用户常用度排序）
    $regions = [
        ['flag' => '🇭🇰', 'name' => '香港', 'tag' => 'HK · 金融中心', 'speed' => '50ms', 'use' => '看 YouTube / 浏览港台资源'],
        ['flag' => '🇨🇳', 'name' => '中国台湾', 'tag' => 'TW · 中华电信', 'speed' => '55ms', 'use' => 'LINE / 巴哈姆特 / 当地服务'],
        ['flag' => '🇳🇱', 'name' => '荷兰', 'tag' => 'NL · 阿姆斯特丹', 'speed' => '180ms', 'use' => '欧洲游戏 / 流媒体解锁'],
        ['flag' => '🇰🇷', 'name' => '韩国', 'tag' => 'KR · 首尔', 'speed' => '65ms', 'use' => '韩剧 / 韩游加速'],
        ['flag' => '🇫🇷', 'name' => '法国', 'tag' => 'FR · 巴黎', 'speed' => '200ms', 'use' => '欧洲学术 / 法语资源'],
    ];
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
                        <p style="margin:0 0 18px;color:rgba(255,255,255,0.5);font-size:11px;letter-spacing:3px;text-transform:uppercase;font-weight:500">NEW REGIONS · 5 大新地区上线</p>
                        <h1 style="margin:0;color:#ffffff;font-size:28px;font-weight:300;letter-spacing:0.5px;line-height:1.4">加了 5 个地区<br><span style="font-weight:700;font-size:24px;color:#ffd86b">加量不加价,这次我们真没涨价</span></h1>
                        <p style="margin:18px 0 0;color:rgba(255,255,255,0.5);font-size:13px;letter-spacing:0.3px">为感谢老用户支持,您的 {{ $planName }} 续费通道已开启</p>
                    </td>
                </tr>
                <tr><td style="background:#e94560;height:3px"></td></tr>

                {{-- 主体内容 --}}
                <tr>
                    <td style="padding:48px 40px 32px">

                        {{-- 引导语 --}}
                        <p style="margin:0 0 24px;color:#333;font-size:14px;line-height:1.95">尊敬的用户：<br>我们团队熬了 <strong style="color:#e94560">18 天</strong>,把 <strong style="color:#0f3460">5 个新地区</strong>全部上线了。没改价格,没减流量,就是把网络铺得更广一点 —— 这是我们对老用户最简单的回报。</p>

                        {{-- 同理心锚点 --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff8e7;border-left:3px solid #ffd86b;margin-bottom:28px">
                            <tr><td style="padding:16px 20px">
                                <p style="margin:0 0 8px;color:#1a1a2e;font-size:13px;font-weight:700;letter-spacing:1px">为什么熬 18 天?</p>
                                <p style="margin:0;color:#666;font-size:12px;line-height:1.8">香港机房搬迁一次,韩国 BGP 线路调优两次,荷兰和法国是首次合作机房需要做合规审计。慢,但稳上线 —— <strong style="color:#0f3460">亏得起,不能对不起信任我们的用户</strong>。</p>
                            </td></tr>
                        </table>

                        {{-- 区块 01：5 大新地区 --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee;margin-bottom:28px">
                            <tr><td style="background:#fafbfc;padding:14px 24px;border-bottom:1px solid #eee">
                                <span style="color:#0f3460;font-size:13px;font-weight:600;letter-spacing:1.5px">01 / 5 大新地区一览</span>
                            </td></tr>
                            <tr><td style="padding:8px 24px 16px">
                                @foreach($regions as $r)
                                <table width="100%" cellpadding="0" cellspacing="0" style="border-bottom:1px solid #f0f0f0">
                                    <tr>
                                        <td width="56" valign="middle" style="padding:14px 12px 14px 0;font-size:28px;line-height:1">{{ $r['flag'] }}</td>
                                        <td valign="middle" style="padding:14px 0">
                                            <p style="margin:0 0 4px;color:#1a1a2e;font-size:15px;font-weight:700">{{ $r['name'] }} <span style="color:#999;font-size:11px;font-weight:500;letter-spacing:1px;margin-left:4px">{{ $r['tag'] }}</span></p>
                                            <p style="margin:0;color:#666;font-size:12px;line-height:1.6">{{ $r['use'] }} · 延迟约 <strong style="color:#0f3460">{{ $r['speed'] }}</strong></p>
                                        </td>
                                    </tr>
                                </table>
                                @endforeach
                            </td></tr>
                        </table>

                        {{-- 区块 02：价格承诺 --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #0f3460;margin-bottom:28px">
                            <tr>
                                <td style="background:linear-gradient(135deg,#0f3460 0%,#1a1a2e 100%);padding:14px 24px">
                                    <span style="color:#ffffff;font-size:13px;font-weight:600;letter-spacing:1.5px">02 / 价格承诺 · 加量不加价</span>
                                </td>
                            </tr>
                            <tr>
                                <td style="padding:24px;background:#fafbfc">
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td width="50%" valign="top" style="padding:8px 12px 8px 0">
                                                <p style="margin:0 0 4px;color:#999;font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:500">V1 之前的套餐</p>
                                                <p style="margin:0;color:#999;font-size:14px;text-decoration:line-through">¥{{ $bonus['price'] }} / 月</p>
                                            </td>
                                            <td width="50%" valign="top" style="padding:8px 0 8px 12px;border-left:1px solid #eee">
                                                <p style="margin:0 0 4px;color:#e94560;font-size:11px;letter-spacing:2px;text-transform:uppercase;font-weight:700">现在依然是</p>
                                                <p style="margin:0;color:#0f3460;font-size:22px;font-weight:700">¥{{ $bonus['price'] }} <span style="color:#999;font-size:12px;font-weight:500">/ 月</span></p>
                                            </td>
                                        </tr>
                                    </table>
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:14px;border-top:1px dashed #ddd">
                                        <tr><td style="padding:14px 0 0">
                                            <span style="color:#333;font-size:13px;line-height:1.8">✅ 新增 5 个地区节点(原本 8 个 → 现在 <strong style="color:#0f3460">13 个</strong>)<br>✅ 流量 / 带宽 / 价格 <strong style="color:#e94560">保持原样</strong>,合同期内老用户不补差价<br>✅ 季度 / 年付方案不变,支持随时升级套餐</span>
                                        </td></tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        {{-- 区块 03：老用户独享 --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fdf3f4;border-left:3px solid #e94560;margin-bottom:28px">
                            <tr><td style="padding:18px 22px">
                                <p style="margin:0 0 8px;color:#e94560;font-size:13px;font-weight:700;letter-spacing:1.5px">03 / 老用户独享福利</p>
                                <p style="margin:0;color:#333;font-size:13px;line-height:1.85"><strong style="color:#e94560">续费 {{ $planName }} 即赠 {{ $bonus['gift'] }} 流量</strong>(系统自动发放到您的账户,有效期同套餐周期)。本福利仅限本邮件收件人,不公开,不对新用户开放 —— 算是给一直跟着我们的老用户一个小礼物。</p>
                            </td></tr>
                        </table>

                        {{-- 区块 04：你可能想问 --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee;margin-bottom:28px">
                            <tr><td style="background:#fafbfc;padding:14px 24px;border-bottom:1px solid #eee">
                                <span style="color:#0f3460;font-size:13px;font-weight:600;letter-spacing:1.5px">04 / 你可能想问</span>
                            </td></tr>
                            <tr><td style="padding:20px 24px 8px">
                                <p style="margin:0 0 6px;color:#1a1a2e;font-size:13px;font-weight:600">Q：会不会很快涨价?</p>
                                <p style="margin:0 0 14px;color:#666;font-size:12px;line-height:1.75">A：今年不会。我们跟上游机房的合同签到 2027 年 6 月,期间合同价锁定。涨价这种话我们说过一次(v19 那次硬件升级),说过的话要做到。</p>

                                <p style="margin:0 0 6px;color:#1a1a2e;font-size:13px;font-weight:600">Q：新地区为什么免费送?</p>
                                <p style="margin:0 0 14px;color:#666;font-size:12px;line-height:1.75">A：机房成本已经包在现有套餐里,新增地区是规模效应 —— 节点越多分摊越低,让老用户多一个选择不影响成本结构。</p>

                                <p style="margin:0 0 6px;color:#1a1a2e;font-size:13px;font-weight:600">Q：能一直这个价吗?</p>
                                <p style="margin:0 0 0;color:#666;font-size:12px;line-height:1.75">A：合同期内可以。合同到期后,如果成本结构没大变,我们会优先考虑老用户保价方案(而不是简单普涨)。具体怎么保价,到时候会单独通知,不在本邮件承诺范围内。</p>
                            </td></tr>
                        </table>

                        {{-- CTA 主按钮 --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px">
                            <tr><td align="center" style="padding:8px 0 12px">
                                <a href="https://vp92vip.com/#/renew" style="display:inline-block;background:#e94560;color:#ffffff;text-decoration:none;padding:16px 64px;font-size:15px;font-weight:700;letter-spacing:3px">立即续费 · 锁定老用户价</a>
                            </td></tr>
                            <tr><td style="text-align:center;padding:6px 0 0">
                                <span style="color:#999;font-size:12px">续费后系统自动恢复 + 自动到账赠流量</span>
                            </td></tr>
                            <tr><td style="text-align:center;padding:14px 0 0">
                                <span style="color:#999;font-size:12px">遇到问题？</span>
                                <a href="https://t.me/VP92jisu_bot" style="color:#0f3460;font-size:12px;text-decoration:none;font-weight:600">AI 智能客服</a>
                                <span style="color:#ddd;margin:0 6px">|</span>
                                <a href="https://t.me/vp92jisu" style="color:#0f3460;font-size:12px;text-decoration:none;font-weight:600">人工客服 @vp92jisu</a>
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
                            <a href="https://t.me/vp92jisu" style="color:#0f3460;font-size:12px;text-decoration:none;font-weight:500">人工客服</a>
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