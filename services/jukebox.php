<?php
require_once __DIR__ . '/damien/jukebox_db.php';

if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    $action = $_GET['api'] ?? '';

    // Albums
    if ($action === 'add') {
        jukebox_add($_GET['artist'] ?? '', $_GET['path'] ?? '', $_GET['name'] ?? '');
        echo json_encode(['ok' => true]);
    } elseif ($action === 'remove') {
        jukebox_remove($_GET['artist'] ?? '', $_GET['path'] ?? '');
        echo json_encode(['ok' => true]);
    } elseif ($action === 'album_up') {
        album_move($_GET['artist'] ?? '', $_GET['path'] ?? '', 'up');
        echo json_encode(['ok' => true]);
    } elseif ($action === 'album_down') {
        album_move($_GET['artist'] ?? '', $_GET['path'] ?? '', 'down');
        echo json_encode(['ok' => true]);
    } elseif ($action === 'selected') {
        $sel = [];
        foreach (jukebox_list() as $a) $sel[$a['artist'] . '|||' . $a['album_path']] = $a['album_name'];
        echo json_encode($sel);
    } elseif ($action === 'albums') {
        $out = shell_exec("/usr/bin/mpc list album 2>/dev/null");
        $albumNames = array_filter(explode("\n", (string)$out));
        $result = [];
        foreach ($albumNames as $alb) {
            $esc = escapeshellarg($alb);
            $info = shell_exec("/usr/bin/mpc -f \"%artist%@@@%file%\" find album $esc 2>/dev/null | head -1");
            if ($info && strpos($info, "@@@") !== false) {
                list($artist, $file) = explode("@@@", trim($info), 2);
                if ($artist && $file) $result[] = ["artist" => $artist, "album" => $alb, "path" => dirname($file)];
            }
        }
        echo json_encode($result);
    }
    // Radios
    elseif ($action === 'radios') {
        echo json_encode(radio_list());
    } elseif ($action === 'radio_add') {
        radio_add($_GET['name'] ?? '', $_GET['url'] ?? '');
        echo json_encode(['ok' => true]);
    } elseif ($action === 'radio_del') {
        radio_remove((int)($_GET['id'] ?? 0));
        echo json_encode(['ok' => true]);
    } elseif ($action === 'radio_up') {
        radio_move((int)($_GET['id'] ?? 0), 'up');
        echo json_encode(['ok' => true]);
    } elseif ($action === 'radio_down') {
        radio_move((int)($_GET['id'] ?? 0), 'down');
        echo json_encode(['ok' => true]);
    }
    exit;
}

$tab = $_GET['tab'] ?? 'albums';
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Jukebox</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0d1117;color:#c9d1d9;font-family:-apple-system,sans-serif;padding:20px}
.panel{max-width:750px;margin:0 auto}
h1{font-size:.85rem;color:#8b949e;letter-spacing:2px;margin-bottom:20px;text-transform:uppercase;text-align:center}
.tabs{display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid #30363d}
.tabs a{color:#8b949e;text-decoration:none;padding:8px 20px;font-size:.8rem;border-bottom:2px solid transparent;margin-bottom:-2px;transition:.2s}
.tabs a.active{color:#58a6ff;border-bottom-color:#58a6ff}
.tabs a:hover{color:#c9d1d9}
input[type=text]{width:100%;background:#0d1117;border:1px solid #30363d;color:#c9d1d9;padding:8px 12px;border-radius:8px;font-size:.85rem;margin-bottom:12px}
input[type=text]:focus{outline:none;border-color:#58a6ff}
.list{list-style:none;max-height:55vh;overflow-y:auto;margin-bottom:20px}
.list li{display:flex;align-items:center;gap:10px;padding:6px 10px;border-bottom:1px solid #21262d;font-size:.85rem}
.list li:hover{background:#161b22}
.list li .info{flex:1;display:flex;flex-direction:column}
.list li .main{color:#c9d1d9}
.list li .sub{color:#8b949e;font-size:.75rem}
input[type=checkbox]{width:16px;height:16px;cursor:pointer;accent-color:#58a6ff;flex-shrink:0}
.add-form{display:flex;gap:8px;margin-bottom:16px}
.add-form input{flex:1}
.add-form button{background:#238636;color:#fff;border:none;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:.8rem;white-space:nowrap}
.add-form button:hover{background:#2ea043}
.btn{background:none;border:none;cursor:pointer;font-size:.8rem;padding:2px 6px;color:#8b949e;transition:.2s}
.btn:hover{color:#c9d1d9}
.btn-del{color:#f85149}.btn-del:hover{color:#ff7b72}
.btn-up{color:#8b949e}.btn-up:hover{color:#58a6ff}
.status{font-size:.75rem;color:#8b949e;margin-bottom:8px}
.radio-item,.sel-item{display:flex;align-items:center;gap:8px;padding:6px 10px;border-bottom:1px solid #21262d;font-size:.85rem}
.radio-item:hover,.sel-item:hover{background:#161b22}
.radio-item .info,.sel-item .info{flex:1}
.mover{display:flex;flex-direction:column;gap:0;line-height:1}
.mover button{background:none;border:none;color:#8b949e;cursor:pointer;font-size:.6rem;padding:0 2px}
.mover button:hover{color:#58a6ff}
</style>
</head>
<body>
<div class="panel">
<h1>Jukebox</h1>
<div class="tabs">
  <a href="?tab=albums" class="<?= $tab==='albums'?'active':'' ?>">Albums</a>
  <a href="?tab=radios" class="<?= $tab==='radios'?'active':'' ?>">Radios</a>
</div>

<?php if ($tab === 'albums'): ?>
<div id="album-panel">
  <input type="text" id="search" placeholder="Filtrer..." oninput="filterAlbums()">
  <div class="status" id="status">Chargement...</div>
  <ul class="list" id="album-list"></ul>
  <div class="status"><strong>Selection (ordre = affichage Minitel)</strong> <span id="sel-count"></span></div>
  <div id="selected-list"></div>
</div>
<?php else: ?>
<div id="radio-panel">
  <div class="add-form">
    <input type="text" id="rname" placeholder="Nom de la radio">
    <input type="text" id="rurl" placeholder="URL du flux (http://...)">
    <button onclick="addRadio()">Ajouter</button>
  </div>
  <div class="status" id="rstatus">Chargement...</div>
  <div id="radio-list"></div>
</div>
<?php endif; ?>
</div>

<?php if ($tab === 'albums'): ?>
<script>
var ALL=[], SELECTED={};
function esc(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

async function init(){
  document.getElementById('status').textContent='Chargement...';
  try { const r=await fetch('?api=albums'); ALL=await r.json(); document.getElementById('status').textContent=ALL.length+' albums'; }
  catch(e) { document.getElementById('status').textContent='Erreur'; return; }
  await loadSel(); render();
}

async function loadSel(){
  try { const r=await fetch('?api=selected'); SELECTED=await r.json(); renderSel(); } catch(e) {}
}

function render(){
  var q=(document.getElementById('search').value||'').toLowerCase();
  var filtered=q?ALL.filter(function(a){return (a.artist+' '+a.album).toLowerCase().includes(q);}):ALL;
  document.getElementById('album-list').innerHTML=filtered.map(function(a){
    var k=a.artist+'|||'+a.path;
    return '<li><input type="checkbox" data-artist="'+esc(a.artist)+'" data-path="'+esc(a.path)+'" data-name="'+esc(a.album)+'"'+(SELECTED[k]?' checked':'')+' onchange="toggle(this)"><span class="info"><span class="main">'+esc(a.album)+'</span><span class="sub">'+esc(a.artist)+'</span></span></li>';
  }).join('');
}

function renderSel(){
  // Build ordered list from SELECTED based on API order (which is by position)
  fetch('?api=list').then(function(r){return r.json();}).then(function(ordered){
    document.getElementById('sel-count').textContent=ordered.length+' albums';
    document.getElementById('selected-list').innerHTML=ordered.length===0
      ? '<span style="color:#8b949e">Aucun</span>'
      : ordered.map(function(a, i){
          return '<div class="sel-item"><div class="mover"><button onclick="moveAlbum(\''+esc(a.artist)+'\',\''+esc(a.album_path)+'\',\'up\')">&#9650;</button><button onclick="moveAlbum(\''+esc(a.artist)+'\',\''+esc(a.album_path)+'\',\'down\')">&#9660;</button></div><span class="info"><span class="main">'+esc(a.album_name||a.album_path)+'</span><span class="sub">'+esc(a.artist)+'</span></span><button class="btn btn-del" onclick="rm(\''+esc(a.artist)+'\',\''+esc(a.album_path)+'\')">x</button></div>';
        }).join('');
  });
}

async function toggle(cb){
  var a=cb.dataset.artist, p=cb.dataset.path, n=cb.dataset.name;
  if(cb.checked){ await fetch('?api=add&artist='+encodeURIComponent(a)+'&path='+encodeURIComponent(p)+'&name='+encodeURIComponent(n)); SELECTED[a+'|||'+p]=n; }
  else { await fetch('?api=remove&artist='+encodeURIComponent(a)+'&path='+encodeURIComponent(p)); delete SELECTED[a+'|||'+p]; }
  renderSel();
}
async function rm(a,p){
  await fetch('?api=remove&artist='+encodeURIComponent(a)+'&path='+encodeURIComponent(p));
  delete SELECTED[a+'|||'+p]; renderSel();
  var cb=document.querySelector('input[data-artist="'+a.replace(/"/g,'&quot;')+'"][data-path="'+p.replace(/"/g,'&quot;')+'"]');
  if(cb) cb.checked=false;
}
async function moveAlbum(a,p,dir){
  await fetch('?api=album_'+dir+'&artist='+encodeURIComponent(a)+'&path='+encodeURIComponent(p));
  renderSel();
}
function filterAlbums(){ render(); }
init();
</script>
<?php else: ?>
<script>
function escR(s){ return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

async function loadRadios(){
  document.getElementById('rstatus').textContent='Chargement...';
  try {
    var r=await fetch('?api=radios'); var radios=await r.json();
    document.getElementById('rstatus').textContent=radios.length+' radios';
    document.getElementById('radio-list').innerHTML=radios.map(function(rd){
      return '<div class="radio-item"><div class="mover"><button onclick="moveRadio('+rd.id+',\'up\')">&#9650;</button><button onclick="moveRadio('+rd.id+',\'down\')">&#9660;</button></div><span class="info"><span class="main">'+escR(rd.name)+'</span><span class="sub">'+escR(rd.url)+'</span></span><button class="btn btn-del" onclick="delRadio('+rd.id+')">x</button></div>';
    }).join('');
  } catch(e) { document.getElementById('rstatus').textContent='Erreur'; }
}
async function addRadio(){
  var n=document.getElementById('rname').value.trim(), u=document.getElementById('rurl').value.trim();
  if(!n||!u) return;
  await fetch('?api=radio_add&name='+encodeURIComponent(n)+'&url='+encodeURIComponent(u));
  document.getElementById('rname').value=''; document.getElementById('rurl').value='';
  loadRadios();
}
async function delRadio(id){ await fetch('?api=radio_del&id='+id); loadRadios(); }
async function moveRadio(id,dir){ await fetch('?api=radio_'+dir+'&id='+id); loadRadios(); }
loadRadios();
</script>
<?php endif; ?>
</body>
</html>
