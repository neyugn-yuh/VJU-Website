<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WpMigrationMap extends Model
{
    protected $table = 'wp_migration_map';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['warnings' => 'array', 'last_attempt_at' => 'datetime'];
    }
}
