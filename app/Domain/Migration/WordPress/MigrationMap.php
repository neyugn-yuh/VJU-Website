<?php

namespace App\Domain\Migration\WordPress;

use App\Models\WpMigrationMap;

/** Source identity (source_type + source_id) -> target record. The basis of idempotent re-runs. */
class MigrationMap
{
    /** @var array<string, ?WpMigrationMap> */
    private array $cache = [];

    public function find(string $type, int|string $id): ?WpMigrationMap
    {
        $key = "{$type}:{$id}";

        return $this->cache[$key] ??= WpMigrationMap::where('source_type', $type)->where('source_id', (string) $id)->first();
    }

    public function targetId(string $type, int|string|null $id): ?int
    {
        if (! $id) {
            return null;
        }
        $row = $this->find($type, $id);

        return $row && $row->status === 'success' ? $row->target_id : null;
    }

    /** First mapped target among a Polylang translation group. */
    public function groupTarget(array $types, array $ids): ?int
    {
        foreach ($types as $type) {
            foreach ($ids as $id) {
                if ($target = $this->targetId($type, $id)) {
                    return $target;
                }
            }
        }

        return null;
    }

    public function record(string $type, int|string $id, array $attributes): WpMigrationMap
    {
        $row = WpMigrationMap::updateOrCreate(
            ['source_type' => $type, 'source_id' => (string) $id],
            [...$attributes, 'last_attempt_at' => now()],
        );

        return $this->cache["{$type}:{$id}"] = $row;
    }

    public function forget(): void
    {
        $this->cache = [];
    }
}
