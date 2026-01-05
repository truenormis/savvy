<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('PRAGMA foreign_keys=off');

        // Recreate accounts table with debt support
        DB::statement('
            CREATE TABLE "accounts_new" (
                "id" integer primary key autoincrement not null,
                "name" varchar not null,
                "type" varchar check ("type" in (\'bank\', \'crypto\', \'cash\', \'debt\')) not null,
                "debt_type" varchar check ("debt_type" in (\'i_owe\', \'owed_to_me\') OR "debt_type" IS NULL),
                "currency_id" integer not null,
                "initial_balance" numeric not null default \'0\',
                "target_amount" numeric,
                "due_date" date,
                "is_paid_off" tinyint(1) not null default \'0\',
                "counterparty" varchar,
                "debt_description" text,
                "is_active" tinyint(1) not null default \'1\',
                "created_at" datetime,
                "updated_at" datetime,
                foreign key("currency_id") references "currencies"("id")
            )
        ');

        // Copy existing data
        DB::statement('
            INSERT INTO accounts_new (id, name, type, currency_id, initial_balance, is_active, created_at, updated_at)
            SELECT id, name, type, currency_id, initial_balance, is_active, created_at, updated_at
            FROM accounts
        ');

        Schema::drop('accounts');

        DB::statement('ALTER TABLE accounts_new RENAME TO accounts');

        // Create indexes
        DB::statement('CREATE INDEX "accounts_type_debt_type_index" ON "accounts" ("type", "debt_type")');
        DB::statement('CREATE INDEX "accounts_is_paid_off_index" ON "accounts" ("is_paid_off")');

        DB::statement('PRAGMA foreign_keys=on');
    }

    public function down(): void
    {
        DB::statement('PRAGMA foreign_keys=off');

        DB::statement('
            CREATE TABLE "accounts_old" (
                "id" integer primary key autoincrement not null,
                "name" varchar not null,
                "type" varchar check ("type" in (\'bank\', \'crypto\', \'cash\')) not null,
                "currency_id" integer not null,
                "initial_balance" numeric not null default \'0\',
                "is_active" tinyint(1) not null default \'1\',
                "created_at" datetime,
                "updated_at" datetime,
                foreign key("currency_id") references "currencies"("id")
            )
        ');

        DB::statement('
            INSERT INTO accounts_old (id, name, type, currency_id, initial_balance, is_active, created_at, updated_at)
            SELECT id, name, type, currency_id, initial_balance, is_active, created_at, updated_at
            FROM accounts
            WHERE type != \'debt\'
        ');

        Schema::drop('accounts');

        DB::statement('ALTER TABLE accounts_old RENAME TO accounts');

        DB::statement('PRAGMA foreign_keys=on');
    }
};
