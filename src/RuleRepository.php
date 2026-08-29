<?php

namespace FFans\BbcodeStudio;

use Illuminate\Database\ConnectionInterface;

class RuleRepository
{
    public function __construct(protected ConnectionInterface $connection)
    {
    }

    public function tableExists(): bool
    {
        return $this->connection->getSchemaBuilder()->hasTable('ffans_bbcode_studio_rules');
    }

    /** @return list<BbcodeRule> */
    public function all(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return BbcodeRule::query()->orderByDesc('sort_order')->orderBy('id')->get()->all();
    }

    /** @return list<BbcodeRule> */
    public function enabled(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return BbcodeRule::query()
            ->where('enabled', true)
            ->orderByDesc('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** @return list<BbcodeRule> */
    public function toolbarRules(): array
    {
        if (! $this->tableExists()) {
            return [];
        }

        return BbcodeRule::query()
            ->where('enabled', true)
            ->where('toolbar_enabled', true)
            ->orderByDesc('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }
}
