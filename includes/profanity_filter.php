<?php

if (!function_exists('maskProfanity')) {
    function maskProfanity(string $text): string {
        if (trim($text) === '') {
            return '';
        }

        $badWords = [

            'damn', 'hell', 'shit', 'bullshit', 'fuck', 'fucking', 'fucker',
            'bitch', 'ass', 'asshole', 'bastard', 'crap', 'dick', 'pussy',
            'scam', 'scammer', 'fake', 'trash', 'garbage', 'idiot', 'stupid',

            'tangina', 'tang-ina', 'putangina', 'puta', 'gago', 'gaga',
            'tarantado', 'tarantada', 'ulol', 'leche', 'bwisit', 'buwisit',
            'kupal', 'pakshet', 'bobo', 'tanga', 'inutil', 'punyeta', 'hayop'
        ];

        foreach ($badWords as $word) {
            $escaped = preg_quote($word, '/');
            $pattern = '/\b' . $escaped . '\b/i';

            $text = preg_replace_callback($pattern, function ($matches) {
                $matched = $matches[0];
                $len = strlen($matched);
                if ($len <= 2) {
                    return str_repeat('*', $len);
                }

                return $matched[0] . str_repeat('*', $len - 1);
            }, $text);
        }

        return $text;
    }
}
