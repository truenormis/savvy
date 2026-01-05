<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('PRAGMA foreign_keys=off');

        // Recreate transactions table with debt types support
        DB::statement('
            CREATE TABLE "transactions_new" (
                "id" integer primary key autoincrement not null,
                "type" varchar check ("type" in (\'income\', \'expense\', \'transfer\', \'debt_payment\', \'debt_collection\')) not null,
                "account_id" integer not null,
                "to_account_id" integer,
                "category_id" integer,
                "amount" numeric not null,
                "to_amount" numeric,
                "exchange_rate" numeric,
                "description" varchar,
                "date" date not null,
                "created_at" datetime,
                "updated_at" datetime,
                foreign key("account_id") references "accounts"("id"),
                foreign key("to_account_id") references "accounts"("id"),
                foreign key("category_id") references "categories"("id")
            )
        ');

        // Copy existing data
        DB::statement('INSERT INTO transactions_new SELECT * FROM transactions');

        Schema::drop('transactions');

        DB::statement('ALTER TABLE transactions_new RENAME TO transactions');

        // Recreate indexes
        DB::statement('CREATE INDEX "transactions_date_index" ON "transactions" ("date")');
        DB::statement('CREATE INDEX "transactions_type_index" ON "transactions" ("type")');

        DB::statement('PRAGMA foreign_keys=on');
    }

    public function down(): void
    {
        DB::statement('PRAGMA foreign_keys=off');

        DB::statement('
            CREATE TABLE "transactions_old" (
                "id" integer primary key autoincrement not null,
                "type" varchar check ("type" in (\'income\', \'expense\', \'transfer\')) not null,
                "account_id" integer not null,
                "to_account_id" integer,
                "category_id" integer,
                "amount" numeric not null,
                "to_amount" numeric,
                "exchange_rate" numeric,
                "description" varchar,
                "date" date not null,
                "created_at" datetime,
                "updated_at" datetime,
                foreign key("account_id") references "accounts"("id"),
                foreign key("to_account_id") references "accounts"("id"),
                foreign key("category_id") references "categories"("id")
            )
        ');

        DB::statement('
            INSERT INTO transactions_old
            SELECT * FROM transactions
            WHERE type IN (\'income\', \'expense\', \'transfer\')
        ');

        Schema::drop('transactions');

        DB::statement('ALTER TABLE transactions_old RENAME TO transactions');

        DB::statement('CREATE INDEX "transactions_date_index" ON "transactions" ("date")');
        DB::statement('CREATE INDEX "transactions_type_index" ON "transactions" ("type")');

        DB::statement('PRAGMA foreign_keys=on');
    }
};
