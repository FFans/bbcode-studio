<?php

namespace FFans\BbcodeStudio\Api;

use FFans\BbcodeStudio\BbcodeRule;
use FFans\BbcodeStudio\BuiltInRuleDefaults;
use FFans\BbcodeStudio\Formatter\MediaRuleDefinition;

class RuleSerializer
{
    /** @return array<string, mixed> */
    public function serialize(BbcodeRule $rule): array
    {
        $builtInDefaults = BuiltInRuleDefaults::for($rule->builtin_key);

        return [
            'type' => 'bbcode-studio-rules',
            'id' => (string) $rule->id,
            'attributes' => [
                'ruleType' => $rule->rule_type,
                'name' => $rule->name,
                'tag' => $rule->tag,
                'description' => $rule->description,
                'usage' => $rule->usage,
                'template' => $rule->template,
                'cssDeclarations' => (string) ($rule->css_declarations ?? ''),
                'icon' => $rule->icon,
                'buttonLabel' => $rule->button_label,
                'example' => $rule->example,
                'exampleAttributes' => $rule->example_attributes ?? [],
                'enabled' => (bool) $rule->enabled,
                'toolbarEnabled' => (bool) $rule->toolbar_enabled,
                'extractPattern' => $rule->extract_pattern,
                'sourceRules' => array_merge(
                    array_map(
                        fn (string $pattern) => ['type' => 'extract', 'pattern' => $pattern],
                        MediaRuleDefinition::patterns((string) $rule->extract_pattern)
                    ),
                    array_map(
                        fn (string $pattern) => ['type' => 'redirect', 'pattern' => $pattern],
                        MediaRuleDefinition::patterns((string) ($rule->redirect_pattern ?? ''))
                    )
                ),
                'captureDefaults' => $builtInDefaults['captureDefaults'] ?? [],
                'embedUrl' => $rule->embed_url,
                'iframeAttributes' => (string) ($rule->iframe_attributes ?? ''),
                'aspectRatio' => $rule->aspect_ratio,
                'sortOrder' => (int) $rule->sort_order,
                'builtIn' => $rule->builtin_key !== null,
                'defaultAttributes' => $builtInDefaults,
                'createdAt' => $rule->created_at?->toAtomString(),
                'updatedAt' => $rule->updated_at?->toAtomString(),
            ],
        ];
    }
}
