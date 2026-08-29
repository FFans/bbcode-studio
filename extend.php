<?php

use Flarum\Api\Resource;
use Flarum\Extend;
use FFans\BbcodeStudio\Api\Controller\CreateRuleController;
use FFans\BbcodeStudio\Api\Controller\DeleteRuleController;
use FFans\BbcodeStudio\Api\Controller\ListRulesController;
use FFans\BbcodeStudio\Api\Controller\TestMediaRuleController;
use FFans\BbcodeStudio\Api\Controller\UpdateRuleController;
use FFans\BbcodeStudio\Api\ForumResourceFields;
use FFans\BbcodeStudio\Formatter\ConfigureFormatter;
use FFans\BbcodeStudio\Formatter\RegisterShortLinkResolver;
use FFans\BbcodeStudio\Frontend\StyleServiceProvider;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js')
        ->css(__DIR__ . '/less/admin.less'),

    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\ServiceProvider())
        ->register(StyleServiceProvider::class),

    (new Extend\Formatter())
        ->configure(ConfigureFormatter::class)
        ->parse(RegisterShortLinkResolver::class),

    (new Extend\ApiResource(Resource\ForumResource::class))
        ->fields(ForumResourceFields::class),

    (new Extend\Routes('api'))
        ->get('/ffans-bbcode-studio/rules', 'ffans-bbcode-studio.rules.index', ListRulesController::class)
        ->post('/ffans-bbcode-studio/rules', 'ffans-bbcode-studio.rules.create', CreateRuleController::class)
        ->post('/ffans-bbcode-studio/rules/test-media', 'ffans-bbcode-studio.rules.test-media', TestMediaRuleController::class)
        ->patch('/ffans-bbcode-studio/rules/{id}', 'ffans-bbcode-studio.rules.update', UpdateRuleController::class)
        ->delete('/ffans-bbcode-studio/rules/{id}', 'ffans-bbcode-studio.rules.delete', DeleteRuleController::class),
];
