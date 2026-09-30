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
            fputcsv($handle, array_map($this->neutraliseFormula(...), array_keys($rows[0])));

            foreach ($rows as $row) {
                fputcsv($handle, array_map($this->neutraliseFormula(...), array_values($row)));
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

    /**
     * Stops a spreadsheet treating a cell as a formula.
     *
     * Volunteers write their own name and their report and availability
     * comments, and all three reach these exports. A cell opening with one of
     * these characters is evaluated on the admin's machine when they open the
     * file, so `=HYPERLINK(...)` in a comment would exfiltrate the roster's
     * contact details sitting in the neighbouring columns.
     *
     * Quoting is not enough — Excel evaluates a quoted formula too. A leading
     * apostrophe is the documented way to force the cell to text; it is not
     * displayed by the spreadsheet, though it does survive into the raw CSV.
     */
    private function neutraliseFormula(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return str_contains("=+-@\t\r", $value[0]) ? "'".$value : $value;
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
