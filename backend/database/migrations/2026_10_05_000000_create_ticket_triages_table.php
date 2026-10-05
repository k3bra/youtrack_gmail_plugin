<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_triages', function (Blueprint $table) {
            $table->id();
            $table->string('issue_id')->index();
            $table->string('status')->default('queued');
            $table->string('verdict')->nullable();
            $table->string('confidence')->nullable();
            $table->json('analysis')->nullable();
            $table->string('session_id')->nullable();
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
        Schema::dropIfExists('ticket_triages');
    }
};
