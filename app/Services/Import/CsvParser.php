<?php

namespace App\Services\Import;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CsvParser
{
    private FormatDetector $formatDetector;

    public function __construct(FormatDetector $formatDetector)
    {
        $this->formatDetector = $formatDetector;
    }

    /**
     * Parse CSV file and return structured data
     */
    public function parse(UploadedFile $file): array
    {
        $content = file_get_contents($file->getRealPath());

        // Detect and handle BOM
        $content = $this->removeBom($content);

        // Detect encoding and convert to UTF-8
        $content = $this->convertToUtf8($content);

        // Detect delimiter
        $delimiter = $this->formatDetector->detectDelimiter($content);

        // Parse rows
        $rows = $this->parseRows($content, $delimiter);

        if (empty($rows)) {
            return [
                'headers' => [],
                'rows' => [],
                'total_rows' => 0,
                'detected_formats' => [
                    'date_format' => 'ISO',
                    'amount_format' => 'US',
                    'has_header' => false,
                    'delimiter' => $delimiter,
                ],
                'suggested_mapping' => [],
            ];
        }

        $firstRow = $rows[0] ?? [];
        $secondRow = $rows[1] ?? [];
        $hasHeader = $this->formatDetector->detectHasHeader($firstRow, $secondRow);

        $headers = $hasHeader ? $firstRow : $this->generateDefaultHeaders(count($firstRow));
        $dataRows = $hasHeader ? array_slice($rows, 1) : $rows;

        // Detect formats from sample data
        $sampleRows = array_slice($dataRows, 0, 20);
        $detectedFormats = $this->detectFormats($sampleRows, $headers);
        $detectedFormats['has_header'] = $hasHeader;
        $detectedFormats['delimiter'] = $delimiter;

        // Suggest mapping based on headers
        $suggestedMapping = $this->formatDetector->suggestMapping($headers);

        return [
            'headers' => $headers,
            'rows' => $dataRows,
            'total_rows' => count($dataRows),
            'detected_formats' => $detectedFormats,
            'suggested_mapping' => $suggestedMapping,
        ];
    }

    /**
     * Parse CSV rows
     */
    private function parseRows(string $content, string $delimiter): array
    {
        $rows = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line, "\r\n");

            if (empty($line)) {
                continue;
            }

            $row = str_getcsv($line, $delimiter, '"', '\\');
            $rows[] = array_map('trim', $row);
        }

        return $rows;
    }

    /**
     * Detect date and amount formats from sample data
     */
    private function detectFormats(array $rows, array $headers): array
    {
        // Find potential date and amount columns
        $dateSamples = [];
        $amountSamples = [];

        // Try suggested columns first
        $suggestedMapping = $this->formatDetector->suggestMapping($headers);

        foreach ($rows as $row) {
            // Collect date samples
            if (isset($suggestedMapping['date'])) {
                $dateValue = $row[$suggestedMapping['date']] ?? null;
                if ($dateValue) {
                    $dateSamples[] = $dateValue;
                }
            }

            // Collect amount samples
            if (isset($suggestedMapping['amount'])) {
                $amountValue = $row[$suggestedMapping['amount']] ?? null;
                if ($amountValue) {
                    $amountSamples[] = $amountValue;
                }
            }
        }

        // If no samples from suggested columns, try to find them heuristically
        if (empty($dateSamples) || empty($amountSamples)) {
            foreach ($rows as $row) {
                foreach ($row as $cell) {
                    $cell = trim($cell);

                    if (empty($cell)) {
                        continue;
                    }

                    // Looks like a date
                    if (empty($dateSamples) && preg_match('/^\d{1,4}[\.\-\/]\d{1,2}[\.\-\/]\d{1,4}$/', $cell)) {
                        $dateSamples[] = $cell;
                    }

                    // Looks like an amount
                    if (empty($amountSamples) && preg_match('/^[\-\+]?\d+[.,]\d{1,2}$/', preg_replace('/[^\d.,\-\+]/', '', $cell))) {
                        $amountSamples[] = $cell;
                    }
                }
            }
        }

        return [
            'date_format' => $this->formatDetector->detectDateFormat($dateSamples),
            'amount_format' => $this->formatDetector->detectAmountFormat($amountSamples),
        ];
    }

    /**
     * Generate default column headers
     */
    private function generateDefaultHeaders(int $count): array
    {
        $headers = [];

        for ($i = 0; $i < $count; $i++) {
            $headers[] = 'Column ' . ($i + 1);
        }

        return $headers;
    }

    /**
     * Remove BOM from content
     */
    private function removeBom(string $content): string
    {
        $boms = [
            "\xEF\xBB\xBF",     // UTF-8
            "\xFE\xFF",         // UTF-16 BE
            "\xFF\xFE",         // UTF-16 LE
            "\x00\x00\xFE\xFF", // UTF-32 BE
            "\xFF\xFE\x00\x00", // UTF-32 LE
        ];

        foreach ($boms as $bom) {
            if (str_starts_with($content, $bom)) {
                return substr($content, strlen($bom));
            }
        }

        return $content;
    }

    /**
     * Convert content to UTF-8
     */
    private function convertToUtf8(string $content): string
    {
        // Detect encoding
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1251', 'ISO-8859-1', 'Windows-1252'], true);

        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        return $content;
    }

    /**
     * Store parsed data in cache for later use
     */
    public function storeInCache(string $importId, array $data): void
    {
        cache()->put("import_{$importId}", $data, now()->addHour());
    }

    /**
     * Retrieve parsed data from cache
     */
    public function getFromCache(string $importId): ?array
    {
        return cache()->get("import_{$importId}");
    }

    /**
     * Remove parsed data from cache
     */
    public function removeFromCache(string $importId): void
    {
        cache()->forget("import_{$importId}");
    }

    /**
     * Generate unique import ID
     */
    public function generateImportId(): string
    {
        return Str::uuid()->toString();
    }
}
