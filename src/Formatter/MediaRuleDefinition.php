<?php

namespace FFans\BbcodeStudio\Formatter;

use s9e\TextFormatter\Configurator;

final class MediaRuleDefinition
{
    public const TEMPLATE = '<xsl:apply-templates/>';
    public const WRAPPER_CLASS = 'BbcodeStudio-media';

    private const ALLOWED_IFRAME_ATTRIBUTES = [
        'allow',
        'allowfullscreen',
        'height',
        'referrerpolicy',
        'scrolling',
    ];

    private const BOOLEAN_IFRAME_ATTRIBUTES = [
        'allowfullscreen',
    ];

    public static function usage(string $tag): string
    {
        return '['.$tag.']{URL}[/'.$tag.']';
    }

    public static function className(string $tag): string
    {
        return 'BbcodeStudio-media-'.$tag;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{bbcodeTag: string, siteIds: list<string>}
     */
    public static function configure(Configurator $configurator, string $tag, string $siteId, array $data): array
    {
        $siteTag = $configurator->MediaEmbed->add($siteId, self::siteConfig($data));
        $fixedHeight = trim((string) ($data['aspect_ratio'] ?? '')) === '';
        self::addWrapperClasses($configurator, $siteTag, $tag, $fixedHeight);
        $siteIds = [$siteId];
        $captureDefaults = self::normalizeCaptureDefaults($data['capture_defaults'] ?? []);
        $captureDefaults = array_intersect_key(
            $captureDefaults,
            array_flip(self::embedUrlCaptures((string) $data['embed_url']))
        );
        $requiredCaptures = array_values(array_diff(
            self::embedUrlCaptures((string) $data['embed_url']),
            array_keys($captureDefaults)
        ));
        $redirectPatterns = self::patterns((string) ($data['redirect_pattern'] ?? ''));
        $sourceHosts = self::hostsFromPatterns((string) ($data['redirect_pattern'] ?? ''));
        $targetHosts = self::hostsFromPatterns((string) $data['extract_pattern']);

        if ($redirectPatterns !== []) {
            $redirectSiteId = $siteId.'redirect';
            $redirectTag = $configurator->MediaEmbed->add($redirectSiteId, self::redirectSiteConfig($data));
            self::addShortLinkFilter(
                $redirectTag,
                $sourceHosts,
                $targetHosts,
                self::patterns((string) $data['extract_pattern']),
                $requiredCaptures
            );
            self::addWrapperClasses($configurator, $redirectTag, $tag, $fixedHeight);
            $siteIds[] = $redirectSiteId;
        }

        $bbcode = $configurator->BBCodes->addCustom(self::usage($tag), self::TEMPLATE, [
            'rules' => ['ignoreTags' => true],
        ]);
        $bbcodeTag = $configurator->tags[$bbcode->tagName];
        foreach (self::captures((string) $data['extract_pattern']) as $capture) {
            $attribute = $bbcodeTag->attributes->add($capture);
            $attribute->required = false;

            if (isset($captureDefaults[$capture])) {
                $attribute->defaultValue = $captureDefaults[$capture];
            }
        }

        foreach (self::patterns((string) $data['extract_pattern']) as $pattern) {
            $bbcodeTag->attributePreprocessors->add('content', $pattern);
        }

        if ($redirectPatterns !== []) {
            $attribute = $bbcodeTag->attributes->add('short_url');
            $attribute->required = false;

            foreach ($redirectPatterns as $pattern) {
                $bbcodeTag->attributePreprocessors->add('content', self::captureWholePattern($pattern, 'short_url'));
            }

            self::addShortLinkFilter(
                $bbcodeTag,
                $sourceHosts,
                $targetHosts,
                self::patterns((string) $data['extract_pattern']),
                $requiredCaptures
            );
        }

        $matchTest = implode(' and ', array_map(fn (string $capture) => '@'.$capture, $requiredCaptures));
        $escapedTag = htmlspecialchars($tag, ENT_QUOTES, 'UTF-8');
        $bbcodeTag->template = '<xsl:choose><xsl:when test="'.$matchTest.'">'
            .(string) $siteTag->template
            .'</xsl:when><xsl:otherwise><xsl:text>['.$escapedTag.']</xsl:text>'
            .'<xsl:value-of select="@content"/><xsl:text>[/'.$escapedTag.']</xsl:text>'
            .'</xsl:otherwise></xsl:choose>';
        $configurator->templateNormalizer->normalizeTag($bbcodeTag);
        $configurator->templateChecker->checkTag($bbcodeTag);

        return ['bbcodeTag' => $bbcode->tagName, 'siteIds' => $siteIds];
    }

    private static function addWrapperClasses(Configurator $configurator, object $tag, string $bbcodeTag, bool $fixedHeight): void
    {
        $classes = [
            self::WRAPPER_CLASS,
            self::className($bbcodeTag),
        ];

        if ($fixedHeight) {
            $classes[] = self::WRAPPER_CLASS.'--fixed-height';
        }

        $tag->template = BbcodeRuleDefinition::addRootClasses((string) $tag->template, $classes);
        $configurator->templateNormalizer->normalizeTag($tag);
        $configurator->templateChecker->checkTag($tag);
    }

    /** @param list<string> $sourceHosts @param list<string> $targetHosts @param list<string> $patterns @param list<string> $requiredCaptures */
    private static function addShortLinkFilter(
        object $tag,
        array $sourceHosts,
        array $targetHosts,
        array $patterns,
        array $requiredCaptures
    ): void {
        $tag->filterChain->insert(1, ResolveShortLinkTag::class.'::resolve')
            ->resetParameters()
            ->addParameterByName('tag')
            ->addParameterByName('bbcodeStudio.shortLinkResolver')
            ->addParameterByValue($sourceHosts)
            ->addParameterByValue($targetHosts)
            ->addParameterByValue($patterns)
            ->addParameterByValue($requiredCaptures)
            ->setJS('returnTrue');
    }

    /** @param list<string> $bbcodeTags @param list<string> $siteIds */
    public static function isolateTaggedMedia(Configurator $configurator, array $bbcodeTags, array $siteIds): void
    {
        foreach ($bbcodeTags as $bbcodeTag) {
            foreach ($siteIds as $siteId) {
                $configurator->tags[$bbcodeTag]->rules->denyDescendant($siteId);
            }
        }
    }

    /** @return list<string> */
    public static function patterns(string $patterns): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $patterns) ?: [])));
    }

    /** @return list<string> */
    public static function hostsFromPatterns(string $patterns): array
    {
        $hosts = [];

        foreach (self::patterns($patterns) as $pattern) {
            $pattern = str_replace('\\.', '.', $pattern);
            preg_match_all('/(?:[a-z0-9-]+\.)+[a-z]{2,}/i', $pattern, $matches);
            $hosts = array_merge($hosts, $matches[0]);
        }

        return array_values(array_unique(array_map('strtolower', $hosts)));
    }

    /** @return list<string> */
    public static function captures(string $patterns): array
    {
        preg_match_all('/\(\?[<\']([a-z][a-z0-9_]*)[>\']/', $patterns, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1] ?? [])));
    }

    public static function captureWholePattern(string $pattern, string $capture): string
    {
        $delimiter = substr($pattern, 0, 1);

        if ($delimiter === '' || ctype_alnum($delimiter) || $delimiter === '\\' || ctype_space($delimiter)) {
            throw new RuleConfigurationException('regexp_delimiter_required');
        }

        $closing = strrpos($pattern, $delimiter);

        if ($closing === false || $closing === 0) {
            throw new RuleConfigurationException('regexp_delimiter_unclosed');
        }

        $body = substr($pattern, 1, $closing - 1);
        $modifiers = substr($pattern, $closing + 1);

        return $delimiter.'(?<'.$capture.'>(?:'.$body.'))'.$delimiter.$modifiers;
    }

    /** @return list<string> */
    public static function embedUrlCaptures(string $embedUrl): array
    {
        preg_match_all('/\{([a-z][a-z0-9_]*)\}/i', $embedUrl, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1] ?? [])));
    }

    /** @return array<string, string> */
    public static function normalizeCaptureDefaults(mixed $defaults): array
    {
        if (! is_array($defaults)) {
            return [];
        }

        $normalized = [];

        foreach ($defaults as $name => $value) {
            $name = strtolower((string) $name);

            if (preg_match('/^[a-z][a-z0-9_]*$/', $name) && is_scalar($value) && (string) $value !== '') {
                $normalized[$name] = (string) $value;
            }
        }

        return $normalized;
    }

    /** @return array<string, string> */
    public static function iframeAttributes(string $input): array
    {
        $attributes = [];
        $offset = 0;
        $length = strlen($input);
        $pattern = '/\G\s*([a-z][a-z0-9-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'))?\s*/i';

        while ($offset < $length) {
            if (! preg_match($pattern, $input, $matches, PREG_UNMATCHED_AS_NULL, $offset)) {
                throw new RuleConfigurationException('iframe_attribute_format');
            }

            $name = strtolower($matches[1]);
            $hasValue = $matches[2] !== null || $matches[3] !== null;

            if (! in_array($name, self::ALLOWED_IFRAME_ATTRIBUTES, true)) {
                throw new RuleConfigurationException('iframe_attribute_not_allowed', ['attribute' => $name]);
            }

            if (array_key_exists($name, $attributes)) {
                throw new RuleConfigurationException('iframe_attribute_repeated', ['attribute' => $name]);
            }

            if (! in_array($name, self::BOOLEAN_IFRAME_ATTRIBUTES, true) && ! $hasValue) {
                throw new RuleConfigurationException('iframe_attribute_requires_value', ['attribute' => $name]);
            }

            $value = $matches[2] ?? $matches[3] ?? '';

            if (preg_match('/[<>{}\x00-\x1f\x7f]/', $value)) {
                throw new RuleConfigurationException('iframe_attribute_static_value');
            }

            $attributes[$name] = $value;
            $offset += strlen($matches[0]);
        }

        return $attributes;
    }

    public static function formatIframeAttributes(string $input): string
    {
        $formatted = [];

        foreach (self::iframeAttributes(trim($input)) as $name => $value) {
            if (in_array($name, self::BOOLEAN_IFRAME_ATTRIBUTES, true) && $value === '') {
                $formatted[] = $name;
            } elseif (! str_contains($value, "'")) {
                $formatted[] = $name."='".$value."'";
            } elseif (! str_contains($value, '"')) {
                $formatted[] = $name.'="'.$value.'"';
            } else {
                throw new RuleConfigurationException('iframe_attribute_quote_types');
            }
        }

        return implode(' ', $formatted);
    }

    /** @param array<string, mixed> $data */
    public static function siteConfig(array $data): array
    {
        $iframe = self::iframeAttributes((string) ($data['iframe_attributes'] ?? ''));
        $aspectRatio = trim((string) ($data['aspect_ratio'] ?? ''));
        $attributes = [];

        $captureDefaults = array_intersect_key(
            self::normalizeCaptureDefaults($data['capture_defaults'] ?? []),
            array_flip(self::embedUrlCaptures((string) $data['embed_url']))
        );

        foreach ($captureDefaults as $name => $value) {
            $attributes[$name] = ['defaultValue' => $value, 'required' => false];
        }

        if ($aspectRatio === '') {
            $iframe['width'] = '100%';
            $iframe['height'] = (int) $iframe['height'];
        } else {
            [$ratioWidth, $ratioHeight] = array_map('floatval', preg_split('/\s*\/\s*/', $aspectRatio));
            $width = 900;
            $iframe['width'] = $width;
            $iframe['height'] = $width * $ratioHeight / $ratioWidth;
        }

        $iframe['src'] = preg_replace_callback(
            '/\{([a-z][a-z0-9_]*)\}/i',
            fn (array $matches) => '{@'.strtolower($matches[1]).'}',
            (string) $data['embed_url']
        );

        return [
            'attributes' => $attributes,
            'host' => self::hostsFromPatterns((string) $data['extract_pattern']),
            'extract' => self::patterns((string) $data['extract_pattern']),
            'iframe' => $iframe,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function redirectSiteConfig(array $data): array
    {
        $config = self::siteConfig($data);
        $requiredCaptures = array_values(array_diff(
            self::embedUrlCaptures((string) $data['embed_url']),
            array_keys($config['attributes'])
        ));
        $config['host'] = self::hostsFromPatterns((string) ($data['redirect_pattern'] ?? ''));
        $config['extract'] = array_map(
            fn (string $pattern) => self::captureWholePattern($pattern, 'short_url'),
            self::patterns((string) ($data['redirect_pattern'] ?? ''))
        );
        $config['attributes'] = ['short_url' => ['required' => false]];

        foreach ($requiredCaptures as $capture) {
            $config['attributes'][$capture] = ['required' => true];
        }

        return $config;
    }
}
