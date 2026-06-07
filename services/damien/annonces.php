<?php
// =============================================================================
// annonces.php — Petites annonces générées par IA
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

    // Retour depuis détail vers liste
    if ($step === 'detail' && $fctn === 'RETOUR') {
        $step = 'liste';
        $context['step'] = 'liste';
    }

    // Depuis liste : sélection d'une annonce
    if ($step === 'liste' && $fctn === 'ENVOI' && is_numeric($input)) {
        $idx = (int)$input - 1;
        $annonces = $context['annonces'] ?? [];
        if (isset($annonces[$idx])) {
            $a   = $annonces[$idx];
            $vdt .= MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
            $vdt .= MiniPaviCli::writeCentered(1, 'DETAIL ANNONCE', VDT_TXTGREEN . VDT_SZDBLH);
            $vdt .= MiniPaviCli::writeCentered(2, 'DETAIL ANNONCE', VDT_TXTGREEN . VDT_SZDBLH);
            $vdt .= MiniPaviCli::setPos(1, 4)  . VDT_TXTYELLOW . substr($a['titre'] ?? '', 0, 38);
            $vdt .= MiniPaviCli::setPos(1, 6)  . VDT_TXTWHITE  . 'VILLE : ' . ($a['ville'] ?? '');
            $vdt .= MiniPaviCli::setPos(1, 7)  . VDT_TXTWHITE  . 'PRIX  : ' . ($a['prix'] ?? '');
            $vdt .= MiniPaviCli::setPos(1, 9)  . VDT_TXTWHITE  . 'DESCRIPTION :';
            foreach (anthropic_wrap($a['description'] ?? '', 36, 4) as $i => $l) {
                $vdt .= MiniPaviCli::setPos(1, 10 + $i) . VDT_TXTWHITE . $l;
            }
            $vdt .= MiniPaviCli::setPos(1, 16) . VDT_TXTWHITE  . 'TEL : ' . ($a['telephone'] ?? '');
            $vdt .= MiniPaviCli::writeLine0('[RETOUR] Liste  [SOMMAIRE] Menu');
            $cmd  = MiniPaviCli::createInputTxtCmd(1, 23, 1, MSK_RETOUR | MSK_SOMMAIRE, false, ' ');
            MiniPaviCli::send($vdt, $self, serialize(['step' => 'detail', 'annonces' => $annonces]), true, $cmd, false);
            exit;
        }
    }

    // Depuis saisie catégorie → montrer recherche, puis générer
    if ($step === 'saisie' && $fctn === 'ENVOI' && $input !== '') {
        $vdt = MiniPaviCli::writeLine0(VDT_BGRED . '    *** RECHERCHE EN COURS... ***   ', true);
        MiniPaviCli::send($vdt, $self, serialize(['step' => 'api', 'cat' => strtoupper($input)]), true, null, 'yes-cnx');
        exit;
    }

    if ($step === 'api') {
        $cat = $context['cat'] ?? '';
        $sys = 'Nous sommes en 1985 en France. Genere 5 petites annonces fictives et amusantes ' .
               'pour la categorie demandee, dans le contexte de 1985 : ' .
               'prix en francs, produits de l\'epoque, references culturelles des annees 80. ' .
               'Pas d\'internet, pas de portable, pas de produits posterieurs a 1985. ' .
               'JSON strict : {"annonces":[{"id":1,"titre":"...","ville":"...","prix":"...",' .
               '"description":"...","telephone":"..."},...]}. ' .
               'ASCII majuscules sans accents. Titres max 28 car, descriptions max 60 car.';
        try {
            $data     = anthropic_call($sys, "Categorie : $cat", 600);
            $parsed   = anthropic_parse_json(anthropic_text($data));
            $annonces = $parsed['annonces'] ?? [];
            foreach ($annonces as &$a) {
                $a['titre']       = anthropic_ascii($a['titre']       ?? '');
                $a['ville']       = anthropic_ascii($a['ville']       ?? '');
                $a['prix']        = anthropic_ascii($a['prix']        ?? '');
                $a['description'] = anthropic_ascii($a['description'] ?? '');
            }
            unset($a);
        } catch (Exception $e) {
            $annonces = [];
        }
        $context = ['step' => 'liste', 'annonces' => $annonces, 'cat' => $cat];
        $step = 'liste';
    }

    // Affichage liste
    if ($step === 'liste') {
        $annonces = $context['annonces'] ?? [];
        $cat      = $context['cat']      ?? '';
        $vdt .= MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
        $vdt .= MiniPaviCli::writeCentered(1, $cat, VDT_TXTGREEN . VDT_SZDBLH);
        $vdt .= MiniPaviCli::writeCentered(2, $cat, VDT_TXTGREEN . VDT_SZDBLH);
        if (empty($annonces)) {
            $vdt .= MiniPaviCli::setPos(1, 10) . VDT_TXTWHITE . 'AUCUNE ANNONCE DISPONIBLE';
        } else {
            foreach ($annonces as $i => $a) {
                $row  = 4 + $i * 3;
                $vdt .= MiniPaviCli::setPos(1, $row)   . VDT_TXTYELLOW . ($i+1) . '. ' . substr($a['titre'], 0, 32);
                $vdt .= MiniPaviCli::setPos(4, $row+1) . VDT_TXTWHITE  . $a['ville'] . ' - ' . $a['prix'];
            }
        }
        $vdt .= MiniPaviCli::setPos(1, 22) . VDT_TXTGREEN . 'Numero (1-5) ou [SOMMAIRE] menu';
        $cmd  = MiniPaviCli::createInputTxtCmd(33, 22, 1, MSK_ENVOI | MSK_SOMMAIRE, true, '_');
        MiniPaviCli::send($vdt, $self, serialize($context), true, $cmd, false);
        exit;
    }

    // Formulaire saisie catégorie
    $vdt .= MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
    $vdt .= MiniPaviCli::writeCentered(1, 'PETITES ANNONCES', VDT_TXTGREEN . VDT_SZDBLH);
    $vdt .= MiniPaviCli::writeCentered(2, 'PETITES ANNONCES', VDT_TXTGREEN . VDT_SZDBLH);
    $vdt .= MiniPaviCli::setPos(1, 6) . VDT_TXTWHITE . MiniPaviCli::toG2('Ex : VOITURES, VELOS, CHIENS...');
    $vdt .= MiniPaviCli::setPos(1, 8) . VDT_TXTWHITE . MiniPaviCli::toG2('Catégorie : ');
    $vdt .= MiniPaviCli::setPos(1, 22) . VDT_TXTGREEN . '[SOMMAIRE] Retour menu';
    $cmd  = MiniPaviCli::createInputMsgCmd(1, 10, 30, 2, MSK_ENVOI | MSK_SOMMAIRE, true, ' ');
    MiniPaviCli::send($vdt, $self, serialize(['step' => 'saisie']), true, $cmd, false);

} catch (Exception $e) {}
exit;
