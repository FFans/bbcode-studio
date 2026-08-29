<?php

namespace FFans\BbcodeStudio;

use Flarum\Database\AbstractModel;

class BbcodeRule extends AbstractModel
{
    protected $table = 'ffans_bbcode_studio_rules';

    protected $casts = [
        'builtin_key' => 'string',
        'example_attributes' => 'array',
        'enabled' => 'boolean',
        'toolbar_enabled' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
