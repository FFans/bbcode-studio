<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema): void {
        if ($schema->hasTable('ffans_bbcode_studio_rules')) {
            return;
        }

        $schema->create('ffans_bbcode_studio_rules', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('builtin_key', 32)->nullable();
            $table->string('name', 100);
            $table->string('tag', 32)->unique();
            $table->string('rule_type', 16)->default('bbcode')->index();
            $table->string('description', 500)->default('');
            $table->text('usage');
            $table->text('template');
            $table->text('css_declarations')->nullable();
            $table->string('icon', 100)->default('fas fa-code');
            $table->string('button_label', 100)->default('');
            $table->text('example')->nullable();
            $table->text('example_attributes')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('toolbar_enabled')->default(true);
            $table->text('extract_pattern')->nullable();
            $table->text('redirect_pattern')->nullable();
            $table->text('embed_url')->nullable();
            $table->text('iframe_attributes')->nullable();
            $table->string('aspect_ratio', 32)->default('16 / 9');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['enabled', 'toolbar_enabled']);
            $table->index('sort_order');
        });
    },

    'down' => function (Builder $schema): void {
        $schema->dropIfExists('ffans_bbcode_studio_rules');
    },
];
