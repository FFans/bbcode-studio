<?php

namespace FFans\BbcodeStudio\Api;

use FFans\BbcodeStudio\RuleRepository;
use Flarum\Api\Schema;

class ForumResourceFields
{
    public function __construct(protected RuleRepository $rules)
    {
    }

    public function __invoke(): array
    {
        return [
            Schema\Arr::make('ffansBbcodeStudioToolbarRules')
                ->get(fn () => array_map(fn ($rule) => [
                    'id' => (string) $rule->id,
                    'tag' => $rule->tag,
                    'name' => $rule->name,
                    'icon' => $rule->icon,
                    'buttonLabel' => $rule->button_label ?: $rule->name,
                    'buttonLabelTranslationKey' => $rule->builtin_key !== null
                        ? 'ffans-bbcode-studio.forum.toolbar.built_in.'.$rule->builtin_key
                        : null,
                    'example' => $rule->example,
                    'exampleAttributes' => $rule->example_attributes ?? [],
                    'sortOrder' => (int) $rule->sort_order,
                ], $this->rules->toolbarRules())),
        ];
    }
}
