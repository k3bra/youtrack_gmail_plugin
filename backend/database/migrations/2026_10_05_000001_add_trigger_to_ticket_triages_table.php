<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_triages', function (Blueprint $table) {
            // manual = ai button / API, ai-fix = picked up automatically from the YouTrack tag.
            $table->string('trigger')->default('manual')->after('issue_id');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_triages', function (Blueprint $table) {
            $table->dropColumn('trigger');
        });
    }
};
