<?php

namespace FFans\BbcodeStudio\Tests\unit;

use FFans\BbcodeStudio\Formatter\MediaRuleTester;
use FFans\BbcodeStudio\Formatter\ShortLinkResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MediaRuleTesterTest extends TestCase
{
    #[Test]
    public function it_tests_direct_urls_with_named_captures(): void
    {
        $tester = $this->tester(new MockHandler());

        $this->assertSame([
            'matched' => true,
            'matchType' => 'extract',
            'ruleNumber' => 1,
            'captures' => ['id' => 'abc123'],
            'embedUrl' => 'https://player.example.com/embed/abc123',
        ], $tester->test(
            'https://video.example.com/watch/abc123',
            [['type' => 'extract', 'pattern' => '!video\.example\.com/watch/(?<id>[a-z0-9]+)!']],
            'https://player.example.com/embed/{id}'
        ));
    }

    #[Test]
    public function it_reports_the_invalid_rule_before_a_matching_rule(): void
    {
        $tester = $this->tester(new MockHandler());

        $this->assertSame([
            'matched' => false,
            'reason' => 'invalid_pattern',
            'ruleNumber' => 2,
        ], $tester->test(
            'https://video.example.com/watch/abc123',
            [
                ['type' => 'extract', 'pattern' => '!video\.example\.com/watch/(?<id>[a-z0-9]+)!'],
                ['type' => 'extract', 'pattern' => '!broken\.example\.com/(?<id>[!'],
            ],
            'https://player.example.com/embed/{id}'
        ));
    }

    #[Test]
    public function it_resolves_matching_short_urls(): void
    {
        $tester = $this->tester(new MockHandler([
            new Response(302, ['Location' => 'https://video.example.com/watch/abc123']),
        ]));

        $this->assertSame([
            'matched' => true,
            'matchType' => 'redirect',
            'ruleNumber' => 2,
            'captures' => ['id' => 'abc123'],
            'embedUrl' => 'https://player.example.com/embed/abc123',
        ], $tester->test(
            'https://short.example.com/a1',
            [
                ['type' => 'extract', 'pattern' => '!video\.example\.com/watch/(?<id>[a-z0-9]+)!'],
                ['type' => 'redirect', 'pattern' => '!short\.example\.com/[a-z0-9]+!'],
            ],
            'https://player.example.com/embed/{id}'
        ));
    }

    private function tester(MockHandler $handler): MediaRuleTester
    {
        return new MediaRuleTester(new ShortLinkResolver(
            new Repository(new ArrayStore()),
            new Client(['handler' => HandlerStack::create($handler)]),
            fn (string $host): bool => true
        ));
    }
}
