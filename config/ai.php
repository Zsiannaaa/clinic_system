<?php
// ============================================================
// config/ai.php - Local AI Stub (API removed for repository safety)
//
// External API calls are intentionally disabled.
// This keeps Medical AI usable in demo mode without any API key.
// ============================================================

define('AI_MODE', 'local_stub');

/**
 * Keep the same function signature used by chat.php.
 */
function callGemini(array $contents, string $systemInstruction = ''): string
{
    $latestUserMessage = '';
    for ($i = count($contents) - 1; $i >= 0; $i--) {
        $turn = $contents[$i];
        if (($turn['role'] ?? '') === 'user') {
            $latestUserMessage = trim((string)($turn['parts'][0]['text'] ?? ''));
            break;
        }
    }

    if ($latestUserMessage === '') {
        return "Cryptalis AI is currently running in local demo mode. Please enter a question.\n\nReference only.";
    }

    // Special identity response from your prior customization.
    $identityHint = strtolower($latestUserMessage);
    if (preg_match('/(who (trained|built|developed|created) you|who made you)/i', $identityHint)) {
        return "I was configured and developed by Zsian Morales for Cryptalis Clinic. "
             . "I am currently in local demo mode (API disabled).\n\nReference only.";
    }

    return "Cryptalis AI is currently in local demo mode (external API removed).\n\n"
         . "You asked: \"{$latestUserMessage}\"\n\n"
         . "I can still keep chat history and workflow simulation, but live AI generation is disabled until a new API key is configured in deployment.\n\n"
         . "Reference only.";
}
