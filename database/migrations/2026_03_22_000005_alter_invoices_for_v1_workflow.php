<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'due_date')) {
                $table->date('due_date')->nullable()->after('issued_at');
            }
            if (!Schema::hasColumn('invoices', 'deduction_amount')) {
                $table->decimal('deduction_amount', 12, 2)->default(0)->after('total_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'due_date')) {
                $table->dropColumn('due_date');
            }
            if (Schema::hasColumn('invoices', 'deduction_amount')) {
                $table->dropColumn('deduction_amount');
            }
        });
    }
};
