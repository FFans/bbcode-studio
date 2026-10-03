<?php

namespace FFans\BbcodeStudio\Tests\integration;

use FFans\BbcodeStudio\BbcodeRule;
use FFans\BbcodeStudio\RuleRepository;
use Flarum\Testing\integration\TestCase;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;

class RuleRepositoryBindingTest extends TestCase
{
    #[Test]
    public function repository_receives_the_configured_flarum_connection(): void
    {
        $this->extension('ffans-bbcode-studio');
        $container = $this->app()->getContainer();
        $connection = $container->make(ConnectionInterface::class);
        $repository = $container->make(RuleRepository::class);

        $this->assertSame($connection, $container->make('db.connection'));
        $this->assertSame($connection, $container->make('flarum.db'));
        $this->assertSame($connection, (new BbcodeRule())->getConnection());
        $this->assertSame($connection, (new ReflectionProperty(RuleRepository::class, 'connection'))->getValue($repository));
        $this->assertTrue($repository->tableExists());
        $this->assertCount(8, $repository->all());
    }
}
