<?php

namespace FFans\BbcodeStudio\Tests\unit;

use FFans\BbcodeStudio\Formatter\BbcodeRuleDefinition;
use FFans\BbcodeStudio\Formatter\MediaRuleDefinition;
use FFans\BbcodeStudio\Formatter\ShortLinkResolver;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use s9e\TextFormatter\Configurator;

class FormatterDefinitionTest extends TestCase
{
    #[Test]
    public function one_media_definition_renders_tagged_and_plain_links_identically(): void
    {
        $configurator = new Configurator();
        $configurator->rendering->setEngine('PHP');
        $configurator->rendering->getEngine()->cacheDir = sys_get_temp_dir();
        $bilibiliTag = MediaRuleDefinition::configure($configurator, 'bilibili', 'ffansbbcodebilibili', [
            'extract_pattern' => '!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)!',
            'embed_url' => '//player.bilibili.com/player.html?bvid={id}&p=1&autoplay=false',
            'iframe_attributes' => "allowfullscreen scrolling='no'",
            'aspect_ratio' => '16 / 9',
        ]);
        $youtubeTag = MediaRuleDefinition::configure($configurator, 'youtube', 'ffansbbcodeyoutube', [
            'extract_pattern' => '!youtube\.com/shorts/(?<id>[-\w]+)!',
            'embed_url' => 'https://www.youtube-nocookie.com/embed/{id}',
            'iframe_attributes' => '',
            'aspect_ratio' => '16 / 9',
        ]);
        MediaRuleDefinition::isolateTaggedMedia(
            $configurator,
            [$bilibiliTag['bbcodeTag'], $youtubeTag['bbcodeTag']],
            array_merge($bilibiliTag['siteIds'], $youtubeTag['siteIds'])
        );

        $components = $configurator->finalize();
        $taggedXml = $components['parser']->parse('[bilibili]https://www.bilibili.com/video/BV1xx411c7mD[/bilibili]');
        $plainXml = $components['parser']->parse('https://www.bilibili.com/video/BV1xx411c7mD');
        $wrongTagXml = $components['parser']->parse('[bilibili]https://www.youtube.com/shorts/IBKjPRS1ZqE[/bilibili]');
        $taggedHtml = $components['renderer']->render($taggedXml);
        $plainHtml = $components['renderer']->render($plainXml);
        $wrongTagHtml = $components['renderer']->render($wrongTagXml);

        $this->assertSame($plainHtml, $taggedHtml);
        $this->assertStringContainsString('//player.bilibili.com/player.html?bvid=BV1xx411c7mD&amp;p=1&amp;autoplay=false', $taggedHtml);
        $this->assertStringContainsString('<iframe', $taggedHtml);
        $this->assertMatchesRegularExpression('/\sallowfullscreen(?:="")?/', $taggedHtml);
        $this->assertStringContainsString('scrolling="no"', $taggedHtml);
        $this->assertStringContainsString('BbcodeStudio-media', $taggedHtml);
        $this->assertStringContainsString('BbcodeStudio-media-bilibili', $taggedHtml);
        $this->assertStringContainsString('BbcodeStudio-media-bilibili', $plainHtml);
        $this->assertStringNotContainsString('<iframe', $wrongTagHtml);
        $this->assertStringContainsString('[bilibili]https://www.youtube.com/shorts/IBKjPRS1ZqE[/bilibili]', $wrongTagHtml);
    }

    #[Test]
    public function media_definition_can_use_a_fixed_iframe_height_without_an_aspect_ratio(): void
    {
        $configurator = new Configurator();
        $configurator->rendering->setEngine('PHP');
        $configurator->rendering->getEngine()->cacheDir = sys_get_temp_dir();
        $definition = MediaRuleDefinition::configure($configurator, 'netease', 'ffansbbcodenetease', [
            'extract_pattern' => '!music\.163\.com/song\?id=(?<id>\d+)!',
            'embed_url' => '//music.163.com/outchain/player?type=2&id={id}&height=66',
            'iframe_attributes' => "scrolling='no' height='86'",
            'aspect_ratio' => '',
        ]);
        MediaRuleDefinition::isolateTaggedMedia($configurator, [$definition['bbcodeTag']], $definition['siteIds']);

        $components = $configurator->finalize();
        $html = $components['renderer']->render($components['parser']->parse('https://music.163.com/song?id=123'));

        $this->assertStringContainsString('BbcodeStudio-media--fixed-height', $html);
        $this->assertStringContainsString('height:86px', $html);
        $this->assertStringContainsString('width:100%', $html);
        $this->assertStringNotContainsString('padding-bottom', $html);
    }

    #[Test]
    public function spotify_media_definition_supports_common_content_types(): void
    {
        $configurator = new Configurator();
        $configurator->rendering->setEngine('PHP');
        $configurator->rendering->getEngine()->cacheDir = sys_get_temp_dir();
        $definition = MediaRuleDefinition::configure($configurator, 'spotify', 'ffansbbcodespotify', [
            'extract_pattern' => '!open\.spotify\.com/(?<type>track|album|playlist|artist|show|episode)/(?<id>[a-zA-Z0-9]+)!',
            'embed_url' => 'https://open.spotify.com/embed/{type}/{id}',
            'iframe_attributes' => "allow='autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture' height='152'",
            'aspect_ratio' => '',
        ]);
        MediaRuleDefinition::isolateTaggedMedia($configurator, [$definition['bbcodeTag']], $definition['siteIds']);

        $components = $configurator->finalize();

        foreach (['track', 'album', 'playlist', 'artist', 'show', 'episode'] as $type) {
            $url = 'https://open.spotify.com/'.$type.'/11dFghVXANMlKmJXsNCbNl';
            $plainHtml = $components['renderer']->render($components['parser']->parse($url));
            $taggedHtml = $components['renderer']->render($components['parser']->parse('[spotify]'.$url.'[/spotify]'));

            $this->assertSame($plainHtml, $taggedHtml, $type);
            $this->assertStringContainsString('open.spotify.com/embed/'.$type.'/11dFghVXANMlKmJXsNCbNl', $plainHtml, $type);
            $this->assertStringContainsString('encrypted-media', $plainHtml, $type);
            $this->assertStringContainsString('height:152px', $plainHtml, $type);
        }
    }

    #[Test]
    public function vimeo_media_definition_supports_common_video_url_shapes(): void
    {
        $configurator = new Configurator();
        $configurator->rendering->setEngine('PHP');
        $configurator->rendering->getEngine()->cacheDir = sys_get_temp_dir();
        $pattern = "!vimeo\\.com/(?:channels/[a-zA-Z0-9_-]+/|groups/[a-zA-Z0-9_-]+/videos/|album/\\d+/video/|video/)?(?<id>\\d+)!\n!player\\.vimeo\\.com/video/(?<id>\\d+)!";
        $definition = MediaRuleDefinition::configure($configurator, 'vimeo', 'ffansbbcodevimeo', [
            'extract_pattern' => $pattern,
            'embed_url' => 'https://player.vimeo.com/video/{id}',
            'iframe_attributes' => "allowfullscreen allow='autoplay; fullscreen; picture-in-picture'",
            'aspect_ratio' => '16 / 9',
        ]);
        MediaRuleDefinition::isolateTaggedMedia($configurator, [$definition['bbcodeTag']], $definition['siteIds']);

        $this->assertSame(['vimeo.com', 'player.vimeo.com'], MediaRuleDefinition::hostsFromPatterns($pattern));

        $components = $configurator->finalize();

        foreach ([
            'https://vimeo.com/76979871',
            'https://vimeo.com/channels/staffpicks/76979871',
            'https://vimeo.com/groups/shortfilms/videos/76979871',
            'https://vimeo.com/album/1234/video/76979871',
            'https://player.vimeo.com/video/76979871',
        ] as $url) {
            $plainHtml = $components['renderer']->render($components['parser']->parse($url));
            $taggedHtml = $components['renderer']->render($components['parser']->parse('[vimeo]'.$url.'[/vimeo]'));

            $this->assertSame($plainHtml, $taggedHtml, $url);
            $this->assertStringContainsString('player.vimeo.com/video/76979871', $plainHtml, $url);
            $this->assertStringContainsString('BbcodeStudio-media-vimeo', $plainHtml, $url);
        }
    }

    #[Test]
    public function short_links_are_resolved_on_the_server_for_tagged_and_plain_urls(): void
    {
        $configurator = new Configurator();
        $configurator->rendering->setEngine('PHP');
        $configurator->rendering->getEngine()->cacheDir = sys_get_temp_dir();
        $definition = MediaRuleDefinition::configure($configurator, 'bilibili', 'ffansbbcodebilibili', [
            'extract_pattern' => '!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)!',
            'redirect_pattern' => '!b23\.tv/[a-zA-Z0-9]+!',
            'embed_url' => '//player.bilibili.com/player.html?bvid={id}',
            'iframe_attributes' => '',
            'aspect_ratio' => '16 / 9',
        ]);
        MediaRuleDefinition::isolateTaggedMedia($configurator, [$definition['bbcodeTag']], $definition['siteIds']);

        $components = $configurator->finalize();
        $resolver = new class extends ShortLinkResolver {
            public int $calls = 0;

            public function __construct()
            {
            }

            public function resolve(string $url, array $sourceHosts, array $targetHosts, array $directPatterns, array $requiredCaptures): ?array
            {
                $this->calls++;

                return str_ends_with($url, 'b23.tv/PYEGMvk') ? ['id' => 'BV1xx411c7mD'] : null;
            }
        };
        $components['parser']->registeredVars['bbcodeStudio.shortLinkResolver'] = $resolver;

        $plainXml = $components['parser']->parse('https://b23.tv/PYEGMvk');
        $plainLogs = $components['parser']->getLogger()->getLogs();
        $taggedXml = $components['parser']->parse('[bilibili]https://b23.tv/PYEGMvk[/bilibili]');
        $plainHtml = $components['renderer']->render($plainXml);
        $taggedHtml = $components['renderer']->render($taggedXml);

        $this->assertStringContainsString('bvid=BV1xx411c7mD', $plainHtml, $plainXml.json_encode($plainLogs));
        $this->assertStringContainsString('bvid=BV1xx411c7mD', $taggedHtml, $taggedXml);
    }

    #[Test]
    public function youtube_pattern_provides_its_hosts_and_distinguishes_url_shapes(): void
    {
        $configurator = new Configurator();
        $configurator->rendering->setEngine('PHP');
        $configurator->rendering->getEngine()->cacheDir = sys_get_temp_dir();
        $pattern = "!youtube\\.com/(?:watch.*?[?&]v=|(?:embed|live|shorts|v)/)(?<id>[-\\w]+)!\n!youtu\\.be/(?<id>[-\\w]+)!\n!youtube-nocookie\\.com/(?:embed|live|shorts|v)/(?<id>[-\\w]+)!";

        $this->assertSame(
            ['youtube.com', 'youtu.be', 'youtube-nocookie.com'],
            MediaRuleDefinition::hostsFromPatterns($pattern)
        );
        $this->assertCount(3, MediaRuleDefinition::patterns($pattern));
        $this->assertSame(['id'], MediaRuleDefinition::captures($pattern));

        $configurator->MediaEmbed->add('ffansbbcodetest', MediaRuleDefinition::siteConfig([
            'extract_pattern' => $pattern,
            'embed_url' => 'https://www.youtube-nocookie.com/embed/{id}',
            'aspect_ratio' => '16 / 9',
        ]));

        $components = $configurator->finalize();

        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ] as $url) {
            $html = $components['renderer']->render($components['parser']->parse($url));

            $this->assertStringContainsString('youtube-nocookie.com/embed/dQw4w9WgXcQ', $html, $url);
        }
    }

    #[Test]
    public function named_capture_list_is_unique_and_preserves_input_order(): void
    {
        $patterns = "!video\\.example\\.com/(?<id>[a-z0-9]+)/(?<page>\\d+)!\n!short\\.example\\.com/(?<id>[a-z0-9]+)!";

        $this->assertSame(['id', 'page'], MediaRuleDefinition::captures($patterns));
        $this->assertSame(['video.example.com', 'short.example.com'], MediaRuleDefinition::hostsFromPatterns($patterns));
    }

    #[Test]
    public function iframe_attributes_are_parsed_and_formatted_consistently(): void
    {
        $input = " allowfullscreen   scrolling = \"no\"   allow = 'autoplay; fullscreen' ";

        $this->assertSame(
            ['allowfullscreen' => '', 'scrolling' => 'no', 'allow' => 'autoplay; fullscreen'],
            MediaRuleDefinition::iframeAttributes($input)
        );
        $this->assertSame(
            "allowfullscreen scrolling='no' allow='autoplay; fullscreen'",
            MediaRuleDefinition::formatIframeAttributes($input)
        );
    }

    #[Test]
    public function iframe_boolean_attributes_accept_values(): void
    {
        $this->assertSame(
            ['allowfullscreen' => 'true'],
            MediaRuleDefinition::iframeAttributes("allowfullscreen='true'")
        );
        $this->assertSame(
            "allowfullscreen='true'",
            MediaRuleDefinition::formatIframeAttributes('allowfullscreen="true"')
        );
    }

    #[Test]
    public function iframe_value_attributes_cannot_be_written_without_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MediaRuleDefinition::iframeAttributes('scrolling');
    }

    #[Test]
    public function iframe_attribute_values_cannot_contain_formatter_expressions(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MediaRuleDefinition::iframeAttributes('allow="{@id}"');
    }

    #[Test]
    public function custom_bbcode_templates_receive_their_generated_class_and_styles(): void
    {
        $template = BbcodeRuleDefinition::template('notice', '<aside class="existing"><xsl:apply-templates/></aside>');

        $this->assertStringContainsString('class="existing BbcodeStudio-bbcode BbcodeStudio-bbcode-notice"', $template);
        $styles = BbcodeRuleDefinition::normalizeStyles('.title { color: red; } padding: 8px;');
        $this->assertStringContainsString('.BbcodeStudio-bbcode-notice', BbcodeRuleDefinition::scopedStyles('notice', $styles));
        $this->assertStringContainsString('.title', BbcodeRuleDefinition::scopedStyles('notice', $styles));
    }

    #[Test]
    public function notice_color_is_optional_and_defaults_to_blue(): void
    {
        $configurator = new Configurator();
        $configurator->rendering->setEngine('PHP');
        $configurator->rendering->getEngine()->cacheDir = sys_get_temp_dir();
        $configurator->BBCodes->addCustom(
            '[notice color={COLOR?}]{TEXT}[/notice]',
            BbcodeRuleDefinition::template(
                'notice',
                <<<'XML'
<aside>
  <xsl:choose>
    <xsl:when test="@color = 'red'"><xsl:attribute name="data-notice-color">red</xsl:attribute></xsl:when>
    <xsl:otherwise><xsl:attribute name="data-notice-color">blue</xsl:attribute></xsl:otherwise>
  </xsl:choose>
  <xsl:apply-templates/>
</aside>
XML
            )
        );

        $components = $configurator->finalize();
        $defaultHtml = $components['renderer']->render($components['parser']->parse('[notice]Important[/notice]'));
        $emptyHtml = $components['renderer']->render($components['parser']->parse('[notice color=]Important[/notice]'));
        $redHtml = $components['renderer']->render($components['parser']->parse('[notice color=red]Important[/notice]'));

        $this->assertStringContainsString('data-notice-color="blue"', $defaultHtml);
        $this->assertStringContainsString('data-notice-color="blue"', $emptyHtml);
        $this->assertStringContainsString('data-notice-color="red"', $redHtml);
    }

    #[Test]
    public function spoiler_bbcode_renders_a_collapsible_block_with_an_optional_title(): void
    {
        $configurator = new Configurator();
        $configurator->rendering->setEngine('PHP');
        $configurator->rendering->getEngine()->cacheDir = sys_get_temp_dir();
        $configurator->BBCodes->addCustom(
            '[spoiler title={SIMPLETEXT?}]{TEXT}[/spoiler]',
            BbcodeRuleDefinition::template(
                'spoiler',
                <<<'XML'
<details>
  <summary>
    <xsl:choose>
      <xsl:when test="string-length(normalize-space(@title)) &gt; 0"><xsl:value-of select="@title"/></xsl:when>
      <xsl:otherwise>Spoiler</xsl:otherwise>
    </xsl:choose>
  </summary>
  <div class="BbcodeStudio-spoiler-content"><xsl:apply-templates/></div>
</details>
XML
            )
        );

        $components = $configurator->finalize();
        $defaultHtml = $components['renderer']->render($components['parser']->parse('[spoiler]Hidden[/spoiler]'));
        $titledHtml = $components['renderer']->render($components['parser']->parse('[spoiler title=Ending]Hidden[/spoiler]'));

        $this->assertStringContainsString('<details', $defaultHtml);
        $this->assertStringContainsString('<summary>Spoiler</summary>', $defaultHtml);
        $this->assertStringContainsString('BbcodeStudio-bbcode-spoiler', $defaultHtml);
        $this->assertStringContainsString('BbcodeStudio-spoiler-content', $defaultHtml);
        $this->assertStringContainsString('<summary>Ending</summary>', $titledHtml);
        $this->assertStringContainsString('Hidden', $titledHtml);
    }

    #[Test]
    public function kdb_bbcode_renders_text_as_a_keyboard_key(): void
    {
        $configurator = new Configurator();
        $configurator->rendering->setEngine('PHP');
        $configurator->rendering->getEngine()->cacheDir = sys_get_temp_dir();
        $configurator->BBCodes->addCustom(
            '[kdb]{TEXT}[/kdb]',
            BbcodeRuleDefinition::template('kdb', '<kbd><xsl:apply-templates/></kbd>')
        );

        $components = $configurator->finalize();
        $html = $components['renderer']->render($components['parser']->parse('[kdb]Ctrl[/kdb]'));

        $this->assertStringContainsString('<kbd', $html);
        $this->assertStringContainsString('BbcodeStudio-bbcode-kdb', $html);
        $this->assertStringContainsString('Ctrl', $html);
        $this->assertStringContainsString('</kbd>', $html);
    }

    #[Test]
    public function custom_bbcode_styles_reject_less_syntax(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BbcodeRuleDefinition::normalizeStyles('@import "theme.less";');
    }

    #[Test]
    public function formatter_rejects_script_templates(): void
    {
        $this->expectException(\RuntimeException::class);

        $configurator = new Configurator();
        $configurator->BBCodes->addCustom('[unsafe]{TEXT}[/unsafe]', '<script><xsl:apply-templates/></script>');
    }
}
