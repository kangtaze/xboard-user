<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>退订确认 — VP92极速加速器</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f5f5f5; margin: 0; padding: 40px 20px; color: #333; }
  .container { max-width: 480px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px 28px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); }
  h1 { font-size: 20px; margin: 0 0 16px; color: #0f3460; }
  p { font-size: 14px; line-height: 1.7; color: #555; margin: 0 0 12px; }
  .btn { display: inline-block; margin-top: 16px; padding: 10px 24px; background: #e94560; color: #fff !important; text-decoration: none; border-radius: 6px; font-size: 14px; }
  .btn:hover { background: #d63854; }
  .note { font-size: 12px; color: #999; margin-top: 20px; }
</style>
</head>
<body>
<div class="container">
  @if($valid)
    <h1>确认退订营销邮件</h1>
    <p>点击下方按钮后，您将不会再收到 VP92 的续费提醒、套餐推荐等营销邮件。</p>
    <p><strong>注意</strong>：交易通知、登录验证、系统安全等邮件仍会正常发送。</p>
    <form method="POST" action="{{ route('track.unsubscribe', ['hashParam' => $hashParam]) }}" style="margin-top: 16px;">
      @csrf
      <button type="submit" class="btn" style="border: none; cursor: pointer;">确认退订</button>
    </form>
  @else
    <h1>链接已过期</h1>
    <p>该退订链接已失效（90 天后过期）。您仍可通过登录用户中心关闭营销邮件通知。</p>
  @endif
  <p class="note">如有疑问请联系客服：telegram @vp92_support</p>
</div>
</body>
</html>