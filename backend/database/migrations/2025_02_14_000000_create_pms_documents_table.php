<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_documents', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->json('analysis_result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_documents');
    }
};
