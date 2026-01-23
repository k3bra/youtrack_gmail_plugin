<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_documents', function (Blueprint $table) {
            $table->text('source_url')->nullable()->after('storage_path');
        });
    }

    public function down(): void
    {
        Schema::table('pms_documents', function (Blueprint $table) {
            $table->dropColumn('source_url');
        });
    }
};
