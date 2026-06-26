<?php

namespace App\Http\Resources;

use App\Data\ReportsData;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Report
 */
class ExportReportsDataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = ReportsData::from($this->resource);

        $row = [
            'id' => $data->id,
            'shift_date' => $data->shift_date,
            'placements_count' => $data->placements_count,
            'videos_count' => $data->videos_count,
            'requests_count' => $data->requests_count,
            'comments' => $data->comments,
            'shift_was_cancelled' => $data->shift_was_cancelled ? 1 : 0,
            'tags' => $this->formatTags($data),
        ];

        $metadata = $data->metadata;

        $row['metadata_shift_id'] = $metadata?->shift_id;
        $row['metadata_shift_time'] = $metadata?->shift_time;
        $row['metadata_location_id'] = $metadata?->location_id;
        $row['metadata_location_name'] = $metadata?->location_name;
        $row['metadata_submitted_by_id'] = $metadata?->submitted_by_id;
        $row['metadata_submitted_by_name'] = $metadata?->submitted_by_name;
        $row['metadata_submitted_by_email'] = $metadata?->submitted_by_email;
        $row['metadata_submitted_by_phone'] = $metadata?->submitted_by_phone;
        $row['metadata_associates'] = $metadata
            ? $metadata->associates->pluck('name')->filter()->implode(', ')
            : null;

        return $row;
    }

    private function formatTags(ReportsData $data): ?string
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
