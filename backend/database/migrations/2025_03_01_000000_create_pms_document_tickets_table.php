<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_document_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pms_document_id')->constrained()->cascadeOnDelete();
            $table->string('issue_id');
            $table->string('issue_url');
            $table->string('issue_status')->nullable();
            $table->string('issue_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_document_tickets');
    }
};
