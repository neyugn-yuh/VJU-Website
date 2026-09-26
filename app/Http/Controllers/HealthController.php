<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/** GET /health — liveness + dependencies. Reports ok/fail only, never configuration values. */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'app' => fn () => true,
            'database' => fn () => DB::select('select 1 as ok')[0]->ok == 1,
            'cache' => function () {
                $key = 'health:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $ok = Cache::get($key) === 'ok';
                Cache::forget($key);

                return $ok;
            },
            'storage' => function () {
                $disk = Storage::disk(config('cms.media.disk'));
                $path = '.health-'.Str::random(8);
                $disk->put($path, 'ok');
                $ok = $disk->get($path) === 'ok';
                $disk->delete($path);

                return $ok;
            },
            'queue' => fn () => config('queue.default') !== 'redis' || Redis::connection(config('queue.connections.redis.connection', 'default'))->ping() !== false,
        ];

        $results = [];
        foreach ($checks as $name => $check) {
            try {
                $results[$name] = $check() ? 'ok' : 'fail';
            } catch (Throwable) {
                $results[$name] = 'fail';
            }
        }

        $healthy = ! in_array('fail', $results, true);

        return response()->json(['status' => $healthy ? 'ok' : 'fail', 'checks' => $results], $healthy ? 200 : 503)
            ->header('Cache-Control', 'no-store');
    }
}
