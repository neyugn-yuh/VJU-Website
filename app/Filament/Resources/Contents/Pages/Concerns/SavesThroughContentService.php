<?php

namespace App\Filament\Resources\Contents\Pages\Concerns;

use Closure;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

trait SavesThroughContentService
{
    /** Maps domain errors onto the form ("status" -> "data.status") or a notification. */
    protected function throughService(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(
                collect($e->errors())->mapWithKeys(fn ($messages, $key) => ["data.{$key}" => $messages])->all()
            );
        } catch (AuthorizationException $e) {
            Notification::make()->danger()->title('Not allowed')->body($e->getMessage())->send();

            throw new Halt;
        }
    }
}
