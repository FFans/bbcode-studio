<?php

namespace FFans\BbcodeStudio\Tests\unit;

use FFans\BbcodeStudio\Formatter\ShortLinkResolver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ShortLinkResolverTest extends TestCase
{
    #[Test]
    public function it_follows_an_allowed_redirect_extracts_values_and_caches_the_result(): void
    {
        $history = [];
        $handler = new MockHandler([
            new Response(302, ['Location' => 'https://www.bilibili.com/video/BV1xx411c7mD']),
        ]);
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::history($history));
        $resolver = new ShortLinkResolver(
            new Repository(new ArrayStore()),
            new Client(['handler' => $stack]),
            fn (string $host): bool => true
        );

        $arguments = [
            'https://b23.tv/PYEGMvk',
            ['b23.tv'],
            ['bilibili.com'],
            ['!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)!'],
            ['id'],
        ];

        $this->assertSame(['id' => 'BV1xx411c7mD'], $resolver->resolve(...$arguments));
        $this->assertSame(['id' => 'BV1xx411c7mD'], $resolver->resolve(...$arguments));
        $this->assertCount(1, $history);
    }

    #[Test]
    public function it_rejects_redirects_to_unconfigured_hosts(): void
    {
        $resolver = new ShortLinkResolver(
            new Repository(new ArrayStore()),
            new Client(['handler' => new MockHandler([
                new Response(302, ['Location' => 'https://evil.example/video/BV1xx411c7mD']),
            ])]),
            fn (string $host): bool => true
        );

        $this->assertNull($resolver->resolve(
            'https://b23.tv/PYEGMvk',
            ['b23.tv'],
            ['bilibili.com'],
            ['!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)!'],
            ['id']
        ));
    }

    #[Test]
    public function it_allows_proxy_fake_ip_addresses_for_configured_short_link_hosts(): void
    {
        $resolver = new ShortLinkResolver(
            new Repository(new ArrayStore()),
            new Client(['handler' => new MockHandler([
                new Response(302, ['Location' => 'https://www.bilibili.com/video/BV1P18y6LEEZ']),
            ])]),
            null,
            fn (string $host): array => $host === 'b23.tv' ? ['198.18.0.31'] : ['203.0.113.10']
        );

        $this->assertSame(['id' => 'BV1P18y6LEEZ'], $resolver->resolve(
            'https://b23.tv/PYEGMvk',
            ['b23.tv'],
            ['bilibili.com'],
            ['!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)!'],
            ['id']
        ));
    }

    #[Test]
    public function it_still_rejects_private_addresses_for_configured_hosts(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(302, ['Location' => 'https://www.bilibili.com/video/BV1P18y6LEEZ']),
        ]));
        $stack->push(Middleware::history($history));
        $resolver = new ShortLinkResolver(
            new Repository(new ArrayStore()),
            new Client(['handler' => $stack]),
            null,
            fn (string $host): array => ['127.0.0.1']
        );

        $this->assertNull($resolver->resolve(
            'https://b23.tv/PYEGMvk',
            ['b23.tv'],
            ['bilibili.com'],
            ['!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)!'],
            ['id']
        ));
        $this->assertCount(0, $history);
    }
}
