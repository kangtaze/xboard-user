<?php
// Inject gift card widget into theme custom_html
$dbPath = '/www/.docker/.data/.mysql/';

// Read the HTML widget
$html = <<<'HTMLEND'
<style>#gc-btn{position:fixed;bottom:80px;right:20px;z-index:9999;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;padding:12px 20px;border-radius:50px;cursor:pointer;font-size:14px;box-shadow:0 4px 15px rgba(99,102,241,.4);transition:all .3s}#gc-btn:hover{transform:scale(1.05);box-shadow:0 6px 20px rgba(99,102,241,.6)}#gc-modal{display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.6);backdrop-filter:blur(4px);align-items:center;justify-content:center}#gc-modal.show{display:flex}#gc-box{background:#1e293b;border:1px solid #334155;border-radius:16px;padding:28px;width:90%;max-width:400px;color:#e2e8f0}.gc-input{width:100%;padding:12px 16px;background:#0f172a;border:1px solid #475569;border-radius:10px;color:#fff;font-size:15px;margin:12px 0;box-sizing:border-box;outline:none}.gc-input:focus{border-color:#6366f1}.gc-submit{width:100%;padding:12px;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;border:none;border-radius:10px;font-size:15px;cursor:pointer;margin-top:8px}.gc-submit:hover{opacity:.9}.gc-submit:disabled{opacity:.5;cursor:not-allowed}.gc-close{position:absolute;top:12px;right:16px;color:#94a3b8;font-size:22px;cursor:pointer;background:none;border:none}.gc-msg{margin-top:12px;padding:10px;border-radius:8px;font-size:13px;display:none}.gc-msg.ok{display:block;background:#064e3b;color:#6ee7b7;border:1px solid #065f46}.gc-msg.err{display:block;background:#450a0a;color:#fca5a5;border:1px solid #7f1d1d}.gc-info{background:#1e1b4b;border:1px solid #3730a3;border-radius:10px;padding:12px;margin-bottom:16px;font-size:13px;color:#a5b4fc}</style><button id="gc-btn" onclick="document.getElementById('gc-modal').classList.add('show')">🎁 兑换礼品卡</button><div id="gc-modal"><div id="gc-box" style="position:relative"><button class="gc-close" onclick="document.getElementById('gc-modal').classList.remove('show')">✕</button><h3 style="margin:0 0 8px;color:#a5b4fc;font-size:18px">🎁 兑换礼品卡</h3><div class="gc-info">输入礼品卡卡号即可兑换对应的套餐或流量，兑换后立即生效。</div><input class="gc-input" id="gc-code" placeholder="请输入礼品卡卡号" /><button class="gc-submit" id="gc-sub" onclick="gcRedeem()">兑换</button><div class="gc-msg" id="gc-msg"></div></div></div><script>async function gcRedeem(){const code=document.getElementById("gc-code").value.trim();const msg=document.getElementById("gc-msg");const btn=document.getElementById("gc-sub");if(!code){msg.className="gc-msg err";msg.textContent="请输入卡号";return}btn.disabled=true;btn.textContent="兑换中...";msg.style.display="none";try{const token=localStorage.getItem("token")||document.cookie.match(/token=([^;]+)/)?.[1]||"";const headers={"Content-Type":"application/json","Authorization":token};const r=await fetch("/api/v1/user/gift-card/redeem",{method:"POST",headers,body:JSON.stringify({code})});const d=await r.json();if(r.ok&&d.data){msg.className="gc-msg ok";msg.textContent="🎉 兑换成功！"+( d.message||d.data.message||"已到账");document.getElementById("gc-code").value=""}else{msg.className="gc-msg err";msg.textContent=d.message||d.errors?.code?.[0]||"兑换失败"}}catch(e){msg.className="gc-msg err";msg.textContent="网络错误，请重试"}btn.disabled=false;btn.textContent="兑换"}</script>
HTMLEND;

// Escape for JSON
$escaped = addslashes($html);

// Connect to DB via PDO
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=xboard', 'xboard', 'xboard');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get current value
    $stmt = $pdo->query("SELECT value FROM v2_settings WHERE name = 'theme_xboard'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $config = json_decode($row['value'], true);
        $config['custom_html'] = $html;
        $newValue = json_encode($config, JSON_UNESCAPED_UNICODE);

        $update = $pdo->prepare("UPDATE v2_settings SET value = ? WHERE name = 'theme_xboard'");
        $update->execute([$newValue]);
        echo "UPDATED OK\n";
    } else {
        echo "NOT FOUND\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
