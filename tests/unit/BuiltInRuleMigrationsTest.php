<?php

namespace FFans\BbcodeStudio\Tests\unit;

use FFans\BbcodeStudio\BuiltInRuleDefaults;
use Illuminate\Database\SQLiteConnection;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BuiltInRuleMigrationsTest extends TestCase
{
    private SQLiteConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', 'audit_');
    }

    #[Test]
    public function a_complete_fresh_install_matches_current_reset_defaults(): void
    {
        $files = glob(dirname(__DIR__, 2).'/migrations/*.php');
        sort($files);
        foreach ($files as $file) {
            $migration = require $file;
            $migration['up']($this->connection->getSchemaBuilder());
        }

        $rows = $this->connection->table('ffans_bbcode_studio_rules')->get();
        $this->assertCount(8, $rows);
        foreach ($rows as $row) {
            $defaults = BuiltInRuleDefaults::for($row->builtin_key);
            $this->assertNotNull($defaults);
            foreach ($defaults as $key => $expected) {
                // These are derived formatter/API values, not database columns.
                if (in_array($key, ['sourceRules', 'captureDefaults'], true)) {
                    continue;
                }
                $column = strtolower(preg_replace('/[A-Z]/', '_$0', $key));
                $actual = $row->{$column};
                if ($key === 'exampleAttributes') {
                    $actual = json_decode($actual, true, flags: JSON_THROW_ON_ERROR);
                } elseif (is_bool($expected)) {
                    $actual = (bool) $actual;
                } elseif (is_int($expected)) {
                    $actual = (int) $actual;
                }
                if (is_string($expected) && is_string($actual)) {
                    $expected = str_replace("\r\n", "\n", $expected);
                    $actual = str_replace("\r\n", "\n", $actual);
                }
                $this->assertSame($expected, $actual, $row->builtin_key.'.'.$key);
            }
            $this->assertFalse(property_exists($row, 'redirect_pattern'));
            $this->assertSame([], array_filter($defaults['sourceRules'], fn ($rule) => $rule['type'] !== 'extract'));
        }
    }

    #[Test]
    public function notice_style_upgrade_and_rollback_are_idempotent_and_preserve_other_fields(): void
    {
        $this->seed();
        $table = $this->connection->table('ffans_bbcode_studio_rules')->where('builtin_key', 'notice');
        $oldStyles = $table->value('css_declarations');
        foreach ([$oldStyles, "\r\n".str_replace("\n", "\r\n", $oldStyles)."\r\n"] as $styles) {
            (clone $table)->update(['css_declarations' => $styles, 'enabled' => true, 'name' => 'Custom name']);
            foreach (['up', 'down', 'up', 'down'] as $direction) {
                $before = (array) (clone $table)->first();
                $this->noticeMigration()[$direction]($this->connection->getSchemaBuilder());
                $after = (array) (clone $table)->first();
                $expected = $direction === 'up' ? str_replace('="balck"', '="black"', $styles) : $styles;
                $this->assertSame($expected, $after['css_declarations']);
                foreach ($before as $key => $value) {
                    if (! in_array($key, ['css_declarations', 'updated_at'], true)) {
                        $this->assertSame($value, $after[$key], $key);
                    }
                }
                (clone $table)->update(['updated_at' => '2000-01-01 00:00:00']);
                $beforeRepeat = (array) (clone $table)->first();
                $this->noticeMigration()[$direction]($this->connection->getSchemaBuilder());
                $this->assertSame($beforeRepeat, (array) (clone $table)->first());
            }
        }
    }

    #[Test]
    public function custom_styles_and_non_builtin_rules_are_untouched_in_both_directions(): void
    {
        $this->seed();
        $table = $this->connection->table('ffans_bbcode_studio_rules')->where('tag', 'notice');
        $oldStyles = $table->value('css_declarations');
        foreach (['up', 'down'] as $direction) {
            $styles = $direction === 'up' ? $oldStyles : str_replace('="balck"', '="black"', $oldStyles);
            foreach ([
                ['css_declarations' => $styles."\ncolor: red;"],
                ['css_declarations' => strtoupper($styles)],
                ['css_declarations' => null],
                ['builtin_key' => null],
                ['builtin_key' => 'NOTICE'],
                ['rule_type' => 'media'],
            ] as $custom) {
                (clone $table)->update(['builtin_key' => 'notice', 'rule_type' => 'bbcode', 'css_declarations' => $styles, ...$custom]);
                $before = (array) (clone $table)->first();
                $this->noticeMigration()[$direction]($this->connection->getSchemaBuilder());
                $this->assertSame($before, (array) (clone $table)->first());
            }
        }
    }

    #[Test]
    public function notice_migration_is_safe_without_the_table(): void
    {
        foreach ($this->noticeMigration() as $direction) {
            $direction($this->connection->getSchemaBuilder());
        }
        $this->assertFalse($this->connection->getSchemaBuilder()->hasTable('ffans_bbcode_studio_rules'));
    }

    private function noticeMigration(): array
    {
        return require dirname(__DIR__, 2).'/migrations/2026_10_03_010000_fix_notice_black_style.php';
    }

    private function seed(): void
    {
        foreach (['2026_08_29_000000_create_ffans_bbcode_studio_rules_table', '2026_08_29_010000_seed_builtin_rules'] as $name) {
            $migration = require dirname(__DIR__, 2).'/migrations/'.$name.'.php';
            $migration['up']($this->connection->getSchemaBuilder());
        }
    }
}
