<?php

namespace App\Services\Import;

use App\DTOs\TransactionData;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Tag;
use App\Services\TransactionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CsvImportService
{
    public function __construct(
        private CsvParser $csvParser,
        private FormatDetector $formatDetector,
        private DuplicateChecker $duplicateChecker,
        private TransactionService $transactionService,
    ) {}

    /**
     * Parse CSV file and return preview data
     */
    public function parse(UploadedFile $file): array
    {
        $importId = $this->csvParser->generateImportId();
        $parsed = $this->csvParser->parse($file);

        // Store full data in cache
        $this->csvParser->storeInCache($importId, $parsed);

        // Return only preview data
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
     * Preview import results without actually importing
     */
    public function preview(string $importId, array $mapping, array $options): array
    {
        $cached = $this->csvParser->getFromCache($importId);

        if (!$cached) {
            throw new \RuntimeException('Import session expired. Please upload the file again.');
        }

        $rows = $cached['rows'];

        // Load existing transactions for duplicate checking
        $this->duplicateChecker->loadExistingTransactions($options['default_account_id']);

        $previewTransactions = [];
        $willCreate = 0;
        $willSkip = 0;
        $hasErrors = 0;
        $currenciesToCreate = [];
        $tagsToCreate = [];
        $categoriesToCreate = [];

        // Get existing entities for matching
        $existingCurrencies = Currency::pluck('id', 'code')->toArray();
        $existingTags = Tag::pluck('id', 'name')->map(fn($id, $name) => $id)->toArray();
        $existingTagsLower = array_change_key_case($existingTags, CASE_LOWER);
        $existingCategories = Category::pluck('id', 'name')->toArray();
        $existingCategoriesLower = array_change_key_case($existingCategories, CASE_LOWER);

        foreach ($rows as $index => $row) {
            $result = $this->processRow($row, $mapping, $options, $index + 1);

            if ($result['error']) {
                $hasErrors++;
                $previewTransactions[] = [
                    'row' => $index + 1,
                    'date' => $result['date'],
                    'type' => $result['type'],
                    'amount' => $result['amount'],
                    'description' => $result['description'],
                    'category' => $result['category'],
                    'tags' => $result['tags'],
                    'status' => 'error',
                    'duplicate_of' => null,
                    'warnings' => [],
                    'error' => $result['error'],
                ];
                continue;
            }

            // Check for duplicates
            $duplicateId = $this->duplicateChecker->isDuplicate(
                $result['date'],
                abs($result['amount']),
                $result['description']
            );

            if ($duplicateId !== null) {
                $willSkip++;
                $previewTransactions[] = [
                    'row' => $index + 1,
                    'date' => $result['date'],
                    'type' => $result['type'],
                    'amount' => abs($result['amount']),
                    'description' => $result['description'],
                    'category' => $result['category'],
                    'tags' => $result['tags'],
                    'status' => 'duplicate',
                    'duplicate_of' => $duplicateId > 0 ? $duplicateId : null,
                    'warnings' => [],
                    'error' => null,
                ];
                continue;
            }

            // Mark as importing for batch duplicate detection
            $this->duplicateChecker->markAsImporting(
                $result['date'],
                abs($result['amount']),
                $result['description']
            );

            $warnings = [];

            // Check if currency needs to be created
            if ($result['currency'] && !isset($existingCurrencies[$result['currency']])) {
                if (!in_array($result['currency'], $currenciesToCreate)) {
                    $currenciesToCreate[] = $result['currency'];
                }
                $warnings[] = "Currency '{$result['currency']}' will be created";
            }

            // Check if tags need to be created
            foreach ($result['tags'] as $tag) {
                $tagLower = strtolower($tag);
                if (!isset($existingTagsLower[$tagLower]) && !in_array($tag, $tagsToCreate)) {
                    $tagsToCreate[] = $tag;
                    $warnings[] = "Tag '{$tag}' will be created";
                }
            }

            // Check if category needs to be created
            if ($result['category']) {
                $categoryLower = strtolower($result['category']);
                if (!isset($existingCategoriesLower[$categoryLower]) && !in_array($result['category'], $categoriesToCreate)) {
                    $categoriesToCreate[] = $result['category'];
                    $warnings[] = "Category '{$result['category']}' will be created";
                }
            }

            $willCreate++;
            $previewTransactions[] = [
                'row' => $index + 1,
                'date' => $result['date'],
                'type' => $result['type'],
                'amount' => abs($result['amount']),
                'description' => $result['description'],
                'category' => $result['category'],
                'tags' => $result['tags'],
                'status' => 'new',
                'duplicate_of' => null,
                'warnings' => $warnings,
                'error' => null,
            ];
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

    /**
     * Execute the import
     */
    public function import(string $importId, array $mapping, array $options): array
    {
        $cached = $this->csvParser->getFromCache($importId);

        if (!$cached) {
            throw new \RuntimeException('Import session expired. Please upload the file again.');
        }

        $rows = $cached['rows'];

        // Load existing transactions for duplicate checking
        $this->duplicateChecker->loadExistingTransactions($options['default_account_id']);

        $created = 0;
        $skippedDuplicates = 0;
        $errors = [];
        $createdCurrencies = [];
        $createdTags = [];
        $createdCategories = [];

        // Pre-create currencies, tags, and categories
        $tagMap = $this->ensureTagsExist($rows, $mapping, $options);
        $categoryMap = $this->ensureCategoriesExist($rows, $mapping, $options);

        // Track which entities were actually created
        $createdTags = array_keys(array_filter($tagMap, fn($data) => $data['created'] ?? false));
        $createdCategories = array_keys(array_filter($categoryMap, fn($data) => $data['created'] ?? false));

        foreach ($rows as $index => $row) {
            $result = $this->processRow($row, $mapping, $options, $index + 1);

            if ($result['error']) {
                $errors[] = ['row' => $index + 1, 'message' => $result['error']];
                continue;
            }

            // Check for duplicates
            $duplicateId = $this->duplicateChecker->isDuplicate(
                $result['date'],
                abs($result['amount']),
                $result['description']
            );

            if ($duplicateId !== null) {
                $skippedDuplicates++;
                continue;
            }

            // Mark as importing
            $this->duplicateChecker->markAsImporting(
                $result['date'],
                abs($result['amount']),
                $result['description']
            );

            // Resolve tag IDs
            $tagIds = [];
            foreach ($result['tags'] as $tag) {
                $tagLower = strtolower($tag);
                if (isset($tagMap[$tagLower])) {
                    $tagIds[] = $tagMap[$tagLower]['id'];
                }
            }

            // Resolve category ID
            $categoryId = null;
            if ($result['category']) {
                $categoryLower = strtolower($result['category']);
                if (isset($categoryMap[$categoryLower])) {
                    $categoryId = $categoryMap[$categoryLower]['id'];
                }
            }

            // Create transaction
            try {
                $transactionData = new TransactionData(
                    type: TransactionType::from($result['type']),
                    accountId: $options['default_account_id'],
                    amount: abs($result['amount']),
                    date: $result['date'],
                    categoryId: $categoryId,
                    description: $result['description'],
                    tagIds: !empty($tagIds) ? $tagIds : null,
                );

                \Log::info('Creating transaction', [
                    'row' => $index + 1,
                    'type' => $result['type'],
                    'amount' => abs($result['amount']),
                    'date' => $result['date'],
                    'categoryId' => $categoryId,
                ]);

                $transaction = $this->transactionService->create($transactionData);

                \Log::info('Transaction created', ['id' => $transaction->id]);

                $created++;
            } catch (\Throwable $e) {
                \Log::error('Failed to create transaction', [
                    'row' => $index + 1,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $errors[] = ['row' => $index + 1, 'message' => $e->getMessage()];
            }
        }

        // Clean up cache
        $this->csvParser->removeFromCache($importId);

        return [
            'created' => $created,
            'skipped_duplicates' => $skippedDuplicates,
            'errors' => $errors,
            'created_currencies' => $createdCurrencies,
            'created_tags' => $createdTags,
            'created_categories' => $createdCategories,
        ];
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

            if (!$result['date']) {
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
            if (!isset($mapping['type'])) {
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

    /**
     * Ensure all tags exist, creating them if necessary
     */
    private function ensureTagsExist(array $rows, array $mapping, array $options): array
    {
        if (!isset($mapping['tags']) || !($options['create_missing_tags'] ?? true)) {
            return Tag::pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower($name) => ['id' => $id, 'created' => false]])
                ->toArray();
        }

        $allTags = [];

        foreach ($rows as $row) {
            if (isset($row[$mapping['tags']])) {
                $tagsValue = trim($row[$mapping['tags']]);
                if ($tagsValue) {
                    $tags = preg_split('/[,;|]/', $tagsValue);
                    foreach ($tags as $tag) {
                        $tag = trim($tag);
                        if ($tag) {
                            $allTags[strtolower($tag)] = $tag;
                        }
                    }
                }
            }
        }

        $tagMap = [];
        $lowerNames = array_keys($allTags);
        $placeholders = implode(',', array_fill(0, count($lowerNames), '?'));

        $existingTags = Tag::whereIn('name', array_values($allTags))
            ->when(count($lowerNames) > 0, function ($query) use ($placeholders, $lowerNames) {
                return $query->orWhereRaw("LOWER(name) IN ({$placeholders})", $lowerNames);
            })
            ->get();

        foreach ($existingTags as $tag) {
            $tagMap[strtolower($tag->name)] = ['id' => $tag->id, 'created' => false];
        }

        foreach ($allTags as $lowerName => $originalName) {
            if (!isset($tagMap[$lowerName])) {
                $tag = Tag::create(['name' => $originalName]);
                $tagMap[$lowerName] = ['id' => $tag->id, 'created' => true];
            }
        }

        return $tagMap;
    }

    /**
     * Ensure all categories exist, creating them if necessary
     */
    private function ensureCategoriesExist(array $rows, array $mapping, array $options): array
    {
        if (!isset($mapping['category']) || !($options['create_missing_categories'] ?? true)) {
            return Category::pluck('id', 'name')
                ->mapWithKeys(fn($id, $name) => [strtolower($name) => ['id' => $id, 'created' => false]])
                ->toArray();
        }

        $allCategories = [];
        $categoryTypes = []; // Track what type each category should be

        foreach ($rows as $row) {
            if (isset($row[$mapping['category']])) {
                $category = trim($row[$mapping['category']]);
                if ($category) {
                    $lowerCategory = strtolower($category);
                    $allCategories[$lowerCategory] = $category;

                    // Determine category type based on amount
                    if (isset($mapping['amount']) && isset($row[$mapping['amount']])) {
                        $amount = $this->formatDetector->parseAmount(
                            $row[$mapping['amount']],
                            $options['amount_format']
                        );

                        if ($amount !== null && !isset($categoryTypes[$lowerCategory])) {
                            $categoryTypes[$lowerCategory] = $amount < 0 ? 'expense' : 'income';
                        }
                    }
                }
            }
        }

        $categoryMap = [];
        $lowerNames = array_keys($allCategories);
        $placeholders = implode(',', array_fill(0, count($lowerNames), '?'));

        $existingCategories = Category::whereIn('name', array_values($allCategories))
            ->when(count($lowerNames) > 0, function ($query) use ($placeholders, $lowerNames) {
                return $query->orWhereRaw("LOWER(name) IN ({$placeholders})", $lowerNames);
            })
            ->get();

        foreach ($existingCategories as $category) {
            $categoryMap[strtolower($category->name)] = ['id' => $category->id, 'created' => false];
        }

        foreach ($allCategories as $lowerName => $originalName) {
            if (!isset($categoryMap[$lowerName])) {
                $type = $categoryTypes[$lowerName] ?? ($options['default_type'] ?? 'expense');

                $category = Category::create([
                    'name' => $originalName,
                    'type' => $type,
                    'icon' => $type === 'income' ? '💰' : '📦',
                    'color' => '#6b7280', // gray-500
                ]);
                $categoryMap[$lowerName] = ['id' => $category->id, 'created' => true];
            }
        }

        return $categoryMap;
    }
}
