<?php

namespace FFans\BbcodeStudio\Formatter;

final class BbcodeUsageInspector
{
    /**
     * @return list<array{name: string, type: string, optional: bool, choices: list<string>}>
     */
    public static function attributes(string $usage): array
    {
        $openingTag = self::openingTag($usage);

        if ($openingTag === '') {
            return [];
        }

        preg_match_all(
            '/(?<![a-z0-9_-])(?<name>[a-z][a-z0-9_-]*)\s*=\s*\{(?<definition>(?:\\\\.|[^{}])*)\}/i',
            $openingTag,
            $matches,
            PREG_SET_ORDER
        );

        $attributes = [];

        foreach ($matches as $match) {
            $definition = $match['definition'];
            $parts = explode(';', $definition);
            $token = array_shift($parts) ?? '';
            $optional = false;

            if (str_ends_with($token, '?')) {
                $optional = true;
                $token = substr($token, 0, -1);
            }

            foreach ($parts as $option) {
                if (strtolower(trim($option)) === 'optional') {
                    $optional = true;
                }
            }

            [$tokenName, $arguments] = array_pad(explode('=', $token, 2), 2, '');
            $type = strtoupper(rtrim(trim($tokenName), '0123456789'));

            if ($type === '') {
                continue;
            }

            $choices = [];

            if ($type === 'CHOICE') {
                $choices = array_values(array_filter(
                    array_map('trim', explode(',', $arguments)),
                    fn (string $choice): bool => $choice !== ''
                ));
            }

            $attributes[] = [
                'name' => strtolower($match['name']),
                'type' => $type,
                'optional' => $optional,
                'choices' => $choices,
            ];
        }

        return $attributes;
    }

    private static function openingTag(string $usage): string
    {
        $start = strpos($usage, '[');

        if ($start === false) {
            return '';
        }

        $depth = 0;
        $escaped = false;

        for ($index = $start + 1, $length = strlen($usage); $index < $length; $index++) {
            $character = $usage[$index];

            if ($escaped) {
                $escaped = false;
                continue;
            }

            if ($character === '\\') {
                $escaped = true;
            } elseif ($character === '{') {
                $depth++;
            } elseif ($character === '}' && $depth > 0) {
                $depth--;
            } elseif ($character === ']' && $depth === 0) {
                return substr($usage, $start, $index - $start + 1);
            }
        }

        return '';
    }
}
