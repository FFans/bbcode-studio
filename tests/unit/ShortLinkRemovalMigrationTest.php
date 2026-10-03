<?php

namespace FFans\BbcodeStudio\Tests\unit;

use Illuminate\Database\SQLiteConnection;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ShortLinkRemovalMigrationTest extends TestCase
{
    #[Test]
    public function migration_removes_redirect_settings_without_changing_other_data(): void
    {
        $connection = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', 'test_');
        $schema = $connection->getSchemaBuilder();
        foreach (['2026_08_29_000000_create_ffans_bbcode_studio_rules_table', '2026_08_29_010000_seed_builtin_rules'] as $name) {
            $migration = require dirname(__DIR__, 2).'/migrations/'.$name.'.php';
            $migration['up']($schema);
        }
        $table = $connection->table('ffans_bbcode_studio_rules');
        (clone $table)->where('tag', 'youtube')->update(['redirect_pattern' => '!custom\.example/[a-z]+!']);
        $before = (clone $table)->orderBy('id')->get()->map(static function ($row): array {
            $data = (array) $row;
            unset($data['redirect_pattern']);
            return $data;
        })->all();

        $migration = require dirname(__DIR__, 2).'/migrations/2026_10_03_020000_remove_short_link_redirects.php';
        foreach (['up', 'up'] as $direction) {
            $migration[$direction]($schema);
            $this->assertFalse($schema->hasColumn('ffans_bbcode_studio_rules', 'redirect_pattern'));
            $this->assertSame($before, (clone $table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all());
        }
        foreach (['down', 'down'] as $direction) {
            $migration[$direction]($schema);
            $this->assertTrue($schema->hasColumn('ffans_bbcode_studio_rules', 'redirect_pattern'));
            $this->assertSame(0, (clone $table)->whereNotNull('redirect_pattern')->count());
        }
    }

    #[Test]
    public function both_directions_are_safe_without_the_table(): void
    {
        $connection = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', 'test_');
        $migration = require dirname(__DIR__, 2).'/migrations/2026_10_03_020000_remove_short_link_redirects.php';
        foreach ($migration as $direction) {
            $direction($connection->getSchemaBuilder());
        }
        $this->assertFalse($connection->getSchemaBuilder()->hasTable('ffans_bbcode_studio_rules'));
    }
}
