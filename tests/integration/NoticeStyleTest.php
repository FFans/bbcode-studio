<?php

namespace FFans\BbcodeStudio\Tests\integration;

use FFans\BbcodeStudio\BbcodeRule;
use Flarum\Formatter\Formatter;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class NoticeStyleTest extends TestCase
{
    #[Test]
    public function black_notice_markup_matches_the_compiled_forum_style(): void
    {
        $this->extension('ffans-bbcode-studio');
        $container = $this->app()->getContainer();
        BbcodeRule::query()->where('builtin_key', 'notice')->update(['enabled' => true]);
        $formatter = $container->make(Formatter::class);
        $formatter->flush();
        $html = $formatter->render($formatter->parse('[notice color=black]Notice content[/notice]'));
        $this->assertStringContainsString('data-notice-color="black"', $html);

        $assets = $container->make('flarum.assets.forum');
        $assets->makeCss()->commit(true);
        $css = $assets->getAssetsDir()->get('forum.css');
        $this->assertMatchesRegularExpression('/\.BbcodeStudio-bbcode-notice\[data-notice-color=["\x27]?black["\x27]?\]/', $css);
        $this->assertStringNotContainsString('balck', $css);
        $this->assertSame(200, $this->send($this->request('GET', '/'))->getStatusCode());
    }
}
