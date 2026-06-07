<?php
// =============================================================================
// confessionnal.php — Confession → pénitence et absolution par IA
// =============================================================================
require_once __DIR__ . '/MiniPaviCli.php';
require_once __DIR__ . '/anthropic.php';
use MiniPavi\MiniPaviCli;
error_reporting(E_ERROR);
ini_set('display_errors', 0);

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
    $context = !empty($rawCtx['step']) ? $rawCtx : ['step' => 'saisie'];
    $step    = $context['step'] ?? 'saisie';
    // Récupère le contenu saisi — compatible tableau et chaîne brute
    $rawContent = MiniPaviCli::$content;
    $input = is_array($rawContent) ? trim(implode('', $rawContent)) : trim((string)$rawContent);
    $input = rtrim($input, '. ');

    // Depuis la réponse → nouveau péché
    if ($step === 'reponse' && in_array($fctn, ['ENVOI', 'SUITE'])) {
        $context = ['step' => 'saisie'];
        $step = 'saisie';
    }

    // Traitement du péché saisi
    if ($step === 'saisie' && $fctn === 'ENVOI' && $input !== '') {
        $vdt = MiniPaviCli::writeLine0(VDT_BGRED . '    *** RECHERCHE EN COURS... ***   ', true);
        MiniPaviCli::send($vdt, $self, serialize(['step' => 'api', 'q' => $input]), true, null, 'yes-cnx');
        exit;
    }

    if ($step === 'api') {
        $peche = $context['q'] ?? '';
        $sys = 'Tu es un pretre catholique humoristique sur Minitel. ' .
               'Reponds en JSON strict : {"penitence":"...","absolution":"..."}. ' .
               'ASCII majuscules, sans accents. Chaque champ max 60 caracteres.';
        try {
            $data   = anthropic_call($sys, "Mon peche : $peche", 200);
            $parsed = anthropic_parse_json(anthropic_text($data));
            $pen    = anthropic_ascii($parsed['penitence']  ?? 'DIRE 10 PATER NOSTER');
            $abs    = anthropic_ascii($parsed['absolution'] ?? 'VOS PECHES SONT PARDONNES');
        } catch (Exception $e) {
            $pen = 'FAIRE 3 TOURS DU PATE DE MAISONS';
            $abs = 'EGO TE ABSOLVO. ALLEZ EN PAIX.';
        }

        $vdt .= MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
        $vdt .= MiniPaviCli::writeCentered(1, 'ABSOLUTION', VDT_TXTCYAN . VDT_SZDBLH);
        $vdt .= MiniPaviCli::writeCentered(2, 'ABSOLUTION', VDT_TXTCYAN . VDT_SZDBLH);

        $vdt .= MiniPaviCli::setPos(1, 5) . VDT_TXTYELLOW . 'PENITENCE :';
        foreach (anthropic_wrap($pen, 38, 3) as $i => $l) {
            $vdt .= MiniPaviCli::setPos(1, 6 + $i) . VDT_TXTWHITE . $l;
        }
        $vdt .= MiniPaviCli::setPos(1, 11) . VDT_TXTYELLOW . 'ABSOLUTION :';
        foreach (anthropic_wrap($abs, 38, 3) as $i => $l) {
            $vdt .= MiniPaviCli::setPos(1, 12 + $i) . VDT_TXTWHITE . $l;
        }
        $vdt .= MiniPaviCli::writeLine0('[ENVOI] Nouveau peche  [SOMMAIRE] Menu');
        $cmd  = MiniPaviCli::createInputTxtCmd(1, 23, 1, MSK_ENVOI | MSK_SOMMAIRE, false, ' ');
        MiniPaviCli::send($vdt, $self, serialize(['step' => 'reponse']), true, $cmd, false);
        exit;
    }

    // Formulaire saisie
    $vdt .= MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
    $vdt .= MiniPaviCli::writeCentered(1, 'LE CONFESSIONNAL', VDT_TXTCYAN . VDT_SZDBLH);
    $vdt .= MiniPaviCli::writeCentered(2, 'LE CONFESSIONNAL', VDT_TXTCYAN . VDT_SZDBLH);
    $vdt .= MiniPaviCli::setPos(1, 6) . VDT_TXTWHITE . MiniPaviCli::toG2('Bénissez-moi mon père...');
    $vdt .= MiniPaviCli::setPos(1, 8) . VDT_TXTWHITE . MiniPaviCli::toG2('Quel est votre péché ?');
    $vdt .= MiniPaviCli::setPos(1, 22) . VDT_TXTGREEN . '[SOMMAIRE] Retour menu';
    $cmd  = MiniPaviCli::createInputMsgCmd(1, 10, 38, 2, MSK_ENVOI | MSK_SOMMAIRE, true, ' ');
    MiniPaviCli::send($vdt, $self, serialize(['step' => 'saisie']), true, $cmd, false);

} catch (Exception $e) {}
exit;
