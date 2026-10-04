{{-- 新用户欢迎邮件 + 使用说明 + 常见问题速查 --}}
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
            <img src="https://vp92vip.com/img/logo.svg" alt="VP92极速加速器" style="height:32px;display:inline-block">
            <p style="margin:12px 0 0;color:#1a1a2e;font-size:11px;letter-spacing:4px;text-transform:uppercase;font-weight:500">VP92 TURBO · GLOBAL ACCELERATION NETWORK</p>
        </td>
    </tr>

    {{-- 主卡片 --}}
    <tr>
        <td>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:2px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.08)">

                {{-- 顶部 Banner --}}
                <tr>
                    <td style="background:#0f3460;padding:48px 40px 44px;text-align:center">
                        <p style="margin:0 0 14px;color:rgba(255,255,255,0.5);font-size:11px;letter-spacing:3px;text-transform:uppercase;font-weight:500">WELCOME · 新用户欢迎</p>
                        <h1 style="margin:0;color:#ffffff;font-size:26px;font-weight:300;letter-spacing:0.5px;line-height:1.4">欢迎加入 <span style="font-weight:700">VP92 极速加速器</span></h1>
                        <p style="margin:18px 0 0;color:rgba(255,255,255,0.6);font-size:13px;letter-spacing:0.3px">注册邮箱：{{ $user_email ?? '' }}</p>
                    </td>
                </tr>
                <tr><td style="background:#e94560;height:3px"></td></tr>

                {{-- 主体内容 --}}
                <tr>
                    <td style="padding:40px 40px 24px">

                        {{-- 引导语 --}}
                        <p style="margin:0 0 32px;color:#333;font-size:14px;line-height:1.9">尊敬的用户您好！感谢您注册 VP92 极速加速器。请先按以下 <strong style="color:#0f3460">3 步</strong>完成首次连接，然后查阅<strong style="color:#0f3460">「常见问题速查」</strong>，覆盖了 90% 新用户遇到的问题。</p>

                        {{-- 区块零：试用节点说明（v56 新增 — 永久模板改动，新用户注册时自动收到）--}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #f5d9b8;margin-bottom:28px;border-radius:4px;overflow:hidden">
                            <tr><td style="background:linear-gradient(135deg,#e94560 0%,#d63851 100%);padding:14px 24px">
                                <span style="color:#ffffff;font-size:13px;font-weight:600;letter-spacing:1.5px">🎁 00 / 关于您当前的试用体验 · 重要说明</span>
                            </td></tr>
                            <tr><td style="background:#fff8f0;padding:20px 24px">
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr><td style="color:#333;font-size:14px;line-height:1.85;padding:0 0 12px">
                                        您当前正在使用的是 <strong style="color:#e94560">【试用节点】</strong>（数量有限，仅供体验）。
                                    </td></tr>
                                    <tr><td style="color:#333;font-size:14px;line-height:1.85;padding:0 0 12px">
                                        购买任一 VIP 套餐后，即可解锁 <strong style="color:#0f3460">全部 153 个高速节点</strong>（覆盖 9 个国家/地区），享受更快更稳的连接体验，加入正式用户行列。
                                    </td></tr>
                                    <tr><td style="padding:8px 0 0;border-top:1px dashed #f5d9b8">
                                        <span style="color:#999;font-size:12px;line-height:1.7;font-style:italic">💡 节点扩充、线路优化、协议升级，我们一直在升级。</span>
                                    </td></tr>
                                </table>
                            </td></tr>
                        </table>

                        {{-- 区块一：快速入门 3 步 --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee;margin-bottom:28px">
                            <tr><td style="background:#fafbfc;padding:14px 24px;border-bottom:1px solid #eee">
                                <span style="color:#0f3460;font-size:13px;font-weight:600;letter-spacing:1.5px">01 / 快速入门 · 3 步上手</span>
                            </td></tr>
                            <tr><td style="padding:24px">

                                {{-- 步骤 1 --}}
                                <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:18px">
                                    <tr>
                                        <td width="36" valign="top" style="padding-top:2px">
                                            <table role="presentation" width="28" height="28" cellpadding="0" cellspacing="0"><tr><td style="background:#0f3460;border-radius:50%;text-align:center;color:#ffffff;font-size:13px;font-weight:700;line-height:28px;font-family:'Helvetica Neue',Arial,sans-serif">1</td></tr></table>
                                        </td>
                                        <td style="padding:2px 0 2px 12px;color:#333;font-size:14px;line-height:1.7">
                                            <strong style="color:#1a1a2e">下载客户端</strong><br>
                                            <span style="color:#666;font-size:13px">访问 vp92vip.com 下载中心，选择对应设备的安装包（Android / Windows）</span>
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
                                            <strong style="color:#1a1a2e">登录您的账号</strong><br>
                                            <span style="color:#666;font-size:13px">使用注册邮箱 + 密码登录 Dashboard</span>
                                        </td>
                                    </tr>
                                </table>

                                {{-- 步骤 3 --}}
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td width="36" valign="top" style="padding-top:2px">
                                            <table role="presentation" width="28" height="28" cellpadding="0" cellspacing="0"><tr><td style="background:#0f3460;border-radius:50%;text-align:center;color:#ffffff;font-size:13px;font-weight:700;line-height:28px;font-family:'Helvetica Neue',Arial,sans-serif">3</td></tr></table>
                                        </td>
                                        <td style="padding:2px 0 2px 12px;color:#333;font-size:14px;line-height:1.7">
                                            <strong style="color:#1a1a2e">选择节点，一键连接</strong><br>
                                            <span style="color:#666;font-size:13px">根据您的运营商（电信/移动/联通）选择对应节点，点击连接即可畅享加速</span>
                                        </td>
                                    </tr>
                                </table>

                            </td></tr>
                        </table>

                        {{-- 区块二：常见问题速查 --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #eee;margin-bottom:28px;border-radius:4px;overflow:hidden">
                            <tr><td style="background:linear-gradient(135deg,#0f3460 0%,#1a1a2e 100%);padding:14px 24px">
                                <span style="color:#ffffff;font-size:13px;font-weight:600;letter-spacing:1.5px">02 / 常见问题速查 · 覆盖 90% 新用户问题</span>
                            </td></tr>
                            <tr><td style="padding:20px 18px">

                                {{-- ★ FAQ 1：节点不显示（重点突出卡片）--}}
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff8f0;border:1px solid #f5d9b8;border-radius:6px;margin-bottom:14px">
                                    <tr><td style="padding:16px 18px">
                                        <p style="margin:0;color:#1a1a2e;font-size:14px;line-height:1.6">
                                            <span style="display:inline-block;background:#e94560;color:#ffffff;font-size:11px;padding:2px 7px;border-radius:3px;margin-right:6px;font-weight:600;letter-spacing:0.5px;vertical-align:middle">最常见</span>
                                            <strong style="font-size:14px">⚡ 节点不显示 / 看不到节点 / 订阅同步失败</strong>
                                        </p>
                                        <p style="margin:8px 0 0;color:#555;font-size:13px;line-height:1.75">这是<strong style="color:#e94560">最高频</strong>的问题！请按顺序操作：</p>
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:10px 0 0">
                                            <tr>
                                                <td style="background:#0f3460;color:#ffffff;padding:5px 11px;border-radius:3px;font-size:12px;font-weight:600;font-family:'Helvetica Neue',Arial,sans-serif">① 退出账号</td>
                                                <td style="color:#999;font-size:13px;padding:0 5px">→</td>
                                                <td style="background:#0f3460;color:#ffffff;padding:5px 11px;border-radius:3px;font-size:12px;font-weight:600;font-family:'Helvetica Neue',Arial,sans-serif">② 关闭 APP</td>
                                                <td style="color:#999;font-size:13px;padding:0 5px">→</td>
                                                <td style="background:#0f3460;color:#ffffff;padding:5px 11px;border-radius:3px;font-size:12px;font-weight:600;font-family:'Helvetica Neue',Arial,sans-serif">③ 重新打开</td>
                                                <td style="color:#999;font-size:13px;padding:0 5px">→</td>
                                                <td style="background:#0f3460;color:#ffffff;padding:5px 11px;border-radius:3px;font-size:12px;font-weight:600;font-family:'Helvetica Neue',Arial,sans-serif">④ 重新登录</td>
                                            </tr>
                                        </table>
                                        <p style="margin:10px 0 0;color:#999;font-size:12px;line-height:1.6;font-style:italic">💡 此操作可解决 90% 节点相关问题（列表为空、连接异常、订阅获取失败等）</p>
                                    </td></tr>
                                </table>

                                {{-- FAQ 2：延迟测试 --}}
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fc;border-left:3px solid #0f3460;border-radius:4px;margin-bottom:10px">
                                    <tr><td style="padding:12px 16px">
                                        <p style="margin:0 0 4px;color:#1a1a2e;font-size:14px;line-height:1.6"><strong>🔄 延迟测试不出结果 / 一直转圈</strong></p>
                                        <p style="margin:0;color:#666;font-size:13px;line-height:1.7">请<strong style="color:#0f3460">多点击一次「延迟测试」按钮</strong>。首次测试可能因节点握手超时无数据，第二次会正常返回。</p>
                                        <p style="margin:6px 0 0;color:#999;font-size:12px;line-height:1.6;font-style:italic">💡 每次切换节点列表后建议重新测一次</p>
                                    </td></tr>
                                </table>

                                {{-- FAQ 3：移动网络 --}}
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fc;border-left:3px solid #0f3460;border-radius:4px;margin-bottom:10px">
                                    <tr><td style="padding:12px 16px">
                                        <p style="margin:0 0 4px;color:#1a1a2e;font-size:14px;line-height:1.6"><strong>📶 移动 5G / 移动宽带用户延迟高、速度慢</strong></p>
                                        <p style="margin:0;color:#666;font-size:13px;line-height:1.7">请选择节点列表<strong style="color:#0f3460">最下方的「移动节点」</strong>，然后<strong style="color:#0f3460">连续测 2-3 次延迟</strong>，选择延迟最低的节点。</p>
                                        <p style="margin:6px 0 0;color:#999;font-size:12px;line-height:1.6;font-style:italic">💡 移动节点已针对中国移动骨干网优化，比默认节点快 30%-50%</p>
                                    </td></tr>
                                </table>

                                {{-- FAQ 4：无法连接 --}}
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fc;border-left:3px solid #0f3460;border-radius:4px;margin-bottom:10px">
                                    <tr><td style="padding:12px 16px">
                                        <p style="margin:0 0 4px;color:#1a1a2e;font-size:14px;line-height:1.6"><strong>⚠️ 无法连接（非节点问题）</strong></p>
                                        <p style="margin:0;color:#666;font-size:13px;line-height:1.8">请按顺序检查：<br>① 本地网络是否正常<br>② 套餐是否已过期<br>③ 是否达到流量上限</p>
                                    </td></tr>
                                </table>

                                {{-- FAQ 5：速度慢 --}}
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fc;border-left:3px solid #0f3460;border-radius:4px;margin-bottom:10px">
                                    <tr><td style="padding:12px 16px">
                                        <p style="margin:0 0 4px;color:#1a1a2e;font-size:14px;line-height:1.6"><strong>🐢 速度慢（已选对运营商节点）</strong></p>
                                        <p style="margin:0;color:#666;font-size:13px;line-height:1.7">手动切换到延迟更低的具体节点；晚高峰时段可优先选择<strong style="color:#0f3460">日本 / 新加坡 / 香港</strong>节点。</p>
                                    </td></tr>
                                </table>

                                {{-- FAQ 6：账户问题 --}}
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8f9fc;border-left:3px solid #0f3460;border-radius:4px;margin-bottom:10px">
                                    <tr><td style="padding:12px 16px">
                                        <p style="margin:0 0 4px;color:#1a1a2e;font-size:14px;line-height:1.6"><strong>🔑 账户问题（找回密码 / 改密码）</strong></p>
                                        <p style="margin:0;color:#666;font-size:13px;line-height:1.7">登录页点击「忘记密码」，使用邮箱验证码重置。</p>
                                    </td></tr>
                                </table>

                                {{-- ★ FAQ 7：流量异常（重点卡片）--}}
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fff8f0;border:1px solid #f5d9b8;border-left:3px solid #e94560;border-radius:6px">
                                    <tr><td style="padding:16px 18px">
                                        <p style="margin:0;color:#1a1a2e;font-size:14px;line-height:1.6">
                                            <span style="display:inline-block;background:#e94560;color:#ffffff;font-size:11px;padding:2px 7px;border-radius:3px;margin-right:6px;font-weight:600;letter-spacing:0.5px;vertical-align:middle">登录后高频</span>
                                            <strong style="font-size:14px">📊 流量异常（消耗过快）</strong>
                                        </p>
                                        <p style="margin:8px 0 0;color:#555;font-size:13px;line-height:1.75">登录后流量异常通常是因为<strong style="color:#0f3460">其他设备或子账号仍在连接</strong>。请按以下步骤排查：</p>
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:10px 0 0">
                                            <tr><td style="padding:4px 0;color:#1a1a2e;font-size:13px;line-height:1.7"><span style="display:inline-block;background:#e94560;color:#ffffff;width:18px;height:18px;line-height:18px;text-align:center;border-radius:50%;font-size:10px;font-weight:700;font-family:'Helvetica Neue',Arial,sans-serif;vertical-align:middle;margin-right:8px">1</span>打开 Dashboard → <strong>「账户」</strong> 页面查看当前在线设备数</td></tr>
                                            <tr><td style="padding:4px 0;color:#1a1a2e;font-size:13px;line-height:1.7"><span style="display:inline-block;background:#e94560;color:#ffffff;width:18px;height:18px;line-height:18px;text-align:center;border-radius:50%;font-size:10px;font-weight:700;font-family:'Helvetica Neue',Arial,sans-serif;vertical-align:middle;margin-right:8px">2</span>对照列表，确认每台设备是否为你本人持有</td></tr>
                                            <tr><td style="padding:4px 0;color:#1a1a2e;font-size:13px;line-height:1.7"><span style="display:inline-block;background:#e94560;color:#ffffff;width:18px;height:18px;line-height:18px;text-align:center;border-radius:50%;font-size:10px;font-weight:700;font-family:'Helvetica Neue',Arial,sans-serif;vertical-align:middle;margin-right:8px">3</span>发现陌生设备，立即点击 <strong>「踢出该设备」</strong> 或修改密码</td></tr>
                                            <tr><td style="padding:4px 0;color:#1a1a2e;font-size:13px;line-height:1.7"><span style="display:inline-block;background:#e94560;color:#ffffff;width:18px;height:18px;line-height:18px;text-align:center;border-radius:50%;font-size:10px;font-weight:700;font-family:'Helvetica Neue',Arial,sans-serif;vertical-align:middle;margin-right:8px">4</span>如怀疑账号被盗，请联系人工客服冻结账号</td></tr>
                                        </table>
                                        <p style="margin:10px 0 0;color:#999;font-size:12px;line-height:1.6;font-style:italic">💡 账号密码请勿告知他人，定期修改可降低被盗风险</p>
                                    </td></tr>
                                </table>

                            </td></tr>
                        </table>

                        {{-- 区块三：下载 CTA --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:8px">
                            <tr><td align="center" style="padding:8px 0 12px">
                                <a href="https://vp92vip.com/#support" style="display:inline-block;background:#0f3460;color:#ffffff;text-decoration:none;padding:14px 56px;font-size:14px;font-weight:600;letter-spacing:2px">前往下载中心 →</a>
                            </td></tr>
                            <tr><td style="text-align:center;padding:6px 0 0">
                                <span style="color:#999;font-size:12px">客户端支持：</span>
                                <a href="https://vp92vip.com/#support" style="color:#0f3460;font-size:12px;text-decoration:none;font-weight:600">Android</a>
                                <span style="color:#ddd;margin:0 6px">|</span>
                                <a href="https://vp92vip.com/#support" style="color:#0f3460;font-size:12px;text-decoration:none;font-weight:600">Windows</a>
                                <span style="color:#ddd;margin:0 6px">|</span>
                                <a href="https://vp92vip.com/vp92-tutorial.html" style="color:#0f3460;font-size:12px;text-decoration:none;font-weight:600">iOS 教程</a>
                            </td></tr>
                        </table>

                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {{-- 页脚 --}}
    <tr>
        <td style="padding:32px 0 0">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="border-top:1px solid #eee;padding-top:24px;text-align:center">
                        <p style="margin:0 0 12px;color:#999;font-size:12px;line-height:1.8">如需帮助，请联系我们的客服团队</p>
                        <p style="margin:0 0 4px">
                            <a href="https://t.me/VP92jisu_bot" style="color:#0f3460;font-size:12px;text-decoration:none;font-weight:500">🤖 AI 智能客服</a>
                            <span style="color:#ddd;margin:0 8px">|</span>
                            <a href="https://t.me/vp92jisu" style="color:#0f3460;font-size:12px;text-decoration:none;font-weight:500">👤 人工客服</a>
                            <span style="color:#ddd;margin:0 8px">|</span>
                            <a href="https://vp92vip.com" style="color:#0f3460;font-size:12px;text-decoration:none;font-weight:500">🌐 官方网站</a>
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
