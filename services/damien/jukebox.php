<?php
// =============================================================================
// jukebox.php — Module Jukebox (Albums + Radios) pour MiniPavi
// =============================================================================
require_once __DIR__ . '/MiniPaviCli.php';
require_once __DIR__ . '/jukebox_db.php';
use MiniPavi\MiniPaviCli;
error_reporting(E_ERROR);
ini_set('display_errors', 0);

function jb_centre(string $text, int $width = 40): string {
    $len = strlen($text);
    $left = intdiv($width - $len, 2);
    return str_repeat(' ', max(0, $left)) . $text;
}

function jb_trunc(string $text, int $maxLen): string {
    if (strlen($text) <= $maxLen) return $text;
    return substr($text, 0, $maxLen - 1) . '.';
}

function jb_play_album(string $album_path): string {
    $esc = escapeshellarg($album_path);
    exec("/usr/bin/mpc clear 2>/dev/null; /usr/bin/mpc search base $esc 2>/dev/null | /usr/bin/mpc add 2>/dev/null; /usr/bin/mpc play 2>/dev/null >/dev/null 2>&1 &");
    return "Lance !";
}

function jb_play_radio(string $url): string {
    $esc = escapeshellarg($url);
    exec("/usr/bin/mpc clear 2>/dev/null; /usr/bin/mpc add $esc 2>/dev/null; /usr/bin/mpc play 2>/dev/null >/dev/null 2>&1 &");
    return "Lance !";
}

try {
    MiniPaviCli::start();
    $fctn = MiniPaviCli::$fctn;
    if ($fctn === 'FIN') exit;

    $menu = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/';
    $self = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
    $vdt = '';
    $cmd = null;

    if ($fctn === 'SOMMAIRE') {
        MiniPaviCli::send('', $menu, serialize([]), true, null, 'yes-cnx');
        exit;
    }

    $rawContent = MiniPaviCli::$content;
    $input = is_array($rawContent) ? trim(implode('', $rawContent)) : trim((string)$rawContent);
    $input = rtrim($input, '. ');

    if ($fctn === 'CNX' || $fctn === 'DIRECTCNX') {
        $context = ['step' => 'menu', 'page' => 0, 'message' => ''];
    } else {
        $context = unserialize(MiniPaviCli::$context);
    }

    $step = $context['step'] ?? 'menu';
    $page = (int)($context['page'] ?? 0);
    $message = $context['message'] ?? '';

    $perPage = 6;

    // === MODE MENU (sous-menu Albums/Radios) ===
    if ($step === 'menu') {
        if ($fctn === 'RETOUR') {
            // RETOUR au sous-menu = retour au menu principal
            MiniPaviCli::send('', $menu, serialize([]), true, null, 'yes-cnx');
            exit;
        }
        if ($fctn === 'ENVOI') {
            if ($input === '1') {
                $step = 'albums'; $page = 0; $message = '';
                $context = ['step' => 'albums', 'page' => 0, 'message' => '', '_skip' => true];
            } elseif ($input === '2') {
                $step = 'radios'; $page = 0; $message = '';
                $context = ['step' => 'radios', 'page' => 0, 'message' => '', '_skip' => true];
            }
        }
        if ($step === 'menu') {
            $context = ['step' => 'menu', 'page' => 0, 'message' => ''];
        }
    }

    // === MODE ALBUMS ===
    if ($step === 'albums') {
        $allAlbums = jukebox_list();
        $totalPages = max(1, (int)ceil(count($allAlbums) / $perPage));
        $skip = ($context['_skip'] ?? false);

        $modeChanged = false;
        if ($fctn === 'RETOUR' && $page === 0) {
            $step = 'menu'; $page = 0; $message = ''; $modeChanged = true;
        } elseif ($fctn === 'SUITE' && !$skip) { $page = min($totalPages - 1, $page + 1); $message = ''; }
        elseif ($fctn === 'RETOUR' && !$skip) { $page = max(0, $page - 1); $message = ''; }
        elseif ($fctn === 'ENVOI' && strlen($input) > 0 && !$skip) {
            $choice = (int)$input;
            if ($choice >= 1 && $choice <= $perPage) {
                $idx = $page * $perPage + $choice - 1;
                if ($idx < count($allAlbums)) {
                    $album = $allAlbums[$idx];
                    $result = jb_play_album($album['album_path']);
                    $message = "Lecture: " . jb_trunc(($album['album_name'] ?? $album['album_path']), 50) . " (" . $result . ")";
                }
            }
        }
        if ($modeChanged) {
            $context = ['step' => 'menu', 'page' => 0, 'message' => ''];
        } else {
            unset($context['_skip']);
            $context = array_merge($context, ['step' => 'albums', 'page' => $page, 'message' => $message]);
        }
    }

    // === MODE RADIOS ===
    if ($step === 'radios') {
        $allRadios = radio_list();
        $totalPages = max(1, (int)ceil(count($allRadios) / $perPage));
        $skip = ($context['_skip'] ?? false);

        $modeChanged = false;
        if ($fctn === 'RETOUR' && $page === 0) {
            $step = 'menu'; $page = 0; $message = ''; $modeChanged = true;
        } elseif ($fctn === 'SUITE' && !$skip) { $page = min($totalPages - 1, $page + 1); $message = ''; }
        elseif ($fctn === 'RETOUR' && !$skip) { $page = max(0, $page - 1); $message = ''; }
        elseif ($fctn === 'ENVOI' && strlen($input) > 0 && !$skip) {
            $choice = (int)$input;
            if ($choice >= 1 && $choice <= $perPage) {
                $idx = $page * $perPage + $choice - 1;
                if ($idx < count($allRadios)) {
                    $radio = $allRadios[$idx];
                    $result = jb_play_radio($radio['url']);
                    $message = "Lecture: " . jb_trunc($radio['name'], 50) . " (" . $result . ")";
                }
            }
        }
        if ($modeChanged) {
            $context = ['step' => 'menu', 'page' => 0, 'message' => ''];
        } else {
            unset($context['_skip']);
            $context = array_merge($context, ['step' => 'radios', 'page' => $page, 'message' => $message]);
        }
    }

    // === CONSTRUCTION ÉCRAN ===
    $vdt .= MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;

    // Barre de statut
    $vdt .= MiniPaviCli::writeLine0('*** 3615 DAMIEN — JUKEBOX ***');

    // Titre
    $vdt .= MiniPaviCli::setPos(1, 1) . VDT_BGBLUE . VDT_TXTYELLOW . VDT_SZDBLH
          . jb_centre('*    JUKEBOX    *', 40);
    $vdt .= MiniPaviCli::setPos(1, 2) . VDT_BGBLUE . VDT_TXTYELLOW . VDT_SZDBLH
          . jb_centre('*    JUKEBOX    *', 40);

    if ($step === 'menu') {
        // Sous-menu
        $vdt .= MiniPaviCli::setPos(1, 4) . VDT_TXTCYAN . str_repeat('=', 40);
        $vdt .= MiniPaviCli::setPos(1, 5) . VDT_TXTYELLOW . jb_centre('CHOISISSEZ UN MODE', 40);
        $vdt .= MiniPaviCli::setPos(1, 7) . VDT_TXTCYAN . VDT_FDINV . ' 1 ' . VDT_FDNORM . VDT_TXTWHITE . '  ALBUMS';
        $vdt .= MiniPaviCli::setPos(7, 8) . VDT_TXTGREEN . 'Vos albums preferes';
        $vdt .= MiniPaviCli::setPos(1, 10) . VDT_TXTCYAN . VDT_FDINV . ' 2 ' . VDT_FDNORM . VDT_TXTWHITE . '  RADIOS';
        $vdt .= MiniPaviCli::setPos(7, 11) . VDT_TXTGREEN . 'Radios francaises';
        $vdt .= MiniPaviCli::setPos(1, 14) . VDT_TXTCYAN . str_repeat('=', 40);
        $vdt .= MiniPaviCli::setPos(1, 16) . VDT_TXTMAGENTA . jb_centre(count(jukebox_list()) . ' albums / ' . count(radio_list()) . ' radios', 40);
        $vdt .= MiniPaviCli::setPos(1, 18) . VDT_TXTCYAN . jb_centre('[SOMMAIRE] Menu principal', 40);
        $vdt .= MiniPaviCli::setPos(1, 20) . VDT_TXTYELLOW . '  Votre choix : ';
        $cmd = MiniPaviCli::createInputTxtCmd(17, 20, 1, MSK_ENVOI | MSK_SOMMAIRE | MSK_RETOUR, true, '_');

    } else {
        // Mode albums ou radios
        $isRadio = ($step === 'radios');
        $items = $isRadio ? radio_list() : jukebox_list();
        $totalPages = max(1, (int)ceil(count($items) / $perPage));
        $label = $isRadio ? 'RADIOS' : 'ALBUMS';

        $vdt .= MiniPaviCli::setPos(1, 3) . VDT_BGBLACK . VDT_TXTYELLOW . VDT_SZNORM
              . jb_centre($label . ' DISPONIBLES', 40);
        $vdt .= MiniPaviCli::setPos(1, 4) . VDT_TXTCYAN . str_repeat('=', 40);

        $startIdx = $page * $perPage;
        for ($i = 0; $i < $perPage; $i++) {
            $row = 5 + $i * 2;
            $idx = $startIdx + $i;
            if ($idx < count($items)) {
                $item = $items[$idx];
                $num = $i + 1;
                if ($isRadio) {
                    $vdt .= MiniPaviCli::setPos(2, $row)
                          . VDT_TXTWHITE . VDT_FDINV . " $num " . VDT_FDNORM
                          . VDT_TXTCYAN . ' ' . MiniPaviCli::toG2(jb_trunc($item['name'], 33));
                } else {
                    $vdt .= MiniPaviCli::setPos(2, $row)
                          . VDT_TXTWHITE . VDT_FDINV . " $num " . VDT_FDNORM
                          . VDT_TXTCYAN . ' ' . MiniPaviCli::toG2(jb_trunc($item['artist'], 33));
                    $vdt .= MiniPaviCli::setPos(7, $row + 1)
                          . VDT_TXTGREEN . MiniPaviCli::toG2(jb_trunc($item['album_name'] ?? $item['album_path'], 33));
                }
            }
        }

        $vdt .= MiniPaviCli::setPos(1, 17) . VDT_TXTCYAN . str_repeat('=', 40);

        if ($message) {
            $vdt .= MiniPaviCli::setPos(1, 18) . VDT_TXTWHITE
                  . MiniPaviCli::toG2(jb_centre('>> ' . jb_trunc($message, 36) . ' <<', 40));
        } else {
            $vdt .= MiniPaviCli::setPos(1, 18) . VDT_TXTGREEN
                  . jb_centre(count($items) . ' ' . ($isRadio ? 'radios' : 'albums') . ' disponibles', 40);
        }

        $navText = '';
        if ($page > 0) $navText .= '[RETOUR] ';
        $navText .= 'Pg ' . ($page + 1) . '/' . $totalPages;
        if ($page < $totalPages - 1) $navText .= ' [SUITE]';
        $vdt .= MiniPaviCli::setPos(1, 20) . VDT_TXTYELLOW . jb_centre($navText, 40);

        $maxN = min($perPage, count($items) - $startIdx);
        $vdt .= MiniPaviCli::setPos(1, 21) . VDT_TXTGREEN
              . jb_centre("Tapez 1-$maxN + ENVOI pour lancer", 40);
        $vdt .= MiniPaviCli::setPos(1, 22) . VDT_TXTCYAN
              . jb_centre('[SOMMAIRE] Menu | [RETOUR] Mode', 40);

        $vdt .= MiniPaviCli::setPos(1, 23) . VDT_TXTYELLOW . '  Votre choix : ';
        $cmd = MiniPaviCli::createInputTxtCmd(17, 23, 1, MSK_ENVOI | MSK_SUITE | MSK_RETOUR | MSK_SOMMAIRE, true, '_');
    }

    MiniPaviCli::send($vdt, $self, serialize($context), true, $cmd, false);

} catch (Exception $e) {}
exit;
