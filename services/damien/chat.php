<?php
// =============================================================================
// chat.php — Messagerie Minitel 1985 v2
// =============================================================================
require_once __DIR__ . '/MiniPaviCli.php';
require_once __DIR__ . '/anthropic.php';
use MiniPavi\MiniPaviCli;
error_reporting(E_ERROR);
ini_set('display_errors', 0);

// Layout
define('MSG_ROW1',   4);   // première ligne messages
define('MSG_ROWS',  14);   // rows 4-17
define('INP_ROW',   19);   // première ligne input
define('INP_W',     38);   // largeur
define('INP_H',      2);   // hauteur (rows 19-20)

$COLOR_CYCLE = [VDT_TXTCYAN, VDT_TXTYELLOW, VDT_TXTGREEN, VDT_TXTWHITE, VDT_TXTRED, VDT_TXTMAGENTA];
$CHAT_FILE   = '/tmp/minitel_chat_' . date('Y-m-d') . '.json';
$MEM_FILE    = '/tmp/minitel_chat_memory.json';
$SPEED_RANGE = ['RAPIDE' => [15, 30], 'NORMAL' => [25, 45], 'LENT' => [40, 60]];
$HOUR        = (int)date('G');

$EVENTS_1985 = [
    'VOUS AVEZ VU LES RESTOS DU COEUR ? COLUCHE EST FORMIDABLE',
    'RAMBO 2 EST SORTI, J AI PRIS UN BILLET POUR CE SOIR',
    'MITTERRAND A ENCORE PARLE A LA TELE HIER SOIR',
    'VOUS AVEZ ESSAYE LE NOUVEAU WALKMAN DE SONY ?',
    'STARSKY ET HUTCH EN REDIFF CE SOIR SUR TF1',
    'LE PRIX DE L ESSENCE A ENCORE AUGMENTE',
    'JE VEUX VOIR WITNESS AU CINEMA, HARRISON FORD...',
    'ILS PARLENT DE METTRE UN MINITEL DANS CHAQUE MAISON !',
    'BACK TO THE FUTURE EN VO A PARIS, QUI VIENT ?',
    'LES GREVES SNCF ENCORE, ON EST BLOQUES !',
    'LE NOUVEAU DISQUE DE RENAUD, VOUS AVEZ ECOUTE ?',
    'VIVEMENT LES VACANCES, JE N EN PEUX PLUS !',
    'QUI REGARDE LA CINQ ? CETTE CHAINE EST EXCELLENTE',
    'VOUS AVEZ VU LE DERNIER ASTERIX AU CINEMA ?',
    'LE COCA COLA CHANGE DE GOUT, C EST UNE CATASTROPHE',
];

// ── État ──────────────────────────────────────────────────────────────────────
function emptyState(): array {
    return ['chars'=>[],'messages'=>[],'pending'=>null,'last_ambient'=>0,'events_used'=>[]];
}
function chatLoad(string $f): array {
    if (!file_exists($f)) return emptyState();
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? array_merge(emptyState(), $d) : emptyState();
}
function chatSave(string $f, array $s): void { file_put_contents($f, json_encode($s)); }
function chatAddMsg(array &$s, string $from, string $text): void {
    $s['messages'][] = ['t' => time(), 'from' => $from, 'text' => $text];
    if (count($s['messages']) > 100) $s['messages'] = array_slice($s['messages'], -100);
}

// ── Mémoire inter-session ─────────────────────────────────────────────────────
function memLoad(string $f): array {
    if (!file_exists($f)) return [];
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? $d : [];
}
function memSave(string $f, array $state): void {
    if (empty($state['chars'])) return;
    $names = implode(', ', array_keys($state['chars']));
    $last  = implode(' | ', array_map(
        fn($m) => $m['from'].': '.substr($m['text'],0,30),
        array_slice($state['messages'], -5)
    ));
    file_put_contents($f, json_encode([
        'date'  => date('Y-m-d'),
        'chars' => $names,
        'last'  => substr($last, 0, 200),
    ]));
}

// ── Seed personnages ──────────────────────────────────────────────────────────
function chatSeedChars(array $colorCycle, string $file, string $memFile): void {
    $mem     = memLoad($memFile);
    $memHint = (!empty($mem['chars']) && ($mem['date']??'') !== date('Y-m-d'))
        ? ' Hier les habitues etaient : '.$mem['chars'].'. Invente de nouveaux personnages differents.'
        : '';

    $sys =
        'Invente 6 personnages français tres differents pour un tchat Minitel 1985.'.$memHint.
        ' Prenoms max 6 lettres MAJUSCULES.'.
        ' Comportement : BAVARD (parle souvent), DISCRET (repond si sollicite), CURIEUX (commente), DISTRAIT (imprevue).'.
        ' Au moins 1 BAVARD, 2 DISCRET.'.
        ' Vitesse : RAPIDE, NORMAL, LENT.'.
        ' hours : [debut, fin] (0-23, ex [9,22]).'.
        ' Un seul doit avoir mysterious:true (DISCRET, phrases enigmatiques).'.
        ' persona max 60 car ASCII maj sans accents.'.
        ' JSON : {"chars":[{"name":"NOM","persona":"...","behavior":"...","speed":"...","hours":[H1,H2],"mysterious":false},...]}';

    $fallback = [
        ['name'=>'ROGER', 'persona'=>'GARAGISTE MARSEILLAIS, FOOT ET PASTIS, DIRECT.',   'behavior'=>'BAVARD',  'speed'=>'RAPIDE','hours'=>[8,23], 'mysterious'=>false],
        ['name'=>'ODILE', 'persona'=>'INSTITUTRICE BRETONNE, TRICOT ET MORALE.',          'behavior'=>'CURIEUX', 'speed'=>'NORMAL','hours'=>[14,22],'mysterious'=>false],
        ['name'=>'GASTON','persona'=>'PHARMACIEN HYPOCONDRIAQUE, TRES ANXIEUX.',          'behavior'=>'DISCRET', 'speed'=>'LENT',  'hours'=>[10,20],'mysterious'=>false],
        ['name'=>'SYLVIE','persona'=>'COIFFEUSE LYONNAISE, POTINS ET MODE.',              'behavior'=>'BAVARD',  'speed'=>'RAPIDE','hours'=>[18,24],'mysterious'=>false],
        ['name'=>'HUBERT','persona'=>'COMPTABLE BORDELAIS, JARDINAGE, METHODIQUE.',       'behavior'=>'DISCRET', 'speed'=>'LENT',  'hours'=>[20,23],'mysterious'=>false],
        ['name'=>'X',     'persona'=>'PERSONNE NE SAIT QUI IL EST. IL OBSERVE.',          'behavior'=>'DISCRET', 'speed'=>'LENT',  'hours'=>[0,23], 'mysterious'=>true],
    ];

    try {
        $parsed   = anthropic_parse_json(anthropic_text(anthropic_call($sys, 'Genere.', 400)));
        $rawChars = $parsed['chars'] ?? $fallback;
    } catch (Exception $e) { $rawChars = $fallback; }

    $validB = ['BAVARD','DISCRET','CURIEUX','DISTRAIT'];
    $validS = ['RAPIDE','NORMAL','LENT'];
    $chars  = [];
    $hasMyst = false;
    foreach (array_slice($rawChars, 0, 6) as $c) {
        $name = strtoupper(preg_replace('/[^A-Z]/','', anthropic_ascii($c['name']??'')));
        $name = substr($name, 0, 6);
        if (!$name || isset($chars[$name])) continue;
        $hours = (isset($c['hours'][0],$c['hours'][1])) ? [(int)$c['hours'][0],(int)$c['hours'][1]] : [8,23];
        $myst  = !$hasMyst && !empty($c['mysterious']);
        if ($myst) $hasMyst = true;
        $chars[$name] = [
            'color_idx' => count($chars) % count($colorCycle),
            'persona'   => anthropic_ascii($c['persona']??''),
            'behavior'  => in_array($c['behavior']??'',$validB) ? $c['behavior'] : 'CURIEUX',
            'speed'     => in_array($c['speed']??'',$validS)    ? $c['speed']    : 'NORMAL',
            'hours'     => $hours,
            'mysterious'=> $myst,
            'has_spoken'=> false,
        ];
    }
    chatSave($file, array_merge(emptyState(), ['chars'=>$chars]));
}

// ── Seed messages ─────────────────────────────────────────────────────────────
function chatSeedMsgs(string $file, int $hour): void {
    $state = chatLoad($file);
    $chars = $state['chars'];
    $active = array_keys(array_filter($chars, fn($c) =>
        in_array($c['behavior'],['BAVARD','CURIEUX']) && $hour>=$c['hours'][0] && $hour<=$c['hours'][1]
    ));
    if (empty($active)) $active = array_keys(array_filter($chars, fn($c) => $c['behavior']==='BAVARD'));
    if (empty($active)) { chatSave($file,$state); return; }

    $list = implode(', ', $active);
    $sys  = 'Minitel 1985. '.$list.' chattent entre eux. '.
            '5 messages varies (sujets de 1985), max 38 car, ASCII maj sans accents. '.
            'JSON : {"msgs":[{"from":"NOM","text":"..."},...]}';
    try {
        $parsed  = anthropic_parse_json(anthropic_text(anthropic_call($sys,'Genere.',280)));
        $rawMsgs = $parsed['msgs'] ?? [];
    } catch (Exception $e) { $rawMsgs = []; }
    if (empty($rawMsgs)) $rawMsgs = [['from'=>$active[0],'text'=>'BONSOIR TOUT LE MONDE !']];

    $n = max(count($rawMsgs),1); $base = time()-600;
    foreach ($rawMsgs as $i => $m) {
        $from = strtoupper(preg_replace('/[^A-Z]/','', anthropic_ascii($m['from']??'')));
        $from = substr($from,0,6);
        $text = trim(anthropic_ascii($m['text']??''));
        if ($from && $text && isset($chars[$from]))
            $state['messages'][] = ['t'=>$base+intdiv($i*600,$n),'from'=>$from,'text'=>$text];
    }
    $state['last_ambient'] = time()-120;
    chatSave($file,$state);
}

// ── Sélection répondant ───────────────────────────────────────────────────────
function chatProcessResponse(array $state, string $input, string $pseudo, array $speedRange, int $hour): array {
    $chars = $state['chars'];
    $avail = array_filter($chars, fn($c) => $hour>=$c['hours'][0] && $hour<=$c['hours'][1]);

    if (preg_match('/^@([A-Z]{1,6})\b/i', strtoupper($input), $m)) {
        $cand = substr(strtoupper(preg_replace('/[^A-Z]/','', $m[1])),0,6);
        if (isset($chars[$cand])) {
            $recent = implode("\n", array_map(fn($msg)=>$msg['from'].': '.$msg['text'], array_slice($state['messages'],-4)));
            $sys = "Tu es $cand. {$chars[$cand]['persona']} 1985, tchat Minitel. ".
                   "Reponds a $pseudo en 1 phrase max 38 car, ASCII maj. Texte brut.";
            try {
                $text = substr(anthropic_ascii(trim(anthropic_text(
                    anthropic_call($sys,"Contexte:\n$recent\n\n$pseudo dit: $input\n\nReponds:",100)
                ))),0,40);
                return ['responder'=>$cand,'text'=>$text?:'...'];
            } catch (Exception $e) { return ['responder'=>$cand,'text'=>'...']; }
        }
    }

    if (empty($avail)) return ['responder'=>null,'text'=>''];
    $desc   = implode("\n", array_map(fn($n,$c)=>"- $n ({$c['behavior']},{$c['speed']}): {$c['persona']}", array_keys($avail), array_values($avail)));
    $recent = implode("\n", array_map(fn($msg)=>$msg['from'].': '.$msg['text'], array_slice($state['messages'],-4)));
    $sys    = 'Salon Minitel 1985. Personnages : '."\n".$desc."\n".
              'BAVARD repond souvent. DISCRET rarement. CURIEUX si interessant. DISTRAIT parfois. '.
              'Il est normal que personne ne reponde. 1 phrase max 38 car ASCII maj. '.
              'JSON : {"responder":"NOM_ou_null","text":"reponse"}';
    try {
        $parsed = anthropic_parse_json(anthropic_text(
            anthropic_call($sys,"Contexte:\n$recent\n\n$pseudo dit: $input\n\nQui repond ?",120)
        ));
        $resp = strtoupper(trim($parsed['responder']??'null'));
        $resp = ($resp==='NULL'||!isset($chars[$resp])) ? null : $resp;
        $text = isset($parsed['text']) ? substr(anthropic_ascii(trim($parsed['text'])),0,40) : '';
        return ['responder'=>$resp,'text'=>$text];
    } catch (Exception $e) { return ['responder'=>null,'text'=>'']; }
}

// ── Ambient ───────────────────────────────────────────────────────────────────
function chatAmbient(array &$state, string $file, int $hour, array $events): void {
    $chars = $state['chars'];

    // Fin de soirée (22h+)
    if ($hour >= 22) {
        $b = array_keys(array_filter($chars, fn($c)=>$c['behavior']==='BAVARD'&&$hour>=$c['hours'][0]&&$hour<=$c['hours'][1]));
        if (!empty($b) && rand(0,3)===0) {
            $t = ['BONNE NUIT A TOUS, JE VAIS AU LIT !','ALLEZ BONNE NUIT, GROSSE JOURNEE DEMAIN','BON JE RACCROCHE, BONSOIR !'];
            chatAddMsg($state, $b[array_rand($b)], $t[array_rand($t)]);
            $state['last_ambient']=time(); chatSave($file,$state); return;
        }
    }

    // Personnage mystérieux (une seule fois, après 5+ msgs)
    if (count($state['messages'])>=5 && rand(0,9)===0) {
        foreach ($chars as $name=>$c) {
            if (($c['mysterious']??false) && !($c['has_spoken']??false)) {
                $sys = "Tu es $name. {$c['persona']} Tu ne parles quasiment jamais. ".
                       "Une seule phrase enigmatique max 28 car ASCII maj. Texte brut.";
                try {
                    $txt = substr(anthropic_ascii(trim(anthropic_text(anthropic_call($sys,'Message:',60)))),0,28);
                    if ($txt) {
                        chatAddMsg($state,$name,$txt);
                        $state['chars'][$name]['has_spoken']=true;
                        $state['last_ambient']=time(); chatSave($file,$state); return;
                    }
                } catch (Exception $e) {}
            }
        }
    }

    $pool = array_keys(array_filter($chars, fn($c)=>
        in_array($c['behavior'],['BAVARD','CURIEUX'])&&$hour>=$c['hours'][0]&&$hour<=$c['hours'][1]
    ));
    if (empty($pool)) return;
    $speaker = $pool[array_rand($pool)];
    $persona = $chars[$speaker]['persona'];
    $others  = array_diff(array_keys($chars),[$speaker]);

    // Dispute (20%)
    if (!empty($others) && rand(0,4)===0) {
        $tgt = $others[array_rand($others)];
        $tp  = $chars[$tgt]['persona']??'';
        $sys = "Tu es $speaker. $persona 1985, tchat Minitel. ".
               "Tu t adresses directement a $tgt ($tp), en desaccord ou en taquinant. 1 phrase max 38 car ASCII maj. Texte brut.";
    }
    // Événement 1985 (15%)
    elseif (!empty($events) && rand(0,6)===0) {
        $left = array_diff($events, $state['events_used']??[]);
        if (empty($left)) { $state['events_used']=[]; $left=$events; }
        $evt = $left[array_rand($left)];
        $state['events_used'][] = $evt;
        chatAddMsg($state,$speaker,$evt);
        $state['last_ambient']=time(); chatSave($file,$state); return;
    }
    else {
        $sys = "Tu es $speaker. $persona 1985, tchat Minitel. Message spontane max 35 car ASCII maj. Texte brut.";
    }
    try {
        $txt = substr(anthropic_ascii(trim(anthropic_text(anthropic_call($sys,'Message:',80)))),0,38);
        if ($txt) { chatAddMsg($state,$speaker,$txt); $state['last_ambient']=time(); chatSave($file,$state); }
    } catch (Exception $e) {}
}

// ── Rendu messages seuls (rows 4-17, sans clearScreen) ───────────────────────
function renderMsgs(array $state, array $colorCycle, string $pseudo): string {
    $chars  = $state['chars'];
    $fitting= []; $used=0;
    foreach (array_reverse($state['messages']) as $msg) {
        $nb = count(anthropic_wrap($msg['text'],25,2));
        if ($used+$nb>MSG_ROWS) break;
        array_unshift($fitting,$msg);
        $used+=$nb;
    }
    $vdt='';
    for ($r=MSG_ROW1; $r<MSG_ROW1+MSG_ROWS; $r++)
        $vdt .= MiniPaviCli::setPos(1,$r).str_repeat(' ',40);
    $row=MSG_ROW1;
    foreach ($fitting as $msg) {
        $from  = $msg['from'];
        $color = ($from===$pseudo||!isset($chars[$from]))
            ? VDT_TXTYELLOW
            : ($colorCycle[($chars[$from]['color_idx']??0)%count($colorCycle)]);
        $prefix= date('H:i',$msg['t']).' '.str_pad(substr($from,0,6),6).' : ';
        $lines = anthropic_wrap($msg['text'],25,2);
        $vdt  .= MiniPaviCli::setPos(1,$row).$color.$prefix.($lines[0]??'');
        $row++;
        if (isset($lines[1])&&$row<MSG_ROW1+MSG_ROWS) {
            $vdt.=MiniPaviCli::setPos(1,$row).$color.str_repeat(' ',15).$lines[1];
            $row++;
        }
    }
    return $vdt;
}

// ── Rendu complet ─────────────────────────────────────────────────────────────
function renderFull(array $state, array $colorCycle, string $pseudo, string $city): string {
    $vdt  = MiniPaviCli::clearScreen().PRO_MIN.PRO_LOCALECHO_OFF.VDT_CUROFF;
    $vdt .= MiniPaviCli::writeCentered(1,'MESSAGERIE 1985',VDT_TXTCYAN.VDT_SZDBLH);
    $vdt .= MiniPaviCli::writeCentered(2,'MESSAGERIE 1985',VDT_TXTCYAN.VDT_SZDBLH);
    $vdt .= MiniPaviCli::setPos(1,3).VDT_TXTCYAN.str_repeat('=',40);
    $vdt .= renderMsgs($state,$colorCycle,$pseudo);
    $vdt .= MiniPaviCli::setPos(1,18).VDT_TXTCYAN.str_repeat('-',40);
    $label= str_pad(substr($pseudo,0,6),6);
    $hint = '@NOM /qui /absent';
    $vdt .= MiniPaviCli::setPos(1,19).VDT_TXTYELLOW.$label.'> '.VDT_TXTGREEN.str_pad($hint,32);
    $vdt .= MiniPaviCli::setPos(1,22).VDT_TXTGREEN.'[ENVOI] Envoyer   [SOMMAIRE] Quitter';
    return $vdt;
}

function inputCmd(): array {
    return MiniPaviCli::createInputMsgCmd(1,INP_ROW,INP_W,INP_H,MSK_ENVOI|MSK_SOMMAIRE,true,' ');
}

// ── Écran fermé (0h-5h) ───────────────────────────────────────────────────────
function renderClosed(): string {
    $vdt  = MiniPaviCli::clearScreen().PRO_MIN.PRO_LOCALECHO_OFF.VDT_CUROFF;
    $vdt .= MiniPaviCli::writeLine0('*** MESSAGERIE 1985 ***');
    $vdt .= MiniPaviCli::writeCentered(7, 'SALON FERME', VDT_TXTRED.VDT_SZDBLH);
    $vdt .= MiniPaviCli::writeCentered(8, 'SALON FERME', VDT_TXTRED.VDT_SZDBLH);
    $vdt .= MiniPaviCli::setPos(1,11).VDT_TXTYELLOW.MiniPaviCli::toG2('   Le salon est fermé de minuit à 6h.');
    $vdt .= MiniPaviCli::setPos(1,13).VDT_TXTCYAN  .'       Revenez ce matin !';
    $vdt .= MiniPaviCli::setPos(1,20).VDT_TXTGREEN .'[SOMMAIRE] Retour menu';
    return $vdt;
}

// ── Écran /qui ────────────────────────────────────────────────────────────────
function renderQui(array $state, array $colorCycle, int $hour): string {
    $vdt  = MiniPaviCli::clearScreen().PRO_MIN.PRO_LOCALECHO_OFF.VDT_CUROFF;
    $vdt .= MiniPaviCli::writeLine0('*** QUI EST DANS LE SALON ? ***');
    $vdt .= MiniPaviCli::writeCentered(1,'LES HABITUES',VDT_TXTYELLOW.VDT_SZDBLH);
    $vdt .= MiniPaviCli::writeCentered(2,'LES HABITUES',VDT_TXTYELLOW.VDT_SZDBLH);
    $vdt .= MiniPaviCli::setPos(1,3).VDT_TXTCYAN.str_repeat('=',40);
    $r=4;
    foreach ($state['chars'] as $name=>$c) {
        if ($r>19) break;
        $col   = $colorCycle[$c['color_idx']%count($colorCycle)];
        $avail = ($hour>=$c['hours'][0]&&$hour<=$c['hours'][1]) ? '[EN LIGNE]' : '[ABSENT  ]';
        $vdt  .= MiniPaviCli::setPos(1,$r).$col.VDT_FDINV.' '.str_pad($name,6).' '.VDT_FDNORM.' '.$avail;
        $r++;
        $vdt  .= MiniPaviCli::setPos(4,$r).VDT_TXTWHITE.substr($c['persona'],0,34);
        $r+=2;
    }
    $vdt .= MiniPaviCli::setPos(1,22).VDT_TXTGREEN.'[ENVOI] Retour au salon';
    return $vdt;
}

// ── MAIN ──────────────────────────────────────────────────────────────────────
try {
    MiniPaviCli::start();
    $fctn = MiniPaviCli::$fctn;
    if ($fctn==='FIN') exit;

    $menu = 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/';
    $self = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];

    if ($fctn==='SOMMAIRE') {
        MiniPaviCli::send('',$menu,serialize([]),true,null,'yes-cnx');
        exit;
    }

    $rawCtx  = unserialize(MiniPaviCli::$context) ?: [];
    $context = !empty($rawCtx['step']) ? $rawCtx : ['step'=>'init'];
    $step    = $context['step']   ?? 'init';
    $pseudo  = $context['pseudo'] ?? 'VOUS';
    $city    = $context['city']   ?? '';

    $rawContent = MiniPaviCli::$content;
    $input = is_array($rawContent) ? trim(implode('',$rawContent)) : trim((string)$rawContent);
    $input = rtrim($input,'. ');

    // ── INIT ──────────────────────────────────────────────────────────────
    if ($step==='init') {
        $vdt = MiniPaviCli::writeLine0(VDT_BGRED.'  *** CONNEXION AU SALON 1985 ***  ',true);
        MiniPaviCli::send($vdt,$self,serialize(['step'=>'profil']),true,null,'yes-cnx');
        exit;
    }

    // ── PROFIL : saisie pseudo ────────────────────────────────────────────
    if ($step==='profil') {
        if ($fctn==='ENVOI' && $input!=='') {
            $p = strtoupper(preg_replace('/[^A-Z0-9]/','', anthropic_ascii($input)));
            $p = substr($p,0,6) ?: 'ANON';
            MiniPaviCli::send('',$self,serialize(['step'=>'profil_city','pseudo'=>$p]),true,null,'yes-cnx');
            exit;
        }
        $vdt  = MiniPaviCli::clearScreen().PRO_MIN.PRO_LOCALECHO_OFF.VDT_CUROFF;
        $vdt .= MiniPaviCli::writeLine0('*** MESSAGERIE 1985 — IDENTIFICATION ***');
        $vdt .= MiniPaviCli::writeCentered(3,'BIENVENUE !',VDT_TXTYELLOW.VDT_SZDBLH);
        $vdt .= MiniPaviCli::writeCentered(4,'BIENVENUE !',VDT_TXTYELLOW.VDT_SZDBLH);
        $vdt .= MiniPaviCli::setPos(1,7).VDT_TXTWHITE.MiniPaviCli::toG2('Pour rejoindre le salon, choisissez');
        $vdt .= MiniPaviCli::setPos(1,8).VDT_TXTWHITE.MiniPaviCli::toG2('votre pseudo (6 caractères max) :');
        $vdt .= MiniPaviCli::setPos(1,11).VDT_TXTYELLOW.'Pseudo : ';
        $vdt .= MiniPaviCli::setPos(1,16).VDT_TXTGREEN.MiniPaviCli::toG2('[ENVOI] pour valider');
        $cmd  = MiniPaviCli::createInputTxtCmd(10,11,6,MSK_ENVOI,true,'_');
        MiniPaviCli::send($vdt,$self,serialize(['step'=>'profil']),true,$cmd,false);
        exit;
    }

    // ── PROFIL CITY : saisie ville ────────────────────────────────────────
    if ($step==='profil_city') {
        $p = $context['pseudo'] ?? 'ANON';
        if ($fctn==='ENVOI') {
            $c = strtoupper(preg_replace('/[^A-Z ]/','', anthropic_ascii($input)));
            $c = substr(trim($c),0,12);
            MiniPaviCli::send('',$self,serialize(['step'=>'check','pseudo'=>$p,'city'=>$c]),true,null,'yes-cnx');
            exit;
        }
        $vdt  = MiniPaviCli::clearScreen().PRO_MIN.PRO_LOCALECHO_OFF.VDT_CUROFF;
        $vdt .= MiniPaviCli::writeLine0('*** MESSAGERIE 1985 — IDENTIFICATION ***');
        $pDisp = 'BIENVENUE '.$p.' !';
        $vdt .= MiniPaviCli::writeCentered(3,$pDisp,VDT_TXTYELLOW.VDT_SZDBLH);
        $vdt .= MiniPaviCli::writeCentered(4,$pDisp,VDT_TXTYELLOW.VDT_SZDBLH);
        $vdt .= MiniPaviCli::setPos(1,7).VDT_TXTWHITE.MiniPaviCli::toG2('Votre ville (facultatif) :');
        $vdt .= MiniPaviCli::setPos(1,11).VDT_TXTYELLOW.'Ville   : ';
        $vdt .= MiniPaviCli::setPos(1,16).VDT_TXTGREEN.MiniPaviCli::toG2('[ENVOI] pour rejoindre le salon');
        $cmd  = MiniPaviCli::createInputTxtCmd(11,11,12,MSK_ENVOI,true,'_');
        MiniPaviCli::send($vdt,$self,serialize(['step'=>'profil_city','pseudo'=>$p]),true,$cmd,false);
        exit;
    }

    // ── CHECK : salon existant ? ──────────────────────────────────────────
    if ($step==='check') {
        $state    = chatLoad($CHAT_FILE);
        $hasSalon = !empty($state['chars']);
        $msgCount = count($state['messages']);
        if ($hasSalon) {
            $ctx = ['step'=>'view','pseudo'=>$pseudo,'city'=>$city,'partial'=>false,'msgs_on_join'=>$msgCount,'ignore_next_envoi'=>true];
            MiniPaviCli::send('',$self,serialize($ctx),true,null,'yes-cnx');
        } else {
            // Sauvegarder mémoire du jour précédent
            $yesterday = '/tmp/minitel_chat_'.date('Y-m-d',strtotime('-1 day')).'.json';
            if (file_exists($yesterday)) memSave($MEM_FILE, chatLoad($yesterday));
            $ctx = ['step'=>'seed_chars','pseudo'=>$pseudo,'city'=>$city,'partial'=>false,'msgs_on_join'=>0];
            MiniPaviCli::send(
                MiniPaviCli::writeLine0(VDT_BGRED.'  *** OUVERTURE DU SALON...       ***',true),
                $self,serialize($ctx),true,null,'yes-cnx'
            );
        }
        exit;
    }

    // ── SEED CHARS ────────────────────────────────────────────────────────
    if ($step==='seed_chars') {
        chatSeedChars($COLOR_CYCLE,$CHAT_FILE,$MEM_FILE);
        $ctx = ['step'=>'seed_msgs','pseudo'=>$pseudo,'city'=>$city,'partial'=>false,'msgs_on_join'=>0];
        MiniPaviCli::send(
            MiniPaviCli::writeLine0(VDT_BGRED.'  *** PREPARATION DU SALON...     ***',true),
            $self,serialize($ctx),true,null,'yes-cnx'
        );
        exit;
    }

    // ── SEED MSGS ─────────────────────────────────────────────────────────
    if ($step==='seed_msgs') {
        chatSeedMsgs($CHAT_FILE,$HOUR);
        $state = chatLoad($CHAT_FILE);
        $ctx   = ['step'=>'view','pseudo'=>$pseudo,'city'=>$city,'partial'=>false,'msgs_on_join'=>count($state['messages']),'ignore_next_envoi'=>true];
        MiniPaviCli::send('',$self,serialize($ctx),true,null,'yes-cnx');
        exit;
    }

    // ── MODE ABSENT : observation seule ───────────────────────────────────
    if ($step==='absent') {
        sleep(5);
        $state = chatLoad($CHAT_FILE);
        if (empty($state['pending']) && (time()-($state['last_ambient']??0))>180)
            chatAmbient($state,$CHAT_FILE,$HOUR,$EVENTS_1985);
        $state = chatLoad($CHAT_FILE);
        $vdt   = renderMsgs($state,$COLOR_CYCLE,$pseudo);
        $vdt  .= MiniPaviCli::writeLine0(VDT_BGBLUE.'  MODE ABSENT — [SOMMAIRE] pour quitter  ');
        $ctx   = ['step'=>'absent','pseudo'=>$pseudo,'city'=>$city,'n'=>($context['n']??0)+1];
        MiniPaviCli::send($vdt,$self,serialize($ctx),true,null,'yes-cnx');
        exit;
    }

    // ── ENVOI dans view ───────────────────────────────────────────────────
    if ($step==='view' && $fctn==='ENVOI' && $input!=='' && !($context['ignore_next_envoi']??false)) {

        // Commande /qui
        if (strtolower($input)==='/qui') {
            $state = chatLoad($CHAT_FILE);
            $vdt   = renderQui($state,$COLOR_CYCLE,$HOUR);
            $cmd   = MiniPaviCli::createInputTxtCmd(38,22,1,MSK_ENVOI,false,' ');
            $ctx   = ['step'=>'view','pseudo'=>$pseudo,'city'=>$city,'partial'=>false,'msgs_on_join'=>$context['msgs_on_join']??0];
            MiniPaviCli::send($vdt,$self,serialize($ctx),true,$cmd,false);
            exit;
        }

        // Commande /absent
        if (strtolower($input)==='/absent') {
            $state = chatLoad($CHAT_FILE);
            chatAddMsg($state,$pseudo,'EST EN MODE ABSENT');
            chatSave($CHAT_FILE,$state);
            $ctx = ['step'=>'absent','pseudo'=>$pseudo,'city'=>$city,'n'=>0];
            MiniPaviCli::send('',$self,serialize($ctx),true,null,'yes-cnx');
            exit;
        }

        // Message normal
        $state = chatLoad($CHAT_FILE);
        chatAddMsg($state,$pseudo,strtoupper(anthropic_ascii($input)));
        chatSave($CHAT_FILE,$state);
        $vdt  = renderMsgs($state,$COLOR_CYCLE,$pseudo);
        $vdt .= MiniPaviCli::writeLine0(VDT_BGRED.'  *** MESSAGE ENVOYE ***           ',false);
        // Vider la zone de saisie (lignes 19-20)
        $vdt .= MiniPaviCli::setPos(1,INP_ROW).str_repeat(' ',INP_W);
        $vdt .= MiniPaviCli::setPos(1,INP_ROW+1).str_repeat(' ',INP_W);
        $ctx  = ['step'=>'processing','pseudo'=>$pseudo,'city'=>$city,'msgs_on_join'=>$context['msgs_on_join']??0];
        MiniPaviCli::send($vdt,$self,serialize($ctx),true,null,'yes-cnx');
        exit;
    }

    // ── PROCESSING ────────────────────────────────────────────────────────
    if ($step==='processing') {
        $state    = chatLoad($CHAT_FILE);
        $lastInput= '';
        foreach (array_reverse($state['messages']) as $msg)
            if ($msg['from']===$pseudo) { $lastInput=$msg['text']; break; }

        $result = chatProcessResponse($state,$lastInput,$pseudo,$SPEED_RANGE,$HOUR);
        if ($result['responder'] && $result['text']) {
            $speed = $state['chars'][$result['responder']]['speed']??'NORMAL';
            [$min,$max] = $SPEED_RANGE[$speed];
            $state['pending']=['at'=>time()+rand($min,$max),'responder'=>$result['responder'],'text'=>$result['text']];
            chatSave($CHAT_FILE,$state);
            $ctx = ['step'=>'waiting','pseudo'=>$pseudo,'city'=>$city,'r'=>$result['responder'],'n'=>0,'msgs_on_join'=>$context['msgs_on_join']??0];
            MiniPaviCli::send('',$self,serialize($ctx),true,null,'yes-cnx');
        } else {
            // Pas de réponse : retour view via yes-cnx (view gère le false+inputCmd)
            $ctx = ['step'=>'view','pseudo'=>$pseudo,'city'=>$city,'partial'=>true,'msgs_on_join'=>$context['msgs_on_join']??0];
            $nb  = count($state['chars'])+1;
            $vdt = MiniPaviCli::writeLine0('  MESSAGERIE 1985 — '.$nb.' CONNECTES  ');
            MiniPaviCli::send($vdt,$self,serialize($ctx),true,null,'yes-cnx');
        }
        exit;
    }

    // ── WAITING : polling toutes les 5s ───────────────────────────────────
    if ($step==='waiting') {
        sleep(5);
        $state   = chatLoad($CHAT_FILE);
        $pending = $state['pending']??null;
        $n       = ($context['n']??0)+1;

        if ($pending && time()>=$pending['at']) {
            // Message arrivé : l'ajouter et rediriger vers view via yes-cnx
            chatAddMsg($state,$pending['responder'],$pending['text']);
            $state['pending']=null;
            chatSave($CHAT_FILE,$state);
            $ctx  = ['step'=>'view','pseudo'=>$pseudo,'city'=>$city,'partial'=>true,'msgs_on_join'=>$context['msgs_on_join']??0];
            MiniPaviCli::send('',$self,serialize($ctx),true,null,'yes-cnx');
        } else {
            // Ambient si silence > 3 min
            if (empty($state['pending']) && (time()-($state['last_ambient']??0))>180)
                chatAmbient($state,$CHAT_FILE,$HOUR,$EVENTS_1985);
            $state        = chatLoad($CHAT_FILE);
            $lastCount    = (int)($context['last_msg_count'] ?? 0);
            $currentCount = count($state['messages']);
            $dots         = ['  .  ',' . . ','. . .',' . . ','  .  '];
            // Ne rafraîchir les messages que si de nouveaux sont arrivés
            $vdt  = ($currentCount > $lastCount) ? renderMsgs($state,$COLOR_CYCLE,$pseudo) : '';
            $vdt .= MiniPaviCli::writeLine0('EN ATTENTE DE '.str_pad($context['r']??'...',6).'  '.$dots[$n%5]);
            $ctx  = ['step'=>'waiting','pseudo'=>$pseudo,'city'=>$city,'r'=>$context['r']??'','n'=>$n,'last_msg_count'=>$currentCount,'msgs_on_join'=>$context['msgs_on_join']??0];
            MiniPaviCli::send($vdt,$self,serialize($ctx),true,null,'yes-cnx');
        }
        exit;
    }

    // ── VIEW ──────────────────────────────────────────────────────────────
    // Salon fermé (0h-5h)
    if ($HOUR>=0 && $HOUR<6) {
        $cmd = MiniPaviCli::createInputTxtCmd(38,22,1,MSK_SOMMAIRE,false,' ');
        MiniPaviCli::send(renderClosed(),$self,serialize(['step'=>'view','pseudo'=>$pseudo,'city'=>$city,'partial'=>false,'msgs_on_join'=>0]),true,$cmd,false);
        exit;
    }

    $state = chatLoad($CHAT_FILE);
    if (empty($state['chars'])) {
        MiniPaviCli::send(
            MiniPaviCli::writeLine0(VDT_BGRED.'  *** CONNEXION AU SALON 1985 ***  ',true),
            $self,serialize(['step'=>'seed_chars','pseudo'=>$pseudo,'city'=>$city,'partial'=>false,'msgs_on_join'=>0]),
            true,null,'yes-cnx'
        );
        exit;
    }

    $partial      = $context['partial']??false;
    $nb           = count($state['chars'])+1;
    $msgsOnJoin   = (int)($context['msgs_on_join']??0);
    $poll_n       = (int)($context['poll_n']??0);
    $lastMsgCount = (int)($context['last_msg_count']??0);
    $currentCount = count($state['messages']);

    if (!$partial && $poll_n < 4) {
        // ── Mode polling : génération ambiance + yes-cnx (4 cycles × 3s = ~12s) ──
        sleep(3);
        if (empty($state['pending']) && (time()-($state['last_ambient']??0))>60)
            chatAmbient($state,$CHAT_FILE,$HOUR,$EVENTS_1985);
        $state        = chatLoad($CHAT_FILE);
        $currentCount = count($state['messages']);
        $vdt  = ($currentCount>$lastMsgCount || $poll_n===0) ? renderMsgs($state,$COLOR_CYCLE,$pseudo) : '';
        $vdt .= MiniPaviCli::writeLine0('  MESSAGERIE 1985 — '.$nb.' CONNECTES  ');
        $ctx  = ['step'=>'view','pseudo'=>$pseudo,'city'=>$city,'partial'=>false,
                 'poll_n'=>$poll_n+1,'last_msg_count'=>$currentCount,
                 'msgs_on_join'=>$msgsOnJoin ?: $currentCount];
        MiniPaviCli::send($vdt,$self,serialize($ctx),true,null,'yes-cnx');
    } else {
        // ── Mode saisie : inputCmd + false (l'utilisateur peut écrire) ──────────
        if ($partial) {
            $vdt  = renderMsgs($state,$COLOR_CYCLE,$pseudo);
            $vdt .= MiniPaviCli::writeLine0('  MESSAGERIE 1985 — '.$nb.' CONNECTES  ');
        } else {
            $vdt = renderFull($state,$COLOR_CYCLE,$pseudo,$city);
            $newMsgs = $currentCount-$msgsOnJoin;
            if ($newMsgs>0 && $msgsOnJoin>0)
                $vdt .= MiniPaviCli::writeLine0(VDT_BGGREEN.' *** '.$newMsgs.' NOUVEAU(X) MSG DEPUIS VOTRE VISITE ***',true);
            elseif ($city!=='')
                $vdt .= MiniPaviCli::writeLine0('  MESSAGERIE 1985 — '.$pseudo.' DE '.$city.'  ');
            else
                $vdt .= MiniPaviCli::writeLine0('  MESSAGERIE 1985 — '.$nb.' CONNECTES  ');
        }
        // poll_n=0 : le prochain ENVOI vide relancera le polling
        $ctx = ['step'=>'view','pseudo'=>$pseudo,'city'=>$city,'partial'=>false,
                'poll_n'=>0,'last_msg_count'=>$currentCount,
                'msgs_on_join'=>$msgsOnJoin ?: $currentCount];
        MiniPaviCli::send($vdt,$self,serialize($ctx),true,inputCmd(),false);
    }

} catch (Exception $e) {}
exit;
