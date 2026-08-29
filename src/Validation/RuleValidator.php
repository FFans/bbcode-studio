<?php

namespace FFans\BbcodeStudio\Validation;

use DOMXPath;
use FFans\BbcodeStudio\BbcodeRule;
use FFans\BbcodeStudio\BuiltInRuleDefaults;
use FFans\BbcodeStudio\Formatter\BbcodeRuleDefinition;
use FFans\BbcodeStudio\Formatter\BbcodeUsageInspector;
use FFans\BbcodeStudio\Formatter\MediaRuleDefinition;
use FFans\BbcodeStudio\Formatter\RuleConfigurationException;
use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Contracts\Validation\Factory;
use s9e\TextFormatter\Configurator;
use s9e\TextFormatter\Configurator\Helpers\TemplateLoader;
use Throwable;

class RuleValidator
{
    public function __construct(
        protected Factory $validator,
        protected TranslatorInterface $translator,
    ) {
    }

    /** @return array<string, mixed> */
    public function validate(array $attributes, ?BbcodeRule $existing = null): array
    {
        [$extractPattern, $redirectPattern] = $this->sourcePatterns($attributes, $existing);
        $exampleAttributes = $attributes['exampleAttributes'] ?? $existing?->example_attributes ?? [];
        $data = [
            'rule_type' => strtolower(trim((string) ($attributes['ruleType'] ?? $existing?->rule_type ?? 'bbcode'))),
            'name' => trim((string) ($attributes['name'] ?? '')),
            'tag' => strtolower(trim((string) ($attributes['tag'] ?? ''))),
            'usage' => trim((string) ($attributes['usage'] ?? '')),
            'template' => trim((string) ($attributes['template'] ?? '')),
            'css_declarations' => trim((string) ($attributes['cssDeclarations'] ?? $existing?->css_declarations ?? '')),
            'description' => trim((string) ($attributes['description'] ?? '')),
            'icon' => trim((string) ($attributes['icon'] ?? 'fas fa-code')),
            'button_label' => trim((string) ($attributes['buttonLabel'] ?? '')),
            'example' => trim((string) ($attributes['example'] ?? '')),
            'enabled' => (bool) ($attributes['enabled'] ?? true),
            'toolbar_enabled' => (bool) ($attributes['toolbarEnabled'] ?? true),
            'extract_pattern' => $extractPattern,
            'redirect_pattern' => $redirectPattern,
            'embed_url' => trim((string) ($attributes['embedUrl'] ?? '')),
            'iframe_attributes' => trim((string) ($attributes['iframeAttributes'] ?? $existing?->iframe_attributes ?? '')),
            'aspect_ratio' => trim((string) ($attributes['aspectRatio'] ?? $existing?->aspect_ratio ?? '16 / 9')),
            'sort_order' => (int) ($attributes['sortOrder'] ?? 0),
        ];

        $builtInDefaults = BuiltInRuleDefaults::for($existing?->builtin_key);

        if ($builtInDefaults !== null) {
            $data['rule_type'] = $builtInDefaults['ruleType'];
            $data['name'] = $builtInDefaults['name'];
            $data['button_label'] = $builtInDefaults['buttonLabel'];
        }

        $unique = 'unique:ffans_bbcode_studio_rules,tag'.($existing ? ','.$existing->id : '');
        $validation = $this->validator->make($data, [
            'rule_type' => ['required', 'in:bbcode,media'],
            'name' => ['required', 'string', 'max:100'],
            'tag' => ['required', 'regex:/^[a-z][a-z0-9_-]{0,31}$/', $unique],
            'usage' => ['required_if:rule_type,bbcode', 'nullable', 'string', 'max:2000'],
            'template' => ['required_if:rule_type,bbcode', 'nullable', 'string', 'max:10000'],
            'css_declarations' => ['nullable', 'string', 'max:5000'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['required', 'regex:/^(?:fas|far|fab) fa-[a-z0-9-]+$/'],
            'button_label' => ['nullable', 'string', 'max:100'],
            'example' => ['nullable', 'string', 'max:2000'],
            'extract_pattern' => ['required_if:rule_type,media', 'nullable', 'string', 'max:2000'],
            'redirect_pattern' => ['nullable', 'string', 'max:2000'],
            'embed_url' => ['required_if:rule_type,media', 'nullable', 'string', 'max:2000'],
            'iframe_attributes' => ['nullable', 'string', 'max:2000'],
            'aspect_ratio' => ['nullable', 'regex:/^\d+(?:\.\d+)?\s*\/\s*\d+(?:\.\d+)?$/'],
            'sort_order' => ['integer', 'between:-10000,10000'],
        ]);

        if ($validation->fails()) {
            throw new ValidationException($validation->errors()->toArray());
        }

        if ($data['rule_type'] === 'media') {
            $data['example_attributes'] = [];
            $data['css_declarations'] = '';
            if ($data['aspect_ratio'] !== '') {
                $data['aspect_ratio'] = preg_replace('/\s*\/\s*/', ' / ', $data['aspect_ratio']);
            }
            $data['iframe_attributes'] = $this->normalizeIframeAttributes($data['iframe_attributes']);
            $data['usage'] = MediaRuleDefinition::usage($data['tag']);
            $data['template'] = MediaRuleDefinition::TEMPLATE;
            $this->validateMedia($data);
            $this->compileMedia($data);
        } else {
            $data['example_attributes'] = $this->normalizeExampleAttributes($exampleAttributes, $data['usage']);
            $data['extract_pattern'] = '';
            $data['redirect_pattern'] = '';
            $data['embed_url'] = '';
            $data['iframe_attributes'] = '';
            $data['aspect_ratio'] = '16 / 9';
            $data['css_declarations'] = $this->normalizeStyles($data['css_declarations']);

            preg_match('/^\[([a-z][a-z0-9_-]*)(?=[\s=\]])/i', $data['usage'], $openingTag);
            preg_match('/\[\/([a-z][a-z0-9_-]*)\]$/i', $data['usage'], $closingTag);

            if (
                strtolower($openingTag[1] ?? '') !== $data['tag']
                || strtolower($closingTag[1] ?? '') !== $data['tag']
            ) {
                throw new ValidationException(['usage' => $this->message('usage_tag_mismatch')]);
            }

            $this->compileBbcode($data);
        }

        return $data;
    }

    /** @return array<string, string|null> */
    private function normalizeExampleAttributes(mixed $configured, string $usage): array
    {
        if (! is_array($configured) || array_is_list($configured) && $configured !== []) {
            throw new ValidationException(['exampleAttributes' => $this->message('example_attributes_object')]);
        }

        if (count($configured) > 32) {
            throw new ValidationException(['exampleAttributes' => $this->message('example_attributes_limit')]);
        }

        $definitions = [];

        foreach (BbcodeUsageInspector::attributes($usage) as $attribute) {
            $definitions[$attribute['name']] = $attribute;
        }

        $configuredByName = [];

        foreach ($configured as $name => $value) {
            $configuredByName[strtolower((string) $name)] = $value;
        }

        $normalized = [];

        foreach ($definitions as $name => $definition) {
            if (! array_key_exists($name, $configuredByName)) {
                continue;
            }

            $value = $configuredByName[$name];

            if ($value === null || $value === '') {
                $normalized[$name] = null;
                continue;
            }

            if (! is_string($value)) {
                throw new ValidationException(['exampleAttributes' => $this->message('example_attribute_text')]);
            }

            $value = trim($value);

            if (mb_strlen($value) > 500) {
                throw new ValidationException(['exampleAttributes' => $this->message('example_attribute_length')]);
            }

            $choices = $definition['choices'];

            if ($choices !== []) {
                $matches = array_values(array_filter(
                    $choices,
                    fn (string $choice): bool => strcasecmp($choice, $value) === 0
                ));

                if ($matches === []) {
                    throw new ValidationException([
                        'exampleAttributes' => $this->message('example_attribute_choice', ['attribute' => $name]),
                    ]);
                }

                $value = $matches[0];
            }

            $normalized[$name] = $value;
        }

        return $normalized;
    }

    /** @return array{string, string} */
    private function sourcePatterns(array $attributes, ?BbcodeRule $existing): array
    {
        if (! array_key_exists('sourceRules', $attributes)) {
            return [
                trim((string) ($attributes['extractPattern'] ?? $existing?->extract_pattern ?? '')),
                trim((string) ($attributes['redirectPattern'] ?? $existing?->redirect_pattern ?? '')),
            ];
        }

        if (! is_array($attributes['sourceRules'])) {
            throw new ValidationException(['sourceRules' => $this->message('source_rules_array')]);
        }

        $patterns = ['extract' => [], 'redirect' => []];

        foreach ($attributes['sourceRules'] as $index => $sourceRule) {
            if (! is_array($sourceRule)) {
                throw new ValidationException([
                    'sourceRules' => $this->message('source_rule_invalid', ['number' => $index + 1]),
                ]);
            }

            $type = (string) ($sourceRule['type'] ?? '');
            $pattern = trim((string) ($sourceRule['pattern'] ?? ''));

            if (! array_key_exists($type, $patterns)) {
                throw new ValidationException([
                    'sourceRules' => $this->message('source_rule_type', ['number' => $index + 1]),
                ]);
            }

            if ($pattern !== '') {
                $patterns[$type][] = $pattern;
            }
        }

        return [implode("\n", $patterns['extract']), implode("\n", $patterns['redirect'])];
    }

    private function normalizeIframeAttributes(string $attributes): string
    {
        try {
            return MediaRuleDefinition::formatIframeAttributes($attributes);
        } catch (RuleConfigurationException $exception) {
            throw new ValidationException(['iframeAttributes' => $this->configurationMessage($exception)]);
        }
    }

    private function normalizeStyles(string $styles): string
    {
        try {
            return BbcodeRuleDefinition::normalizeStyles($styles);
        } catch (RuleConfigurationException $exception) {
            throw new ValidationException(['cssDeclarations' => $this->configurationMessage($exception)]);
        }
    }

    /** @param array<string, mixed> $data */
    private function validateMedia(array $data): void
    {
        $iframeAttributes = MediaRuleDefinition::iframeAttributes($data['iframe_attributes']);

        if ($data['aspect_ratio'] === '') {
            if (! isset($iframeAttributes['height']) || ! preg_match('/^[1-9]\d{0,3}$/', $iframeAttributes['height'])) {
                throw new ValidationException(['iframeAttributes' => $this->message('fixed_height_required')]);
            }
        } else {
            [$ratioWidth, $ratioHeight] = array_map('floatval', preg_split('/\s*\/\s*/', $data['aspect_ratio']));

            if ($ratioWidth <= 0 || $ratioHeight <= 0) {
                throw new ValidationException(['aspectRatio' => $this->message('aspect_ratio_positive')]);
            }
        }

        preg_match_all('/\{([a-z][a-z0-9_]*)\}/i', $data['embed_url'], $placeholderMatches);
        $placeholders = array_map('strtolower', $placeholderMatches[1] ?? []);

        if ($placeholders === []) {
            throw new ValidationException(['embedUrl' => $this->message('embed_capture_required')]);
        }

        foreach (MediaRuleDefinition::patterns($data['extract_pattern']) as $index => $pattern) {
            if (MediaRuleDefinition::hostsFromPatterns($pattern) === []) {
                throw new ValidationException([
                    'extractPattern' => $this->message('extract_hostname_required', ['number' => $index + 1]),
                ]);
            }

            $captureNames = MediaRuleDefinition::captures($pattern);

            if ($captureNames === []) {
                throw new ValidationException([
                    'extractPattern' => $this->message('extract_capture_required', ['number' => $index + 1]),
                ]);
            }

            if (array_diff($placeholders, $captureNames) !== []) {
                throw new ValidationException(['embedUrl' => $this->message('embed_capture_mismatch')]);
            }

            set_error_handler(static fn () => true);
            $validRegexp = preg_match($pattern, '') !== false;
            restore_error_handler();

            if (! $validRegexp) {
                throw new ValidationException([
                    'extractPattern' => $this->message('extract_regexp_invalid', ['number' => $index + 1]),
                ]);
            }
        }

        foreach (MediaRuleDefinition::patterns($data['redirect_pattern']) as $index => $pattern) {
            if (MediaRuleDefinition::hostsFromPatterns($pattern) === []) {
                throw new ValidationException([
                    'sourceRules' => $this->message('redirect_hostname_required', ['number' => $index + 1]),
                ]);
            }

            set_error_handler(static fn () => true);
            $validRegexp = preg_match($pattern, '') !== false;
            restore_error_handler();

            if (! $validRegexp) {
                throw new ValidationException([
                    'sourceRules' => $this->message('redirect_regexp_invalid', ['number' => $index + 1]),
                ]);
            }

            try {
                MediaRuleDefinition::captureWholePattern($pattern, 'short_url');
            } catch (RuleConfigurationException $exception) {
                throw new ValidationException(['sourceRules' => $this->configurationMessage($exception)]);
            }
        }

        if (! str_starts_with($data['embed_url'], 'https://') && ! str_starts_with($data['embed_url'], '//')) {
            throw new ValidationException(['embedUrl' => $this->message('embed_url_https')]);
        }
    }

    /** @param array<string, mixed> $data */
    private function compileBbcode(array $data): void
    {
        try {
            $configurator = new Configurator();
            $bbcode = $configurator->BBCodes->addCustom(
                $data['usage'],
                BbcodeRuleDefinition::template($data['tag'], $data['template'])
            );
        } catch (RuleConfigurationException $exception) {
            throw new ValidationException(['template' => $this->configurationMessage($exception)]);
        } catch (Throwable) {
            throw new ValidationException(['template' => $this->message('bbcode_definition_invalid')]);
        }

        $tag = $configurator->tags[$bbcode->tagName];
        $definedAttributes = array_keys(iterator_to_array($tag->attributes));
        $undefinedAttributes = array_values(array_diff(
            $this->templateAttributeReferences((string) $tag->template),
            $definedAttributes
        ));

        if ($undefinedAttributes !== []) {
            throw new ValidationException([
                'template' => $this->message('template_attributes_undeclared', [
                    'attributes' => implode(', ', array_map(fn (string $attribute) => '@'.$attribute, $undefinedAttributes)),
                ]),
            ]);
        }
    }

    /** @return list<string> */
    private function templateAttributeReferences(string $template): array
    {
        $dom = TemplateLoader::load($template);
        $xpath = new DOMXPath($dom);
        $attributes = [];

        foreach ($xpath->query('//@*') as $attribute) {
            preg_match_all('/(?<![a-z0-9_-])@([a-z][a-z0-9_-]*)/i', $attribute->nodeValue, $matches);
            $attributes = array_merge($attributes, $matches[1] ?? []);
        }

        return array_values(array_unique(array_map('strtolower', $attributes)));
    }

    /** @param array<string, mixed> $data */
    private function compileMedia(array $data): void
    {
        try {
            $configurator = new Configurator();
            $definition = MediaRuleDefinition::configure($configurator, $data['tag'], 'ffansbbcodetest', $data);
            MediaRuleDefinition::isolateTaggedMedia($configurator, [$definition['bbcodeTag']], $definition['siteIds']);
        } catch (RuleConfigurationException $exception) {
            throw new ValidationException(['media' => $this->configurationMessage($exception)]);
        } catch (Throwable) {
            throw new ValidationException(['media' => $this->message('media_definition_invalid')]);
        }
    }

    /** @param array<string, mixed> $parameters */
    private function message(string $key, array $parameters = []): string
    {
        return $this->translator->trans('ffans-bbcode-studio.api.validation.'.$key, $parameters);
    }

    private function configurationMessage(RuleConfigurationException $exception): string
    {
        return $this->message($exception->translationKey, $exception->parameters);
    }
}
