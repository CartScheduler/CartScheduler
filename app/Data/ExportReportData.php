<?php

namespace App\Data;

use App\Models\Report;
use Spatie\LaravelData\Data;

class ExportReportData extends Data
{
    public function __construct(
        public int $id,
        public ?string $shift_date,
        public ?int $placements_count,
        public ?int $videos_count,
        public ?int $requests_count,
        public ?string $comments,
        public int $shift_was_cancelled,
        public ?string $tags,
        public ?int $metadata_shift_id,
        public ?string $metadata_shift_time,
        public ?int $metadata_location_id,
        public ?string $metadata_location_name,
        public ?int $metadata_submitted_by_id,
        public ?string $metadata_submitted_by_name,
        public ?string $metadata_submitted_by_email,
        public ?string $metadata_submitted_by_phone,
        public ?string $metadata_associates,
    ) {
    }

    public static function fromReport(Report $report): self
    {
        $data = ReportsData::from($report);
        $metadata = $data->metadata;

        return new self(
            id: $data->id,
            shift_date: $data->shift_date,
            placements_count: $data->placements_count,
            videos_count: $data->videos_count,
            requests_count: $data->requests_count,
            comments: $data->comments,
            shift_was_cancelled: $data->shift_was_cancelled ? 1 : 0,
            tags: self::formatTags($data),
            metadata_shift_id: $metadata?->shift_id,
            metadata_shift_time: $metadata?->shift_time,
            metadata_location_id: $metadata?->location_id,
            metadata_location_name: $metadata?->location_name,
            metadata_submitted_by_id: $metadata?->submitted_by_id,
            metadata_submitted_by_name: $metadata?->submitted_by_name,
            metadata_submitted_by_email: $metadata?->submitted_by_email,
            metadata_submitted_by_phone: $metadata?->submitted_by_phone,
            metadata_associates: $metadata
                ? $metadata->associates->pluck('name')->filter()->implode(', ')
                : null,
        );
    }

    private static function formatTags(ReportsData $data): ?string
    {
        if ($data->tags->isEmpty()) {
            return null;
        }

        return $data->tags
            ->map(function (mixed $tag): ?string {
                $name = data_get($tag, 'name');

                if (is_array($name)) {
                    return $name['en'] ?? reset($name) ?: null;
                }

                return is_string($name) ? $name : null;
            })
            ->filter()
            ->implode(', ');
    }
}
