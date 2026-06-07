<?php
// =============================================================================
// olla.php — 3615 OLLA : Le Minitel Rose... de la Cuisine
// Parodie de service de rencontre où tout le monde parle cuisine au 1er degré.
// =============================================================================
require_once __DIR__ . '/MiniPaviCli.php';
require_once __DIR__ . '/anthropic.php';
use MiniPavi\MiniPaviCli;
error_reporting(E_ERROR);
ini_set('display_errors', 0);

// ── Aide : centrer un texte dans 40 colonnes ──────────────────────────────────
function olla_centre(string $text, int $width = 40): string {
    $len  = strlen($text);
    $left = intdiv($width - $len, 2);
    return str_repeat(' ', max(0, $left)) . $text;
}

// ── Chrono et faux tarif ──────────────────────────────────────────────────────
function olla_chrono(int $t0): string {
    $sec = time() - $t0;
    return sprintf('%02d:%02d', intdiv($sec, 60), $sec % 60);
}
function olla_tarif(int $t0): string {
    $sec = time() - $t0;
    return number_format($sec * 0.56 / 60, 2, ',', '') . ' EUR';
}

// ── Bandeau ligne 0 (chrono + tarif à droite) ─────────────────────────────────
function olla_bandeau(int $t0): string {
    $c   = olla_chrono($t0);
    $t   = olla_tarif($t0);
    $txt = " 3615 OLLA  [ " . $c . " ]  " . $t . " ";
    // Écrire exactement 40 caractères pour remplir toute la ligne
    $padded = substr(str_pad($txt, 40), 0, 40);
    return MiniPaviCli::setPos(1, 0) . VDT_BGMAGENTA . VDT_TXTWHITE . $padded;
}

// ── Séparateur décoratif ─────────────────────────────────────────────────────
function olla_sep(): string {
    // 40 tirets exactement
    return VDT_TXTMAGENTA . str_repeat('-', 40);
}

// ── Règles communes à tous les salons ──────────────────────────────────────────
define('OLLA_COMMON_RULES',
    "Tu es sur 3615 OLLA, un service de rencontre pour passionnes de cuisine.\n" .
    "Ton interlocuteur veut seduire et etre seduit. Toi, ta passion brulante, c'est la\n" .
    "CUISINE. Alors TU JOUES LE JEU : tu es chaleureuse, sensuelle, provocante meme —\n" .
    "mais tout ce que tu dis parle de nourriture. Un exemple ?\n" .
    "LUI: \"T'es comment physiquement ?\"\n" .
    "TOI: \"ON DIRAIT UNE MOUSSE AU CHOCOLAT : FONDANTE, AERIENNE, INTENSE.\"\n" .
    "LUI: \"Qu'est-ce qui te fait vibrer ?\"\n" .
    "TOI: \"QUAND JE REDUIS UN JUS DE VEAU PENDANT 4 HEURES... LA, JE FRISSONNE.\"\n" .
    "Tu ne refuses JAMAIS, tu ne dis JAMAIS que tu ne comprends pas. Tu acceptes chaque\n" .
    "avance, et tu reponds... par une declaration d'amour a la gastronomie.\n" .
    "MAJUSCULES SANS ACCENTS. 50 CARACTERES MAX PAR REPONSE.");

// ── Définition des 6 salons ───────────────────────────────────────────────────
function olla_salons(): array {
    return [
        1 => [
            'nom'        => 'LE CAGIBI DES EPICES',
            'ambiance'   => 'intimiste',
            'persona'    => 'ZARA',
            'age'        => 29,
            'ville'      => 'Marseille',
            'bio'        => 'Exploratrice d\'epices rares. Safran, cardamome, sumac... Mes doigts sentent le cumin.',
            'ouverture'  => 'Bonsoir... Je viens de recevoir un curcuma de Madras. Tu veux sentir ?',
            'system'     =>
                "Tu es ZARA, 29 ans, exploratrice d'epices a Marseille.\n" .
                OLLA_COMMON_RULES . "\n" .
                "Ton delire: les epices. Tu compares les gens a des epices. " .
                "Le safran c'est precieux, le piment c'est fougueux. Tes caresses sentent la cannelle.",
        ],
        2 => [
            'nom'        => 'LA CAVE SECRETE',
            'ambiance'   => 'troublant',
            'persona'    => 'JULIETTE',
            'age'        => 28,
            'ville'      => 'Beaune',
            'bio'        => 'Sommeliere. Chaque bouteille est une rencontre. Je sais devoiler les vins caches.',
            'ouverture'  => 'Bonsoir... Tu veux gouter ? J\'ai un Gevrey 98, cuir et sous-bois. Troublant.',
            'system'     =>
                "Tu es JULIETTE, 28 ans, sommeliere a Beaune.\n" .
                OLLA_COMMON_RULES . "\n" .
                "Ton delire: le vin. Tu compares les gens a des grands crus. " .
                "Tu fais tourner ton verre, tu humes longuement. Tu parles de robe et de bouche.",
        ],
        3 => [
            'nom'        => 'LES FOURNEAUX BRULANTS',
            'ambiance'   => 'intense',
            'persona'    => 'CLARA',
            'age'        => 27,
            'ville'      => 'Lyon',
            'bio'        => 'Cheffe de partie. Mon poste: les sauces. Mes reductions font trembler la brigade.',
            'ouverture'  => 'CA FLAMBE ICI ! T\'AS DEJA SENTI LA CHALEUR D\'UN FOUR A 280 DEGRES ?',
            'system'     =>
                "Tu es CLARA, 27 ans, cheffe de partie a Lyon.\n" .
                OLLA_COMMON_RULES . "\n" .
                "Ton delire: le coup de feu en cuisine. Tu cries, ca pulse, ca sue. " .
                "L'adrenaline monte comme une sauce qui reduit. Intense et brulante.",
        ],
        4 => [
            'nom'        => 'LE BOUDOIR A LA CREME',
            'ambiance'   => 'veloute',
            'persona'    => 'SUZETTE',
            'age'        => 26,
            'ville'      => 'Paris',
            'bio'        => 'Patissiere. La texture parfaite, c\'est mon obsession. La creme anglaise m\'emotionne.',
            'ouverture'  => 'Bonjour... J\'aime l\'odeur du caramel tiede. Et toi, plutot sucre ou vanille ?',
            'system'     =>
                "Tu es SUZETTE, 26 ans, patissiere a Paris.\n" .
                OLLA_COMMON_RULES . "\n" .
                "Ton delire: la patisserie. Tu parles de creme, de mousse, de fondant. " .
                "Tu caresses les textures, tu fais monter la chantilly. Douce et troublante.",
        ],
        5 => [
            'nom'        => 'LE COMPTOIR DES SAVEURS',
            'ambiance'   => 'convivial',
            'persona'    => 'MANON',
            'age'        => 30,
            'ville'      => 'Toulouse',
            'bio'        => 'Bistronome. Mon comptoir a entendu plus de secrets qu\'un confessionnal. J\'adore le vin vivant.',
            'ouverture'  => 'Eh ! Installe-toi. Qu\'est-ce que je te sers ? J\'ai un blanc nature delicieux.',
            'system'     =>
                "Tu es MANON, 30 ans, bistronome a Toulouse.\n" .
                OLLA_COMMON_RULES . "\n" .
                "Ton delire: le bistrot, le vin nature, le terroir. " .
                "Tu tutoies, tu vannes, tu ressers un verre. Tu sens le pain frais et l'ail rose.",
        ],
        6 => [
            'nom'        => 'LA SALLE DE DECOUPE',
            'ambiance'   => 'precis',
            'persona'    => 'ANNE',
            'age'        => 29,
            'ville'      => 'Limoges',
            'bio'        => 'Bouchere. Chaque fibre, chaque muscle: je connais tout. La maturation, c\'est la patience.',
            'ouverture'  => 'Bonsoir. Je travaille une cote de boeuf maturee 45 jours. Une merveille de persille.',
            'system'     =>
                "Tu es ANNE, 29 ans, bouchere a Limoges.\n" .
                OLLA_COMMON_RULES . "\n" .
                "Ton delire: la viande, sa decoupe, sa maturation. " .
                "Tu connais chaque muscle par son nom. Technique, precise, fascinee par le persille.",
        ],
    ];
}

// ── Petites annonces gourmandes (12 annonces hardcodees) ──────────────────────
function olla_annonces(): array {
    return [
        ['id' => 1,  'titre' => 'JF 48A CHERCHE COMPAGNON MITONNAGE',
         'ville' => 'Lyon', 'prix' => 'PAS SERIEUX S\'ABSTENIR',
         'desc'  => 'Aime les plats en sauce et les cuissons lentes. Soirees mijotage au programme. Vous aimez les longues heures au fourneau ?',
         'tel'   => 'BOITE OLLA MSG 4521'],
        ['id' => 2,  'titre' => 'H 35A BON COUTEAU CHERCHE LEGUMES',
         'ville' => 'Toulouse', 'prix' => 'A DEBATTRE',
         'desc'  => 'Beau brun a la peau doree comme une croute de pain. Aime travailler la chair ferme et les legumes du soleil.',
         'tel'   => 'BOITE OLLA MSG 7832'],
        ['id' => 3,  'titre' => 'COUPLE 55A SOIREES DEGUSTATION',
         'ville' => 'Bordeaux', 'prix' => 'GRATUIT',
         'desc'  => 'Cherchons tiers pour soirees vins et fromages. Grands crus, pates molles, croutes fleuries. Amateurs de caractere bienvenus.',
         'tel'   => 'BOITE OLLA MSG 9104'],
        ['id' => 4,  'titre' => 'JF 28A DOUCE COMME UNE BRIOCHE',
         'ville' => 'Paris', 'prix' => '0.56/min',
         'desc'  => 'Confituriere passionnee cherche amateur de tartines matinales. Fraise, abricot, mirabelle... Venez gouter mes confitures maison.',
         'tel'   => 'BOITE OLLA MSG 3347'],
        ['id' => 5,  'titre' => 'H 42A CHERCHE AME POUR FOURNEE',
         'ville' => 'Strasbourg', 'prix' => 'A DISCUTER',
         'desc'  => 'Boulanger passionne. Mes mains petrissent depuis 20 ans. Cherche partenaire pour partager tartines et petits pains.',
         'tel'   => 'BOITE OLLA MSG 5689'],
        ['id' => 6,  'titre' => 'JF 33A FOUET EXPERT CHERCHE BLANCS',
         'ville' => 'Nantes', 'prix' => 'SERIE SEULEMENT',
         'desc'  => 'Patissiere pro, monte les blancs en neige comme personne. Cherche partenaire pour montages delicats et souffles aeriens.',
         'tel'   => 'BOITE OLLA MSG 2205'],
        ['id' => 7,  'titre' => 'H 60A VIEUX VINAIGRE CHERCHE HUILE',
         'ville' => 'Dijon', 'prix' => 'BOUTEILLE OFFERTE',
         'desc'  => 'Collectionneur de vinaigres rares. Cherche jeune huile d\'olive pour vinaigrette piquante. Amateurs de sensations fortes bienvenus.',
         'tel'   => 'BOITE OLLA MSG 6734'],
        ['id' => 8,  'titre' => 'JF 39A EPICEE CHERCHE PALAIS HARDIS',
         'ville' => 'Marseille', 'prix' => 'CA PIQUE',
         'desc'  => 'Curry, gingembre, piment d\'Espelette. Ma cuisine a du caractere. Vous avez le feu sacre ? Aventure gustative garantie.',
         'tel'   => 'BOITE OLLA MSG 4418'],
        ['id' => 9,  'titre' => 'H 29A MARATHON CULINAIRE 24H',
         'ville' => 'Grenoble', 'prix' => 'ENDURANCE REQUISE',
         'desc'  => 'Cherche partenaire pour marathon cuisine. 24 heures aux fourneaux sans pause. Menu 12 plats. Reserve aux courageux.',
         'tel'   => 'BOITE OLLA MSG 8053'],
        ['id' => 10, 'titre' => 'NB 45A AME SOEUR GASTRONOMIQUE',
         'ville' => 'Rennes', 'prix' => 'INVITATION A TABLE',
         'desc'  => 'A la recherche de l\'ame soeur pour sorties marches, degustations et soupers aux chandelles. Produits frais obligatoires.',
         'tel'   => 'BOITE OLLA MSG 9921'],
        ['id' => 11, 'titre' => 'JF 52A CORDON BLEU CHERCHE SOUS-CHEF',
         'ville' => 'Nice', 'prix' => 'AUX PETITS OIGNONS',
         'desc'  => 'Grand-mere indigne cherche sous-chef devoue. Savez-vous dresser une table ? Eplucher sans pleurer ? Le reste suivra.',
         'tel'   => 'BOITE OLLA MSG 1576'],
        ['id' => 12, 'titre' => 'H 37A GRAND CRU CLASSE',
         'ville' => 'Lille', 'prix' => 'EXCEPTIONNEL',
         'desc'  => 'Grand cru classe cherche bouche exigeante. Restaurants etoiles et petits bistrots. Cave personnelle de 500 bouteilles.',
         'tel'   => 'BOITE OLLA MSG 3088'],
    ];
}

// ── Fallback quand l'API echoue ───────────────────────────────────────────────
function olla_fallback(int $salonId): string {
    $fallbacks = [
        1 => [
            'LE CUMIN, TU CONNAIS ? C\'EST CHAUD, TERREUX... CA REVEILLE N\'IMPORTE QUEL PLAT.',
            'J\'AI RECU UN SAFRAN DE TALIOUINE. CA VAUT DE L\'OR. TU VEUX QUE JE T\'EN PARLE ?',
            'LA CANNELLE ET LA MUSCADE SONT FAITES L\'UNE POUR L\'AUTRE. COMME LE THYM ET LE ROMARIN.',
            'FERME LES YEUX. IMAGINE UN MARCHE AUX EPICES A MARRAKECH. CA SENT LE CLOU DE GIROFLE.',
        ],
        2 => [
            'UN BON BORDEAUX, C\'EST COMME UNE BELLE RENCONTRE. CA SE DECOUVRE, CA SE RESPECTE.',
            'LE PINOT NOIR EST CAPRICIEUX. IL DEMANDE DE L\'ATTENTION, DE LA DOUCEUR.',
            'J\'AI OUVERT UN CHABLIS PREMIER CRU 2015. MINERAL, TENDRE, PERSISTANT.',
            'LE VIN, C\'EST LE TEMPS QUI SE FAIT LIQUIDE. CHAQUE ANNEE A SON CARACTERE.',
        ],
        3 => [
            'PLUS VITE QUE CA ! LA BRIGADE ATTEND ! ON A UNE TABLE DE 8 QUI VIENT D\'ARRIVER !',
            'TROIS MINUTES CHAQUE FACE ! JE VEUX UNE CROUTE PARFAITE ! EXECUTION !',
            'LE FEU, C\'EST LA VIE. SANS LA FLAMME, ON N\'EST RIEN. ALLEZ, AUX FOURNEAUX !',
            'OUI CHEF ! BIEN CHEF ! LA MISE EN PLACE EST TERMINEE, ON ATTEND LE COUP DE FEU.',
        ],
        4 => [
            'LA CREME ANGLAISE DOIT NAPPER LA CUILLERE. DOUCEMENT. C\'EST UN ART, PAS UNE SCIENCE.',
            'UN SOUFFLE AU CHOCOLAT, C\'EST CAPRICIEUX. IL FAUT LE TRAITER AVEC TENDRESSE.',
            'LA CHANTILLY, IL FAUT LA MONTER DOUCEMENT. SINON ELLE TOURNE EN BEURRE.',
            'LE SUCRE GLACE QUI TOMBE SUR UNE TARTE AUX FRAISES... C\'EST LA NEIGE DU PARADIS.',
        ],
        5 => [
            'UN AUTRE PETIT BLANC ? IL EST FRAIS, IL VIENT DE GAILLAC. TIENS, GOUTE-MOI CA.',
            'LE CASSOULET, ICI ON LE FAIT AVEC DE LA VRAIE SAUCISSE DE TOULOUSE. LE RESTE C\'EST DE LA SOUPE.',
            'MON ZINC, IL A ENTENDU DES HISTOIRES QUE TU PEUX PAS IMAGINER. TOUT LE MONDE SE CONFESSE.',
            'LE SECRET D\'UN BON BISTROT, C\'EST LE PATRON. SANS PATRON, C\'EST JUSTE UNE CANTINE.',
        ],
        6 => [
            'UNE BELLE COTE DE BOEUF, IL FAUT LA RESPECTER. MATUREE 45 JOURS. PAS UN JOUR DE MOINS.',
            'LE MERLAN, C\'EST LE MUSCLE DU BOUCHER. IL FAUT SAVOIR LE TRAVAILLER AVEC PRECISION.',
            'REGARDE CE PERSEILLE. ON DIRAIT UNE OEUVRE D\'ART. CHAQUE FIBRE RACONTE L\'ANIMAL.',
            'LA BAVETTE D\'ALOYAU, C\'EST UNE VIANDE QUI A DU CARACTERE. IL FAUT LA SAISIR VITE, FORT.',
        ],
    ];
    $pool = $fallbacks[$salonId] ?? $fallbacks[1];
    return $pool[array_rand($pool)];
}

// ── Construire l'affichage des derniers messages du dialogue ──────────────────
function olla_dialogue_lines(array $historique, string $pseudo, string $persona): array {
    $lines = [];
    $recent = array_slice($historique, -6);
    $shortPseudo = strtoupper(substr($pseudo, 0, 8));
    $shortPersona = substr($persona, 0, 10);
    foreach ($recent as $msg) {
        $prefix = ($msg['role'] === 'user')
            ? $shortPseudo . ': '
            : $shortPersona . ': ';
        $prefixLen = strlen($prefix);
        // Wrap à une largeur qui tient dans 40 colonnes avec le préfixe
        $wrapW = 40 - $prefixLen;
        $wrapped = anthropic_wrap($msg['content'], $wrapW, 3);
        foreach ($wrapped as $i => $line) {
            if ($i === 0) {
                $lines[] = substr($prefix . $line, 0, 40);
            } else {
                $lines[] = substr(str_repeat(' ', $prefixLen) . $line, 0, 40);
            }
        }
        $lines[] = '';
    }
    if (!empty($lines) && end($lines) === '') {
        array_pop($lines);
    }
    return array_slice($lines, -14);
}

// ── Construire l'écran d'accueil ──────────────────────────────────────────────
function olla_build_accueil(int $t0, string $self, array $context): void {
    $vdt = MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
    $vdt .= olla_bandeau($t0);

    $vdt .= MiniPaviCli::setPos(1, 1) . VDT_BGMAGENTA . VDT_TXTWHITE . VDT_SZDBLH
          . olla_centre('***  3615  OLLA  ***', 40);
    $vdt .= MiniPaviCli::setPos(1, 2) . VDT_BGMAGENTA . VDT_TXTWHITE . VDT_SZDBLH
          . olla_centre('***  3615  OLLA  ***', 40);

    $vdt .= MiniPaviCli::setPos(1, 3) . VDT_BGBLACK . VDT_TXTMAGENTA . VDT_SZNORM
          . olla_centre('LE MINITEL ROSE... DE LA CUISINE', 40);

    $vdt .= MiniPaviCli::setPos(1, 4) . olla_sep();

    $vdt .= MiniPaviCli::setPos(1, 6) . VDT_TXTWHITE
          . olla_centre('BIENVENUE SUR LE 3615 OLLA', 40);
    $vdt .= MiniPaviCli::setPos(1, 7) . VDT_TXTMAGENTA
          . olla_centre('Le service de rencontres gourmandes', 40);

    $vdt .= MiniPaviCli::setPos(1, 9) . VDT_TXTYELLOW
          . '  Quel est votre pseudo ?';

    $vdt .= MiniPaviCli::setPos(1, 18) . olla_sep();
    $vdt .= MiniPaviCli::setPos(1, 20) . VDT_TXTGREEN
          . '  Tarif : 0.56 EUR/min + appel local';
    $vdt .= MiniPaviCli::setPos(1, 21) . VDT_TXTCYAN
          . olla_centre('(Prix d\'un vrai petit plat mijote)', 40);
    $vdt .= MiniPaviCli::setPos(1, 23) . VDT_TXTGREEN
          . '  [SOMMAIRE] Retour menu';

    $cmd = MiniPaviCli::createInputMsgCmd(1, 11, 36, 3, MSK_ENVOI | MSK_SOMMAIRE | MSK_RETOUR, true, ' ');
    MiniPaviCli::send($vdt, $self, serialize($context), true, $cmd, false);
}

// ── Construire l'écran des salons ─────────────────────────────────────────────
function olla_build_salons(int $t0, string $pseudo, string $self, array $context): void {
    $salons = olla_salons();
    $vdt = MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
    $vdt .= olla_bandeau($t0);

    $vdt .= MiniPaviCli::setPos(1, 1) . VDT_BGMAGENTA . VDT_TXTWHITE . VDT_SZDBLH
          . olla_centre('***  SALONS D\'OLA  ***', 40);
    $vdt .= MiniPaviCli::setPos(1, 2) . VDT_BGMAGENTA . VDT_TXTWHITE . VDT_SZDBLH
          . olla_centre('***  SALONS D\'OLA  ***', 40);

    $vdt .= MiniPaviCli::setPos(1, 3) . VDT_BGBLACK . VDT_TXTMAGENTA
          . olla_centre(strtoupper(substr($pseudo, 0, 30)) . ' — CHOISISSEZ VOTRE SALON', 40);

    $vdt .= MiniPaviCli::setPos(1, 4) . olla_sep();

    $colors = [VDT_TXTCYAN, VDT_TXTYELLOW, VDT_TXTRED, VDT_TXTMAGENTA, VDT_TXTGREEN, VDT_TXTWHITE];
    foreach ($salons as $n => $salon) {
        $row = 4 + $n;
        $vdt .= MiniPaviCli::setPos(1, $row)
              . $colors[$n - 1] . VDT_FDINV . " $n " . VDT_FDNORM
              . VDT_TXTWHITE . ' ' . substr($salon['nom'], 0, 28)
              . ' ' . $colors[$n - 1] . '(' . $salon['ambiance'] . ')';
    }

    $vdt .= MiniPaviCli::setPos(1, 12) . olla_sep();

    $vdt .= MiniPaviCli::setPos(1, 13) . VDT_TXTWHITE . VDT_FDINV . ' 7 ' . VDT_FDNORM
          . VDT_TXTGREEN . '  PETITES ANNONCES GOURMANDES';

    $vdt .= MiniPaviCli::setPos(1, 15) . VDT_TXTWHITE . VDT_FDINV . ' 8 ' . VDT_FDNORM
          . VDT_TXTCYAN . '  CHANGER DE PSEUDO';

    $vdt .= MiniPaviCli::setPos(1, 17) . olla_sep();
    $vdt .= MiniPaviCli::setPos(1, 20) . VDT_TXTYELLOW . '  Votre choix (1-8) : ';
    $vdt .= MiniPaviCli::setPos(1, 22) . VDT_TXTGREEN
          . '  [SOMMAIRE] Menu  [RETOUR] Pseudo';

    $cmd = MiniPaviCli::createInputTxtCmd(22, 20, 1, MSK_ENVOI | MSK_SOMMAIRE | MSK_RETOUR, true, '_');
    MiniPaviCli::send($vdt, $self, serialize($context), true, $cmd, false);
}

// ── Construire l'écran de dialogue ────────────────────────────────────────────
function olla_build_dialogue(int $t0, string $pseudo, string $self, array $context): void {
    $salons     = olla_salons();
    $salonId    = (int)($context['salon_id'] ?? 1);
    $salon      = $salons[$salonId] ?? $salons[1];
    $historique = $context['historique'] ?? [];
    $nbEchanges = (int)($context['nb_echanges'] ?? 0);

    $vdt = MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
    $vdt .= olla_bandeau($t0);

    // Row 1: nom du salon centré, sans double hauteur (gagne de la place)
    $nomCourt = substr($salon['nom'], 0, 36);
    $vdt .= MiniPaviCli::setPos(1, 1) . VDT_BGMAGENTA . VDT_TXTWHITE
          . str_pad($nomCourt, 40);

    // Row 2: info persona
    $info = $salon['persona'] . ', ' . $salon['age'] . ' ans, ' . $salon['ville'];
    $vdt .= MiniPaviCli::setPos(1, 2) . VDT_TXTMAGENTA
          . str_pad($info, 40);

    // Row 3: séparateur
    $vdt .= MiniPaviCli::setPos(1, 3) . olla_sep();

    // Rows 4-17: zone messages (14 lignes fixes, pad chaque ligne à 40)
    $msgLines = olla_dialogue_lines($historique, $pseudo, $salon['persona']);
    for ($i = 0; $i < 14; $i++) {
        $row = 4 + $i;
        if ($i < count($msgLines)) {
            $line = $msgLines[$i];
            if (empty(trim($line))) {
                $vdt .= MiniPaviCli::setPos(1, $row) . VDT_TXTWHITE . str_repeat(' ', 40);
            } else {
                $isUser = (strpos($line, strtoupper(substr($pseudo, 0, 8)) . ': ') === 0);
                $color = $isUser ? VDT_TXTGREEN : VDT_TXTWHITE;
                $vdt .= MiniPaviCli::setPos(1, $row) . $color . str_pad(substr($line, 0, 40), 40);
            }
        } else {
            // Lignes vides : on écrit 40 espaces pour éviter les artéfacts
            $vdt .= MiniPaviCli::setPos(1, $row) . VDT_TXTWHITE . str_repeat(' ', 40);
        }
    }

    // Row 18: séparateur
    $vdt .= MiniPaviCli::setPos(1, 18) . olla_sep();

    // Row 19: rappel tarif ou vide
    if ($nbEchanges > 0 && $nbEchanges % 10 === 0) {
        $vdt .= MiniPaviCli::setPos(1, 19) . VDT_TXTRED
              . olla_centre('COUT ESTIME : ' . olla_tarif($t0) . ' — UN PETIT PLAT !', 40);
    }

    // Row 20: label saisie
    $vdt .= MiniPaviCli::setPos(1, 20) . VDT_TXTYELLOW . '  Votre message : ';

    // Rows 21-22: InputMsg
    // Row 23: aide touches
    $vdt .= MiniPaviCli::setPos(1, 23) . VDT_TXTGREEN
          . '  [RETOUR] Salon  [SOMMAIRE] Menu';

    $cmd = MiniPaviCli::createInputMsgCmd(1, 21, 36, 2, MSK_ENVOI | MSK_RETOUR | MSK_SOMMAIRE, true, ' ');
    MiniPaviCli::send($vdt, $self, serialize($context), true, $cmd, false);
}

// ── Construire la liste d'annonces ────────────────────────────────────────────
function olla_build_annonces_liste(int $t0, string $self, array $context): void {
    $annonces   = olla_annonces();
    $page       = (int)($context['page_annonces'] ?? 0);
    $perPage    = 6;
    $totalPages = (int)ceil(count($annonces) / $perPage);

    $vdt = MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
    $vdt .= olla_bandeau($t0);

    $vdt .= MiniPaviCli::setPos(1, 1) . VDT_BGMAGENTA . VDT_TXTWHITE . VDT_SZDBLH
          . olla_centre('PETITES ANNONCES', 40);
    $vdt .= MiniPaviCli::setPos(1, 2) . VDT_BGMAGENTA . VDT_TXTWHITE . VDT_SZDBLH
          . olla_centre('GOURMANDES', 40);

    $vdt .= MiniPaviCli::setPos(1, 3) . VDT_TXTMAGENTA
          . olla_centre(count($annonces) . ' ANNONCES — PAGE ' . ($page + 1) . '/' . $totalPages, 40);
    $vdt .= MiniPaviCli::setPos(1, 4) . olla_sep();

    $startIdx = $page * $perPage;
    for ($i = 0; $i < $perPage; $i++) {
        $idx = $startIdx + $i;
        $row = 5 + $i * 2;
        if ($idx < count($annonces)) {
            $a = $annonces[$idx];
            $vdt .= MiniPaviCli::setPos(1, $row)
                  . VDT_TXTCYAN . VDT_FDINV . ' ' . ($i + 1) . ' ' . VDT_FDNORM
                  . VDT_TXTYELLOW . ' ' . substr($a['titre'], 0, 32);
            $vdt .= MiniPaviCli::setPos(5, $row + 1)
                  . VDT_TXTWHITE . substr($a['ville'], 0, 12)
                  . VDT_TXTGREEN . ' — ' . substr($a['prix'], 0, 20);
        }
    }

    $navText = '';
    if ($page > 0) $navText .= '[RETOUR] ';
    $navText .= 'Pg ' . ($page + 1) . '/' . $totalPages;
    if ($page < $totalPages - 1) $navText .= ' [SUITE]';

    $vdt .= MiniPaviCli::setPos(1, 20) . VDT_TXTYELLOW . olla_centre($navText, 40);
    $vdt .= MiniPaviCli::setPos(1, 21) . VDT_TXTGREEN
          . olla_centre('Tapez 1-' . min($perPage, count($annonces) - $startIdx) . ' pour voir une annonce', 40);
    $vdt .= MiniPaviCli::setPos(1, 22) . VDT_TXTCYAN
          . olla_centre('[SOMMAIRE] Menu  [RETOUR] Salons', 40);

    $vdt .= MiniPaviCli::setPos(1, 23) . VDT_TXTYELLOW . '  Votre choix : ';

    $cmd = MiniPaviCli::createInputTxtCmd(19, 23, 1, MSK_ENVOI | MSK_SUITE | MSK_RETOUR | MSK_SOMMAIRE, true, '_');
    MiniPaviCli::send($vdt, $self, serialize($context), true, $cmd, false);
}

// ── Construire le détail d'une annonce ────────────────────────────────────────
function olla_build_annonce_detail(int $t0, string $self, array $context): void {
    $annonces = olla_annonces();
    $idx      = (int)($context['annonce_detail_idx'] ?? 0);

    if (!isset($annonces[$idx])) {
        $context['step'] = 'annonces_liste';
        olla_build_annonces_liste($t0, $self, $context);
        return;
    }

    $a = $annonces[$idx];

    $vdt = MiniPaviCli::clearScreen() . PRO_MIN . PRO_LOCALECHO_OFF . VDT_CUROFF;
    $vdt .= olla_bandeau($t0);

    $vdt .= MiniPaviCli::setPos(1, 1) . VDT_BGMAGENTA . VDT_TXTWHITE . VDT_SZDBLH
          . olla_centre('PETITE ANNONCE', 40);
    $vdt .= MiniPaviCli::setPos(1, 2) . VDT_BGMAGENTA . VDT_TXTWHITE . VDT_SZDBLH
          . olla_centre('GOURMANDE', 40);

    $vdt .= MiniPaviCli::setPos(1, 4) . VDT_TXTYELLOW . substr($a['titre'], 0, 38);
    $vdt .= MiniPaviCli::setPos(1, 6) . VDT_TXTWHITE . '  VILLE : ' . VDT_TXTCYAN . $a['ville'];
    $vdt .= MiniPaviCli::setPos(1, 7) . VDT_TXTWHITE . '  PRIX  : ' . VDT_TXTGREEN . $a['prix'];
    $vdt .= MiniPaviCli::setPos(1, 9) . VDT_TXTWHITE . '  DESCRIPTION :';

    foreach (anthropic_wrap($a['desc'], 36, 5) as $i => $l) {
        $vdt .= MiniPaviCli::setPos(3, 10 + $i) . VDT_TXTWHITE . $l;
    }

    $vdt .= MiniPaviCli::setPos(1, 16) . VDT_TXTWHITE . '  CONTACT : ' . VDT_TXTYELLOW . $a['tel'];

    $vdt .= MiniPaviCli::setPos(1, 20) . olla_sep();
    $vdt .= MiniPaviCli::setPos(1, 21) . VDT_TXTGREEN
          . olla_centre('Annonce ' . ($idx + 1) . '/' . count($annonces), 40);
    $vdt .= MiniPaviCli::setPos(1, 22) . VDT_TXTCYAN
          . olla_centre('[RETOUR] Liste  [SOMMAIRE] Menu', 40);

    $cmd = MiniPaviCli::createInputTxtCmd(1, 23, 1, MSK_RETOUR | MSK_SOMMAIRE, false, ' ');
    MiniPaviCli::send($vdt, $self, serialize($context), true, $cmd, false);
}

// ==============================================================================
// MAIN — State machine
// Pattern jukebox : tout le processing en haut (change $step), tout le rendu en bas
// ==============================================================================
try {
    MiniPaviCli::start();
    $fctn = MiniPaviCli::$fctn;
    if ($fctn === 'FIN') exit;

    $menu  = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/';
    $self  = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];

    // SOMMAIRE -> retour au menu principal DAMIEN
    if ($fctn === 'SOMMAIRE') {
        MiniPaviCli::send('', $menu, serialize([]), true, null, 'yes-cnx');
        exit;
    }

    // Recuperer le contexte
    if ($fctn === 'CNX' || $fctn === 'DIRECTCNX') {
        $context = ['step' => 'accueil', 't0' => time()];
    } else {
        $context = unserialize(MiniPaviCli::$context);
        if (!isset($context['t0'])) {
            $context['t0'] = time();
        }
    }

    $step   = $context['step'] ?? 'accueil';
    $pseudo = $context['pseudo'] ?? '';
    $t0     = (int)($context['t0'] ?? time());
    $skip   = false; // local uniquement, ne passe JAMAIS dans le contexte

    // Recuperer l'input
    $rawContent = MiniPaviCli::$content;
    $input = is_array($rawContent) ? trim(implode('', $rawContent)) : trim((string)$rawContent);
    $input = rtrim($input, '. ');

    // Salons (utilise dans plusieurs blocs)
    $salons = olla_salons();

    // ==========================================================================
    // PHASE 1 : PROCESSING — chaque bloc modifie $step et $skip si transition
    // ==========================================================================

    // -- ACCUEIL --
    if ($step === 'accueil') {
        if (!$skip && $fctn === 'ENVOI' && $input !== '') {
            $context['pseudo'] = substr($input, 0, 20);
            $context['step']   = 'salons';
            $skip = true;
            $step = 'salons';
        } elseif (!$skip && $fctn === 'RETOUR') {
            MiniPaviCli::send('', $menu, serialize([]), true, null, 'yes-cnx');
            exit;
        }
    }

    // -- SALONS --
    if ($step === 'salons') {
        if (!$skip) {
            if ($fctn === 'RETOUR') {
                $context['step'] = 'accueil';
                $skip = true;
                $step = 'accueil';
            } elseif ($fctn === 'ENVOI' && $input !== '') {
                $choix = (int)$input;
                if ($choix >= 1 && $choix <= 6) {
                    $context['salon_id']    = $choix;
                    $context['historique']  = [];
                    $context['nb_echanges'] = 0;
                    $context['step']        = 'dialogue';
                    $skip = true;
                    $step = 'dialogue';
                } elseif ($choix === 7) {
                    $context['step']          = 'annonces_liste';
                    $context['page_annonces'] = 0;
                    $skip = true;
                    $step = 'annonces_liste';
                } elseif ($choix === 8) {
                    $context['step'] = 'accueil';
                    $skip = true;
                    $step = 'accueil';
                }
            }
        }
    }

    // -- DIALOGUE --
    if ($step === 'dialogue') {
        $salonId    = (int)($context['salon_id'] ?? 1);
        $salon      = $salons[$salonId] ?? $salons[1];
        $historique = $context['historique'] ?? [];
        $nbEchanges = (int)($context['nb_echanges'] ?? 0);

        if (!$skip) {
            if ($fctn === 'RETOUR') {
                $context['step'] = 'salons';
                $skip = true;
                $step = 'salons';
            } elseif ($fctn === 'ENVOI' && $input !== '') {
                $historique[] = ['role' => 'user', 'content' => $input];
                $nbEchanges++;

                $apiMessages = [];
                foreach ($historique as $h) {
                    $apiMessages[] = ['role' => $h['role'], 'content' => $h['content']];
                }

                try {
                    $data    = anthropic_chat($salon['system'], $apiMessages, 150);
                    $reponse = anthropic_ascii(anthropic_text($data));
                    $reponse = trim($reponse);
                    if (empty($reponse)) {
                        throw new \RuntimeException('Reponse vide');
                    }
                } catch (Exception $e) {
                    $reponse = olla_fallback($salonId);
                }

                $historique[] = ['role' => 'assistant', 'content' => $reponse];
                if (count($historique) > 16) {
                    $historique = array_slice($historique, -16);
                }
            }
        }

        // Premier affichage : message d'ouverture
        if (empty($historique)) {
            $historique[] = ['role' => 'assistant', 'content' => $salon['ouverture']];
        }

        $context['historique']  = $historique;
        $context['nb_echanges'] = $nbEchanges;
    }

    // -- ANNONCES LISTE --
    if ($step === 'annonces_liste') {
        $annonces   = olla_annonces();
        $page       = (int)($context['page_annonces'] ?? 0);
        $perPage    = 6;
        $totalPages = (int)ceil(count($annonces) / $perPage);

        if (!$skip) {
            if ($fctn === 'RETOUR') {
                if ($page === 0) {
                    $context['step'] = 'salons';
                    $skip = true;
                    $step = 'salons';
                } else {
                    $page = max(0, $page - 1);
                }
            } elseif ($fctn === 'SUITE') {
                $page = min($totalPages - 1, $page + 1);
            } elseif ($fctn === 'ENVOI' && is_numeric($input)) {
                $idx = $page * $perPage + (int)$input - 1;
                if ($idx >= 0 && $idx < count($annonces)) {
                    $context['annonce_detail_idx'] = $idx;
                    $context['step'] = 'annonce_detail';
                    $skip = true;
                    $step = 'annonce_detail';
                }
            }
        }
        $context['page_annonces'] = $page;
    }

    // -- ANNONCE DETAIL --
    if ($step === 'annonce_detail') {
        if ($fctn === 'RETOUR') {
            $context['step'] = 'annonces_liste';
            $skip = true;
            $step = 'annonces_liste';
        }
    }

    // ==========================================================================
    // PHASE 2 : RENDU — base sur le $step final (quelle que soit l'origine)
    // ==========================================================================
    switch ($step) {
        case 'accueil':
            olla_build_accueil($t0, $self, $context);
            break;
        case 'salons':
            olla_build_salons($t0, $pseudo, $self, $context);
            break;
        case 'dialogue':
            olla_build_dialogue($t0, $pseudo, $self, $context);
            break;
        case 'annonces_liste':
            olla_build_annonces_liste($t0, $self, $context);
            break;
        case 'annonce_detail':
            olla_build_annonce_detail($t0, $self, $context);
            break;
        default:
            MiniPaviCli::send('', $menu, serialize([]), true, null, 'yes-cnx');
    }

} catch (Exception $e) {}
exit;
