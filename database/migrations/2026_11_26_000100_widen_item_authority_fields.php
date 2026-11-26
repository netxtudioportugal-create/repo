<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Domain B masters cite several sources in authority_source (up to about
     * 240 characters) and authority_version runs past 60 characters
     * ("Cooper 3rd ed. (2020); RBT Ethics Code (2.0), updated 08/2024").
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->text('authority_source')->nullable()->change();
            $table->string('authority_version', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('authority_source')->nullable()->change();
            $table->string('authority_version', 60)->nullable()->change();
        });
    }
};
