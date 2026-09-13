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
    public function it_applies_capture_defaults_and_preserves_explicit_values(): void
    {
        $tester = $this->tester(new MockHandler());
        $pattern = '!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)/?(?:\?(?:[^#\s&]*&)*p=(?<p>[1-9]\d*)(?=[&#\s]|$))?!';
        $embedUrl = '//player.bilibili.com/player.html?bvid={id}&p={p}&autoplay=false';

        $default = $tester->test(
            'https://www.bilibili.com/video/BV1Js411o76u',
            [['type' => 'extract', 'pattern' => $pattern]],
            $embedUrl,
            ['p' => '1']
        );
        $explicit = $tester->test(
            'https://www.bilibili.com/video/BV1Js411o76u?share_source=copy_web&p=2',
            [['type' => 'extract', 'pattern' => $pattern]],
            $embedUrl,
            ['p' => '1']
        );
        $trailingSlash = $tester->test(
            'https://www.bilibili.com/video/BV1ujBYBNEPg/?spm_id_from=333.788.videopod.episodes&p=6',
            [['type' => 'extract', 'pattern' => $pattern]],
            $embedUrl,
            ['p' => '1']
        );

        $this->assertSame('1', $default['captures']['p']);
        $this->assertSame('//player.bilibili.com/player.html?bvid=BV1Js411o76u&p=1&autoplay=false', $default['embedUrl']);
        $this->assertSame('2', $explicit['captures']['p']);
        $this->assertSame('//player.bilibili.com/player.html?bvid=BV1Js411o76u&p=2&autoplay=false', $explicit['embedUrl']);
        $this->assertSame('6', $trailingSlash['captures']['p']);
        $this->assertSame('//player.bilibili.com/player.html?bvid=BV1ujBYBNEPg&p=6&autoplay=false', $trailingSlash['embedUrl']);
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
