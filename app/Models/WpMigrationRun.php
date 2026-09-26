<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WpMigrationRun extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['options' => 'array', 'stats' => 'array', 'dry_run' => 'boolean', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
