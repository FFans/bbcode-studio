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
        $schema = $this->database()->getSchemaBuilder();
        $repository = $container->make(RuleRepository::class);
        $ruleCount = count($repository->all());
        $schema->rename('ffans_bbcode_studio_rules', 'audit_saved_rules');

        try {
            foreach (['all', 'enabled', 'toolbarRules'] as $method) {
                $this->assertSame([], $repository->{$method}());
            }

            // Missing-table reads must leave the surrounding transaction usable.
            $this->assertSame($ruleCount, $this->database()->table('audit_saved_rules')->count());

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
        } finally {
            // MySQL/MariaDB implicitly commit RENAME TABLE, so teardown cannot undo it.
            $schema->rename('audit_saved_rules', 'ffans_bbcode_studio_rules');
        }

        $this->assertCount($ruleCount, $repository->all());
        $this->assertFalse($schema->hasTable('audit_saved_rules'));
    }
}
