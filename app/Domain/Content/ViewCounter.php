<?php

namespace App\Domain\Content;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Page views without a row per request: HINCRBY into a per-day Redis hash, aggregated into
 * content_views_daily by `cms:flush-views` (scheduled every 5 minutes).
 */
class ViewCounter
{
    private const PREFIX = 'cms:views:';

    public function record(int $contentId): void
    {
        try {
            Redis::hincrby(self::PREFIX.now()->toDateString(), (string) $contentId, 1);
        } catch (\Throwable $e) {
            report($e); // analytics must never break page rendering
        }
    }

    /** @return int number of views flushed */
    public function flush(): int
    {
        $total = 0;

        foreach (Redis::keys(self::PREFIX.'*') as $key) {
            // Redis::keys returns keys including the connection prefix.
            $key = substr($key, strpos($key, self::PREFIX));
            if (str_ends_with($key, ':processing')) {
                $processing = $key;
                $date = substr($key, strlen(self::PREFIX), 10);
            } else {
                $date = substr($key, strlen(self::PREFIX));
                $processing = $key.':processing';
                if (Redis::exists($processing) || ! Redis::rename($key, $processing)) {
                    continue;
                }
            }

            $counts = Redis::hgetall($processing);
            DB::transaction(function () use ($counts, $date, &$total) {
                $existing = DB::table('contents')->whereIn('id', array_keys($counts))->pluck('id')->flip();
                foreach ($counts as $id => $views) {
                    if (! isset($existing[$id])) {
                        continue;
                    }
                    DB::statement(
                        'INSERT INTO content_views_daily (content_id, date, views) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE views = views + VALUES(views)',
                        [(int) $id, $date, (int) $views]
                    );
                    DB::table('contents')->where('id', $id)->increment('view_count', (int) $views);
                    $total += (int) $views;
                }
            });
            Redis::del($processing);
        }

        return $total;
    }
}
