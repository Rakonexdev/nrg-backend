<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            if (!Schema::hasColumn('collections', 'next_due_date')) {
                $table->date('next_due_date')->nullable()->after('collection_date');
            }
            if (!Schema::hasColumn('collections', 'settlement_status')) {
                $table->enum('settlement_status', ['unsettled', 'settled'])->default('unsettled')->after('verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            if (Schema::hasColumn('collections', 'next_due_date')) {
                $table->dropColumn('next_due_date');
            }
            if (Schema::hasColumn('collections', 'settlement_status')) {
                $table->dropColumn('settlement_status');
            }
        });
    }
};
