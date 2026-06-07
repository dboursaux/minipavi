<?php
// =============================================================================
// index.php — Menu principal du service MiniPavi "damien"
// =============================================================================
require_once __DIR__ . '/MiniPaviCli.php';
use MiniPavi\MiniPaviCli;
error_reporting(E_ERROR);
ini_set('display_errors', 0);

// Centre $text dans une colonne de $width caractères (remplie avec des espaces)
function centerPad(string $text, int $width): string {
    $len   = strlen($text);
    $left  = intdiv($width - $len, 2);
    $right = $width - $len - $left;
    return str_repeat(' ', max(0, $left)) . $text . str_repeat(' ', max(0, $right));
}

try {
    MiniPaviCli::start();
    $fctn  = MiniPaviCli::$fctn;
    if ($fctn === 'FIN') exit;

    $context = ($fctn === 'CNX' || $fctn === 'DIRECTCNX')
        ? ['step' => 'menu']
        : unserialize(MiniPaviCli::$context);

    $input = trim(MiniPaviCli::$content[0] ?? '');
    $self  = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
    $base  = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/';
    $vdt   = '';

    // ── Traitement du choix ───────────────────────────────────────────────────
    if (($context['step'] ?? '') === 'saisie' && $fctn === 'ENVOI') {
        $modules = [
            '1' => 'google.php',
            '2' => 'horoscope.php',
            '3' => 'confessionnal.php',
            '4' => 'annonces.php',
            '5' => 'jukebox.php',
            '6' => 'olla.php',
        ];
        if (isset($modules[$input])) {
            MiniPaviCli::send('', $base . $modules[$input], serialize([]), true, null, 'yes-cnx');
            exit;
        }
    }

    // ── Construction de l'écran ───────────────────────────────────────────────
    $vdt .= MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;

    // Barre de statut (ligne 0)
    $vdt .= MiniPaviCli::writeLine0('*** BIENVENUE SUR 3615 DAMIEN ***');

    // ── Bloc titre (rows 1-2) — fond bleu, jaune, double hauteur ─────────────
    $titleRow = centerPad('*  3615  DAMIEN  *', 40);
    $vdt .= MiniPaviCli::setPos(1, 1) . VDT_BGBLUE . VDT_TXTYELLOW . VDT_SZDBLH . $titleRow;
    $vdt .= MiniPaviCli::setPos(1, 2) . VDT_BGBLUE . VDT_TXTYELLOW . VDT_SZDBLH . $titleRow;

    // ── Sous-titre (row 3) ────────────────────────────────────────────────────
    $vdt .= MiniPaviCli::setPos(1, 3) . VDT_BGBLACK . VDT_TXTYELLOW . VDT_SZNORM
          . centerPad('LE MINITEL QUI PENSE', 40);

    // ── Séparateur haut (row 4) ───────────────────────────────────────────────
    $vdt .= MiniPaviCli::setPos(1, 4) . VDT_BGBLACK . VDT_TXTCYAN . str_repeat('=', 40);

    // ── Six items du menu (rows 5-16) ─────────────────────────────────────────
    // Chaque item occupe 2 rows : titre / sous-titre
    // Le numéro est affiché en vidéo inverse (case colorée style touche clavier)
    $items = [
        1 => ['color' => VDT_TXTCYAN,    'label' => 'GOOGLE - INTELLIGENCE IA',  'sub' => "Questions a l'IA Claude"],
        2 => ['color' => VDT_TXTYELLOW,  'label' => 'HOROSCOPE DU JOUR',          'sub' => 'Decouvrez votre destin'],
        3 => ['color' => VDT_TXTMAGENTA, 'label' => 'LE CONFESSIONNAL',           'sub' => 'Avouez et soyez absous'],
        4 => ['color' => VDT_TXTRED,     'label' => 'PETITES ANNONCES',           'sub' => 'Annonces fictives par IA'],
        5 => ['color' => VDT_TXTGREEN,   'label' => 'JUKEBOX',                     'sub' => 'Lancez vos albums preferes'],
        6 => ['color' => VDT_TXTMAGENTA, 'label' => '3615 OLLA',                    'sub' => 'Rencontres gourmandes'],
    ];

    foreach ($items as $n => $item) {
        $row = 4 + ($n - 1) * 2 + 1;  // rows 5, 7, 9, 11, 13, 15
        $vdt .= MiniPaviCli::setPos(2, $row)
              . $item['color'] . VDT_FDINV . " $n " . VDT_FDNORM
              . VDT_TXTWHITE . '  ' . $item['label'];
        $vdt .= MiniPaviCli::setPos(7, $row + 1)
              . VDT_TXTGREEN . $item['sub'];
    }

    // ── Séparateur bas (row 18) ───────────────────────────────────────────────
    $vdt .= MiniPaviCli::setPos(1, 18) . VDT_TXTCYAN . str_repeat('=', 40);

    // ── Signature (row 19) ────────────────────────────────────────────────────
    $vdt .= MiniPaviCli::setPos(1, 19) . VDT_TXTCYAN
          . centerPad('Propulse par Claude (Anthropic)', 40);

    // ── Prompt de saisie (rows 21-22) ────────────────────────────────────────
    // MSK_AUTOSEND : soumet dès la 1re touche — pas besoin d'appuyer sur ENVOI
    $vdt .= MiniPaviCli::setPos(1, 21) . VDT_TXTGREEN
          . centerPad('(tapez directement le numero)', 40);
    $vdt .= MiniPaviCli::setPos(1, 22) . VDT_TXTYELLOW . '  Votre choix : ';

    $cmd = MiniPaviCli::createInputTxtCmd(17, 22, 1, MSK_ENVOI | 256, true, '_');

    MiniPaviCli::send($vdt, $self, serialize(['step' => 'saisie']), true, $cmd, false);

} catch (Exception $e) {}
exit;
