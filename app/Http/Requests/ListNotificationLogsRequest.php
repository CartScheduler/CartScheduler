<?php

namespace App\Http\Requests;

use App\Enums\NotificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationLogsRequest extends FormRequest
{
    /**
     * Quick time filters, in minutes.
     */
    public const array PERIODS = [
        '10m' => 10,
        '1h' => 60,
        '24h' => 1440,
        '7d' => 10080,
        '30d' => 43200,
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(NotificationStatus::class)],
            'type' => ['nullable', 'string', 'max:100'],
            'channel' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:255'],
            'period' => ['nullable', Rule::in(array_keys(self::PERIODS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'unresolved' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array{status: ?string, type: ?string, channel: ?string, search: ?string, period: ?string, from: ?string, to: ?string, unresolved: bool}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'status' => $validated['status'] ?? null,
            'type' => $validated['type'] ?? null,
            'channel' => $validated['channel'] ?? null,
            'search' => $validated['search'] ?? null,
            'period' => $validated['period'] ?? null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'unresolved' => (bool) ($validated['unresolved'] ?? false),
        ];
    }
}
