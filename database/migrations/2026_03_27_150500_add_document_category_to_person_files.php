<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE person_files MODIFY COLUMN category ENUM('id_document','passport','contract','other','document') DEFAULT 'document'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE person_files MODIFY COLUMN category ENUM('id_document','passport','contract','other') DEFAULT 'id_document'");
    }
};
