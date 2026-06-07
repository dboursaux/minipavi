<?php
// =============================================================================
// google_ai.php — Wrapper IA pour le module Google (migré vers Anthropic)
// Nom conservé pour compatibilité avec l'appelant (index.php → google.php)
// =============================================================================

require_once __DIR__ . '/anthropic.php';

/**
 * Pose une question au modèle Anthropic et retourne la réponse texte brute.
 * Interface identique à l'ancienne fonction OpenAI/Google AI.
 */
function google_ai_call(string $question): string
{
    $system = 'Tu es un assistant concis. Réponds en français, de manière courte et claire. '
            . 'Maximum 3 phrases. Pas de markdown, pas de listes, texte brut uniquement.';

    try {
        $data = anthropic_call($system, $question, 300);
        return anthropic_text($data);
    } catch (\Exception $e) {
        return 'Erreur IA : ' . $e->getMessage();
    }
}
