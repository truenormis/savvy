<?php

use App\Enums\UserRole;
use App\Http\Middleware\AuthenticateSession;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Account;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function makeImportUser(): User
{
    return User::create([
        'name' => 'Tester '.uniqid(),
        'email' => uniqid().'@example.com',
        'password' => bcrypt('password'),
        'role' => UserRole::ReadWrite,
    ]);
}

beforeEach(function () {
    Storage::fake('uploads');
    config(['uploads.disk' => 'uploads']);
    $this->withoutMiddleware([VerifyCsrfToken::class, AuthenticateSession::class]);
    $this->user = makeImportUser();
    $this->actingAs($this->user);
});

it('drives the full S3 multipart upload and async import pipeline', function () {
    $currency = Currency::create([
        'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'is_base' => true, 'exchange_rate' => 1,
    ]);
    $account = Account::create([
        'name' => 'Main', 'type' => 'bank', 'currency_id' => $currency->id, 'initial_balance' => 0, 'is_active' => true,
    ]);

    $csv = "Date,Amount,Description\n2024-01-01,-12.50,Coffee\n2024-01-02,2000,Salary\n";

    // 1. Create multipart upload
    $create = $this->postJson('/api/s3/multipart', [
        'bucket' => 'transaction-imports',
        'filename' => 'statement.csv',
        'type' => 'text/csv',
        'size' => strlen($csv),
    ])->assertOk()->json();

    expect($create['uploadId'])->not->toBeEmpty();
    expect($create['key'])->toContain('transaction-imports/');

    // 2. Sign part 1
    $sign = $this->getJson("/api/s3/multipart/{$create['uploadId']}/1?key=".urlencode($create['key']))
        ->assertOk()->json();

    expect($sign['url'])->toContain('/api/uploads/'.$create['uploadId'].'/parts/1');

    // 3. PUT the part through the signed (keyless) route
    $path = str_replace(config('app.url'), '', $sign['url']);
    $put = $this->call('PUT', $path, [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], $csv);
    expect($put->getStatusCode())->toBe(200);
    $etag = trim($put->headers->get('ETag'), '"');
    expect($etag)->toBe(md5($csv));

    // 4. Complete
    $complete = $this->postJson("/api/s3/multipart/{$create['uploadId']}/complete?key=".urlencode($create['key']), [
        'parts' => [['PartNumber' => 1, 'ETag' => $etag]],
    ])->assertOk()->json();

    expect($complete['location'])->not->toBeEmpty();
    expect(Upload::find($create['uploadId'])->status)->toBe(Upload::STATUS_COMPLETED);

    // 5. Parse (async, runs inline on the sync queue)
    $parse = $this->postJson('/api/transactions/import/parse', [
        'upload_id' => $create['uploadId'],
    ])->assertOk()->json('data');

    $importId = $parse['import_id'];

    $status = $this->getJson("/api/transactions/import/{$importId}")->assertOk()->json('data');
    expect($status['status'])->toBe('parsed');
    expect($status['total_rows'])->toBe(2);
    expect($status['parse']['headers'])->toBe(['Date', 'Amount', 'Description']);

    // 6. Execute (async, runs inline)
    $execute = $this->postJson('/api/transactions/import/execute', [
        'import_id' => $importId,
        'mapping' => ['date' => 0, 'amount' => 1, 'description' => 2],
        'options' => [
            'date_format' => 'ISO',
            'amount_format' => 'US',
            'default_account_id' => $account->id,
            'default_type' => 'expense',
        ],
    ])->assertOk()->json('data');

    expect($execute['status'])->toBe('importing');

    // Poll for completion, exactly like the frontend does
    $final = $this->getJson("/api/transactions/import/{$importId}")->assertOk()->json('data');

    expect($final['status'])->toBe('completed');
    expect($final['result']['created'])->toBe(2);
    expect(Transaction::count())->toBe(2);
});

it('rejects an upload into an unknown bucket', function () {
    $this->postJson('/api/s3/multipart', [
        'bucket' => 'nope',
        'filename' => 'x.csv',
        'size' => 10,
    ])->assertStatus(422);
});

it('forbids signing parts for another users upload', function () {
    $other = makeImportUser();

    $create = $this->postJson('/api/s3/multipart', [
        'bucket' => 'transaction-imports',
        'filename' => 'statement.csv',
        'size' => 100,
    ])->json();

    $this->actingAs($other)
        ->getJson("/api/s3/multipart/{$create['uploadId']}/1?key=".urlencode($create['key']))
        ->assertStatus(403);
});
