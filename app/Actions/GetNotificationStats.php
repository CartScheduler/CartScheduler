<?php

namespace App\Actions;

use App\Data\NotificationStatsData;
use App\Enums\NotificationStatus;
use App\Models\NotificationLog;

class GetNotificationStats
{
    public function execute(): NotificationStatsData
    {
        $now = now();
        $sent = NotificationLog::query()
            ->where('status', NotificationStatus::Sent)
            ->where('sent_at', '>=', $now->copy()->subDay())
            ->selectRaw('SUM(CASE WHEN sent_at >= ? THEN 1 ELSE 0 END) AS last_10_minutes', [$now->copy()->subMinutes(10)])
            ->selectRaw('SUM(CASE WHEN sent_at >= ? THEN 1 ELSE 0 END) AS last_hour', [$now->copy()->subHour()])
            ->selectRaw('COUNT(*) AS last_24_hours')
            ->toBase()
            ->first();

        return new NotificationStatsData(
            sent_last_10_minutes: (int) ($sent->last_10_minutes ?? 0),
            sent_last_hour: (int) ($sent->last_hour ?? 0),
            sent_last_24_hours: (int) ($sent->last_24_hours ?? 0),
            failed_last_7_days: NotificationLog::query()
                ->failedOrStalled()
                ->where('updated_at', '>=', $now->copy()->subDays(7))
                ->count(),
            queued: NotificationLog::query()->whereIn('status', [NotificationStatus::Queued, NotificationStatus::Retrying])->count(),
        );
    }
}
