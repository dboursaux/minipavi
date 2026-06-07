<?php
// =============================================================================
// docteur.php — C'est grave docteur ? Consultation IA avec le Dr Moreau
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

    $rawContent = MiniPaviCli::$content;
    $input = is_array($rawContent) ? trim(implode('', $rawContent)) : trim((string)$rawContent);
    $input = rtrim($input, '. ');

    // Depuis la réponse → nouveau symptôme
    if ($step === 'reponse' && in_array($fctn, ['ENVOI', 'SUITE'])) {
        $context = ['step' => 'saisie'];
        $step = 'saisie';
    }

    // Symptôme saisi → afficher recherche puis appeler l'IA
    if ($step === 'saisie' && $fctn === 'ENVOI' && $input !== '') {
        $vdt = MiniPaviCli::writeLine0(VDT_BGRED . '    *** RECHERCHE EN COURS... ***   ', true);
        MiniPaviCli::send($vdt, $self, serialize(['step' => 'api', 'q' => $input]), true, null, 'yes-cnx');
        exit;
    }

    if ($step === 'api') {
        $symptome = $context['q'] ?? '';
        $sys =
            'Tu es le Docteur Moreau, medecin generaliste français de 1985. ' .
            'Pour chaque symptome, aussi banal soit-il, tu etablis un diagnostic catastrophiste ' .
            'et dramatique, une ordonnance absurde mais redigee en termes medicaux, ' .
            'et un conseil presente comme une question de vie ou de mort. ' .
            'Tu prends tout au premier degre, aucun humour apparent, ton serieux est total. ' .
            'JSON strict, ASCII majuscules sans accents : ' .
            '{"diagnostic":"(catastrophiste, 55 car max)",' .
            '"gravite":"(ex: TRES GRAVE / URGENT / POTENTIELLEMENT MORTEL, 18 car max)",' .
            '"ordonnance":"(absurde mais medical, 55 car max)",' .
            '"conseil":"(inutile presente comme vital, 50 car max)"}';
        try {
            $data   = anthropic_call($sys, "Symptome : $symptome", 300);
            $parsed = anthropic_parse_json(anthropic_text($data));
            $diag   = anthropic_ascii($parsed['diagnostic']  ?? 'SYNDROME INDETERMINE A SURVEILLER');
            $grav   = anthropic_ascii($parsed['gravite']     ?? 'TRES GRAVE');
            $ordo   = anthropic_ascii($parsed['ordonnance']  ?? 'REPOS COMPLET ET BAINS DE PIEDS');
            $cons   = anthropic_ascii($parsed['conseil']     ?? 'NE RESTEZ PAS SEUL CE SOIR');
        } catch (Exception $e) {
            $diag = 'SYNDROME INDETERMINE A SURVEILLER';
            $grav = 'TRES GRAVE';
            $ordo = 'REPOS COMPLET ET BAINS DE PIEDS';
            $cons = 'NE RESTEZ PAS SEUL CE SOIR';
        }

        $vdt .= MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
        $vdt .= MiniPaviCli::writeLine0('~~~ CABINET DU Dr MOREAU ~~~');

        $vdt .= MiniPaviCli::writeCentered(1, "C'EST GRAVE DOCTEUR ?", VDT_TXTRED . VDT_SZDBLH);
        $vdt .= MiniPaviCli::writeCentered(2, "C'EST GRAVE DOCTEUR ?", VDT_TXTRED . VDT_SZDBLH);

        $vdt .= MiniPaviCli::setPos(1, 3) . VDT_TXTCYAN . str_repeat('-', 40);

        // Diagnostic
        $vdt .= MiniPaviCli::setPos(1, 4) . VDT_TXTYELLOW . VDT_FDINV . ' DIAGNOSTIC ' . VDT_FDNORM . VDT_TXTYELLOW . ':';
        foreach (anthropic_wrap($diag, 37, 3) as $i => $l) {
            $vdt .= MiniPaviCli::setPos(2, 5 + $i) . VDT_TXTWHITE . $l;
        }

        // Gravité
        $vdt .= MiniPaviCli::setPos(1, 8) . VDT_TXTRED . VDT_FDINV . ' GRAVITE  ' . VDT_FDNORM
              . VDT_TXTRED . ' : ' . VDT_FDINV . ' ' . $grav . ' ' . VDT_FDNORM;

        $vdt .= MiniPaviCli::setPos(1, 9) . VDT_TXTCYAN . str_repeat('-', 40);

        // Ordonnance
        $vdt .= MiniPaviCli::setPos(1, 10) . VDT_TXTGREEN . VDT_FDINV . ' ORDONNANCE ' . VDT_FDNORM . VDT_TXTGREEN . ':';
        foreach (anthropic_wrap($ordo, 37, 3) as $i => $l) {
            $vdt .= MiniPaviCli::setPos(2, 11 + $i) . VDT_TXTWHITE . $l;
        }

        // Conseil
        $vdt .= MiniPaviCli::setPos(1, 14) . VDT_TXTMAGENTA . VDT_FDINV . ' CONSEIL  ' . VDT_FDNORM . VDT_TXTMAGENTA . ':';
        foreach (anthropic_wrap($cons, 37, 2) as $i => $l) {
            $vdt .= MiniPaviCli::setPos(2, 15 + $i) . VDT_TXTWHITE . $l;
        }

        $vdt .= MiniPaviCli::setPos(1, 17) . VDT_TXTCYAN . str_repeat('-', 40);
        $vdt .= MiniPaviCli::setPos(1, 18) . VDT_TXTYELLOW
              . centerPad('Dr MOREAU decline toute responsabilite.', 40);

        $vdt .= MiniPaviCli::writeLine0('[ENVOI] Autre symptome  [SOMMAIRE] Menu');
        $cmd  = MiniPaviCli::createInputTxtCmd(1, 23, 1, MSK_ENVOI | MSK_SOMMAIRE, false, ' ');
        MiniPaviCli::send($vdt, $self, serialize(['step' => 'reponse']), true, $cmd, false);
        exit;
    }

    // ── Formulaire saisie ────────────────────────────────────────────────────
    $vdt .= MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
    $vdt .= MiniPaviCli::writeCentered(1, "C'EST GRAVE DOCTEUR ?", VDT_TXTRED . VDT_SZDBLH);
    $vdt .= MiniPaviCli::writeCentered(2, "C'EST GRAVE DOCTEUR ?", VDT_TXTRED . VDT_SZDBLH);
    $vdt .= MiniPaviCli::setPos(1, 4) . VDT_TXTCYAN . str_repeat('-', 40);
    $vdt .= MiniPaviCli::setPos(1, 6)  . VDT_TXTWHITE . MiniPaviCli::toG2('Cabinet du Dr Moreau');
    $vdt .= MiniPaviCli::setPos(1, 7)  . VDT_TXTWHITE . MiniPaviCli::toG2('Consultation Minitel — 24h/24');
    $vdt .= MiniPaviCli::setPos(1, 9)  . VDT_TXTYELLOW . MiniPaviCli::toG2('Décrivez votre symptôme :');
    $vdt .= MiniPaviCli::setPos(1, 22) . VDT_TXTGREEN  . '[SOMMAIRE] Retour menu';
    $cmd  = MiniPaviCli::createInputMsgCmd(1, 11, 38, 2, MSK_ENVOI | MSK_SOMMAIRE, true, ' ');
    MiniPaviCli::send($vdt, $self, serialize(['step' => 'saisie']), true, $cmd, false);

} catch (Exception $e) {}
exit;

function centerPad(string $text, int $width): string {
    $len   = strlen($text);
    $left  = intdiv($width - $len, 2);
    $right = $width - $len - $left;
    return str_repeat(' ', max(0, $left)) . $text . str_repeat(' ', max(0, $right));
}
