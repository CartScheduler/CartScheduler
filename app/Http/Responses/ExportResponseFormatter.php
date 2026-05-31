<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\MissingValue;
use Symfony\Component\HttpFoundation\Response;

class ExportResponseFormatter
{
    public static function download(
        Request $request,
        AnonymousResourceCollection $resource,
        string $filename,
    ): Response {
        $rows = self::rowsFromResource($resource, $request);

        $handle = fopen('php://temp', 'r+');

        if ($rows !== []) {
            fputcsv($handle, array_keys($rows[0]));

            foreach ($rows as $row) {
                fputcsv($handle, self::normalizeRow($row));
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
    private static function rowsFromResource(
        AnonymousResourceCollection $resource,
        Request $request,
    ): array {
        $resolved = $resource->toArray($request);

        if (array_is_list($resolved)) {
            return $resolved;
        }

        return $resolved['data'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<mixed>
     */
    private static function normalizeRow(array $row): array
    {
        return array_map(static function (mixed $value): mixed {
            if ($value instanceof MissingValue) {
                return null;
            }

            return $value;
        }, $row);
    }
}
