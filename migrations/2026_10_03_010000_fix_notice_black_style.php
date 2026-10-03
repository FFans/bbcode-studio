<?php

use Illuminate\Database\Schema\Builder;

// Freeze the old default so future default changes cannot alter this migration.
$oldStyles = <<<'LESS'
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
LESS;
$newStyles = str_replace('data-notice-color="balck"', 'data-notice-color="black"', $oldStyles);

$migrate = static function (Builder $schema, string $fromStyles, string $fromSelector, string $toSelector): void {
    if (! $schema->hasTable('ffans_bbcode_studio_rules')) {
        return;
    }

    $connection = $schema->getConnection();
    $connection->transaction(static function () use ($connection, $fromStyles, $fromSelector, $toSelector): void {
        $table = $connection->table('ffans_bbcode_studio_rules');
        $rules = (clone $table)->where('builtin_key', 'notice')->where('rule_type', 'bbcode')->lockForUpdate()->get();

        foreach ($rules as $rule) {
            // Compare exactly in PHP, including on case-insensitive database collations.
            if ($rule->builtin_key !== 'notice' || $rule->rule_type !== 'bbcode'
                || trim(str_replace("\r\n", "\n", $rule->css_declarations ?? '')) !== $fromStyles) {
                continue;
            }

            (clone $table)->where('id', $rule->id)->update([
                'css_declarations' => str_replace($fromSelector, $toSelector, $rule->css_declarations),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    });
};

return [
    'up' => static function (Builder $schema) use ($migrate, $oldStyles): void {
        $migrate($schema, $oldStyles, 'data-notice-color="balck"', 'data-notice-color="black"');
    },
    'down' => static function (Builder $schema) use ($migrate, $newStyles): void {
        $migrate($schema, $newStyles, 'data-notice-color="black"', 'data-notice-color="balck"');
    },
];
