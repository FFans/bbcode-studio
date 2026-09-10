<?php

namespace FFans\BbcodeStudio\Formatter;

use FFans\BbcodeStudio\BuiltInRuleDefaults;
use FFans\BbcodeStudio\RuleRepository;
use InvalidArgumentException;
use s9e\TextFormatter\Configurator;

class ConfigureFormatter
{
    public function __construct(protected RuleRepository $rules)
    {
    }

    public function __invoke(Configurator $configurator): void
    {
        $mediaBbcodeTags = [];
        $mediaSiteIds = [];

        foreach ($this->rules->enabled() as $rule) {
            if ($rule->rule_type === 'media') {
                $siteId = 'ffansbbcode'.$rule->id;
                $builtInDefaults = BuiltInRuleDefaults::for($rule->builtin_key);
                $definition = MediaRuleDefinition::configure($configurator, $rule->tag, $siteId, [
                    'extract_pattern' => $rule->extract_pattern,
                    'redirect_pattern' => $rule->redirect_pattern,
                    'embed_url' => $rule->embed_url,
                    'iframe_attributes' => $rule->iframe_attributes,
                    'aspect_ratio' => $rule->aspect_ratio,
                    'capture_defaults' => $builtInDefaults['captureDefaults'] ?? [],
                ]);
                $mediaBbcodeTags[] = $definition['bbcodeTag'];
                $mediaSiteIds = array_merge($mediaSiteIds, $definition['siteIds']);

                continue;
            }

            try {
                $template = BbcodeRuleDefinition::template($rule->tag, $rule->template);
            } catch (InvalidArgumentException) {
                // Preserve formatter startup for legacy rules that predate automatic DOM classes.
                $template = $rule->template;
            }

            $configurator->BBCodes->addCustom($rule->usage, $template);
        }

        MediaRuleDefinition::isolateTaggedMedia($configurator, $mediaBbcodeTags, $mediaSiteIds);
    }
}
