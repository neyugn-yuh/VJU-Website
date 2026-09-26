<?php

namespace App\Domain\Audit;

use Illuminate\Database\Eloquent\Model;

/**
 * Writes create/update/delete/restore audit entries for a model.
 * Models may narrow the events with `protected array $auditEvents = [...]`.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        $enabled = fn (Model $m, string $event) => ! property_exists($m, 'auditEvents') || in_array($event, $m->auditEvents, true);

        static::created(function (Model $model) use ($enabled) {
            if ($enabled($model, 'created')) {
                Audit::record('create', $model, null, $model->getAttributes());
            }
        });

        static::updated(function (Model $model) use ($enabled) {
            $changes = array_diff_key($model->getChanges(), array_flip(['updated_at', 'remember_token', 'last_login_at']));
            if ($changes && $enabled($model, 'updated')) {
                Audit::record('update', $model, array_intersect_key($model->getOriginal(), $changes), $changes);
            }
        });

        static::deleted(function (Model $model) use ($enabled) {
            $force = ! method_exists($model, 'isForceDeleting') || $model->isForceDeleting();
            if ($enabled($model, 'deleted')) {
                Audit::record($force ? 'delete' : 'trash', $model, $model->getOriginal(), null);
            }
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) use ($enabled) {
                if ($enabled($model, 'restored')) {
                    Audit::record('restore', $model);
                }
            });
        }
    }
}
