<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema): void {
        if (! $schema->hasTable('ffans_bbcode_studio_rules')) {
            return;
        }

        $schema->getConnection()
            ->table('ffans_bbcode_studio_rules')
            ->where('builtin_key', 'bilibili')
            ->where('extract_pattern', '!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)(?:\?(?:[^#\s&]*&)*p=(?<p>[1-9]\d*)(?=[&#\s]|$))?!')
            ->where('embed_url', '//player.bilibili.com/player.html?bvid={id}&p={p}&autoplay=false')
            ->update([
                'extract_pattern' => '!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)/?(?:\?(?:[^#\s&]*&)*p=(?<p>[1-9]\d*)(?=[&#\s]|$))?!',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    },

    'down' => function (Builder $schema): void {
        if (! $schema->hasTable('ffans_bbcode_studio_rules')) {
            return;
        }

        $schema->getConnection()
            ->table('ffans_bbcode_studio_rules')
            ->where('builtin_key', 'bilibili')
            ->where('extract_pattern', '!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)/?(?:\?(?:[^#\s&]*&)*p=(?<p>[1-9]\d*)(?=[&#\s]|$))?!')
            ->where('embed_url', '//player.bilibili.com/player.html?bvid={id}&p={p}&autoplay=false')
            ->update([
                'extract_pattern' => '!bilibili\.com/video/(?<id>BV[a-zA-Z0-9]+)(?:\?(?:[^#\s&]*&)*p=(?<p>[1-9]\d*)(?=[&#\s]|$))?!',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    },
];
