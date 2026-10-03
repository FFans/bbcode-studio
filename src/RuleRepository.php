<?php

namespace FFans\BbcodeStudio;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;

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
        return $this->get(BbcodeRule::query());
    }

    /** @return list<BbcodeRule> */
    public function enabled(): array
    {
        return $this->get(BbcodeRule::query()->where('enabled', true));
    }

    /** @return list<BbcodeRule> */
    public function toolbarRules(): array
    {
        return $this->get(BbcodeRule::query()
            ->where('enabled', true)
            ->where('toolbar_enabled', true));
    }

    /** @return list<BbcodeRule> */
    private function get(Builder $query): array
    {
        try {
            return $query->orderByDesc('sort_order')->orderBy('id')->get()->all();
        } catch (QueryException $exception) {
            // Formatter and asset configuration can run before extension migrations.
            // Only a missing rules table is optional; other database failures must surface.
            $info = $exception->errorInfo ?? [];
            $missingTable = match ($this->connection->getDriverName()) {
                'mysql', 'mariadb' => ($info[0] ?? null) === '42S02' && (int)($info[1] ?? 0) === 1146,
                'pgsql' => ($info[0] ?? null) === '42P01',
                'sqlite' => ($info[0] ?? null) === 'HY000'
                    && (int)($info[1] ?? 0) === 1
                    && ($info[2] ?? '') === 'no such table: ' . $this->connection->getTablePrefix() . 'ffans_bbcode_studio_rules',
                default => false,
            };

            if (!$missingTable) {
                throw $exception;
            }

            return [];
        }
    }
}
