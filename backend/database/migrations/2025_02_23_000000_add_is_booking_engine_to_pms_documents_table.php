<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_documents', function (Blueprint $table) {
            $table->boolean('is_booking_engine')->default(false)->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('pms_documents', function (Blueprint $table) {
            $table->dropColumn('is_booking_engine');
        });
    }
};
