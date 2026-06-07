<?php
// Auto-detect host for gateway WebSocket URL (works on both VPS and local)
$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$wsProto = $isSecure ? 'wss' : 'ws';

// Gateway WebSocket URL: explicit param, or auto-detect from host
$gw = $_REQUEST['gw'] ?? ($wsProto . '://' . $host . '/ws');

if (isset($_REQUEST['url'])) {
    // Explicit service URL provided (Andre mode, backward compatible)
    $url = $gw . '?url=' . urlencode($_REQUEST['url']);
} elseif (isset($_REQUEST['gw'])) {
    // gw provided but no url — just connect to gateway
    $url = $gw;
} else {
    // No params: default VPS/web deployment
    // Gateway runs on localhost, use internal URL to avoid loopback through internet
    $svcUrl = 'http://127.0.0.1/damien/';
    $url = $gw . '?url=' . urlencode($svcUrl);
}

// Show keyboard by default on web; hide in kiosk mode (?kiosk=1)
$showKeyboard = !isset($_REQUEST['kiosk']);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Minitel 3615 DAMIEN</title>
  <link rel="stylesheet" href="css/minitel-keyboard.css" />
  <link rel="stylesheet" href="css/minitel-minipavi-webmedia.css" />
  <link rel="stylesheet" href="css/crt.css" />
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    html, body { width: 100%; height: 100%; overflow: hidden; background: #202020; }
    x-minitel {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      width: 100vw;
      height: 100vh;
    }
    #screen-area {
      display: flex;
      justify-content: center;
      align-items: center;
      flex-shrink: 1;
      min-height: 0;
      width: 100%;
      background: black;
    }
    #oldeffect {
      display: flex;
      justify-content: flex-start;
      align-items: flex-start;
      height: 100%;
    }
    canvas.minitel-screen, canvas.minitel-cursor {
      image-rendering: pixelated;
      image-rendering: crisp-edges;
    }
    <?php if ($showKeyboard): ?>
    /* Web: screen fills available space, keyboard below */
    #screen-area {
      flex: 1 1 auto;
      overflow: hidden;
    }
    canvas.minitel-screen, canvas.minitel-cursor {
      width: auto !important;
      max-width: 100% !important;
      height: 100% !important;
    }
    #keyboard-area {
      flex: 0 0 auto;
      width: 100%;
      max-width: 50em;
      padding: 0.3em;
      background: #202020;
    }
    <?php else: ?>
    /* Kiosk mode: full screen, no keyboard */
    #screen-area {
      flex: 1 1 100%;
    }
    canvas.minitel-screen, canvas.minitel-cursor {
      width: auto !important;
      height: 100vh !important;
    }
    #keyboard-area { display: none; }
    <?php endif; ?>
  </style>
</head>
<body>
  <x-minitel id="emul-1" data-socket="<?php echo $url; ?>" data-speed="1200" data-color="true">
    <div id="screen-area">
      <div id="oldeffect" class="minitel-oldeffect-off">
        <canvas class="minitel-screen" data-minitel="screen"></canvas>
        <canvas class="minitel-cursor" data-minitel="cursor"></canvas>
        <div id="scanlines" class="minitel-scanlines-off"></div>
        <div id="crt" class="minitel-crt-off"></div>
      </div>
    </div>
    <div id="keyboard-area">
      <import src="import/minitel-minipavi-webmedia.html"></import>
      <import src="import/minitel-keyboard.html"></import>
    </div>
    <audio class="minitel-beep" data-minitel="beep">
      <source src="sound/minitel-bip.mp3" type="audio/mpeg"/>
    </audio>
  </x-minitel>

  <script src="library/generichelper/generichelper.js"></script>
  <script src="library/import-html/import-html.js"></script>
  <script src="library/autocallback/autocallback.js"></script>
  <script src="library/query-parameters/query-parameters.js"></script>
  <script src="library/finite-stack/finite-stack.js"></script>
  <script src="library/key-simulator/key-simulator.js"></script>
  <script src="library/settings-suite/settings-suite.js"></script>
  <script src="library/minitel/constant.js"></script>
  <script src="library/minitel/protocol.js"></script>
  <script src="library/minitel/elements.js"></script>
  <script src="library/minitel/text-grid.js"></script>
  <script src="library/minitel/char-size.js"></script>
  <script src="library/minitel/font-sprite.js"></script>
  <script src="library/minitel/page-cell.js"></script>
  <script src="library/minitel/vram.js"></script>
  <script src="library/minitel/vdu-cursor.js"></script>
  <script src="library/minitel/vdu.js"></script>
  <script src="library/minitel/decoder.js"></script>
  <script src="library/minitel/keyboard.js"></script>
  <script src="library/minitel/minitel-emulator.js"></script>
  <script src="library/minitel/start-emulators.js"></script>
  <script src="library/minitel/minitel-minipavi-webmedia.js"></script>
  <script src="app/minitel.js"></script>
</body>
</html>
