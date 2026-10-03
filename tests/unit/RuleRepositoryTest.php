<?php

namespace FFans\BbcodeStudio\Tests\unit;

use FFans\BbcodeStudio\BbcodeRule;
use FFans\BbcodeStudio\RuleRepository;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\SQLiteConnection;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RuleRepositoryTest extends TestCase
{
    private ?ConnectionResolverInterface $previousResolver;
    private SQLiteConnection $connection;
    private RuleRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousResolver = BbcodeRule::getConnectionResolver();
        $this->connection = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', 'test_', ['driver' => 'sqlite']);
        BbcodeRule::setConnectionResolver(new ConnectionResolver(['' => $this->connection]));
        $this->repository = new RuleRepository($this->connection);
    }

    protected function tearDown(): void
    {
        if ($this->previousResolver !== null) {
            BbcodeRule::setConnectionResolver($this->previousResolver);
        } else {
            BbcodeRule::unsetConnectionResolver();
        }
        parent::tearDown();
    }

    #[Test]
    public function missing_table_is_safe_and_becomes_visible_after_migration(): void
    {
        foreach (['all', 'enabled', 'toolbarRules'] as $method) {
            $this->assertSame([], $this->repository->{$method}());
        }

        $this->seed();
        $this->assertCount(8, $this->repository->all());
    }

    #[Test]
    public function reads_use_one_query_and_preserve_filters_and_order(): void
    {
        $this->seed();
        $table = $this->connection->table('ffans_bbcode_studio_rules');
        (clone $table)->whereIn('tag', ['notice', 'kdb', 'spoiler'])->update(['enabled' => true]);
        (clone $table)->where('tag', 'spoiler')->update(['toolbar_enabled' => false]);
        (clone $table)->whereIn('tag', ['notice', 'kdb'])->update(['sort_order' => 80]);
        $this->connection->enableQueryLog();

        foreach ([
            'all' => ['notice', 'kdb', 'spoiler', 'bilibili', 'youtube', 'vimeo', 'spotify', 'netease'],
            'enabled' => ['notice', 'kdb', 'spoiler'],
            'toolbarRules' => ['notice', 'kdb'],
        ] as $method => $tags) {
            $this->connection->flushQueryLog();
            $this->assertSame($tags, array_map(fn ($rule) => $rule->tag, $this->repository->{$method}()));
            $this->assertCount(1, $this->connection->getQueryLog(), $method);
        }
    }

    #[Test]
    public function missing_columns_are_not_hidden(): void
    {
        $this->connection->statement('CREATE TABLE incomplete_rules (id INTEGER)');
        $this->connection->statement('CREATE VIEW test_ffans_bbcode_studio_rules AS SELECT missing_column FROM incomplete_rules');
        $this->expectException(QueryException::class);
        $this->repository->toolbarRules();
    }

    #[Test]
    #[DataProvider('databaseErrors')]
    public function only_missing_table_errors_are_ignored(string $driver, array $info, bool $ignored): void
    {
        $previous = new \PDOException($info[2]);
        $previous->errorInfo = $info;
        $exception = new QueryException('test', 'select * from test_ffans_bbcode_studio_rules', [], $previous);
        $connection = $this->getMockBuilder(SQLiteConnection::class)
            ->setConstructorArgs([new PDO('sqlite::memory:'), ':memory:', 'test_', ['driver' => $driver]])
            ->onlyMethods(['select'])
            ->getMock();
        $connection->expects($this->once())->method('select')->willThrowException($exception);
        BbcodeRule::setConnectionResolver(new ConnectionResolver(['' => $connection]));

        if (! $ignored) {
            $this->expectExceptionObject($exception);
        }
        $this->assertSame([], (new RuleRepository($connection))->all());
    }

    public static function databaseErrors(): array
    {
        return [
            'mysql missing table' => ['mysql', ['42S02', 1146, "Table 'test.test_ffans_bbcode_studio_rules' doesn't exist"], true],
            'mariadb missing table' => ['mariadb', ['42S02', 1146, "Table 'test.test_ffans_bbcode_studio_rules' doesn't exist"], true],
            'postgres missing table' => ['pgsql', ['42P01', 7, 'relation "test_ffans_bbcode_studio_rules" does not exist'], true],
            'mysql missing column' => ['mysql', ['42S22', 1054, 'Unknown column'], false],
            'mysql connection lost' => ['mysql', ['HY000', 2006, 'MySQL server has gone away'], false],
            'mysql denied' => ['mysql', ['42000', 1142, 'SELECT command denied'], false],
            'postgres denied' => ['pgsql', ['42501', 7, 'permission denied'], false],
            'sqlite locked' => ['sqlite', ['HY000', 5, 'database is locked'], false],
            'sqlite other table' => ['sqlite', ['HY000', 1, 'no such table: another_table'], false],
        ];
    }

    private function seed(): void
    {
        foreach (['2026_08_29_000000_create_ffans_bbcode_studio_rules_table', '2026_08_29_010000_seed_builtin_rules'] as $name) {
            $migration = require dirname(__DIR__, 2).'/migrations/'.$name.'.php';
            $migration['up']($this->connection->getSchemaBuilder());
        }
    }
}
