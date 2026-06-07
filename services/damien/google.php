<?php
// =============================================================================
// google.php — Module IA : saisie libre → réponse Anthropic
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

    // Log de debug pour diagnostiquer la saisie
    $dbg = date('H:i:s') . " fctn=$fctn"
         . " content=" . json_encode(MiniPaviCli::$content)
         . " ctx=" . MiniPaviCli::$context . "\n";
    file_put_contents('/tmp/google_debug.log', $dbg, FILE_APPEND);

    // Retour menu sur SOMMAIRE
    if ($fctn === 'SOMMAIRE') {
        MiniPaviCli::send('', $menu, serialize([]), true, null, 'yes-cnx');
        exit;
    }

    $rawCtx  = unserialize(MiniPaviCli::$context) ?: [];
    $context = !empty($rawCtx['step']) ? $rawCtx : ['step' => 'saisie'];
    $step    = $context['step'] ?? 'saisie';

    // Récupère le contenu saisi — compatible tableau (InputMsg) et chaîne (InputTxt)
    $rawContent = MiniPaviCli::$content;
    if (is_array($rawContent)) {
        $input = trim(implode('', $rawContent));
    } else {
        $input = trim((string)$rawContent);
    }
    // Retire aussi les caractères de remplissage (points) éventuellement en fin
    $input = rtrim($input, '. ');

    if ($step === 'saisie' && $fctn === 'ENVOI' && $input !== '') {
        // Afficher "RECHERCHE..." sur la ligne 0, puis rediriger vers l'étape api
        $vdt = MiniPaviCli::writeLine0(VDT_BGRED . '    *** RECHERCHE EN COURS... ***   ', true);
        MiniPaviCli::send($vdt, $self, serialize(['step' => 'api', 'q' => $input]), true, null, 'yes-cnx');
        exit;
    }

    if ($step === 'api') {
        $question = $context['q'] ?? '';
        $system = 'Nous sommes en 1985. Tu es un assistant sur Minitel, concis et serieux. ' .
                  'Tu reponds UNIQUEMENT avec les connaissances disponibles en 1985 : ' .
                  'tu ignores tout ce qui s\'est passe apres 1985 (internet, telephones mobiles, ' .
                  'chute du mur de Berlin, etc.). Si on te parle d\'evenements posterieurs a 1985, ' .
                  'reponds que ca n\'existe pas ou que tu ne connais pas. ' .
                  'Reponds en francais, maximum 3 phrases. Texte brut, sans markdown.';
        try {
            $data   = anthropic_call($system, $question, 300);
            $texte  = anthropic_ascii(anthropic_text($data));
        } catch (Exception $e) {
            $texte = 'ERREUR IA : ' . strtoupper($e->getMessage());
        }
        $lignes = anthropic_wrap($texte, 38, 14);

        $vdt .= MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
        $vdt .= MiniPaviCli::writeCentered(1, 'REPONSE', VDT_TXTYELLOW . VDT_SZDBLH);
        $vdt .= MiniPaviCli::writeCentered(2, 'REPONSE', VDT_TXTYELLOW . VDT_SZDBLH);
        foreach ($lignes as $i => $ligne) {
            $vdt .= MiniPaviCli::setPos(1, 4 + $i) . VDT_TXTWHITE . $ligne;
        }
        $vdt .= MiniPaviCli::writeLine0('[ENVOI] Nouvelle question  [SOMMAIRE] Menu');
        $cmd  = MiniPaviCli::createInputTxtCmd(1, 23, 1, MSK_ENVOI | MSK_SOMMAIRE, false, ' ');
        MiniPaviCli::send($vdt, $self, serialize(['step' => 'saisie']), true, $cmd, false);
        exit;
    }

    // Affichage formulaire saisie — zone InputMsg (multi-char robuste)
    $vdt .= MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
    $vdt .= MiniPaviCli::writeCentered(1, 'GOOGLE / IA', VDT_TXTCYAN . VDT_SZDBLH);
    $vdt .= MiniPaviCli::writeCentered(2, 'GOOGLE / IA', VDT_TXTCYAN . VDT_SZDBLH);
    $vdt .= MiniPaviCli::setPos(1, 6) . VDT_TXTWHITE . MiniPaviCli::toG2('Posez votre question :');
    $vdt .= MiniPaviCli::setPos(1, 22) . VDT_TXTGREEN . '[SOMMAIRE] Retour menu';
    // InputMsg height=2 : permet une saisie libre sur 2 lignes, 38 car chacune
    $cmd  = MiniPaviCli::createInputMsgCmd(1, 8, 38, 2, MSK_ENVOI | MSK_SOMMAIRE, true, ' ');
    MiniPaviCli::send($vdt, $self, serialize(['step' => 'saisie']), true, $cmd, false);

} catch (Exception $e) {}
exit;
