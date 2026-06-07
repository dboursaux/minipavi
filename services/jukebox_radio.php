<?php
require_once __DIR__ . '/damien/jukebox_db.php';

$action = $_GET['a'] ?? '';

// API
if ($action === 'play_radio') {
    $id = (int)($_GET['id'] ?? 0);
    foreach (radio_list() as $r) {
        if ($r['id'] === $id) {
            exec("/usr/bin/mpc clear 2>/dev/null; /usr/bin/mpc add ".escapeshellarg($r['url'])." 2>/dev/null; /usr/bin/mpc play 2>/dev/null >/dev/null 2>&1 &");
            echo json_encode(['ok'=>true,'name'=>$r['name']]); exit;
        }
    }
    echo json_encode(['ok'=>false]); exit;
}
if ($action === 'play_album') {
    $path = $_GET['path'] ?? '';
    exec("/usr/bin/mpc clear 2>/dev/null; /usr/bin/mpc search base ".escapeshellarg($path)." 2>/dev/null | /usr/bin/mpc add 2>/dev/null; /usr/bin/mpc play 2>/dev/null >/dev/null 2>&1 &");
    echo json_encode(['ok'=>true]); exit;
}
if ($action === 'pause') { exec('/usr/bin/mpc pause 2>/dev/null'); echo json_encode(['ok'=>true]); exit; }
if ($action === 'resume') { exec('/usr/bin/mpc play 2>/dev/null'); echo json_encode(['ok'=>true]); exit; }
if ($action === 'stop') { exec('/usr/bin/mpc stop 2>/dev/null'); echo json_encode(['ok'=>true]); exit; }
if ($action === 'volume') {
    $v = max(0, min(100, (int)($_GET['v'] ?? 50)));
    exec("/usr/bin/mpc volume $v 2>/dev/null");
    echo json_encode(['ok'=>true,'volume'=>$v]); exit;
}
if ($action === 'status') {
    $status = shell_exec('/usr/bin/mpc status 2>/dev/null');
    $current = shell_exec('/usr/bin/mpc current 2>/dev/null');
    $vol = 30; $state = 'stop';
    if (preg_match('/volume:\s*(\d+)%/', $status, $m)) $vol = (int)$m[1];
    if (preg_match('/\[(\w+)\]/', $status, $m)) $state = strtolower($m[1]);
    echo json_encode(['volume'=>$vol,'state'=>$state,'song'=>trim($current)]); exit;
}

$tab = $_GET['tab'] ?? 'radios';
$radios = radio_list();
$albums = jukebox_list();
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="theme-color" content="#0d1117">
<link rel="manifest" href="data:application/json,<?= urlencode('{"name":"Andre","short_name":"Andre","start_url":"/jukebox_radio.php","display":"standalone","background_color":"#0d1117","theme_color":"#0d1117","icons":[{"src":"data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="192" height="192" viewBox="0 0 192 192"><rect width="192" height="192" rx="40" fill="#16213e"/><text x="96" y="120" text-anchor="middle" font-size="80">📻</text></svg>') . '","sizes":"192x192","type":"image/svg+xml"}]}') ?>">
<title>André</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{background:#0d1117;color:#c9d1d9;font-family:-apple-system,sans-serif;min-height:100vh;display:flex;justify-content:center}
.app{max-width:500px;width:100%;padding:16px;padding-top:max(16px, env(safe-area-inset-top));padding-bottom:100px}
/* Now playing */
.now{background:linear-gradient(135deg,#1a1a3e,#16213e);border-radius:20px;padding:20px;text-align:center;margin-bottom:16px;box-shadow:0 8px 32px rgba(0,0,0,.3)}
.now .state{font-size:.6rem;text-transform:uppercase;letter-spacing:2px;color:#8b949e;margin-bottom:6px}
.now .state .dot{display:inline-block;width:6px;height:6px;border-radius:50%;margin-right:6px}
.now .state .dot.play{background:#3fb950}
.now .state .dot.pause{background:#f0a500}
.now .state .dot.stop{background:#f85149}
.now .song{font-size:1.1rem;font-weight:600;color:#fff;margin-bottom:2px;word-break:break-word}
.now .vol{display:flex;align-items:center;gap:8px;justify-content:center;margin:12px 0 8px}
.now .vol input[type=range]{-webkit-appearance:none;flex:1;max-width:160px;height:4px;border-radius:2px;background:#30363d;outline:none}
.now .vol input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;width:28px;height:28px;border-radius:50%;background:#58a6ff;cursor:pointer;border:2px solid #fff}
.now .vol .val{font-size:.7rem;color:#8b949e;min-width:30px;text-align:center;font-variant-numeric:tabular-nums}
.now .ctrl{display:flex;justify-content:center;gap:20px;align-items:center}
.now .ctrl button{background:#21262d;color:#c9d1d9;border:1px solid #30363d;width:44px;height:44px;border-radius:50%;font-size:1.1rem;cursor:pointer;transition:.15s;display:flex;align-items:center;justify-content:center}
.now .ctrl button:active{background:#58a6ff;border-color:#58a6ff;transform:scale(.95)}
.now .ctrl .play-btn{width:56px;height:56px;font-size:1.4rem;background:#238636;border-color:#238636;color:#fff}

/* Tabs */
.tabs{display:flex;gap:0;margin-bottom:16px;border-bottom:2px solid #30363d;position:sticky;top:0;background:#0d1117;z-index:10}
.tabs a{flex:1;color:#8b949e;text-decoration:none;padding:10px 0;font-size:.75rem;text-align:center;border-bottom:2px solid transparent;margin-bottom:-2px;transition:.2s;letter-spacing:.5px}
.tabs a.active{color:#58a6ff;border-bottom-color:#58a6ff}

/* List items */
.items{margin-bottom:16px}
.item{display:flex;align-items:center;gap:12px;padding:12px 10px;border-bottom:1px solid #21262d;cursor:pointer;transition:.15s;border-radius:8px}
.item:active{background:#161b22}
.item .name{flex:1;font-size:.9rem;color:#c9d1d9}
.item .sub{font-size:.72rem;color:#8b949e}
.item .ico{font-size:.9rem;color:#8b949e;width:20px;text-align:center}
</style>
</head>
<body>
<div class="app">
  <div class="now">
    <div class="state"><span class="dot stop" id="dot"></span><span id="state-text">Arrêté</span></div>
    <div class="song" id="song">André</div>
    <div class="vol">
      <span style="font-size:.8rem">🔈</span>
      <input type="range" id="vol-slider" min="0" max="100" value="30" oninput="setVolume(this.value)">
      <span style="font-size:.8rem">🔊</span>
      <span class="val" id="vol-val">30%</span>
    </div>
    <div class="ctrl">
      <button onclick="action('stop')">⏹</button>
      <button class="play-btn" id="play-btn" onclick="togglePlay()">▶</button>
      <button onclick="refreshStatus()">🔄</button>
    </div>
  </div>

  <div class="tabs">
    <a href="?tab=radios" class="<?= $tab==='radios'?'active':'' ?>">📻 Radios</a>
    <a href="?tab=albums" class="<?= $tab==='albums'?'active':'' ?>">💿 Albums</a>
  </div>

  <div class="items">
  <?php if ($tab === 'radios'): ?>
    <?php foreach ($radios as $r): ?>
    <div class="item" id="item-<?= $r['id'] ?>" onclick="playRadio(<?= $r['id'] ?>)">
      <span class="ico">📻</span>
      <span class="name"><?= htmlspecialchars($r['name']) ?></span>
    </div>
    <?php endforeach; ?>
  <?php else: ?>
    <?php foreach ($albums as $a): ?>
    <div class="item" id="item-<?= md5($a['album_path']) ?>" onclick="playAlbum('<?= htmlspecialchars($a['album_path'], ENT_QUOTES) ?>','<?= htmlspecialchars($a['artist'].' - '.($a['album_name']??$a['album_path']), ENT_QUOTES) ?>')">
      <span class="ico">💿</span>
      <span class="info"><span class="name"><?= htmlspecialchars($a['album_name'] ?? $a['album_path']) ?></span><span class="sub"><?= htmlspecialchars($a['artist']) ?></span></span>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
  </div>
</div>

<script>
var currentState='stop';

async function refreshStatus(){
  try {
    const r=await fetch('?a=status'); const s=await r.json();
    currentState=s.state;
    document.getElementById('dot').className='dot '+(s.state==='play'?'play':s.state==='pause'?'pause':'stop');
    document.getElementById('state-text').textContent=s.state==='play'?'Lecture':s.state==='pause'?'Pause':'Arrete';
    document.getElementById('song').textContent=s.song||'Andre';
    document.getElementById('vol-slider').value=s.volume;
    document.getElementById('vol-val').textContent=s.volume+'%';
    document.getElementById('play-btn').textContent=s.state==='play'?'⏸':'▶';
  } catch(e){}
}

async function playRadio(id){
  document.querySelectorAll('.item').forEach(el=>el.style.background='');
  const el=document.getElementById('item-'+id);
  if(el) el.style.background='#16213e';
  await fetch('?a=play_radio&id='+id);
  refreshStatus();
}

async function playAlbum(path, name){
  document.querySelectorAll('.item').forEach(el=>el.style.background='');
  await fetch('?a=play_album&path='+encodeURIComponent(path));
  refreshStatus();
}

async function togglePlay(){
  await fetch('?a='+(currentState==='play'?'pause':'resume'));
  refreshStatus();
}
async function setVolume(v){
  await fetch('?a=volume&v='+v);
  document.getElementById('vol-val').textContent=v+'%';
}
function action(a){ fetch('?a='+a).then(()=>refreshStatus()); }

refreshStatus();
setInterval(refreshStatus, 5000);
</script>
</body>
</html>
