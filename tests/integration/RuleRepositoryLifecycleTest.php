<?php

namespace FFans\BbcodeStudio\Tests\integration;

use FFans\BbcodeStudio\RuleRepository;
use Flarum\Formatter\Formatter;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RuleRepositoryLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('ffans-bbcode-studio');
    }

    #[Test]
    public function api_formatter_and_assets_work_before_the_rules_table_exists(): void
    {
        $container = $this->app()->getContainer();
        $this->database()->getSchemaBuilder()->rename('ffans_bbcode_studio_rules', 'audit_saved_rules');
        $this->assertSame([], $container->make(RuleRepository::class)->all());

        $formatter = $container->make(Formatter::class);
        $formatter->flush();
        $this->assertStringContainsString('Plain content', $formatter->render($formatter->parse('Plain content')));

        $response = $this->send($this->request('GET', '/api'));
        $this->assertSame(200, $response->getStatusCode());
        $document = json_decode((string) $response->getBody(), true);
        $this->assertSame([], $document['data']['attributes']['ffansBbcodeStudioToolbarRules']);

        foreach (['forum', 'admin'] as $frontend) {
            $assets = $container->make('flarum.assets.'.$frontend);
            $assets->makeCss()->commit(true);
            $this->assertNotEmpty($assets->getAssetsDir()->get($frontend.'.css'));
        }

        $this->assertSame(200, $this->send($this->request('GET', '/'))->getStatusCode());
    }
}
