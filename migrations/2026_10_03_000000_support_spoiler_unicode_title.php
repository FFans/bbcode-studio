<?php

use Illuminate\Database\Schema\Builder;

$oldUsage = '[spoiler title={SIMPLETEXT?}]{TEXT}[/spoiler]';
$newUsage = '[spoiler title={TEXT?}]{TEXT}[/spoiler]';
$oldTitle = '<xsl:otherwise>Spoiler</xsl:otherwise>';
$newTitle = '<xsl:otherwise><xsl:value-of select="$L_BBCODE_STUDIO_SPOILER"/></xsl:otherwise>';
$oldTemplate = <<<'XML'
<details>
  <summary>
    <xsl:choose>
      <xsl:when test="string-length(normalize-space(@title)) &gt; 0"><xsl:value-of select="@title"/></xsl:when>
      <xsl:otherwise>Spoiler</xsl:otherwise>
    </xsl:choose>
  </summary>
  <div class="BbcodeStudio-spoiler-content"><xsl:apply-templates/></div>
</details>
XML;
$newTemplate = str_replace($oldTitle, $newTitle, $oldTemplate);
$oldLabels = [
    'name' => 'Spoiler',
    'button_label' => 'Spoiler',
    'description' => 'Hide content inside a collapsible spoiler block.',
];
$newLabels = [
    'name' => 'Collapser',
    'button_label' => 'Collapser',
    'description' => 'Display content in a collapsible block.',
];

$migrate = static function (
    Builder $schema,
    string $fromUsage,
    string $toUsage,
    string $fromTemplate,
    string $fromTitle,
    string $toTitle,
    array $fromLabels,
    array $toLabels
): void {
    if (! $schema->hasTable('ffans_bbcode_studio_rules')) {
        return;
    }

    $connection = $schema->getConnection();
    $connection->transaction(static function () use ($connection, $fromUsage, $toUsage, $fromTemplate, $fromTitle, $toTitle, $fromLabels, $toLabels): void {
        $table = $connection->table('ffans_bbcode_studio_rules');
        $rules = (clone $table)
            ->where('builtin_key', 'spoiler')
            ->where('rule_type', 'bbcode')
            ->lockForUpdate()
            ->get();

        foreach ($rules as $rule) {
            // Check in PHP too: database collations may compare strings case-insensitively.
            if ($rule->builtin_key !== 'spoiler' || $rule->rule_type !== 'bbcode') {
                continue;
            }

            $changes = [];
            foreach ($fromLabels as $field => $value) {
                if ($rule->{$field} === $value) {
                    $changes[$field] = $toLabels[$field];
                }
            }

            if ($rule->usage === $fromUsage) {
                $changes['usage'] = $toUsage;
            }

            if (trim(str_replace("\r\n", "\n", $rule->template)) === $fromTemplate) {
                // Preserve the stored line endings and surrounding whitespace.
                $changes['template'] = str_replace($fromTitle, $toTitle, $rule->template);
            }

            if ($changes !== []) {
                $changes['updated_at'] = date('Y-m-d H:i:s');
                (clone $table)->where('id', $rule->id)->update($changes);
            }
        }
    });
};

return [
    'up' => static function (Builder $schema) use ($migrate, $oldUsage, $newUsage, $oldTemplate, $oldTitle, $newTitle, $oldLabels, $newLabels): void {
        $migrate($schema, $oldUsage, $newUsage, $oldTemplate, $oldTitle, $newTitle, $oldLabels, $newLabels);
    },
    'down' => static function (Builder $schema) use ($migrate, $oldUsage, $newUsage, $newTemplate, $oldTitle, $newTitle, $oldLabels, $newLabels): void {
        // Restore known defaults only; this does not rewrite posts or restore a database snapshot.
        $migrate($schema, $newUsage, $oldUsage, $newTemplate, $newTitle, $oldTitle, $newLabels, $oldLabels);
    },
];
