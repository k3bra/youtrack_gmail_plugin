<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_fixes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_triage_id')->constrained()->cascadeOnDelete();
            $table->string('issue_id')->index();
            $table->string('status')->default('queued');
            $table->string('repo')->nullable();
            $table->string('branch')->nullable();
            $table->string('commit_sha')->nullable();
            $table->string('pr_url')->nullable();
            $table->json('result')->nullable();
            $table->string('model')->nullable();
            $table->decimal('cost_usd', 8, 4)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('num_turns')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_fixes');
    }
};
