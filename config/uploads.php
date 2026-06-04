<?php

return [

    'disk' => env('UPLOAD_DISK', 'uploads'),

    'part_size' => (int) env('UPLOAD_PART_SIZE', 8 * 1024 * 1024),

    'url_ttl' => (int) env('UPLOAD_URL_TTL', 3600),

    'max_parts' => (int) env('UPLOAD_MAX_PARTS', 10000),

    'import_chunk' => (int) env('UPLOAD_IMPORT_CHUNK', 1000),

    // An import is split into byte-range slices of this size, each its own
    // queued job. The point is queue ergonomics, not raw speed: a huge import
    // becomes many short jobs (granular progress, per-slice retry, restart-safe,
    // never one job hogging a worker) instead of a single long-running job.
    'import_slice_bytes' => (int) env('UPLOAD_IMPORT_SLICE_BYTES', 16 * 1024 * 1024),

    'import_max_slices' => (int) env('UPLOAD_IMPORT_MAX_SLICES', 2000),

    'verify_etags' => (bool) env('UPLOAD_VERIFY_ETAGS', false),

    'buckets' => [

        'transaction-imports' => [
            'prefix' => 'transaction-imports',
            'max_size' => (int) env('UPLOAD_IMPORT_MAX_SIZE', 512 * 1024 * 1024),
            'allowed_extensions' => ['csv', 'txt'],
            'allowed_mime_types' => [
                'text/csv',
                'text/plain',
                'application/csv',
                'application/vnd.ms-excel',
                'application/octet-stream',
            ],
        ],

    ],

];
