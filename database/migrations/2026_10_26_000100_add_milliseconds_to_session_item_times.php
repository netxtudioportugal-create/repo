<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // response_time_ms is shown_at → answered_at; whole-second columns made it 1 or a multiple of 1000.
        Schema::table('session_items', function (Blueprint $table) {
            $table->timestamp('shown_at', 3)->nullable()->change();
            $table->timestamp('answered_at', 3)->nullable()->change();
            $table->timestamp('checked_at', 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('session_items', function (Blueprint $table) {
            $table->timestamp('shown_at')->nullable()->change();
            $table->timestamp('answered_at')->nullable()->change();
            $table->timestamp('checked_at')->nullable()->change();
        });
    }
};
