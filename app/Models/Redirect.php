<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use App\Domain\SEO\RedirectResolver;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_hit_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(fn (Redirect $r) => $r->old_url = RedirectResolver::normalize($r->old_url));
    }
}
