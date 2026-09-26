<?php

use App\Domain\Content\ContentService;
use App\Domain\Content\ViewCounter;
use App\Domain\User\Permissions;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('cms:publish-scheduled', function (ContentService $service) {
    $this->info('Published '.$service->publishDue().' scheduled item(s).');
})->purpose('Publish scheduled content whose time has come (idempotent)');

Artisan::command('cms:flush-views', function (ViewCounter $counter) {
    $this->info('Flushed '.$counter->flush().' view(s).');
})->purpose('Aggregate buffered page views into content_views_daily');

Artisan::command('cms:user {email} {--name=} {--role=Admin} {--password=}', function () {
    $role = $this->option('role');
    if (! array_key_exists($role, Permissions::matrix())) {
        $this->error('Unknown role. Use one of: '.implode(', ', array_keys(Permissions::matrix())));

        return 1;
    }

    $user = User::firstOrNew(['email' => strtolower($this->argument('email'))]);
    $user->name = $this->option('name') ?: ($user->name ?: $this->argument('email'));
    if ($this->option('password')) {
        $user->password = $this->option('password');
    }
    $user->forceFill(['is_active' => true])->save();
    $user->syncRoles([$role]);

    $this->info("User {$user->email} now has role {$role}.");
})->purpose('Create or update a CMS user and set their role (bootstrap the first Admin)');

Schedule::command('cms:publish-scheduled')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('cms:flush-views')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily();
