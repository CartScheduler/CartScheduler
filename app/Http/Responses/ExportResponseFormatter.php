<?php

namespace App\Http\Responses;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Symfony\Component\HttpFoundation\Response;

class ExportResponseFormatter
{
    /**
     * @param  DataCollection<int, Data>|iterable<Data>  $rows
     */
    public static function download(
        DataCollection|iterable $rows,
        string $filename,
    ): Response {
        $rows = self::rowsToArrays($rows);

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
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function rowsToArrays(DataCollection|iterable $rows): array
    {
        $items = $rows instanceof DataCollection ? $rows->items() : $rows;

        return array_values(array_map(
            static fn (Data $row): array => $row->toArray(),
            is_array($items) ? $items : iterator_to_array($items),
        ));
    }
}
