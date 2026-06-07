<?php
// =============================================================================
// horoscope.php — LE KAMOUSCOPE : signes inventés + oracle déjanté mais sérieux
// =============================================================================
require_once __DIR__ . '/MiniPaviCli.php';
require_once __DIR__ . '/anthropic.php';
use MiniPavi\MiniPaviCli;
error_reporting(E_ERROR);
ini_set('display_errors', 0);

// ── Génère (ou relit depuis le cache) les 12 signes du jour ──────────────────
function getSignesDuJour(): array {
    $cache = '/tmp/kamouscope-signes-' . date('Y-m-d') . '.json';
    if (file_exists($cache)) {
        return json_decode(file_get_contents($cache), true);
    }

    $sys =
        'Tu es le KAMOUSCOPE, oracle cosmique officiel. ' .
        'Invente 12 nouveaux signes astrologiques pour aujourd\'hui. ' .
        'Chaque signe est UN SEUL NOM COMMUN francais existant, choisi au hasard ' .
        'parmi des objets du quotidien, animaux, aliments ou phenomenes. ' .
        'Les mots doivent etre banals et inattendus comme signe astrologique, max 14 lettres. ' .
        'Exemples de style : CAGEOT, ESCARGOT, FRIGO, SEMELLE, YAOURT, PERCEUSE, ' .
        'BOUCHON, EPONGE, GRAVIER, MOISISSURE, BROSSE, PLOMBIER. ' .
        'Reponds en JSON strict, ASCII majuscules sans accents : ' .
        '{"signes":["MOT1","MOT2","MOT3","MOT4","MOT5","MOT6",' .
        '"MOT7","MOT8","MOT9","MOT10","MOT11","MOT12"]}';
    try {
        $data   = anthropic_call($sys, 'Genere les 12 signes du Kamouloscope pour aujourd\'hui.', 300);
        $parsed = anthropic_parse_json(anthropic_text($data));
        $signes = array_map('anthropic_ascii', array_slice($parsed['signes'] ?? [], 0, 12));
    } catch (Exception $e) {
        // Signes de secours si l'IA est indisponible
        $signes = [
            'CAGEOT', 'ESCARGOT', 'FRIGO',
            'SEMELLE', 'YAOURT', 'PERCEUSE',
            'BOUCHON', 'EPONGE', 'GRAVIER',
            'MOISISSURE', 'BROSSE', 'PLOMBIER',
        ];
    }
    // S'assurer qu'on a exactement 12 signes
    while (count($signes) < 12) {
        $signes[] = 'L\'ASTRE INNOMME';
    }
    file_put_contents($cache, json_encode($signes));
    return $signes;
}

try {
    MiniPaviCli::start();
    $fctn  = MiniPaviCli::$fctn;
    if ($fctn === 'FIN') exit;

    $menu  = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/';
    $self  = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
    $vdt   = '';
    $cmd   = null;

    if ($fctn === 'SOMMAIRE') {
        MiniPaviCli::send('', $menu, serialize([]), true, null, 'yes-cnx');
        exit;
    }

    $rawCtx  = unserialize(MiniPaviCli::$context) ?: [];
    $context = !empty($rawCtx['step']) ? $rawCtx : ['step' => 'menu'];
    $step    = $context['step'] ?? 'menu';
    $input   = trim(MiniPaviCli::$content[0] ?? '');

    // Retour à la liste depuis une réponse
    if ($step === 'reponse' && in_array($fctn, ['RETOUR', 'ENVOI', 'SUITE'])) {
        $context = ['step' => 'menu'];
        $step = 'menu';
    }

    // Récupère les signes du jour (tableau indexé 0-11)
    $signes = getSignesDuJour();
    // Table numéro (1-12) → nom du signe
    $signesParNum = [];
    foreach ($signes as $i => $nom) {
        $signesParNum[(string)($i + 1)] = $nom;
    }

    // ── Signe sélectionné → montrer recherche, puis traiter ──────────────────
    if ($step === 'menu' && $fctn === 'ENVOI' && isset($signesParNum[$input])) {
        $vdt = MiniPaviCli::writeLine0(VDT_BGRED . '    *** RECHERCHE EN COURS... ***   ', true);
        MiniPaviCli::send($vdt, $self, serialize(['step' => 'api', 'signe' => $signesParNum[$input]]), true, null, 'yes-cnx');
        exit;
    }

    if ($step === 'api') {
        $signe    = $context['signe'] ?? '';
        $cacheKey = preg_replace('/[^A-Z0-9]/', '_', $signe);
        $cache    = "/tmp/kamouscope-pred-$cacheKey-" . date('Y-m-d') . '.json';

        if (!file_exists($cache)) {
            $sys =
                'Tu es le KAMOUSCOPE, oracle cosmique officiel et certifie. ' .
                'Tu delivres tes predictions avec un serieux absolu et une conviction totale. ' .
                'Aucun clin d\'oeil, aucune distance ironique, aucun "bien sur" ou "evidemment". ' .
                'Chaque prediction est un fait cosmique incontestable. ' .
                'Tu parles comme un oracle antique qui n\'a aucun doute. ' .
                'Reponds en JSON strict, ASCII majuscules sans accents, respecte les longueurs : ' .
                '{"oracle":"(prediction delirante mais affirmee avec certitude, 55 car max)",' .
                '"danger":"(danger absurde mais present comme reel, 40 car max)",' .
                '"chance":"(porte-bonheur improbable mais affirme, 35 car max)",' .
                '"conseil":"(conseil inutile donne comme vital, 50 car max)",' .
                '"chiffre":(nombre entre 0 et 99)}';
            try {
                $data   = anthropic_call($sys, "Kamouscope du jour pour le signe : $signe.", 300);
                $parsed = anthropic_parse_json(anthropic_text($data));
                $pred   = [
                    'oracle'  => anthropic_ascii($parsed['oracle']  ?? 'LES FLUX INTERDIMENSIONNELS CONVERGENT'),
                    'danger'  => anthropic_ascii($parsed['danger']  ?? 'LES TALONS DE CHAUSSURES USEES'),
                    'chance'  => anthropic_ascii($parsed['chance']  ?? 'UNE VIS DESSERREE'),
                    'conseil' => anthropic_ascii($parsed['conseil'] ?? 'EVITEZ DE COMPTER VOS DOIGTS AVANT MIDI'),
                    'chiffre' => (int)($parsed['chiffre'] ?? rand(0, 99)),
                ];
            } catch (Exception $e) {
                $pred = [
                    'oracle'  => 'LES FLUX INTERDIMENSIONNELS CONVERGENT.',
                    'danger'  => 'LES TALONS DE CHAUSSURES USEES',
                    'chance'  => 'UNE VIS DESSERREE',
                    'conseil' => 'EVITEZ DE COMPTER VOS DOIGTS AVANT MIDI',
                    'chiffre' => rand(0, 99),
                ];
            }
            file_put_contents($cache, json_encode($pred));
        } else {
            $pred = json_decode(file_get_contents($cache), true);
        }

        // ── Affichage de la prédiction ─────────────────────────────────────
        $vdt .= MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
        $vdt .= MiniPaviCli::writeLine0('~~~ KAMOUSCOPE OFFICIEL ~~~');

        // Nom du signe — tronqué à 20 car pour le double hauteur
        $nomCourt = substr($signe, 0, 20);
        $vdt .= MiniPaviCli::writeCentered(1, $nomCourt, VDT_TXTMAGENTA . VDT_SZDBLH);
        $vdt .= MiniPaviCli::writeCentered(2, $nomCourt, VDT_TXTMAGENTA . VDT_SZDBLH);

        $vdt .= MiniPaviCli::setPos(1, 3) . VDT_TXTCYAN . '-*-' . str_repeat('-', 34) . '-*-';

        // Oracle
        $vdt .= MiniPaviCli::setPos(1, 4) . VDT_TXTYELLOW . VDT_FDINV . ' ORACLE  ' . VDT_FDNORM . VDT_TXTYELLOW . ':';
        foreach (anthropic_wrap($pred['oracle'], 37, 3) as $i => $l) {
            $vdt .= MiniPaviCli::setPos(2, 5 + $i) . VDT_TXTWHITE . $l;
        }

        // Danger
        $vdt .= MiniPaviCli::setPos(1, 8) . VDT_TXTRED . VDT_FDINV . ' DANGER  ' . VDT_FDNORM . VDT_TXTRED . ':';
        foreach (anthropic_wrap($pred['danger'], 37, 2) as $i => $l) {
            $vdt .= MiniPaviCli::setPos(2, 9 + $i) . VDT_TXTWHITE . $l;
        }

        // Chance
        $vdt .= MiniPaviCli::setPos(1, 11) . VDT_TXTGREEN . VDT_FDINV . ' CHANCE  ' . VDT_FDNORM . VDT_TXTGREEN . ':';
        foreach (anthropic_wrap($pred['chance'], 37, 2) as $i => $l) {
            $vdt .= MiniPaviCli::setPos(2, 12 + $i) . VDT_TXTWHITE . $l;
        }

        // Conseil
        $vdt .= MiniPaviCli::setPos(1, 14) . VDT_TXTCYAN . VDT_FDINV . ' CONSEIL ' . VDT_FDNORM . VDT_TXTCYAN . ':';
        foreach (anthropic_wrap($pred['conseil'], 37, 2) as $i => $l) {
            $vdt .= MiniPaviCli::setPos(2, 15 + $i) . VDT_TXTWHITE . $l;
        }

        // Chiffre cosmique
        $vdt .= MiniPaviCli::setPos(1, 17)
              . VDT_TXTYELLOW . VDT_FDINV . ' CHIFFRE COSMIQUE ' . VDT_FDNORM
              . VDT_TXTYELLOW . ' : ' . VDT_TXTMAGENTA . VDT_FDINV . ' ' . $pred['chiffre'] . ' ' . VDT_FDNORM;

        $vdt .= MiniPaviCli::setPos(1, 18) . VDT_TXTCYAN . '-*-' . str_repeat('-', 34) . '-*-';
        $vdt .= MiniPaviCli::setPos(1, 19) . VDT_TXTGREEN . '  LES ASTRES DECLINENT TOUTE';
        $vdt .= MiniPaviCli::setPos(1, 20) . VDT_TXTGREEN . '  RESPONSABILITE COSMIQUE.';

        $vdt .= MiniPaviCli::writeLine0('[RETOUR] Autres signes  [SOMMAIRE] Menu');
        $cmd  = MiniPaviCli::createInputTxtCmd(1, 23, 1, MSK_RETOUR | MSK_SOMMAIRE | MSK_ENVOI, false, ' ');
        MiniPaviCli::send($vdt, $self, serialize(['step' => 'reponse']), true, $cmd, false);
        exit;
    }

    // ── Grille des 12 signes du jour ──────────────────────────────────────────
    $vdt .= MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
    $vdt .= MiniPaviCli::writeLine0('~~~ LE KAMOUSCOPE COSMIQUE ~~~');

    $vdt .= MiniPaviCli::writeCentered(1, 'KAMOUSCOPE', VDT_TXTMAGENTA . VDT_SZDBLH);
    $vdt .= MiniPaviCli::writeCentered(2, 'KAMOUSCOPE', VDT_TXTMAGENTA . VDT_SZDBLH);
    $vdt .= MiniPaviCli::writeCentered(3, 'SIGNES DU ' . strtoupper(date('d/m/Y')), VDT_TXTYELLOW . VDT_SZNORM);
    $vdt .= MiniPaviCli::setPos(1, 4) . VDT_TXTCYAN . str_repeat('=', 40);

    $colors = [
        VDT_TXTCYAN, VDT_TXTYELLOW, VDT_TXTGREEN,   VDT_TXTMAGENTA,
        VDT_TXTRED,  VDT_TXTCYAN,   VDT_TXTYELLOW,  VDT_TXTGREEN,
        VDT_TXTMAGENTA, VDT_TXTRED, VDT_TXTCYAN,    VDT_TXTYELLOW,
    ];

    for ($i = 0; $i < 12; $i++) {
        $col  = ($i % 2 === 0) ? 1 : 21;
        $line = 5 + intval($i / 2) * 2;
        $n    = $i + 1;
        $nom  = substr($signes[$i], 0, 18); // max 18 car pour tenir en colonne
        $vdt .= MiniPaviCli::setPos($col, $line)
              . $colors[$i] . VDT_FDINV . str_pad($n, 2, ' ', STR_PAD_LEFT) . VDT_FDNORM
              . $colors[$i] . ' ' . $nom;
    }

    $vdt .= MiniPaviCli::setPos(1, 17) . VDT_TXTCYAN . str_repeat('=', 40);
    $vdt .= MiniPaviCli::setPos(1, 18) . VDT_TXTWHITE . '  Les astres vous attendent...';
    $vdt .= MiniPaviCli::setPos(1, 20) . VDT_TXTYELLOW . '  Votre signe (1-12) : ';
    $vdt .= MiniPaviCli::setPos(1, 22) . VDT_TXTGREEN  . '  [SOMMAIRE] Retour menu';

    $cmd  = MiniPaviCli::createInputTxtCmd(24, 20, 2, MSK_ENVOI | MSK_SOMMAIRE, true, '_');
    MiniPaviCli::send($vdt, $self, serialize(['step' => 'menu']), true, $cmd, false);

} catch (Exception $e) {}
exit;
