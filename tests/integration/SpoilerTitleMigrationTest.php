<?php

namespace FFans\BbcodeStudio\Tests\integration;

use FFans\BbcodeStudio\BbcodeRule;
use FFans\BbcodeStudio\BuiltInRuleDefaults;
use FFans\BbcodeStudio\Formatter\BbcodeRuleDefinition;
use Flarum\Formatter\Formatter;
use Flarum\Testing\integration\TestCase;
use Illuminate\Database\SQLiteConnection;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use s9e\TextFormatter\Configurator;

class SpoilerTitleMigrationTest extends TestCase
{
    private const OLD_USAGE = '[spoiler title={SIMPLETEXT?}]{TEXT}[/spoiler]';
    private const NEW_USAGE = '[spoiler title={TEXT?}]{TEXT}[/spoiler]';
    private const OLD_TITLE = '<xsl:otherwise>Spoiler</xsl:otherwise>';
    private const NEW_TITLE = '<xsl:otherwise><xsl:value-of select="$L_BBCODE_STUDIO_SPOILER"/></xsl:otherwise>';

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('ffans-bbcode-studio');
    }

    #[Test]
    public function defaults_round_trip_and_each_direction_is_idempotent(): void
    {
        $this->app();
        $rule = BbcodeRule::query()->where('builtin_key', 'spoiler')->firstOrFail();
        $schema = $rule->getConnection()->getSchemaBuilder();
        $migration = $this->migration();
        $oldTemplate = $this->oldTemplate();

        foreach ([$oldTemplate, "\r\n".str_replace("\n", "\r\n", $oldTemplate)."\r\n"] as $template) {
            $rule->forceFill([
                'name' => 'Spoiler',
                'button_label' => 'Spoiler',
                'description' => 'Hide content inside a collapsible spoiler block.',
                'usage' => self::OLD_USAGE,
                'template' => $template,
                'css_declarations' => 'color: red;',
                'enabled' => true,
                'toolbar_enabled' => false,
                'updated_at' => '2000-01-01 00:00:00',
            ])->save();
            $original = $rule->refresh()->getAttributes();

            foreach (['up', 'down', 'up', 'down'] as $direction) {
                $migration[$direction]($schema);
                $rule->refresh();
                $this->assertSame($direction === 'up' ? 'Collapser' : 'Spoiler', $rule->name);
                $this->assertSame($direction === 'up' ? 'Collapser' : 'Spoiler', $rule->button_label);
                $this->assertSame(
                    $direction === 'up' ? 'Display content in a collapsible block.' : 'Hide content inside a collapsible spoiler block.',
                    $rule->description
                );
                $this->assertSame($direction === 'up' ? self::NEW_USAGE : self::OLD_USAGE, $rule->usage);
                $this->assertSame(
                    $direction === 'up' ? str_replace(self::OLD_TITLE, self::NEW_TITLE, $template) : $template,
                    $rule->template
                );
                foreach ($original as $key => $value) {
                    if (! in_array($key, ['name', 'button_label', 'description', 'usage', 'template', 'updated_at'], true)) {
                        $this->assertSame($value, $rule->getAttributes()[$key], $key);
                    }
                }

                // A no-op must not even touch updated_at.
                $rule->getConnection()->table($rule->getTable())->where('id', $rule->id)
                    ->update(['updated_at' => '2000-01-01 00:00:00']);
                $beforeRepeat = $rule->refresh()->getAttributes();
                $migration[$direction]($schema);
                $this->assertSame($beforeRepeat, $rule->refresh()->getAttributes());
            }
        }
    }

    #[Test]
    public function upgrade_and_rollback_preserve_custom_fields_independently(): void
    {
        $this->app();
        $rule = BbcodeRule::query()->where('builtin_key', 'spoiler')->firstOrFail();
        $schema = $rule->getConnection()->getSchemaBuilder();
        $migration = $this->migration();
        $oldTemplate = $this->oldTemplate();
        $newTemplate = BuiltInRuleDefaults::for('spoiler')['template'];
        $customUsage = '[spoiler title={TEXT?} lang={SIMPLETEXT?}]{TEXT}[/spoiler]';
        $customTemplate = str_replace('Spoiler', '自定义默认标题', $oldTemplate);

        foreach (['up', 'down'] as $direction) {
            $fromUsage = $direction === 'up' ? self::OLD_USAGE : self::NEW_USAGE;
            $toUsage = $direction === 'up' ? self::NEW_USAGE : self::OLD_USAGE;
            $fromTemplate = $direction === 'up' ? $oldTemplate : $newTemplate;
            $toTemplate = $direction === 'up' ? $newTemplate : $oldTemplate;

            foreach ([
                [$customUsage, $fromTemplate, $customUsage, $toTemplate],
                [$fromUsage, $customTemplate, $toUsage, $customTemplate],
                [$customUsage, $customTemplate, $customUsage, $customTemplate],
                [strtoupper($fromUsage), $customTemplate, strtoupper($fromUsage), $customTemplate],
            ] as [$usage, $template, $expectedUsage, $expectedTemplate]) {
                $rule->forceFill(['usage' => $usage, 'template' => $template])->save();
                $migration[$direction]($schema);
                $rule->refresh();
                $this->assertSame($expectedUsage, $rule->usage);
                $this->assertSame($expectedTemplate, $rule->template);
            }

            foreach ([['builtin_key' => null], ['builtin_key' => 'SPOILER'], ['rule_type' => 'media']] as $identity) {
                $rule->forceFill([
                    'builtin_key' => 'spoiler', 'rule_type' => 'bbcode',
                    'usage' => $fromUsage, 'template' => $fromTemplate, ...$identity,
                ])->save();
                $before = $rule->refresh()->getAttributes();
                $migration[$direction]($schema);
                $this->assertSame($before, $rule->refresh()->getAttributes());
            }
            $rule->forceFill(['builtin_key' => 'spoiler', 'rule_type' => 'bbcode'])->save();
        }
    }

    #[Test]
    public function rollback_removes_the_parameter_dependency_without_rewriting_saved_titles(): void
    {
        $this->app();
        $rule = BbcodeRule::query()->where('builtin_key', 'spoiler')->firstOrFail();
        $rule->forceFill(['enabled' => true])->save();
        $formatter = $this->app()->getContainer()->make(Formatter::class);
        $storedXml = $formatter->parse('[spoiler title="中文标题 🎉"]Hidden[/spoiler]');
        $untitledXml = $formatter->parse('[spoiler]Hidden[/spoiler]');

        $this->migration()['down']($rule->getConnection()->getSchemaBuilder());
        $rule->refresh();
        $this->assertSame(self::OLD_USAGE, $rule->usage);
        $this->assertSame($this->oldTemplate(), $rule->template);

        // Emulate the old renderer without registering this release's translation parameter.
        $configurator = new Configurator();
        $configurator->rendering->setEngine('PHP');
        $configurator->rendering->getEngine()->cacheDir = sys_get_temp_dir();
        $configurator->BBCodes->addCustom($rule->usage, BbcodeRuleDefinition::template($rule->tag, $rule->template));
        $renderer = $configurator->finalize()['renderer'];
        $this->assertStringContainsString('<summary>中文标题 🎉</summary>', $renderer->render($storedXml));
        $this->assertStringContainsString('<summary>Spoiler</summary>', $renderer->render($untitledXml));
    }

    #[Test]
    public function upgrade_converges_from_either_partially_updated_default(): void
    {
        $this->app();
        $rule = BbcodeRule::query()->where('builtin_key', 'spoiler')->firstOrFail();
        $schema = $rule->getConnection()->getSchemaBuilder();
        $migration = $this->migration();
        $newTemplate = BuiltInRuleDefaults::for('spoiler')['template'];

        foreach ([
            [self::NEW_USAGE, $this->oldTemplate()],
            [self::OLD_USAGE, $newTemplate],
        ] as [$usage, $template]) {
            $rule->forceFill(['usage' => $usage, 'template' => $template])->save();
            $migration['up']($schema);
            $rule->refresh();
            $this->assertSame(self::NEW_USAGE, $rule->usage);
            $this->assertSame($newTemplate, $rule->template);
        }
    }

    #[Test]
    public function custom_labels_survive_upgrade_and_rollback(): void
    {
        $this->app();
        $rule = BbcodeRule::query()->where('builtin_key', 'spoiler')->firstOrFail();
        $schema = $rule->getConnection()->getSchemaBuilder();
        $migration = $this->migration();

        foreach (['up', 'down'] as $direction) {
            foreach (['name', 'button_label', 'description'] as $customField) {
                $rule->forceFill([
                    'name' => $direction === 'up' ? 'Spoiler' : 'Collapser',
                    'button_label' => $direction === 'up' ? 'Spoiler' : 'Collapser',
                    'description' => $direction === 'up'
                        ? 'Hide content inside a collapsible spoiler block.'
                        : 'Display content in a collapsible block.',
                    $customField => '管理员自定义文案',
                ])->save();
                $migration[$direction]($schema);
                $rule->refresh();
                $this->assertSame('管理员自定义文案', $rule->{$customField});
                foreach (['name', 'button_label', 'description'] as $field) {
                    if ($field !== $customField) {
                        $expected = $field === 'description'
                            ? ($direction === 'up' ? 'Display content in a collapsible block.' : 'Hide content inside a collapsible spoiler block.')
                            : ($direction === 'up' ? 'Collapser' : 'Spoiler');
                        $this->assertSame($expected, $rule->{$field});
                    }
                }
            }
        }
    }

    #[Test]
    public function both_directions_are_safe_when_the_rules_table_is_absent(): void
    {
        $connection = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', 'test_');
        $schema = $connection->getSchemaBuilder();
        foreach ($this->migration() as $direction) {
            $direction($schema);
        }
        $this->assertFalse($schema->hasTable('ffans_bbcode_studio_rules'));
    }

    private function migration(): array
    {
        return require dirname(__DIR__, 2).'/migrations/2026_10_03_000000_support_spoiler_unicode_title.php';
    }

    private function oldTemplate(): string
    {
        return str_replace(self::NEW_TITLE, self::OLD_TITLE, BuiltInRuleDefaults::for('spoiler')['template']);
    }
}
