<?php

namespace Tests\Traits;

trait AssertsExportResponses
{
    /**
     * @return list<list<string|null>>
     */
    protected function assertExportCsvDownload(mixed $response, string $filename): array
    {
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringContainsString(
            $filename,
            (string) $response->headers->get('content-disposition'),
        );

        $content = $response->getContent();
        $this->assertNotEmpty($content);

        return array_values(array_map(
            'str_getcsv',
            array_filter(explode("\n", trim($content))),
        ));
    }
}
