<?php
/**
 * PedalWorks Dynamics - Review Profanity Filter Utility
 * MP4 Rubric: "apply regex to filter or mask bad/foul words on the review"
 *
 * Replaces matching foul words with asterisks preserving word boundaries.
 */

if (!function_exists('maskProfanity')) {
    function maskProfanity(string $text): string {
        if (trim($text) === '') {
            return '';
        }

        // List of offensive/foul words commonly screened in English & Tagalog contexts
        $badWords = [
            // English vulgarities & slurs
            'damn', 'hell', 'shit', 'bullshit', 'fuck', 'fucking', 'fucker',
            'bitch', 'ass', 'asshole', 'bastard', 'crap', 'dick', 'pussy',
            'scam', 'scammer', 'fake', 'trash', 'garbage', 'idiot', 'stupid',
            // Filipino/Tagalog vulgarities
            'tangina', 'tang-ina', 'putangina', 'puta', 'gago', 'gaga',
            'tarantado', 'tarantada', 'ulol', 'leche', 'bwisit', 'buwisit',
            'kupal', 'pakshet', 'bobo', 'tanga', 'inutil', 'punyeta', 'hayop'
        ];

        // Apply regex with word boundaries (\b) and case-insensitivity (/i)
        foreach ($badWords as $word) {
            $escaped = preg_quote($word, '/');
            $pattern = '/\b' . $escaped . '\b/i';

            $text = preg_replace_callback($pattern, function ($matches) {
                $matched = $matches[0];
                $len = strlen($matched);
                if ($len <= 2) {
                    return str_repeat('*', $len);
                }
                // Keep first character, mask the rest (e.g. "f***")
                return $matched[0] . str_repeat('*', $len - 1);
            }, $text);
        }

        return $text;
    }
}
