<?php

namespace FFans\BbcodeStudio\Formatter;

use DOMXPath;
use Less_Parser;
use s9e\TextFormatter\Configurator\Helpers\TemplateLoader;

final class BbcodeRuleDefinition
{
    public const WRAPPER_CLASS = 'BbcodeStudio-bbcode';

    public static function className(string $tag): string
    {
        return 'BbcodeStudio-bbcode-'.$tag;
    }

    public static function normalizeStyles(string $styles): string
    {
        $styles = trim($styles);

        if ($styles === '') {
            return '';
        }

        if (preg_match('/@(?:import|plugin)\b/i', $styles)) {
            throw new RuleConfigurationException('styles_imports_unsupported');
        }

        if (preg_match('/(?:expression\s*\(|javascript\s*:)/i', $styles)) {
            throw new RuleConfigurationException('styles_unsafe_value');
        }

        self::assertBalancedBraces($styles);

        try {
            $parser = new Less_Parser();
            $parser->parse(self::scopedStyles('validation', $styles));
            $parser->getCss();
        } catch (\Throwable $exception) {
            throw new RuleConfigurationException('styles_invalid', previous: $exception);
        }

        return $styles;
    }

    private static function assertBalancedBraces(string $styles): void
    {
        $depth = 0;
        $quote = null;
        $comment = false;

        for ($index = 0, $length = strlen($styles); $index < $length; $index++) {
            $character = $styles[$index];
            $next = $styles[$index + 1] ?? '';

            if ($comment) {
                if ($character === '*' && $next === '/') {
                    $comment = false;
                    $index++;
                }

                continue;
            }

            if ($quote !== null) {
                if ($character === '\\') {
                    $index++;
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '/' && $next === '*') {
                $comment = true;
                $index++;
            } elseif ($character === '"' || $character === "'") {
                $quote = $character;
            } elseif ($character === '{') {
                $depth++;
            } elseif ($character === '}' && --$depth < 0) {
                throw new RuleConfigurationException('styles_close_scope');
            }
        }

        if ($comment || $quote !== null || $depth !== 0) {
            throw new RuleConfigurationException('styles_unclosed');
        }
    }

    public static function scopedStyles(string $tag, string $styles): string
    {
        $styles = trim($styles);

        return $styles === '' ? '' : '.'.self::className($tag)." {\n".$styles."\n}\n";
    }

    /** @param iterable<\FFans\BbcodeStudio\BbcodeRule> $rules */
    public static function stylesheet(iterable $rules): string
    {
        $stylesheet = '';

        foreach ($rules as $rule) {
            if ($rule->rule_type === 'bbcode' && trim((string) $rule->css_declarations) !== '') {
                $stylesheet .= self::scopedStyles($rule->tag, (string) $rule->css_declarations);
            }
        }

        return $stylesheet;
    }

    public static function template(string $tag, string $template): string
    {
        return self::addRootClasses($template, [
            self::WRAPPER_CLASS,
            self::className($tag),
        ]);
    }

    /** @param list<string> $classes */
    public static function addRootClasses(string $template, array $classes): string
    {
        $dom = TemplateLoader::load($template);
        $xpath = new DOMXPath($dom);
        $xslNamespace = TemplateLoader::XMLNS_XSL;
        $roots = $xpath->query(
            '//*[namespace-uri() != "'.$xslNamespace.'"]'
            .'[not(ancestor::*[namespace-uri() != "'.$xslNamespace.'"])]'
        );

        if ($roots->length === 0) {
            throw new RuleConfigurationException('template_requires_element');
        }

        foreach ($roots as $root) {
            $root->setAttribute(
                'class',
                implode(' ', array_unique(array_filter([
                    ...preg_split('/\s+/', trim($root->getAttribute('class'))) ?: [],
                    ...$classes,
                ])))
            );
        }

        return TemplateLoader::save($dom);
    }
}
