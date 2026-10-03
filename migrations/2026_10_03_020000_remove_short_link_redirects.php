<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => static function (Builder $schema): void {
        if ($schema->hasTable('ffans_bbcode_studio_rules') && $schema->hasColumn('ffans_bbcode_studio_rules', 'redirect_pattern')) {
            $schema->table('ffans_bbcode_studio_rules', static function (Blueprint $table): void {
                $table->dropColumn('redirect_pattern');
            });
        }
    },
    'down' => static function (Builder $schema): void {
        // Restore only the schema for older code; deleted redirect settings cannot be recovered.
        if ($schema->hasTable('ffans_bbcode_studio_rules') && ! $schema->hasColumn('ffans_bbcode_studio_rules', 'redirect_pattern')) {
            $schema->table('ffans_bbcode_studio_rules', static function (Blueprint $table): void {
                $table->text('redirect_pattern')->nullable();
            });
        }
    },
];
