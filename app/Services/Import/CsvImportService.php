<?php

namespace App\Services\Import;

use App\Models\Category;
use App\Models\Currency;
use App\Models\Tag;
use App\Models\Upload;
use App\Services\Upload\UploadManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CsvImportService
{
    public function __construct(
        private CsvParser $csvParser,
        private FormatDetector $formatDetector,
        private DuplicateChecker $duplicateChecker,
        private UploadManager $uploadManager,
    ) {}

    /**
     * Parse CSV file and return preview data
     */
    public function parse(UploadedFile $file): array
    {
        $importId = $this->csvParser->generateImportId();
        $parsed = $this->csvParser->parse($file);

        $this->csvParser->storeInCache($importId, $parsed);

        return $this->previewPayload($importId, $parsed);
    }

    /**
     * Inspect the head of a completed multipart upload (constant memory, no full read)
     */
    public function parseUpload(Upload $upload, string $importId): array
    {
        $stream = $this->uploadManager->readStream($upload);

        try {
            $inspection = $this->csvParser->inspect($stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return [
            'import_id' => $importId,
            'headers' => $inspection['headers'],
            'preview_rows' => array_slice($inspection['rows'], 0, 10),
            'total_rows' => $this->estimateRows($inspection, $upload),
            'detected_formats' => $inspection['detected_formats'],
            'suggested_mapping' => $inspection['suggested_mapping'],
            'parse_meta' => [
                'delimiter' => $inspection['delimiter'],
                'has_header' => $inspection['has_header'],
                'encoding' => $inspection['encoding'],
            ],
        ];
    }

    private function estimateRows(array $inspection, Upload $upload): int
    {
        if ($inspection['complete']) {
            return count($inspection['rows']);
        }

        $avg = $inspection['avg_line_bytes'];

        if (! $avg || ! $upload->size) {
            return count($inspection['rows']);
        }

        $estimate = (int) floor($upload->size / $avg) - ($inspection['has_header'] ? 1 : 0);

        return max($estimate, count($inspection['rows']));
    }

    private function previewPayload(string $importId, array $parsed): array
    {
        return [
            'import_id' => $importId,
            'headers' => $parsed['headers'],
            'preview_rows' => array_slice($parsed['rows'], 0, 10),
            'total_rows' => $parsed['total_rows'],
            'detected_formats' => $parsed['detected_formats'],
            'suggested_mapping' => $parsed['suggested_mapping'],
        ];
    }

    /**
     * Preview import results by streaming a bounded sample of the upload
     */
    public function preview(Upload $upload, array $parseMeta, array $mapping, array $options, int $sampleLimit = 2000): array
    {
        $this->duplicateChecker->loadExistingTransactions($options['default_account_id']);

        $existingCurrencies = Currency::pluck('id', 'code')->toArray();
        $existingTagsLower = array_change_key_case(Tag::pluck('id', 'name')->toArray(), CASE_LOWER);
        $existingCategoriesLower = array_change_key_case(Category::pluck('id', 'name')->toArray(), CASE_LOWER);

        $previewTransactions = [];
        $willCreate = 0;
        $willSkip = 0;
        $hasErrors = 0;
        $currenciesToCreate = [];
        $tagsToCreate = [];
        $categoriesToCreate = [];
        $maxPreviewRows = 200;

        $stream = $this->uploadManager->readStream($upload);

        try {
            $this->csvParser->each($stream, $parseMeta, function (array $row, int $index) use (
                $mapping, $options, $existingCurrencies, $existingTagsLower, $existingCategoriesLower, $maxPreviewRows,
                &$previewTransactions, &$willCreate, &$willSkip, &$hasErrors,
                &$currenciesToCreate, &$tagsToCreate, &$categoriesToCreate
            ) {
                $result = $this->processRow($row, $mapping, $options, $index + 1);
                $capture = count($previewTransactions) < $maxPreviewRows;

                if ($result['error']) {
                    $hasErrors++;
                    if ($capture) {
                        $previewTransactions[] = $this->previewRow($index, $result, 'error', null, [], $result['error']);
                    }

                    return;
                }

                $duplicateId = $this->duplicateChecker->isDuplicate($result['date'], abs($result['amount']), $result['description']);

                if ($duplicateId !== null) {
                    $willSkip++;
                    if ($capture) {
                        $previewTransactions[] = $this->previewRow($index, $result, 'duplicate', $duplicateId > 0 ? $duplicateId : null, [], null);
                    }

                    return;
                }

                $this->duplicateChecker->markAsImporting($result['date'], abs($result['amount']), $result['description']);

                $warnings = [];

                if ($result['currency'] && ! isset($existingCurrencies[$result['currency']])) {
                    if (! in_array($result['currency'], $currenciesToCreate)) {
                        $currenciesToCreate[] = $result['currency'];
                    }
                    $warnings[] = "Currency '{$result['currency']}' will be created";
                }

                foreach ($result['tags'] as $tag) {
                    if (! isset($existingTagsLower[strtolower($tag)]) && ! in_array($tag, $tagsToCreate)) {
                        $tagsToCreate[] = $tag;
                        $warnings[] = "Tag '{$tag}' will be created";
                    }
                }

                if ($result['category'] && ! isset($existingCategoriesLower[strtolower($result['category'])]) && ! in_array($result['category'], $categoriesToCreate)) {
                    $categoriesToCreate[] = $result['category'];
                    $warnings[] = "Category '{$result['category']}' will be created";
                }

                $willCreate++;
                if ($capture) {
                    $previewTransactions[] = $this->previewRow($index, $result, 'new', null, $warnings, null);
                }
            }, $sampleLimit);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return [
            'preview_transactions' => $previewTransactions,
            'summary' => [
                'will_create' => $willCreate,
                'will_skip' => $willSkip,
                'has_errors' => $hasErrors,
                'currencies_to_create' => $currenciesToCreate,
                'tags_to_create' => $tagsToCreate,
                'categories_to_create' => $categoriesToCreate,
            ],
        ];
    }

    private function previewRow(int $index, array $result, string $status, ?int $duplicateOf, array $warnings, ?string $error): array
    {
        return [
            'row' => $index + 1,
            'date' => $result['date'],
            'type' => $result['type'],
            'amount' => $status === 'error' ? $result['amount'] : abs((float) $result['amount']),
            'description' => $result['description'],
            'category' => $result['category'],
            'tags' => $result['tags'],
            'status' => $status,
            'duplicate_of' => $duplicateOf,
            'warnings' => $warnings,
            'error' => $error,
        ];
    }

    /**
     * Pre-create all missing categories/tags single-threaded (race-free) and
     * return the name->id maps the parallel slices resolve against.
     *
     * @return array{categories:array<string,int>,tags:array<string,int>,created_categories:array<int,string>,created_tags:array<int,string>}
     */
    public function collectEntities(Upload $upload, array $parseMeta, array $mapping, array $options): array
    {
        $categoryMap = Category::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id])->toArray();
        $tagMap = Tag::pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [strtolower($name) => $id])->toArray();

        $createMissingTags = ($options['create_missing_tags'] ?? true) && isset($mapping['tags']);
        $createMissingCategories = ($options['create_missing_categories'] ?? true) && isset($mapping['category']);

        if (! $createMissingTags && ! $createMissingCategories) {
            return ['categories' => $categoryMap, 'tags' => $tagMap, 'created_categories' => [], 'created_tags' => []];
        }

        $newCategories = [];
        $newTags = [];

        $stream = $this->uploadManager->readStream($upload);

        try {
            $this->csvParser->each($stream, $parseMeta, function (array $row) use (
                $mapping, $options, $createMissingTags, $createMissingCategories, $categoryMap, $tagMap, &$newCategories, &$newTags
            ) {
                if ($createMissingCategories && isset($row[$mapping['category']])) {
                    $name = trim($row[$mapping['category']]);
                    $key = strtolower($name);

                    if ($name !== '' && ! isset($categoryMap[$key]) && ! isset($newCategories[$key])) {
                        $amount = isset($mapping['amount'], $row[$mapping['amount']])
                            ? $this->formatDetector->parseAmount($row[$mapping['amount']], $options['amount_format'])
                            : null;
                        $newCategories[$key] = ['name' => $name, 'type' => ($amount !== null && $amount < 0) ? 'expense' : 'income'];
                    }
                }

                if ($createMissingTags && isset($row[$mapping['tags']])) {
                    foreach (preg_split('/[,;|]/', trim($row[$mapping['tags']])) as $tag) {
                        $tag = trim($tag);
                        $key = strtolower($tag);
                        if ($tag !== '' && ! isset($tagMap[$key]) && ! isset($newTags[$key])) {
                            $newTags[$key] = $tag;
                        }
                    }
                }
            });
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $createdCategories = [];
        $createdTags = [];

        foreach ($newCategories as $key => $data) {
            $categoryMap[$key] = Category::create([
                'name' => $data['name'],
                'type' => $data['type'],
                'icon' => $data['type'] === 'income' ? '💰' : '📦',
                'color' => '#6b7280',
            ])->id;
            $createdCategories[] = $data['name'];
        }

        foreach ($newTags as $key => $name) {
            $tagMap[$key] = Tag::create(['name' => $name])->id;
            $createdTags[] = $name;
        }

        return ['categories' => $categoryMap, 'tags' => $tagMap, 'created_categories' => $createdCategories, 'created_tags' => $createdTags];
    }

    /**
     * Import one byte-range slice of the upload via chunked bulk inserts.
     * Entities are resolved read-only against the pre-built maps, so slices
     * run in parallel without racing. Duplicates are dropped by the DB index.
     *
     * @param  array{categories:array<string,int>,tags:array<string,int>}  $entityMaps
     * @return array{created:int,skipped:int,errors:array<int,array{row:int,message:string}>,processed:int}
     */
    public function importSlice(Upload $upload, array $parseMeta, array $mapping, array $options, int $start, int $end, bool $isFirstRange, array $entityMaps, ?callable $onProgress = null): array
    {
        DB::connection()->disableQueryLog();

        $accountId = $options['default_account_id'];
        $categoryMap = $entityMaps['categories'] ?? [];
        $tagMap = $entityMaps['tags'] ?? [];
        $hasHeader = $isFirstRange && ($parseMeta['has_header'] ?? false);

        $created = 0;
        $skipped = 0;
        $errors = [];
        $maxErrors = 200;
        $chunkSize = max(100, (int) config('uploads.import_chunk', 1000));
        $now = now()->toDateTimeString();
        $buffer = [];
        $tagBuffer = [];

        $flush = function () use (&$buffer, &$tagBuffer, &$created, &$skipped, &$errors, $accountId, $onProgress) {
            if ($buffer === []) {
                return;
            }

            $attempted = count($buffer);
            $inserted = DB::table('transactions')->insertOrIgnore($buffer);
            $created += $inserted;
            $skipped += $attempted - $inserted;

            if ($tagBuffer !== []) {
                $ids = DB::table('transactions')
                    ->where('account_id', $accountId)
                    ->whereIn('dedup_hash', array_keys($tagBuffer))
                    ->pluck('id', 'dedup_hash');

                $pivot = [];
                foreach ($tagBuffer as $hash => $tagIds) {
                    if (! isset($ids[$hash])) {
                        continue;
                    }
                    foreach (array_unique($tagIds) as $tagId) {
                        $pivot[] = ['transaction_id' => $ids[$hash], 'tag_id' => $tagId];
                    }
                }

                if ($pivot !== []) {
                    DB::table('transaction_tag')->insertOrIgnore($pivot);
                }
            }

            $buffer = [];
            $tagBuffer = [];

            if ($onProgress !== null) {
                $onProgress(['created' => $created, 'skipped' => $skipped, 'errors' => count($errors)]);
            }
        };

        $stream = $this->uploadManager->readStream($upload);

        try {
            $this->csvParser->eachRange($stream, $parseMeta, $start, $end, $hasHeader, function (array $row, int $index) use (
                $mapping, $options, $accountId, $maxErrors, $chunkSize, $now, $flush, $categoryMap, $tagMap, &$errors, &$buffer, &$tagBuffer
            ) {
                $result = $this->processRow($row, $mapping, $options, $index + 1);

                if ($result['error']) {
                    if (count($errors) < $maxErrors) {
                        $errors[] = ['row' => $index + 1, 'message' => $result['error']];
                    }

                    return;
                }

                $categoryId = $result['category'] ? ($categoryMap[strtolower($result['category'])] ?? null) : null;

                $tagIds = [];
                foreach ($result['tags'] as $tag) {
                    $key = strtolower($tag);
                    if (isset($tagMap[$key])) {
                        $tagIds[] = $tagMap[$key];
                    }
                }

                $amount = abs((float) $result['amount']);
                $hash = $this->dedupHash($result['date'], $amount, $result['description']);

                $buffer[] = [
                    'type' => $result['type'],
                    'account_id' => $accountId,
                    'category_id' => $categoryId,
                    'amount' => $amount,
                    'description' => $result['description'],
                    'date' => $result['date'],
                    'dedup_hash' => $hash,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($tagIds !== []) {
                    $tagBuffer[$hash] = $tagIds;
                }

                if (count($buffer) >= $chunkSize) {
                    $flush();
                }
            });
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $flush();

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'processed' => $created + $skipped + count($errors),
        ];
    }

    private function dedupHash(string $date, float $amount, ?string $description): string
    {
        $normalizedDescription = $description
            ? strtolower(trim(preg_replace('/\s+/', ' ', $description)))
            : '';

        return md5($date.'|'.round($amount, 2).'|'.$normalizedDescription);
    }

    /**
     * Process a single CSV row
     */
    private function processRow(array $row, array $mapping, array $options, int $rowNumber): array
    {
        $result = [
            'date' => null,
            'amount' => null,
            'type' => $options['default_type'] ?? 'expense',
            'description' => null,
            'category' => null,
            'tags' => [],
            'currency' => null,
            'error' => null,
        ];

        // Parse date
        if (isset($mapping['date']) && isset($row[$mapping['date']])) {
            $dateValue = $row[$mapping['date']];
            $result['date'] = $this->formatDetector->parseDate($dateValue, $options['date_format']);

            if (! $result['date']) {
                $result['error'] = "Invalid date format: '{$dateValue}'";

                return $result;
            }
        } else {
            $result['error'] = 'Date column not mapped or empty';

            return $result;
        }

        // Parse amount
        if (isset($mapping['amount']) && isset($row[$mapping['amount']])) {
            $amountValue = $row[$mapping['amount']];
            $result['amount'] = $this->formatDetector->parseAmount($amountValue, $options['amount_format']);

            if ($result['amount'] === null) {
                $result['error'] = "Invalid amount format: '{$amountValue}'";

                return $result;
            }

            // Determine type based on amount sign if not explicitly mapped
            if (! isset($mapping['type'])) {
                if ($result['amount'] < 0) {
                    $result['type'] = 'expense';
                } elseif ($result['amount'] > 0) {
                    $result['type'] = 'income';
                } else {
                    $result['type'] = $options['default_type'] ?? 'expense';
                }
            }
        } else {
            $result['error'] = 'Amount column not mapped or empty';

            return $result;
        }

        // Parse type if mapped
        if (isset($mapping['type']) && isset($row[$mapping['type']])) {
            $typeValue = strtolower(trim($row[$mapping['type']]));

            if (in_array($typeValue, ['income', 'credit', 'deposit', '+', 'cr'])) {
                $result['type'] = 'income';
            } elseif (in_array($typeValue, ['expense', 'debit', 'withdrawal', '-', 'dr'])) {
                $result['type'] = 'expense';
            }
        }

        // Parse description
        if (isset($mapping['description']) && isset($row[$mapping['description']])) {
            $result['description'] = trim($row[$mapping['description']]) ?: null;
        }

        // Parse category
        if (isset($mapping['category']) && isset($row[$mapping['category']])) {
            $result['category'] = trim($row[$mapping['category']]) ?: null;
        }

        // Parse tags
        if (isset($mapping['tags']) && isset($row[$mapping['tags']])) {
            $tagsValue = trim($row[$mapping['tags']]);
            if ($tagsValue) {
                // Split by comma, semicolon, or pipe
                $tags = preg_split('/[,;|]/', $tagsValue);
                $result['tags'] = array_filter(array_map('trim', $tags));
            }
        }

        // Parse currency
        if (isset($mapping['currency']) && isset($row[$mapping['currency']])) {
            $result['currency'] = strtoupper(trim($row[$mapping['currency']])) ?: null;
        }

        return $result;
    }
}
