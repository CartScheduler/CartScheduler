<?php

namespace App\Http\Controllers;

use App\Actions\GetNotificationStats;
use App\Data\NotificationLogData;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Http\Controllers\Concerns\EnsuresAllowedSettingsUser;
use App\Http\Requests\ListNotificationLogsRequest;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ShowNotificationLogsController extends Controller
{
    use EnsuresAllowedSettingsUser;

    public function __invoke(ListNotificationLogsRequest $request, GetNotificationStats $getNotificationStats): Response
    {
        $this->ensureAllowedSettingsUser($request);

        $filters = $request->filters();

        $logs = $this->filteredQuery($filters)
            ->with('notifiable')
            ->latest('updated_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (NotificationLog $log) => NotificationLogData::fromModel($log));

        return Inertia::render('Admin/Notifications/List', [
            'logs' => $logs,
            'filters' => $filters,
            'stats' => $getNotificationStats->execute(),
            'options' => [
                'statuses' => collect(NotificationStatus::cases())
                    ->map(fn (NotificationStatus $status) => ['value' => $status->value, 'label' => $status->label()])
                    ->values(),
                'types' => NotificationLog::query()->distinct()->orderBy('type')->pluck('type')
                    ->merge(collect(NotificationType::cases())->map->value)
                    ->unique()
                    ->sort()
                    ->map(fn (string $type) => ['value' => $type, 'label' => NotificationType::labelFor($type)])
                    ->values(),
                'channels' => NotificationLog::query()->distinct()->orderBy('channel')->pluck('channel')
                    ->push('mail')
                    ->unique()
                    ->map(fn (string $channel) => ['value' => $channel, 'label' => $channel === 'mail' ? 'Email' : ucfirst($channel)])
                    ->values(),
                'periods' => [
                    ['value' => '10m', 'label' => 'Last 10 minutes'],
                    ['value' => '1h', 'label' => 'Last hour'],
                    ['value' => '24h', 'label' => 'Last 24 hours'],
                    ['value' => '7d', 'label' => 'Last 7 days'],
                    ['value' => '30d', 'label' => 'Last 30 days'],
                ],
            ],
        ]);
    }

    /**
     * @param  array{status: ?string, type: ?string, channel: ?string, search: ?string, period: ?string, from: ?string, to: ?string}  $filters
     * @return Builder<NotificationLog>
     */
    private function filteredQuery(array $filters): Builder
    {
        $status = NotificationStatus::tryFrom((string) $filters['status']);

        return NotificationLog::query()
            ->when($status === NotificationStatus::Failed, fn (Builder $query) => $query->failedOrStalled())
            ->when($status && $status !== NotificationStatus::Failed, fn (Builder $query) => $query->where('status', $status))
            ->when($filters['type'], fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['channel'], fn (Builder $query, string $channel) => $query->where('channel', $channel))
            ->when($filters['search'], fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('recipient', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")
                ->orWhereHasMorph('notifiable', [User::class], fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))))
            ->when($filters['period'], fn (Builder $query, string $period) => $query
                ->where('updated_at', '>=', now()->subMinutes(ListNotificationLogsRequest::PERIODS[$period])))
            ->when($filters['from'], fn (Builder $query, string $from) => $query->where('updated_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'], fn (Builder $query, string $to) => $query->where('updated_at', '<=', Carbon::parse($to)->endOfDay()));
    }
}
