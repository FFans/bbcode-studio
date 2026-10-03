<?php

namespace FFans\BbcodeStudio\Tests\unit;

use FFans\BbcodeStudio\Formatter\MediaRuleTester;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MediaRuleTesterTest extends TestCase
{
    #[Test]
    public function it_tests_direct_urls_with_named_captures(): void
    {
        $tester = new MediaRuleTester();

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
        $tester = new MediaRuleTester();

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
        $tester = new MediaRuleTester();
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
    public function short_urls_do_not_match_direct_rules(): void
    {
        $tester = new MediaRuleTester();
        $this->assertSame(['matched' => false, 'reason' => 'no_match'], $tester->test(
            'https://b23.tv/PYEGMvk',
            [['type' => 'extract', 'pattern' => '!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)!']],
            '//player.bilibili.com/player.html?bvid={id}'
        ));
    }

    #[Test]
    public function legacy_redirect_rules_are_rejected_even_after_a_matching_direct_rule(): void
    {
        $tester = new MediaRuleTester();
        $this->assertSame(['matched' => false, 'reason' => 'invalid_pattern', 'ruleNumber' => 2], $tester->test(
            'https://video.example.com/watch/abc123',
            [
                ['type' => 'extract', 'pattern' => '!video\.example\.com/watch/(?<id>[a-z0-9]+)!'],
                ['type' => 'redirect', 'pattern' => '!short\.example\.com/[a-z0-9]+!'],
            ],
            'https://player.example.com/embed/{id}'
        ));
    }
}
