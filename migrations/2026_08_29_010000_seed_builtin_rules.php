<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema): void {
        if (! $schema->hasTable('ffans_bbcode_studio_rules')) {
            return;
        }

        $bbcode = static function (
            string $key,
            string $name,
            string $description,
            string $usage,
            string $template,
            string $styles,
            string $icon,
            string $buttonLabel,
            string $example,
            array $exampleAttributes,
            int $sortOrder
        ): array {
            return [
                'builtin_key' => $key,
                'name' => $name,
                'tag' => $key,
                'rule_type' => 'bbcode',
                'description' => $description,
                'usage' => $usage,
                'template' => $template,
                'css_declarations' => $styles,
                'icon' => $icon,
                'button_label' => $buttonLabel,
                'example' => $example,
                'example_attributes' => json_encode($exampleAttributes, JSON_THROW_ON_ERROR),
                'enabled' => false,
                'toolbar_enabled' => true,
                'extract_pattern' => '',
                'redirect_pattern' => null,
                'embed_url' => '',
                'iframe_attributes' => '',
                'aspect_ratio' => '16 / 9',
                'sort_order' => $sortOrder,
            ];
        };

        $media = static function (
            string $key,
            string $name,
            string $description,
            string $icon,
            string $buttonLabel,
            string $example,
            string $extractPattern,
            ?string $redirectPattern,
            string $embedUrl,
            string $iframeAttributes,
            string $aspectRatio,
            int $sortOrder
        ): array {
            return [
                'builtin_key' => $key,
                'name' => $name,
                'tag' => $key,
                'rule_type' => 'media',
                'description' => $description,
                'usage' => '['.$key.']{URL}[/'.$key.']',
                'template' => '<xsl:apply-templates/>',
                'css_declarations' => '',
                'icon' => $icon,
                'button_label' => $buttonLabel,
                'example' => $example,
                'example_attributes' => json_encode([], JSON_THROW_ON_ERROR),
                'enabled' => false,
                'toolbar_enabled' => true,
                'extract_pattern' => $extractPattern,
                'redirect_pattern' => $redirectPattern,
                'embed_url' => $embedUrl,
                'iframe_attributes' => $iframeAttributes,
                'aspect_ratio' => $aspectRatio,
                'sort_order' => $sortOrder,
            ];
        };

        $rules = [
            $media(
                'bilibili',
                'Bilibili',
                'Embed a Bilibili video from its BV link.',
                'fab fa-bilibili',
                'Bilibili',
                'https://www.bilibili.com/video/BV1GJ411x7h7',
                '!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)!',
                '!b23\.tv/[a-zA-Z0-9]+!',
                '//player.bilibili.com/player.html?bvid={id}&p=1&autoplay=false',
                "scrolling='no' allowfullscreen",
                '16 / 9',
                50
            ),
            $media(
                'youtube',
                'YouTube',
                'Embed a YouTube video from its watch URL.',
                'fab fa-youtube',
                'YouTube',
                'https://youtu.be/A8LRxIANzQs',
                "!youtube\\.com/(?:watch.*?[?&]v=|(?:embed|live|shorts|v)/)(?<id>[-\\w]+)!\n!youtu\\.be/(?<id>[-\\w]+)!\n!youtube-nocookie\\.com/(?:embed|live|shorts|v)/(?<id>[-\\w]+)!",
                null,
                'https://www.youtube-nocookie.com/embed/{id}',
                "allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share' referrerpolicy='strict-origin-when-cross-origin' allowfullscreen='true'",
                '16 / 9',
                40
            ),
            $bbcode(
                'notice',
                'Notice',
                'Colored notice block.',
                '[notice color={CHOICE=red,yellow,green,black?}]{TEXT}[/notice]',
                <<<'XML'
<aside>
  <xsl:choose>
    <xsl:when test="@color = 'red'"><xsl:attribute name="data-notice-color">red</xsl:attribute></xsl:when>
    <xsl:when test="@color = 'yellow'"><xsl:attribute name="data-notice-color">yellow</xsl:attribute></xsl:when>
    <xsl:when test="@color = 'green'"><xsl:attribute name="data-notice-color">green</xsl:attribute></xsl:when>
    <xsl:when test="@color = 'black'"><xsl:attribute name="data-notice-color">black</xsl:attribute></xsl:when>
    <xsl:otherwise><xsl:attribute name="data-notice-color">blue</xsl:attribute></xsl:otherwise>
  </xsl:choose>
  <xsl:apply-templates/>
</aside>
XML,
                <<<'LESS'
border-left: 4px solid #3b82f6;
background: #eff6ff;
padding: 12px 16px;
border-radius: 4px;

p:last-child, ul:last-child, ol:last-child {
    margin-bottom: 0;
}

&[data-notice-color="red"] {
  border-color: #dc2626;
  background: #fef2f2;
}

&[data-notice-color="yellow"] {
  border-color: #d97706;
  background: #fffbeb;
}

&[data-notice-color="green"] {
  border-color: #16a34a;
  background: #f0fdf4;
}

&[data-notice-color="balck"] {
  border-color: #7b7b7b;
  background: #e3e3e3;
}
LESS,
                'fas fa-circle-info',
                'Notice',
                'Important',
                ['color' => null],
                80
            ),
            $media(
                'netease',
                'NetEase Cloud Music',
                'Embed a NetEase Cloud Music song from its share URL.',
                'fas fa-music',
                'NetEase Cloud Music',
                'https://music.163.com/#/song?id=2073467158',
                '!music\.163\.com/(?:#/)?song\?(?:[^#\s&]*&)*id=(?<id>\d+)!',
                null,
                '//music.163.com/outchain/player?type=2&id={id}&auto=1&height=66',
                "scrolling='no' height='86'",
                '',
                10
            ),
            $media(
                'spotify',
                'Spotify',
                'Embed Spotify tracks, albums, playlists, artists, shows, and episodes.',
                'fab fa-spotify',
                'Spotify',
                'https://open.spotify.com/track/11dFghVXANMlKmJXsNCbNl',
                '!open\.spotify\.com/(?<type>track|album|playlist|artist|show|episode)/(?<id>[a-zA-Z0-9]+)!',
                null,
                'https://open.spotify.com/embed/{type}/{id}',
                "allow='autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture' height='152'",
                '',
                20
            ),
            $media(
                'vimeo',
                'Vimeo',
                'Embed Vimeo videos from common Vimeo URL formats.',
                'fab fa-vimeo-v',
                'Vimeo',
                'https://vimeo.com/76979871',
                "!vimeo\\.com/(?:channels/[a-zA-Z0-9_-]+/|groups/[a-zA-Z0-9_-]+/videos/|album/\\d+/video/|video/)?(?<id>\\d+)!\n!player\\.vimeo\\.com/video/(?<id>\\d+)!",
                null,
                'https://player.vimeo.com/video/{id}',
                "allowfullscreen allow='autoplay; fullscreen; picture-in-picture'",
                '16 / 9',
                30
            ),
            $bbcode(
                'spoiler',
                'Spoiler',
                'Hide content inside a collapsible spoiler block.',
                '[spoiler title={SIMPLETEXT?}]{TEXT}[/spoiler]',
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
XML,
                <<<'LESS'
border: 1px solid var(--control-bg);
border-radius: var(--border-radius);
overflow: hidden;

> summary {
  cursor: pointer;
  padding: 10px 12px;
  background: var(--control-bg);
  font-weight: 600;
}

> .BbcodeStudio-spoiler-content {
  padding: 12px;
}
LESS,
                'fas fa-eye-slash',
                'Spoiler',
                'Hidden content',
                ['title' => null],
                60
            ),
            $bbcode(
                'kdb',
                'KDB',
                'Display text as an inline keyboard key.',
                '[kdb]{TEXT}[/kdb]',
                '<kbd><xsl:apply-templates/></kbd>',
                <<<'LESS'
display: inline-block;
padding: 2px 6px;
border: 1px solid var(--control-bg);
border-bottom-width: 2px;
border-radius: 4px;
background: var(--body-bg);
box-shadow: 0 1px 0 var(--control-bg);
font-family: var(--code-font, monospace);
font-size: 0.875em;
line-height: 1.4;
white-space: nowrap;
LESS,
                'fas fa-keyboard',
                'KDB',
                'Ctrl',
                [],
                70
            ),
        ];

        $db = $schema->getConnection();
        $now = date('Y-m-d H:i:s');

        foreach ($rules as $rule) {
            if ($db->table('ffans_bbcode_studio_rules')
                ->where('builtin_key', $rule['builtin_key'])
                ->orWhere('tag', $rule['tag'])
                ->exists()) {
                continue;
            }

            $db->table('ffans_bbcode_studio_rules')->insert([
                ...$rule,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    },

    'down' => function (Builder $schema): void {
        if (! $schema->hasTable('ffans_bbcode_studio_rules')) {
            return;
        }

        $schema->getConnection()
            ->table('ffans_bbcode_studio_rules')
            ->whereIn('builtin_key', [
                'notice',
                'kdb',
                'spoiler',
                'bilibili',
                'youtube',
                'vimeo',
                'spotify',
                'netease',
            ])
            ->delete();
    },
];
