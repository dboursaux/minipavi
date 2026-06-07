<?php
header('Content-Type: text/html; charset=utf-8');

function mpd($cmd) {
    $s = fsockopen('127.0.0.1', 6600, $e, $r, 2);
    if (!$s) return [];
    fgets($s); fwrite($s, $cmd . "\n");
    $o = [];
    while (!feof($s)) {
        $l = fgets($s);
        if ($l === false) break;
        $l = rtrim($l);
        if ($l === 'OK' || $l === 'list OK') break;
        if (preg_match('/^ACK/', $l)) break;
        $o[] = $l;
    }
    fclose($s);
    return $o;
}

$status = mpd('status');
$vol = 0; $state = 'stop'; $song = ''; $artist = ''; $elapsed = 0; $duration = 1;
foreach ($status as $l) {
    if (preg_match('/^volume: (\d+)/', $l, $m)) $vol = (int)$m[1];
    if (preg_match('/^state: (.+)/', $l, $m)) $state = $m[1];
    if (preg_match('/^elapsed: ([\d.]+)/', $l, $m)) $elapsed = (float)$m[1];
    if (preg_match('/^duration: ([\d.]+)/', $l, $m)) $duration = (float)$m[1];
}
$cur = mpd('currentsong');
foreach ($cur as $l) {
    if (preg_match('/^Title: (.+)/', $l, $m)) $song = $m[1];
    if (preg_match('/^Artist: (.+)/', $l, $m)) $artist = $m[1];
}

$a = $_GET['a'] ?? '';
$v = $_GET['v'] ?? '';
switch ($a) {
    case 'play': mpd('play'); break;
    case 'pause': mpd('pause 1'); break;
    case 'next': mpd('next'); break;
    case 'prev': mpd('previous'); break;
    case 'volup': mpd('setvol ' . min(100, $vol+5)); break;
    case 'voldown': mpd('setvol ' . max(0, $vol-5)); break;
    case 'random': mpd('random'); mpd('play'); break;
    case 'addplay':
        mpd('clear');
        mpd('add "' . addslashes($v) . '"');
        mpd('play');
        break;
    case 'addalbum':
        mpd('clear');
        $tracks = mpd('find album "' . addslashes($v) . '"');
        foreach ($tracks as $l) {
            if (preg_match('/^file: (.+)/', $l, $m)) {
                mpd('add "' . addslashes($m[1]) . '"');
            }
        }
        mpd('play');
        break;
    case 'add':
        mpd('add "' . addslashes($v) . '"');
        break;
}
if ($a) {
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

$artist = $_GET['artist'] ?? '';
$album = $_GET['album'] ?? '';

$pct = $duration > 0 ? min(100, round($elapsed*100/$duration)) : 0;
$isplaying = $state === 'play';
$refresh = $isplaying ? 8 : 30;
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Andre</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#1a1a2e;color:#e0e0e0;font-family:-apple-system,sans-serif;display:flex;justify-content:center;padding:20px;min-height:100vh}
.app{max-width:600px;width:100%}
.now{background:#16213e;border-radius:20px;padding:24px;text-align:center;margin-bottom:20px;box-shadow:0 10px 40px rgba(0,0,0,0.4)}
.now h1{font-size:.75rem;color:#888;letter-spacing:2px;text-transform:uppercase;margin-bottom:4px}
.now .song{font-size:1.2rem;font-weight:600;color:#fff}
.now .artist{font-size:.9rem;color:#aaa;margin-bottom:12px}
.now .prog{background:#0f3460;border-radius:10px;height:4px;overflow:hidden;margin:12px 0}
.now .prog div{background:#e94560;height:100%;border-radius:10px;transition:width 2s linear}
.now .ctrl{display:flex;justify-content:center;gap:14px;align-items:center}
.now .btn{background:#0f3460;color:#fff;border:none;width:44px;height:44px;border-radius:50%;font-size:1.1rem;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;text-decoration:none}
.now .btn:hover{background:#e94560}
.now .btn.play{width:56px;height:56px;font-size:1.4rem}
.now .vol{display:flex;align-items:center;gap:6px;margin-top:12px;justify-content:center;font-size:.8rem;color:#666}
.now .state{display:inline-block;width:6px;height:6px;border-radius:50%;margin-right:6px}
.now .state.play{background:#4ecca3}.now .state.pause{background:#f0a500}.now .state.stop{background:#e94560}
.lib{background:#16213e;border-radius:20px;padding:20px;box-shadow:0 10px 40px rgba(0,0,0,0.4);margin-bottom:20px}
.lib h2{font-size:.8rem;color:#888;margin-bottom:16px;letter-spacing:1px}
.breadcrumb{font-size:.75rem;color:#666;margin-bottom:12px}
.breadcrumb a{color:#e94560;text-decoration:none}
.list{list-style:none}
.list li{border-bottom:1px solid rgba(255,255,255,0.05)}
.list li:last-child{border-bottom:none}
.list a{display:block;padding:12px 8px;color:#e0e0e0;text-decoration:none;border-radius:8px;transition:all .15s;font-size:.95rem}
.list a:hover{background:rgba(233,69,96,0.15);color:#e94560}
.flex{display:flex;align-items:center;gap:8px}
.flex1{flex:1}
.act{background:#0f3460;color:#fff;border:none;padding:6px 12px;border-radius:6px;font-size:.7rem;cursor:pointer;text-decoration:none;white-space:nowrap;display:inline-block}
.act:hover{background:#e94560}
.queue{font-size:.8rem;color:#888}
.queue li{padding:8px;border-bottom:1px solid rgba(255,255,255,0.05);display:flex;align-items:center;gap:8px}
.queue .pos{color:#e94560;font-weight:600;width:24px;text-align:right;flex-shrink:0}
</style>
</head>
<body>
<div class="app">
  <div class="now">
    <h1>Andre</h1>
    <div class="song"><?= htmlspecialchars($song ?: '--') ?></div>
    <div class="artist"><?= htmlspecialchars($artist) ?></div>
    <div class="prog"><div style="width:<?= $pct ?>%"></div></div>
    <div style="font-size:.7rem;color:#666"><span class="state <?= $state ?>"></span><?= ucfirst($state) ?> &middot; <?= $vol ?>%</div>
    <div class="ctrl">
      <a href="?a=prev" class="btn">&#x23EE;</a>
      <a href="?a=<?= $isplaying ? 'pause' : 'play' ?>" class="btn play"><?= $isplaying ? '&#x23F8;' : '&#x25B6;' ?></a>
      <a href="?a=next" class="btn">&#x23ED;</a>
    </div>
    <div class="vol">
      <a href="?a=voldown" class="btn" style="width:30px;height:30px;font-size:.7rem">&minus;</a>
      <span><?= $vol ?>%</span>
      <a href="?a=volup" class="btn" style="width:30px;height:30px;font-size:.7rem">+</a>
      <span style="margin-left:12px"><a href="?a=random" class="btn" style="width:30px;height:30px;font-size:.7rem">&#x1F500;</a></span>
    </div>
  </div>

  <div class="lib">
    <?php if (!$artist && !$album): ?>
      <h2>Artistes (<?= count(mpd('list artist')) ?>)</h2>
      <ul class="list">
      <?php foreach (mpd('list artist') as $art): ?>
        <li><a href="?artist=<?= urlencode($art) ?>"><?= htmlspecialchars($art) ?></a></li>
      <?php endforeach; ?>
      </ul>
    <?php elseif ($artist && !$album): ?>
      <h2><?= htmlspecialchars($artist) ?></h2>
      <div class="breadcrumb"><a href="?">Artistes</a> &rsaquo; <?= htmlspecialchars($artist) ?></div>
      <ul class="list">
      <?php foreach (mpd('list album artist "' . addslashes($artist) . '"') as $alb): ?>
        <li><a href="?artist=<?= urlencode($artist) ?>&album=<?= urlencode($alb) ?>"><?= htmlspecialchars($alb) ?></a></li>
      <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <h2><?= htmlspecialchars($album) ?></h2>
      <div class="breadcrumb">
        <a href="?">Artistes</a> &rsaquo;
        <a href="?artist=<?= urlencode($artist) ?>"><?= htmlspecialchars($artist) ?></a> &rsaquo;
        <?= htmlspecialchars($album) ?>
      </div>
      <ul class="list">
      <?php
      $tracks = mpd('find album "' . addslashes($album) . '"');
      foreach ($tracks as $t):
          if (!preg_match('/^file: (.+)/', $t, $m)) continue;
          $file = $m[1];
          $title = basename($file);
      ?>
        <li class="flex">
          <span class="flex1"><?= htmlspecialchars($title) ?></span>
          <a href="?a=addplay&v=<?= urlencode($file) ?>" class="act">&#x25B6;</a>
          <a href="?a=add&v=<?= urlencode($file) ?>" class="act">+</a>
        </li>
      <?php endforeach; ?>
      </ul>
      <div style="margin-top:12px">
        <a href="?a=addalbum&v=<?= urlencode($album) ?>" class="act" style="padding:8px 20px">&#x25B6; Lire tout</a>
      </div>
    <?php endif; ?>
  </div>

  <?php $pl = mpd('playlistinfo'); if ($pl): ?>
  <div class="lib">
    <h2>File d'attente (<?= count($pl) ?>)</h2>
    <ul class="queue">
    <?php $i = 1; foreach ($pl as $l):
        if (preg_match('/^file: (.+)/', $l, $m)): $i++; ?>
        <li><span class="pos"><?= $i-1 ?></span><span><?= htmlspecialchars(basename($m[1])) ?></span></li>
    <?php endif; endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>
<meta http-equiv="refresh" content="<?= $refresh ?>">
</body>
</html>
