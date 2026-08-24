<?php

namespace App\Http\Responses;

use App\Settings\GeneralSettings;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns a set of Data objects into a CSV download.
 *
 * Injected rather than called statically so that the download name — which
 * needs the configured site name — is built in one place instead of once per
 * export controller.
 */
class ExportResponseFormatter
{
    public function __construct(private readonly GeneralSettings $settings) {}

    /**
     * @param  DataCollection<int, Data>|iterable<Data>  $rows
     * @param  string  $type  Names the export within the file name, e.g. 'shift-counts'.
     */
    public function download(
        DataCollection|iterable $rows,
        string $type,
    ): Response {
        $rows = $this->rowsToArrays($rows);

        $handle = fopen('php://temp', 'r+');

        if ($rows !== []) {
            fputcsv($handle, array_keys($rows[0]));

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$this->filename($type).'"',
        ]);
    }

    private function filename(string $type): string
    {
        $dateTime = now()->format('Y-m-d_His');
        $siteName = Str::snake($this->settings->siteName);

        return "{$siteName}-{$type}_{$dateTime}.csv";
    }

    /**
     * @param  DataCollection<int, Data>|iterable<Data>  $rows
     * @return list<array<string, mixed>>
     */
    private function rowsToArrays(DataCollection|iterable $rows): array
    {
        $items = $rows instanceof DataCollection ? $rows->items() : $rows;

        return array_values(array_map(
            static fn (Data $row): array => $row->toArray(),
            is_array($items) ? $items : iterator_to_array($items),
        ));
    }
}
