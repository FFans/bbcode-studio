<?php

namespace FFans\BbcodeStudio\Tests\integration;

use FFans\BbcodeStudio\BbcodeRule;
use FFans\BbcodeStudio\Formatter\BbcodeRuleDefinition;
use FFans\BbcodeStudio\Formatter\ShortLinkResolver;
use Flarum\Formatter\Formatter;
use Flarum\Locale\LocaleManager;
use Flarum\Locale\Translator;
use Flarum\Locale\TranslatorInterface;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class RuleApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('ffans-bbcode-studio');
        $this->prepareDatabase([
            User::class => [['id' => 2]],
        ]);

        // The integration bootstrap primes the core catalogue before enabling the current extension.
        // Register this extension's resources explicitly and vary the cache for these test processes.
        $locales = $this->app()->getContainer()->make(LocaleManager::class);
        $locales->addTranslations('en', dirname(__DIR__, 2).'/locale/en.yml');
        $locales->addTranslations('zh-Hans', dirname(__DIR__, 2).'/locale/zh-Hans.yml');

        $translator = $this->app()->getContainer()->make(Translator::class);
        $translator->setFallbackLocales(['en', 'en-FFANS']);
    }

    #[Test]
    public function only_administrators_can_list_rules(): void
    {
        $forbidden = $this->send($this->request('GET', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 2,
        ]));

        $this->assertSame(403, $forbidden->getStatusCode());

        $response = $this->send($this->request('GET', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
        ]));
        $document = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode(), json_encode($document));
        $this->assertCount(8, $document['data']);
        $expectedOrder = ['notice', 'kdb', 'spoiler', 'bilibili', 'youtube', 'vimeo', 'spotify', 'netease'];
        $this->assertSame(
            $expectedOrder,
            array_column(array_column($document['data'], 'attributes'), 'tag')
        );
        $this->assertSame(
            array_fill(0, count($expectedOrder), true),
            array_column(array_column($document['data'], 'attributes'), 'builtIn')
        );
        $this->assertSame(
            array_fill(0, count($expectedOrder), false),
            array_column(array_column($document['data'], 'attributes'), 'enabled')
        );
        foreach ($document['data'] as $rule) {
            $this->assertSame(
                $rule['attributes']['tag'],
                $rule['attributes']['defaultAttributes']['tag']
            );
            $this->assertFalse($rule['attributes']['defaultAttributes']['enabled']);
        }
        $this->assertEqualsCanonicalizing(
            ['bbcode', 'media'],
            array_values(array_unique(array_column(array_column($document['data'], 'attributes'), 'ruleType')))
        );
        $bilibili = current(array_filter($document['data'], fn (array $rule) => $rule['attributes']['tag'] === 'bilibili'));
        $this->assertSame(
            ['extract', 'redirect'],
            array_column($bilibili['attributes']['sourceRules'], 'type')
        );
        $this->assertSame('!b23\.tv/[a-zA-Z0-9]+!', BbcodeRule::query()->where('tag', 'bilibili')->value('redirect_pattern'));
        $youtube = current(array_filter($document['data'], fn (array $rule) => $rule['attributes']['tag'] === 'youtube'));
        $this->assertSame('https://youtu.be/A8LRxIANzQs', $youtube['attributes']['example']);
        $this->assertSame('https://youtu.be/A8LRxIANzQs', $youtube['attributes']['defaultAttributes']['example']);
        $notice = current(array_filter($document['data'], fn (array $rule) => $rule['attributes']['tag'] === 'notice'));
        $spoiler = current(array_filter($document['data'], fn (array $rule) => $rule['attributes']['tag'] === 'spoiler'));
        $this->assertSame(['color' => null], $notice['attributes']['exampleAttributes']);
        $this->assertSame(['title' => null], $spoiler['attributes']['exampleAttributes']);

        $forumResponse = $this->send($this->request('GET', '/api'));
        $forumDocument = json_decode((string) $forumResponse->getBody(), true);
        $this->assertSame([], $forumDocument['data']['attributes']['ffansBbcodeStudioToolbarRules']);
    }

    #[Test]
    public function only_administrators_can_test_unsaved_media_rules(): void
    {
        $payload = [
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-media-tests',
                    'attributes' => [
                        'url' => 'https://video.example.com/watch/abc123',
                        'sourceRules' => [
                            ['type' => 'extract', 'pattern' => '!video\.example\.com/watch/(?<id>[a-z0-9]+)!'],
                        ],
                        'embedUrl' => 'https://player.example.com/embed/{id}',
                    ],
                ],
            ],
        ];
        $forbidden = $this->send($this->request('POST', '/api/ffans-bbcode-studio/rules/test-media', [
            ...$payload,
            'authenticatedAs' => 2,
        ]));

        $this->assertSame(403, $forbidden->getStatusCode());

        $response = $this->send($this->request('POST', '/api/ffans-bbcode-studio/rules/test-media', [
            ...$payload,
            'authenticatedAs' => 1,
        ]));
        $document = json_decode((string) $response->getBody(), true);

        $this->assertSame(200, $response->getStatusCode(), json_encode($document));
        $this->assertSame([
            'matched' => true,
            'matchType' => 'extract',
            'ruleNumber' => 1,
            'captures' => ['id' => 'abc123'],
            'embedUrl' => 'https://player.example.com/embed/abc123',
        ], $document['data']);
    }

    #[Test]
    public function admin_assets_compile_with_the_attribute_configuration_controls(): void
    {
        $assets = $this->app()->getContainer()->make('flarum.assets.admin');
        $compiler = $assets->makeCss();
        $compiler->commit(true);

        $stylesheet = $assets->getAssetsDir()->get('admin.css');

        $this->assertStringContainsString('.BbcodeStudioExampleAttribute', $stylesheet);
    }

    #[Test]
    public function seeded_builtin_values_match_their_reset_defaults(): void
    {
        $response = $this->send($this->request('GET', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
        ]));
        $rules = json_decode((string) $response->getBody(), true)['data'];
        $fields = [
            'name',
            'usage',
            'template',
            'cssDeclarations',
            'enabled',
            'exampleAttributes',
            'extractPattern',
            'sourceRules',
            'embedUrl',
            'iframeAttributes',
            'aspectRatio',
        ];

        foreach ($rules as $rule) {
            $attributes = $rule['attributes'];

            foreach ($fields as $field) {
                $this->assertSame(
                    $attributes['defaultAttributes'][$field],
                    $attributes[$field],
                    $attributes['tag'].'.'.$field
                );
            }
        }
    }

    #[Test]
    public function administrator_can_create_a_checked_rule_and_forum_receives_toolbar_metadata(): void
    {
        $response = $this->send($this->request('POST', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-rules',
                    'attributes' => [
                        'ruleType' => 'bbcode',
                        'name' => 'Custom notice',
                        'tag' => 'custom-notice',
                        'description' => 'Custom notice box',
                        'usage' => '[custom-notice tone={CHOICE=info,warning;optional} title={SIMPLETEXT?}]{TEXT}[/custom-notice]',
                        'template' => '<aside><xsl:apply-templates/></aside>',
                        'cssDeclarations' => 'color: red; .title { font-weight: bold; }',
                        'icon' => 'fas fa-circle-info',
                        'buttonLabel' => 'Notice',
                        'example' => 'Important',
                        'exampleAttributes' => [
                            'title' => null,
                            'tone' => 'warning',
                        ],
                        'enabled' => true,
                        'toolbarEnabled' => true,
                        'aspectRatio' => '16 / 9',
                        'sortOrder' => 10,
                    ],
                ],
            ],
        ]));
        $document = json_decode((string) $response->getBody(), true);

        $this->assertSame(201, $response->getStatusCode(), json_encode($document));
        $this->assertSame('custom-notice', $document['data']['attributes']['tag']);
        $this->assertFalse($document['data']['attributes']['builtIn']);
        $this->assertNull($document['data']['attributes']['defaultAttributes']);
        $this->assertSame('color: red; .title { font-weight: bold; }', $document['data']['attributes']['cssDeclarations']);
        $this->assertSame(
            ['tone' => 'warning', 'title' => null],
            $document['data']['attributes']['exampleAttributes']
        );
        $this->assertSame(
            ['tone' => 'warning', 'title' => null],
            BbcodeRule::query()->where('tag', 'custom-notice')->firstOrFail()->example_attributes
        );

        $forumResponse = $this->send($this->request('GET', '/api'));
        $forumDocument = json_decode((string) $forumResponse->getBody(), true);
        $toolbarRules = $forumDocument['data']['attributes']['ffansBbcodeStudioToolbarRules'];

        $this->assertContains('custom-notice', array_column($toolbarRules, 'tag'));
        $toolbarRule = current(array_filter($toolbarRules, fn (array $rule) => $rule['tag'] === 'custom-notice'));
        $this->assertSame(['tone' => 'warning', 'title' => null], $toolbarRule['exampleAttributes']);

        $formatter = $this->app()->getContainer()->make(Formatter::class);
        $html = $formatter->convert('[custom-notice]Important[/custom-notice]');

        $this->assertStringContainsString('BbcodeStudio-bbcode-custom-notice', $html);
        $this->assertStringContainsString('BbcodeStudio-bbcode', $html);
        $this->assertStringContainsString('BbcodeStudio-bbcode-custom-notice', $html);
        $stylesheet = BbcodeRuleDefinition::stylesheet(BbcodeRule::query()->get());
        $this->assertStringContainsString('.BbcodeStudio-bbcode-custom-notice', $stylesheet);
        $this->assertStringContainsString('.title { font-weight: bold; }', $stylesheet);
    }

    #[Test]
    public function built_in_toolbar_labels_always_use_translations(): void
    {
        $notice = BbcodeRule::query()->where('builtin_key', 'notice')->firstOrFail();
        $notice->enabled = true;
        $notice->save();

        $forumResponse = $this->send($this->request('GET', '/api'));
        $forumDocument = json_decode((string) $forumResponse->getBody(), true);
        $noticeToolbarRule = current(array_filter(
            $forumDocument['data']['attributes']['ffansBbcodeStudioToolbarRules'],
            fn (array $rule) => $rule['tag'] === 'notice'
        ));

        $this->assertSame('ffans-bbcode-studio.forum.toolbar.built_in.notice', $noticeToolbarRule['buttonLabelTranslationKey']);

        $notice->button_label = 'Custom notice tooltip';
        $notice->save();

        $forumResponse = $this->send($this->request('GET', '/api'));
        $forumDocument = json_decode((string) $forumResponse->getBody(), true);
        $noticeToolbarRule = current(array_filter(
            $forumDocument['data']['attributes']['ffansBbcodeStudioToolbarRules'],
            fn (array $rule) => $rule['tag'] === 'notice'
        ));

        $this->assertSame('Custom notice tooltip', $noticeToolbarRule['buttonLabel']);
        $this->assertSame('ffans-bbcode-studio.forum.toolbar.built_in.notice', $noticeToolbarRule['buttonLabelTranslationKey']);
    }

    #[Test]
    public function inserted_choice_values_must_be_declared_by_the_usage(): void
    {
        $response = $this->send($this->request('POST', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-rules',
                    'attributes' => [
                        'ruleType' => 'bbcode',
                        'name' => 'Choice value',
                        'tag' => 'choice-value',
                        'usage' => '[choice-value mode={CHOICE=one,two;optional}]{TEXT}[/choice-value]',
                        'template' => '<span><xsl:apply-templates/></span>',
                        'icon' => 'fas fa-code',
                        'exampleAttributes' => ['mode' => 'three'],
                        'enabled' => true,
                        'toolbarEnabled' => true,
                        'aspectRatio' => '16 / 9',
                    ],
                ],
            ],
        ]));

        $document = json_decode((string) $response->getBody(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('declared choices', $document['errors'][0]['detail']);
        $this->assertNull(BbcodeRule::query()->where('tag', 'choice-value')->first());
    }

    #[Test]
    public function usage_tag_must_exactly_match_the_configured_tag(): void
    {
        $response = $this->send($this->request('POST', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-rules',
                    'attributes' => [
                        'ruleType' => 'bbcode',
                        'name' => 'Exact tag',
                        'tag' => 'exact-tag',
                        'usage' => '[exact-tag-extra]{TEXT}[/exact-tag-extra]',
                        'template' => '<span><xsl:apply-templates/></span>',
                        'icon' => 'fas fa-code',
                        'enabled' => true,
                        'toolbarEnabled' => false,
                        'aspectRatio' => '16 / 9',
                    ],
                ],
            ],
        ]));
        $document = json_decode((string) $response->getBody(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            'The BBCode usage opening and closing tags must exactly match the configured tag.',
            $document['errors'][0]['detail']
        );
        $this->assertNull(BbcodeRule::query()->where('tag', 'exact-tag')->first());
    }

    #[Test]
    public function usage_closing_tag_must_exactly_match_the_configured_tag(): void
    {
        $response = $this->send($this->request('POST', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-rules',
                    'attributes' => [
                        'ruleType' => 'bbcode',
                        'name' => 'Exact closing tag',
                        'tag' => 'exact-closing-tag',
                        'usage' => '[exact-closing-tag]{TEXT}[/exact-closing-tag-extra]',
                        'template' => '<span><xsl:apply-templates/></span>',
                        'icon' => 'fas fa-code',
                        'enabled' => true,
                        'toolbarEnabled' => false,
                        'aspectRatio' => '16 / 9',
                    ],
                ],
            ],
        ]));
        $document = json_decode((string) $response->getBody(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(
            'The BBCode usage opening and closing tags must exactly match the configured tag.',
            $document['errors'][0]['detail']
        );
        $this->assertNull(BbcodeRule::query()->where('tag', 'exact-closing-tag')->first());
    }

    #[Test]
    public function validation_errors_follow_the_current_locale(): void
    {
        $this->app()->getContainer()->make(TranslatorInterface::class)->setLocale('zh-Hans');

        $response = $this->send($this->request('POST', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-rules',
                    'attributes' => [
                        'ruleType' => 'bbcode',
                        'name' => 'Localized error',
                        'tag' => 'localized-error',
                        'usage' => '[localized-error]{TEXT}[/localized-error]',
                        'template' => '<span><xsl:apply-templates/></span>',
                        'cssDeclarations' => '@import "theme.less";',
                        'icon' => 'fas fa-code',
                        'enabled' => true,
                        'toolbarEnabled' => false,
                        'aspectRatio' => '16 / 9',
                    ],
                ],
            ],
        ]));
        $document = json_decode((string) $response->getBody(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('自定义 BBCode 样式不支持 Less 导入和插件。', $document['errors'][0]['detail']);
        $this->assertNull(BbcodeRule::query()->where('tag', 'localized-error')->first());
    }

    #[Test]
    public function administrator_can_update_and_reset_built_in_rules_but_cannot_delete_them(): void
    {
        $listResponse = $this->send($this->request('GET', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
        ]));
        $rules = array_values(array_filter(
            json_decode((string) $listResponse->getBody(), true)['data'],
            fn (array $rule) => $rule['attributes']['builtIn']
        ));

        $this->assertCount(8, $rules);
        $bilibili = current(array_filter($rules, fn (array $rule) => $rule['id'] === '1'));
        $bilibili['attributes']['name'] = 'Bilibili Video';
        $bilibili['attributes']['ruleType'] = 'bbcode';
        $bilibili['attributes']['description'] = 'Updated Bilibili rule';
        $bilibili['attributes']['buttonLabel'] = 'Custom Bilibili tooltip';

        $updateResponse = $this->send($this->request('PATCH', '/api/ffans-bbcode-studio/rules/1', [
            'authenticatedAs' => 1,
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-rules',
                    'id' => '1',
                    'attributes' => $bilibili['attributes'],
                ],
            ],
        ]));
        $updateDocument = json_decode((string) $updateResponse->getBody(), true);

        $this->assertSame(200, $updateResponse->getStatusCode(), json_encode($updateDocument));
        $this->assertSame('Bilibili', $updateDocument['data']['attributes']['name']);
        $this->assertSame('media', $updateDocument['data']['attributes']['ruleType']);
        $this->assertSame('Bilibili', BbcodeRule::query()->findOrFail(1)->name);
        $this->assertSame('media', BbcodeRule::query()->findOrFail(1)->rule_type);
        $this->assertSame('Updated Bilibili rule', BbcodeRule::query()->findOrFail(1)->description);
        $this->assertSame('Bilibili', $updateDocument['data']['attributes']['buttonLabel']);
        $this->assertSame('Bilibili', BbcodeRule::query()->findOrFail(1)->button_label);
        $this->assertTrue($updateDocument['data']['attributes']['builtIn']);

        $deleteResponse = $this->send($this->request('DELETE', '/api/ffans-bbcode-studio/rules/1', [
            'authenticatedAs' => 1,
        ]));

        $this->assertSame(403, $deleteResponse->getStatusCode());
        $this->assertNotNull(BbcodeRule::query()->find(1));

        $resetResponse = $this->send($this->request('PATCH', '/api/ffans-bbcode-studio/rules/1', [
            'authenticatedAs' => 1,
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-rules',
                    'id' => '1',
                    'attributes' => $updateDocument['data']['attributes']['defaultAttributes'],
                ],
            ],
        ]));
        $resetDocument = json_decode((string) $resetResponse->getBody(), true);

        $this->assertSame(200, $resetResponse->getStatusCode(), json_encode($resetDocument));
        $this->assertSame('Bilibili', $resetDocument['data']['attributes']['name']);
        $this->assertSame('Embed a Bilibili video from its BV link.', BbcodeRule::query()->findOrFail(1)->description);
    }

    #[Test]
    public function administrator_can_delete_a_custom_rule(): void
    {
        $rule = new BbcodeRule();
        $rule->forceFill([
            'rule_type' => 'bbcode',
            'name' => 'Deletable',
            'tag' => 'deletable',
            'description' => '',
            'usage' => '[deletable]{TEXT}[/deletable]',
            'template' => '<span><xsl:apply-templates/></span>',
            'css_declarations' => '',
            'icon' => 'fas fa-code',
            'button_label' => '',
            'example' => '',
            'enabled' => true,
            'toolbar_enabled' => false,
            'extract_pattern' => '',
            'redirect_pattern' => '',
            'embed_url' => '',
            'iframe_attributes' => '',
            'aspect_ratio' => '16 / 9',
            'sort_order' => 0,
        ]);
        $rule->save();

        $deleteResponse = $this->send($this->request('DELETE', '/api/ffans-bbcode-studio/rules/'.$rule->id, [
            'authenticatedAs' => 1,
        ]));

        $this->assertSame(204, $deleteResponse->getStatusCode());
        $this->assertNull(BbcodeRule::query()->find($rule->id));
    }

    #[Test]
    public function every_built_in_rule_default_can_be_saved(): void
    {
        $listResponse = $this->send($this->request('GET', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
        ]));
        $rules = array_values(array_filter(
            json_decode((string) $listResponse->getBody(), true)['data'],
            fn (array $rule) => $rule['attributes']['builtIn']
        ));

        $this->assertCount(8, $rules);

        foreach ($rules as $rule) {
            $response = $this->send($this->request('PATCH', '/api/ffans-bbcode-studio/rules/'.$rule['id'], [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'bbcode-studio-rules',
                        'id' => $rule['id'],
                        'attributes' => $rule['attributes']['defaultAttributes'],
                    ],
                ],
            ]));
            $document = json_decode((string) $response->getBody(), true);

            $this->assertSame(200, $response->getStatusCode(), $rule['attributes']['tag'].': '.json_encode($document));
            $this->assertSame($rule['attributes']['tag'], $document['data']['attributes']['tag']);
            $this->assertTrue($document['data']['attributes']['builtIn']);
        }
    }

    #[Test]
    public function unsafe_template_is_rejected(): void
    {
        $response = $this->send($this->request('POST', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-rules',
                    'attributes' => [
                        'ruleType' => 'bbcode',
                        'name' => 'Unsafe',
                        'tag' => 'unsafe',
                        'usage' => '[unsafe]{TEXT}[/unsafe]',
                        'template' => '<script><xsl:apply-templates/></script>',
                        'icon' => 'fas fa-code',
                        'enabled' => true,
                        'toolbarEnabled' => false,
                        'aspectRatio' => '16 / 9',
                    ],
                ],
            ],
        ]));

        $this->assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function template_attributes_must_be_declared_by_the_bbcode_usage(): void
    {
        $response = $this->send($this->request('POST', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-rules',
                    'attributes' => [
                        'ruleType' => 'bbcode',
                        'name' => 'Missing variable',
                        'tag' => 'missing-variable',
                        'usage' => '[missing-variable]{TEXT}[/missing-variable]',
                        'template' => '<span><xsl:value-of select="@missing"/></span>',
                        'icon' => 'fas fa-code',
                        'enabled' => true,
                        'toolbarEnabled' => false,
                        'aspectRatio' => '16 / 9',
                    ],
                ],
            ],
        ]));

        $document = json_decode((string) $response->getBody(), true);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('@missing', $document['errors'][0]['detail']);
        $this->assertNull(BbcodeRule::query()->where('tag', 'missing-variable')->first());
    }

    #[Test]
    public function custom_bbcode_styles_reject_less_syntax(): void
    {
        $response = $this->send($this->request('POST', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-rules',
                    'attributes' => [
                        'ruleType' => 'bbcode',
                        'name' => 'Less styles',
                        'tag' => 'less-styles',
                        'usage' => '[less-styles]{TEXT}[/less-styles]',
                        'template' => '<span><xsl:apply-templates/></span>',
                        'cssDeclarations' => '@import "https://example.com/styles.less";',
                        'icon' => 'fas fa-code',
                        'enabled' => true,
                        'toolbarEnabled' => false,
                        'aspectRatio' => '16 / 9',
                    ],
                ],
            ],
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertNull(BbcodeRule::query()->where('tag', 'less-styles')->first());
    }

    #[Test]
    public function built_in_bilibili_bbcode_and_plain_link_render_as_iframes(): void
    {
        $this->enableBuiltInRule('bilibili');
        $this->send($this->request('GET', '/api'));
        $formatter = $this->app()->getContainer()->make(Formatter::class);

        $bbcodeHtml = $formatter->convert('[bilibili]https://www.bilibili.com/video/BV1xx411c7mD[/bilibili]');
        $plainLinkHtml = $formatter->convert('https://www.bilibili.com/video/BV1xx411c7mD');
        $partHtml = $formatter->convert('https://www.bilibili.com/video/BV1Js411o76u?p=2');
        $trailingSlashPartHtml = $formatter->convert('https://www.bilibili.com/video/BV1ujBYBNEPg/?spm_id_from=333.788.videopod.episodes&p=6');

        $this->assertStringContainsString('//player.bilibili.com/player.html?bvid=BV1xx411c7mD&amp;p=1&amp;autoplay=false', $bbcodeHtml);
        $this->assertStringContainsString('//player.bilibili.com/player.html?bvid=BV1xx411c7mD&amp;p=1&amp;autoplay=false', $plainLinkHtml);
        $this->assertStringContainsString('//player.bilibili.com/player.html?bvid=BV1Js411o76u&amp;p=2&amp;autoplay=false', $partHtml);
        $this->assertStringContainsString('//player.bilibili.com/player.html?bvid=BV1ujBYBNEPg&amp;p=6&amp;autoplay=false', $trailingSlashPartHtml);
        $this->assertSame('//player.bilibili.com/player.html?bvid={id}&p={p}&autoplay=false', BbcodeRule::query()->findOrFail(1)->embed_url);
        $this->assertSame("scrolling='no' allowfullscreen", BbcodeRule::query()->findOrFail(1)->iframe_attributes);
        $this->assertStringContainsString('scrolling="no"', $bbcodeHtml);
        $this->assertStringContainsString('allowfullscreen', $bbcodeHtml);
        $this->assertStringContainsString('BbcodeStudio-media', $bbcodeHtml);
        $this->assertStringContainsString('BbcodeStudio-media-bilibili', $plainLinkHtml);
        $this->assertSame($plainLinkHtml, $bbcodeHtml);
        $this->assertStringContainsString('ffansbbcode', $formatter->getJs());
    }

    #[Test]
    public function bilibili_part_parameter_migration_updates_only_the_previous_defaults(): void
    {
        $rule = BbcodeRule::query()->where('builtin_key', 'bilibili')->firstOrFail();
        $oldPattern = '!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)!';
        $oldEmbedUrl = '//player.bilibili.com/player.html?bvid={id}&p=1&autoplay=false';
        $newPattern = '!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)(?:\?(?:[^#\s&]*&)*p=(?<p>[1-9]\d*)(?=[&#\s]|$))?!';
        $newEmbedUrl = '//player.bilibili.com/player.html?bvid={id}&p={p}&autoplay=false';
        $migration = require dirname(__DIR__, 2).'/migrations/2026_09_10_000000_support_bilibili_part_parameter.php';

        $rule->forceFill(['extract_pattern' => $oldPattern, 'embed_url' => $oldEmbedUrl])->save();
        $migration['up']($rule->getConnection()->getSchemaBuilder());
        $rule->refresh();

        $this->assertSame($newPattern, $rule->extract_pattern);
        $this->assertSame($newEmbedUrl, $rule->embed_url);

        $rule->forceFill(['extract_pattern' => '!custom-bilibili-pattern!', 'embed_url' => $oldEmbedUrl])->save();
        $migration['up']($rule->getConnection()->getSchemaBuilder());
        $rule->refresh();

        $this->assertSame('!custom-bilibili-pattern!', $rule->extract_pattern);
        $this->assertSame($oldEmbedUrl, $rule->embed_url);
    }

    #[Test]
    public function bilibili_trailing_slash_migration_updates_only_the_beta_two_defaults(): void
    {
        $rule = BbcodeRule::query()->where('builtin_key', 'bilibili')->firstOrFail();
        $betaTwoPattern = '!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)(?:\?(?:[^#\s&]*&)*p=(?<p>[1-9]\d*)(?=[&#\s]|$))?!';
        $correctedPattern = '!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)/?(?:\?(?:[^#\s&]*&)*p=(?<p>[1-9]\d*)(?=[&#\s]|$))?!';
        $embedUrl = '//player.bilibili.com/player.html?bvid={id}&p={p}&autoplay=false';
        $migration = require dirname(__DIR__, 2).'/migrations/2026_09_11_000000_support_bilibili_trailing_slash.php';

        $rule->forceFill(['extract_pattern' => $betaTwoPattern, 'embed_url' => $embedUrl])->save();
        $migration['up']($rule->getConnection()->getSchemaBuilder());
        $rule->refresh();

        $this->assertSame($correctedPattern, $rule->extract_pattern);
        $this->assertSame($embedUrl, $rule->embed_url);

        $rule->forceFill(['extract_pattern' => '!custom-bilibili-pattern!'])->save();
        $migration['up']($rule->getConnection()->getSchemaBuilder());
        $rule->refresh();

        $this->assertSame('!custom-bilibili-pattern!', $rule->extract_pattern);
        $this->assertSame($embedUrl, $rule->embed_url);
    }

    #[Test]
    public function built_in_bilibili_short_links_are_resolved_while_formatting(): void
    {
        $this->enableBuiltInRule('bilibili');
        $resolver = new class extends ShortLinkResolver {
            public function __construct()
            {
            }

            public function resolve(string $url, array $sourceHosts, array $targetHosts, array $directPatterns, array $requiredCaptures): ?array
            {
                return str_ends_with($url, 'b23.tv/PYEGMvk') ? ['id' => 'BV1xx411c7mD'] : null;
            }
        };
        $this->app()->getContainer()->instance(ShortLinkResolver::class, $resolver);
        $formatter = $this->app()->getContainer()->make(Formatter::class);

        $plainHtml = $formatter->render($formatter->parse('https://b23.tv/PYEGMvk'));
        $taggedHtml = $formatter->render($formatter->parse('[bilibili]https://b23.tv/PYEGMvk[/bilibili]'));

        $this->assertStringContainsString('bvid=BV1xx411c7mD', $plainHtml);
        $this->assertStringContainsString('bvid=BV1xx411c7mD', $taggedHtml);
    }

    #[Test]
    public function built_in_notice_supports_safe_color_variants(): void
    {
        $this->enableBuiltInRule('notice');
        $formatter = $this->app()->getContainer()->make(Formatter::class);

        $red = $formatter->convert('[notice color=red]Important[/notice]');
        $blue = $formatter->convert('[notice color=blue]Information[/notice]');
        $default = $formatter->convert('[notice]Default information[/notice]');

        $this->assertStringContainsString('data-notice-color="red"', $red);
        $this->assertStringContainsString('data-notice-color="blue"', $blue);
        $this->assertStringContainsString('data-notice-color="blue"', $default);
        $this->assertStringContainsString('BbcodeStudio-bbcode-notice', $red);
        $this->assertStringNotContainsString('<script', $red);
    }

    #[Test]
    public function built_in_youtube_lines_support_different_host_shapes(): void
    {
        $this->enableBuiltInRule('youtube');
        $formatter = $this->app()->getContainer()->make(Formatter::class);

        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ',
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ] as $url) {
            $html = $formatter->convert($url);

            $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $html, $url);
        }
    }

    #[Test]
    public function built_in_spotify_rule_uses_compact_fixed_height(): void
    {
        $this->enableBuiltInRule('spotify');
        $formatter = $this->app()->getContainer()->make(Formatter::class);
        $url = 'https://open.spotify.com/track/11dFghVXANMlKmJXsNCbNl';

        $html = $formatter->convert($url);

        $this->assertStringContainsString('open.spotify.com/embed/track/11dFghVXANMlKmJXsNCbNl', $html);
        $this->assertStringContainsString('BbcodeStudio-media--fixed-height', $html);
        $this->assertStringContainsString('height:152px', $html);
        $this->assertSame('', BbcodeRule::query()->where('tag', 'spotify')->value('aspect_ratio'));
        $this->assertStringContainsString(
            "height='152'",
            (string) BbcodeRule::query()->where('tag', 'spotify')->value('iframe_attributes')
        );
    }

    #[Test]
    public function built_in_netease_cloud_music_rule_supports_share_links(): void
    {
        $this->enableBuiltInRule('netease');
        $formatter = $this->app()->getContainer()->make(Formatter::class);
        $url = 'https://music.163.com/#/song?fx-wechatnew=t1&fx-wxqd=&id=2073467158&playerUIModeId=1225003';

        $plainHtml = $formatter->convert($url);
        $taggedHtml = $formatter->convert('[netease]'.$url.'[/netease]');

        $this->assertSame($plainHtml, $taggedHtml);
        $this->assertStringContainsString(
            '//music.163.com/outchain/player?type=2&amp;id=2073467158&amp;auto=1&amp;height=66',
            $plainHtml
        );
        $this->assertStringContainsString('BbcodeStudio-media-netease', $plainHtml);
        $this->assertStringContainsString('BbcodeStudio-media--fixed-height', $plainHtml);
        $this->assertStringContainsString('height:86px', $plainHtml);
        $this->assertSame('', BbcodeRule::query()->where('tag', 'netease')->value('aspect_ratio'));
        $this->assertSame("scrolling='no' height='86'", BbcodeRule::query()->where('tag', 'netease')->value('iframe_attributes'));
    }

    #[Test]
    public function administrator_can_create_fixed_height_media_rule_without_an_aspect_ratio(): void
    {
        $response = $this->send($this->request('POST', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-rules',
                    'attributes' => [
                        'ruleType' => 'media',
                        'name' => 'Example audio',
                        'tag' => 'exampleaudio',
                        'icon' => 'fas fa-music',
                        'sourceRules' => [
                            ['type' => 'extract', 'pattern' => '!audio\.example\.com/song/(?<id>[a-z0-9]+)!'],
                        ],
                        'embedUrl' => 'https://player.example.com/embed/{id}',
                        'iframeAttributes' => "scrolling='no' height='120'",
                        'aspectRatio' => '',
                    ],
                ],
            ],
        ]));
        $document = json_decode((string) $response->getBody(), true);

        $this->assertSame(201, $response->getStatusCode(), json_encode($document));
        $this->assertSame('', $document['data']['attributes']['aspectRatio']);
        $this->assertSame("scrolling='no' height='120'", $document['data']['attributes']['iframeAttributes']);

        $formatter = $this->app()->getContainer()->make(Formatter::class);
        $html = $formatter->convert('https://audio.example.com/song/abc123');

        $this->assertStringContainsString('BbcodeStudio-media--fixed-height', $html);
        $this->assertStringContainsString('height:120px', $html);
    }

    #[Test]
    public function fixed_height_media_rule_requires_an_iframe_height(): void
    {
        $response = $this->send($this->request('POST', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-rules',
                    'attributes' => [
                        'ruleType' => 'media',
                        'name' => 'Missing height',
                        'tag' => 'missingheight',
                        'icon' => 'fas fa-music',
                        'sourceRules' => [
                            ['type' => 'extract', 'pattern' => '!audio\.example\.com/song/(?<id>[a-z0-9]+)!'],
                        ],
                        'embedUrl' => 'https://player.example.com/embed/{id}',
                        'iframeAttributes' => "scrolling='no'",
                        'aspectRatio' => '',
                    ],
                ],
            ],
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertNull(BbcodeRule::query()->where('tag', 'missingheight')->first());
    }

    #[Test]
    public function administrator_can_create_media_rule_without_duplicate_bbcode_configuration(): void
    {
        $response = $this->send($this->request('POST', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-rules',
                    'attributes' => [
                        'ruleType' => 'media',
                        'name' => 'Example video',
                        'tag' => 'examplevideo',
                        'icon' => 'fas fa-play',
                        'enabled' => true,
                        'toolbarEnabled' => true,
                        'sourceRules' => [
                            ['type' => 'extract', 'pattern' => '!video\.example\.com/watch/(?<id>[a-z0-9]+)!'],
                            ['type' => 'redirect', 'pattern' => '!short\.example\.com/[a-z0-9]+!'],
                        ],
                        'embedUrl' => 'https://player.example.com/embed/{id}',
                        'iframeAttributes' => " allowfullscreen   scrolling = \"yes\"   allow='autoplay; fullscreen' ",
                        'aspectRatio' => '4/3',
                    ],
                ],
            ],
        ]));
        $document = json_decode((string) $response->getBody(), true);

        $this->assertSame(201, $response->getStatusCode(), json_encode($document));
        $this->assertSame('media', $document['data']['attributes']['ruleType']);
        $this->assertSame('[examplevideo]{URL}[/examplevideo]', $document['data']['attributes']['usage']);
        $this->assertSame('<xsl:apply-templates/>', $document['data']['attributes']['template']);
        $this->assertSame('4 / 3', $document['data']['attributes']['aspectRatio']);
        $this->assertSame('4 / 3', BbcodeRule::query()->where('tag', 'examplevideo')->value('aspect_ratio'));
        $this->assertSame("allowfullscreen scrolling='yes' allow='autoplay; fullscreen'", $document['data']['attributes']['iframeAttributes']);
        $this->assertSame(['extract', 'redirect'], array_column($document['data']['attributes']['sourceRules'], 'type'));
        $this->assertSame(
            '!short\.example\.com/[a-z0-9]+!',
            BbcodeRule::query()->where('tag', 'examplevideo')->value('redirect_pattern')
        );

        $formatter = $this->app()->getContainer()->make(Formatter::class);
        $taggedHtml = $formatter->convert('[examplevideo]https://video.example.com/watch/abc123[/examplevideo]');
        $plainHtml = $formatter->convert('https://video.example.com/watch/abc123');

        $this->assertSame($plainHtml, $taggedHtml);
        $this->assertStringContainsString('https://player.example.com/embed/abc123', $taggedHtml);
        $this->assertMatchesRegularExpression('/\sallowfullscreen(?:="")?/', $taggedHtml);
        $this->assertStringContainsString('scrolling="yes"', $taggedHtml);
        $this->assertStringContainsString('allow="autoplay; fullscreen"', $taggedHtml);
    }

    #[Test]
    public function unsafe_iframe_attribute_is_rejected(): void
    {
        $response = $this->send($this->request('POST', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-rules',
                    'attributes' => [
                        'ruleType' => 'media',
                        'name' => 'Unsafe iframe',
                        'tag' => 'unsafeiframe',
                        'icon' => 'fas fa-play',
                        'enabled' => true,
                        'toolbarEnabled' => false,
                        'extractPattern' => '!video\.example\.com/watch/(?<id>[a-z0-9]+)!',
                        'embedUrl' => 'https://player.example.com/embed/{id}',
                        'iframeAttributes' => "onload='alert(1)'",
                        'aspectRatio' => '16 / 9',
                    ],
                ],
            ],
        ]));

        $this->assertSame(422, $response->getStatusCode());
        $this->assertNull(BbcodeRule::query()->where('tag', 'unsafeiframe')->first());
    }

    #[Test]
    public function media_extraction_pattern_requires_a_literal_source_host(): void
    {
        $response = $this->send($this->request('POST', '/api/ffans-bbcode-studio/rules', [
            'authenticatedAs' => 1,
            'json' => [
                'data' => [
                    'type' => 'bbcode-studio-rules',
                    'attributes' => [
                        'ruleType' => 'media',
                        'name' => 'Missing host',
                        'tag' => 'missinghost',
                        'icon' => 'fas fa-play',
                        'enabled' => true,
                        'toolbarEnabled' => false,
                        'extractPattern' => '!/watch/(?<id>[a-z0-9]+)!',
                        'embedUrl' => 'https://player.example.com/embed/{id}',
                        'aspectRatio' => '16 / 9',
                    ],
                ],
            ],
        ]));

        $this->assertSame(422, $response->getStatusCode());
    }

    private function enableBuiltInRule(string $tag): void
    {
        BbcodeRule::query()->where('tag', $tag)->update(['enabled' => true]);
    }
}
