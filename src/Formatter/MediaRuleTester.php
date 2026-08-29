<?php

namespace FFans\BbcodeStudio\Formatter;

use InvalidArgumentException;

final class MediaRuleTester
{
    public function __construct(private ShortLinkResolver $shortLinkResolver)
    {
    }

    /**
     * @param array<int, mixed> $sourceRules
     * @return array{matched: bool, reason?: string, matchType?: string, ruleNumber?: int, captures?: array<string, string>, embedUrl?: string}
     */
    public function test(string $url, array $sourceRules, string $embedUrl): array
    {
        $url = trim($url);
        $embedUrl = trim($embedUrl);

        if ($url === '') {
            return $this->failure('empty_url');
        }

        if (mb_strlen($url) > 2000) {
            return $this->failure('url_too_long');
        }

        $requiredCaptures = MediaRuleDefinition::embedUrlCaptures($embedUrl);

        if ($requiredCaptures === []) {
            return $this->failure('missing_embed_capture');
        }

        if (! str_starts_with($embedUrl, 'https://') && ! str_starts_with($embedUrl, '//')) {
            return $this->failure('invalid_embed_url');
        }

        $extractRules = [];
        $redirectRules = [];

        foreach ($sourceRules as $index => $sourceRule) {
            $ruleNumber = $index + 1;

            if (! is_array($sourceRule)) {
                return $this->failure('invalid_pattern', $ruleNumber);
            }

            $type = (string) ($sourceRule['type'] ?? '');
            $pattern = trim((string) ($sourceRule['pattern'] ?? ''));

            if (! in_array($type, ['extract', 'redirect'], true) || $pattern === '' || mb_strlen($pattern) > 2000) {
                return $this->failure('invalid_pattern', $ruleNumber);
            }

            set_error_handler(static fn () => true);
            $validRegexp = preg_match($pattern, '') !== false;
            restore_error_handler();

            if (! $validRegexp || MediaRuleDefinition::hostsFromPatterns($pattern) === []) {
                return $this->failure('invalid_pattern', $ruleNumber);
            }

            if ($type === 'extract') {
                $captures = MediaRuleDefinition::captures($pattern);

                if ($captures === [] || array_diff($requiredCaptures, $captures) !== []) {
                    return $this->failure('missing_pattern_capture', $ruleNumber);
                }

                $extractRules[] = ['number' => $ruleNumber, 'pattern' => $pattern];
            } else {
                try {
                    MediaRuleDefinition::captureWholePattern($pattern, 'short_url');
                } catch (InvalidArgumentException) {
                    return $this->failure('invalid_pattern', $ruleNumber);
                }

                $redirectRules[] = ['number' => $ruleNumber, 'pattern' => $pattern];
            }
        }

        if ($extractRules === []) {
            return $this->failure('missing_extract_rule');
        }

        foreach ($extractRules as $rule) {
            if (preg_match($rule['pattern'], $url, $matches) !== 1) {
                continue;
            }

            $captures = $this->namedCaptures($matches);

            if ($this->missingRequiredCapture($captures, $requiredCaptures)) {
                return $this->failure('empty_capture', $rule['number']);
            }

            return $this->success('extract', $rule['number'], $captures, $embedUrl);
        }

        foreach ($redirectRules as $rule) {
            if (preg_match($rule['pattern'], $url) !== 1) {
                continue;
            }

            $captures = $this->shortLinkResolver->resolve(
                $url,
                MediaRuleDefinition::hostsFromPatterns(implode("\n", array_column($redirectRules, 'pattern'))),
                MediaRuleDefinition::hostsFromPatterns(implode("\n", array_column($extractRules, 'pattern'))),
                array_column($extractRules, 'pattern'),
                $requiredCaptures
            );

            if ($captures === null) {
                return $this->failure('redirect_failed', $rule['number'], 'redirect');
            }

            return $this->success('redirect', $rule['number'], $captures, $embedUrl);
        }

        return $this->failure('no_match');
    }

    /** @param array<int|string, mixed> $matches @return array<string, string> */
    private function namedCaptures(array $matches): array
    {
        $captures = [];

        foreach ($matches as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $captures[strtolower($name)] = $value;
            }
        }

        return $captures;
    }

    /** @param array<string, string> $captures @param list<string> $required */
    private function missingRequiredCapture(array $captures, array $required): bool
    {
        foreach ($required as $name) {
            if (! isset($captures[$name]) || $captures[$name] === '') {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, string> $captures */
    private function success(string $matchType, int $ruleNumber, array $captures, string $embedUrl): array
    {
        return [
            'matched' => true,
            'matchType' => $matchType,
            'ruleNumber' => $ruleNumber,
            'captures' => $captures,
            'embedUrl' => preg_replace_callback(
                '/\{([a-z][a-z0-9_]*)\}/i',
                fn (array $matches) => $captures[strtolower($matches[1])] ?? $matches[0],
                $embedUrl
            ),
        ];
    }

    private function failure(string $reason, ?int $ruleNumber = null, ?string $matchType = null): array
    {
        return array_filter([
            'matched' => false,
            'reason' => $reason,
            'matchType' => $matchType,
            'ruleNumber' => $ruleNumber,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
