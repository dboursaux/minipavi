<?php
// =============================================================================
// anthropic.php — Fonctions d'appel à l'API Anthropic (claude-haiku-4-5-20251001)
// =============================================================================

/**
 * Appel à l'API Anthropic Messages.
 * Retourne le tableau décodé de la réponse, ou lance une exception.
 */
function anthropic_call(string $system, string $user, int $maxTokens = 500): array
{
    $apiKey = getenv('ANTHROPIC_API_KEY');
    if (!$apiKey) {
        throw new \RuntimeException('ANTHROPIC_API_KEY non définie');
    }

    $body = json_encode([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => $maxTokens,
        'system'     => $system,
        'messages'   => [
            ['role' => 'user', 'content' => $user],
        ],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        throw new \RuntimeException("Erreur API Anthropic HTTP $httpCode");
    }

    $data = json_decode($response, true);
    if (!isset($data['content'][0]['text'])) {
        throw new \RuntimeException('Réponse Anthropic malformée');
    }

    return $data;
}

/**
 * Appel à l'API Anthropic avec historique de conversation.
 * $messages doit être un tableau de ['role'=>'user'|'assistant', 'content'=>'...'].
 * Retourne le tableau décodé de la réponse, ou lance une exception.
 */
function anthropic_chat(string $system, array $messages, int $maxTokens = 500): array
{
    $apiKey = getenv('ANTHROPIC_API_KEY');
    if (!$apiKey) {
        throw new \RuntimeException('ANTHROPIC_API_KEY non définie');
    }

    $body = json_encode([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => $maxTokens,
        'system'     => $system,
        'messages'   => $messages,
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        throw new \RuntimeException("Erreur API Anthropic HTTP $httpCode");
    }

    $data = json_decode($response, true);
    if (!isset($data['content'][0]['text'])) {
        throw new \RuntimeException('Réponse Anthropic malformée');
    }

    return $data;
}

/**
 * Extrait le texte brut de la réponse anthropic_call().
 */
function anthropic_text(array $data): string
{
    return $data['content'][0]['text'] ?? '';
}

/**
 * Parse un bloc JSON dans la réponse (avec ou sans balises ```json).
 * Retourne le tableau décodé ou null si parsing impossible.
 */
function anthropic_parse_json(string $text): ?array
{
    // Supprimer les balises de code éventuelles
    $text = preg_replace('/^```json\s*/m', '', $text);
    $text = preg_replace('/^```\s*/m', '', $text);
    $text = trim($text);

    $decoded = json_decode($text, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Convertit une chaîne UTF-8 en ASCII majuscules (pour affichage Minitel).
 * Supprime les accents et caractères non-ASCII.
 */
function anthropic_ascii(string $s): string
{
    // Translittération des caractères accentués
    $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    return strtoupper($s);
}

/**
 * Coupe un texte en lignes de $width caractères max, par mots entiers.
 * Retourne un tableau de lignes (max $maxLines lignes).
 */
function anthropic_wrap(string $s, int $width = 37, int $maxLines = 12): array
{
    $words = explode(' ', $s);
    $lines = [];
    $current = '';

    foreach ($words as $word) {
        if (strlen($current) + strlen($word) + ($current ? 1 : 0) <= $width) {
            $current .= ($current ? ' ' : '') . $word;
        } else {
            if ($current !== '') {
                $lines[] = $current;
            }
            $current = $word;
        }
        if (count($lines) >= $maxLines) {
            break;
        }
    }
    if ($current !== '' && count($lines) < $maxLines) {
        $lines[] = $current;
    }

    return $lines;
}
