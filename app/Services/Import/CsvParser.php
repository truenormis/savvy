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
        return $this->parseString((string) file_get_contents($file->getRealPath()));
    }

    /**
     * Inspect the head of a CSV stream without loading the whole file.
     *
     * @param  resource  $stream
     */
    public function inspect($stream, int $sampleSize = 50): array
    {
        $lines = [];
        $first = true;
        $complete = true;

        while (count($lines) < $sampleSize) {
            $line = fgets($stream);

            if ($line === false) {
                break;
            }

            if ($first) {
                $line = $this->removeBom($line);
                $first = false;
            }

            $line = rtrim($line, "\r\n");

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        if (count($lines) >= $sampleSize) {
            $complete = false;
        }

        $encoding = mb_detect_encoding(implode("\n", $lines), ['UTF-8', 'Windows-1251', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UTF-8';
        $lines = array_map(fn ($line) => $this->toUtf8Line($line, $encoding), $lines);

        if ($lines === []) {
            return [
                'headers' => [],
                'rows' => [],
                'detected_formats' => ['date_format' => 'ISO', 'amount_format' => 'US', 'has_header' => false, 'delimiter' => ','],
                'suggested_mapping' => [],
                'delimiter' => ',',
                'has_header' => false,
                'encoding' => $encoding,
                'avg_line_bytes' => 0,
                'complete' => true,
            ];
        }

        $delimiter = $this->formatDetector->detectDelimiter(implode("\n", $lines));
        $rows = array_map(fn ($line) => array_map('trim', str_getcsv($line, $delimiter, '"', '\\')), $lines);

        $hasHeader = $this->formatDetector->detectHasHeader($rows[0] ?? [], $rows[1] ?? []);
        $headers = $hasHeader ? $rows[0] : $this->generateDefaultHeaders(count($rows[0]));
        $dataRows = $hasHeader ? array_slice($rows, 1) : $rows;

        $detectedFormats = $this->detectFormats(array_slice($dataRows, 0, 20), $headers);
        $detectedFormats['has_header'] = $hasHeader;
        $detectedFormats['delimiter'] = $delimiter;

        $avgLineBytes = (int) round(array_sum(array_map('strlen', $lines)) / max(1, count($lines))) + 1;

        return [
            'headers' => $headers,
            'rows' => $dataRows,
            'detected_formats' => $detectedFormats,
            'suggested_mapping' => $this->formatDetector->suggestMapping($headers),
            'delimiter' => $delimiter,
            'has_header' => $hasHeader,
            'encoding' => $encoding,
            'avg_line_bytes' => $avgLineBytes,
            'complete' => $complete,
        ];
    }

    /**
     * Stream data rows from a CSV stream, invoking the callback per row.
     *
     * @param  resource  $stream
     * @param  array{delimiter:string,has_header:bool,encoding:string}  $meta
     */
    public function each($stream, array $meta, callable $callback, ?int $limit = null): int
    {
        $delimiter = $meta['delimiter'] ?? ',';
        $hasHeader = $meta['has_header'] ?? false;
        $encoding = $meta['encoding'] ?? 'UTF-8';

        $first = true;
        $headerHandled = false;
        $count = 0;

        while (($line = fgets($stream)) !== false) {
            if ($first) {
                $line = $this->removeBom($line);
                $first = false;
            }

            $line = rtrim($line, "\r\n");

            if ($line === '') {
                continue;
            }

            if ($hasHeader && ! $headerHandled) {
                $headerHandled = true;

                continue;
            }

            $line = $this->toUtf8Line($line, $encoding);
            $row = array_map('trim', str_getcsv($line, $delimiter, '"', '\\'));

            $callback($row, $count);
            $count++;

            if ($limit !== null && $count >= $limit) {
                break;
            }
        }

        return $count;
    }

    /**
     * Stream data rows whose line START falls within the byte range [$start, $end).
     * A line straddling $end is processed in full by this range; a partial line at
     * $start belongs to the previous range and is skipped.
     *
     * @param  resource  $stream
     * @param  array{delimiter:string,has_header:bool,encoding:string}  $meta
     */
    public function eachRange($stream, array $meta, int $start, int $end, bool $hasHeader, callable $callback): int
    {
        $delimiter = $meta['delimiter'] ?? ',';
        $encoding = $meta['encoding'] ?? 'UTF-8';

        fseek($stream, $start);
        $pos = $start;

        if ($start > 0) {
            $pos += strlen((string) fgets($stream));
        }

        $first = ($start === 0);
        $skipHeader = ($start === 0 && $hasHeader);
        $count = 0;

        while ($pos < $end) {
            $line = fgets($stream);

            if ($line === false) {
                break;
            }

            $pos += strlen($line);

            if ($first) {
                $line = $this->removeBom($line);
                $first = false;
            }

            $line = rtrim($line, "\r\n");

            if ($line === '') {
                continue;
            }

            if ($skipHeader) {
                $skipHeader = false;

                continue;
            }

            $line = $this->toUtf8Line($line, $encoding);
            $row = array_map('trim', str_getcsv($line, $delimiter, '"', '\\'));

            $callback($row, $count);
            $count++;
        }

        return $count;
    }

    private function toUtf8Line(string $line, string $encoding): string
    {
        return $encoding && $encoding !== 'UTF-8'
            ? mb_convert_encoding($line, 'UTF-8', $encoding)
            : $line;
    }

    /**
     * Parse raw CSV content and return structured data
     */
    public function parseString(string $content): array
    {
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
            $headers[] = 'Column '.($i + 1);
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
