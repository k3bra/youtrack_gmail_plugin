<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('request_type');
            $table->string('email_subject')->nullable();
            $table->string('email_from')->nullable();
            $table->text('email_body')->nullable();
            $table->text('email_thread_url')->nullable();
            $table->string('ai_summary')->nullable();
            $table->text('ai_description')->nullable();
            $table->json('ai_labels')->nullable();
            $table->string('youtrack_issue_id')->nullable();
            $table->string('status');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_requests');
    }
};
