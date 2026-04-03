<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_code')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('quoted_amount', 12, 2)->nullable();
            $table->text('scope_summary')->nullable();
            $table->string('manual_reference_number')->nullable();
            $table->string('document_url')->nullable();
            $table->enum('status', ['draft', 'uploaded', 'accepted', 'cancelled'])->default('uploaded');
            $table->date('quoted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
