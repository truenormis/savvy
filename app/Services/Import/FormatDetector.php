<?php

namespace App\Services\Import;

use DateTime;

class FormatDetector
{
    private const DATE_FORMATS = [
        'ISO' => ['Y-m-d', 'Y/m/d'],
        'DD.MM.YYYY' => ['d.m.Y', 'd.m.y'],
        'DD/MM/YYYY' => ['d/m/Y', 'd/m/y'],
        'MM/DD/YYYY' => ['m/d/Y', 'm/d/y', 'n/j/Y', 'n/j/y'],
    ];

    /**
     * Detect date format from sample values
     */
    public function detectDateFormat(array $samples): string
    {
        $samples = array_filter($samples, fn($s) => !empty(trim($s)));

        if (empty($samples)) {
            return 'ISO';
        }

        $scores = [];

        foreach (self::DATE_FORMATS as $format => $patterns) {
            $scores[$format] = 0;

            foreach ($samples as $sample) {
                $sample = trim($sample);

                foreach ($patterns as $pattern) {
                    $date = DateTime::createFromFormat($pattern, $sample);

                    if ($date && $date->format($pattern) === $sample) {
                        $year = (int) $date->format('Y');

                        // Validate year is reasonable (1990-2100)
                        if ($year >= 1990 && $year <= 2100) {
                            $scores[$format]++;
                            break;
                        }
                    }
                }
            }
        }

        arsort($scores);
        $bestFormat = array_key_first($scores);

        return $scores[$bestFormat] > 0 ? $bestFormat : 'ISO';
    }

    /**
     * Detect amount format from sample values
     * US: 1,234.56 (comma = thousands, dot = decimal)
     * EU: 1.234,56 (dot = thousands, comma = decimal)
     */
    public function detectAmountFormat(array $samples): string
    {
        $samples = array_filter($samples, fn($s) => !empty(trim($s)));

        if (empty($samples)) {
            return 'US';
        }

        $usScore = 0;
        $euScore = 0;

        foreach ($samples as $sample) {
            $sample = preg_replace('/[^0-9.,\-\+]/', '', trim($sample));

            if (empty($sample)) {
                continue;
            }

            // US format patterns: 1,234.56 or 1234.56
            if (preg_match('/^\-?\+?\d{1,3}(,\d{3})*\.\d{1,2}$/', $sample)) {
                $usScore += 2;
            } elseif (preg_match('/^\-?\+?\d+\.\d{1,2}$/', $sample)) {
                $usScore++;
            }

            // EU format patterns: 1.234,56 or 1234,56
            if (preg_match('/^\-?\+?\d{1,3}(\.\d{3})*,\d{1,2}$/', $sample)) {
                $euScore += 2;
            } elseif (preg_match('/^\-?\+?\d+,\d{1,2}$/', $sample)) {
                $euScore++;
            }

            // Check last separator position
            $lastDot = strrpos($sample, '.');
            $lastComma = strrpos($sample, ',');

            if ($lastDot !== false && $lastComma !== false) {
                if ($lastDot > $lastComma) {
                    $usScore++;
                } else {
                    $euScore++;
                }
            }
        }

        return $euScore > $usScore ? 'EU' : 'US';
    }

    /**
     * Parse amount string to float based on format
     */
    public function parseAmount(string $value, string $format): ?float
    {
        $value = trim($value);

        if (empty($value)) {
            return null;
        }

        // Remove currency symbols and spaces
        $value = preg_replace('/[^\d.,\-\+]/', '', $value);

        if (empty($value)) {
            return null;
        }

        if ($format === 'EU') {
            // Remove thousand separators (.), convert decimal (,) to (.)
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } else {
            // Remove thousand separators (,)
            $value = str_replace(',', '', $value);
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * Parse date string based on format
     */
    public function parseDate(string $value, string $format): ?string
    {
        $value = trim($value);

        if (empty($value)) {
            return null;
        }

        $patterns = self::DATE_FORMATS[$format] ?? self::DATE_FORMATS['ISO'];

        foreach ($patterns as $pattern) {
            $date = DateTime::createFromFormat($pattern, $value);

            if ($date) {
                $year = (int) $date->format('Y');

                // Handle 2-digit years
                if ($year < 100) {
                    $year = $year > 50 ? 1900 + $year : 2000 + $year;
                    $date->setDate($year, (int) $date->format('m'), (int) $date->format('d'));
                }

                if ($year >= 1990 && $year <= 2100) {
                    return $date->format('Y-m-d');
                }
            }
        }

        return null;
    }

    /**
     * Detect if first row is a header
     */
    public function detectHasHeader(array $firstRow, array $secondRow): bool
    {
        if (empty($firstRow) || empty($secondRow)) {
            return false;
        }

        $headerScore = 0;

        foreach ($firstRow as $index => $cell) {
            $cell = trim($cell);
            $dataCell = trim($secondRow[$index] ?? '');

            // Check if first row looks like text and second row looks like data
            $cellIsNumeric = is_numeric(preg_replace('/[^0-9.\-,]/', '', $cell));
            $dataCellIsNumeric = is_numeric(preg_replace('/[^0-9.\-,]/', '', $dataCell));

            if (!$cellIsNumeric && $dataCellIsNumeric) {
                $headerScore++;
            }

            // Check for common header keywords
            $lowerCell = strtolower($cell);
            $headerKeywords = ['date', 'amount', 'description', 'category', 'memo', 'note', 'type', 'sum', 'total', 'balance'];

            foreach ($headerKeywords as $keyword) {
                if (str_contains($lowerCell, $keyword)) {
                    $headerScore += 2;
                    break;
                }
            }
        }

        return $headerScore >= 2;
    }

    /**
     * Suggest column mapping based on header names
     */
    public function suggestMapping(array $headers): array
    {
        $mapping = [];

        $patterns = [
            'date' => ['date', 'datum', 'fecha', 'data', 'transaction date', 'posted', 'booking'],
            'amount' => ['amount', 'sum', 'betrag', 'importe', 'value', 'total', 'сумма'],
            'description' => ['description', 'memo', 'note', 'beschreibung', 'details', 'narrative', 'purpose', 'reference', 'описание'],
            'category' => ['category', 'kategorie', 'type', 'classification', 'категория'],
            'tags' => ['tags', 'labels', 'keywords', 'теги'],
            'currency' => ['currency', 'währung', 'ccy', 'валюта'],
        ];

        foreach ($headers as $index => $header) {
            $normalizedHeader = strtolower(trim($header));

            foreach ($patterns as $field => $fieldPatterns) {
                if (!isset($mapping[$field])) {
                    foreach ($fieldPatterns as $pattern) {
                        if (str_contains($normalizedHeader, $pattern)) {
                            $mapping[$field] = $index;
                            break 2;
                        }
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Detect CSV delimiter
     */
    public function detectDelimiter(string $content): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $scores = [];

        $lines = array_slice(explode("\n", $content), 0, 5);

        foreach ($delimiters as $delimiter) {
            $counts = [];

            foreach ($lines as $line) {
                if (empty(trim($line))) {
                    continue;
                }
                $counts[] = substr_count($line, $delimiter);
            }

            // Check if count is consistent across lines
            if (!empty($counts)) {
                $uniqueCounts = array_unique($counts);
                if (count($uniqueCounts) === 1 && $counts[0] > 0) {
                    $scores[$delimiter] = $counts[0];
                }
            }
        }

        if (empty($scores)) {
            return ',';
        }

        arsort($scores);
        return array_key_first($scores);
    }
}
