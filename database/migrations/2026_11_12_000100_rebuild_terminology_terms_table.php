<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staging first ran an earlier uk-UA / ru-UA-only version of
     * 2026_11_06_000100_create_terminology_terms_table (column approved_term,
     * no localized_term / review_status). Laravel will not re-run a migration
     * with the same name, so rebuild the table here when the old layout is
     * found. The table only holds rows written by terms:import, which
     * recreates them on the next run. On a fresh database this does nothing.
     */
    public function up(): void
    {
        if (! Schema::hasTable('terminology_terms') || ! Schema::hasColumn('terminology_terms', 'approved_term')) {
            return;
        }

        Schema::drop('terminology_terms');

        Schema::create('terminology_terms', function (Blueprint $table) {
            $table->id();
            $table->string('dictionary_id', 60);
            $table->string('dictionary_version', 10);
            $table->string('term_key', 60);
            $table->string('source_term_en');
            $table->string('locale', 10);
            $table->string('localized_term');
            $table->string('status', 20);
            $table->string('review_status', 20);
            $table->string('reviewer')->nullable();
            $table->string('approved_by')->nullable();
            $table->date('effective_on')->nullable();
            $table->text('notes')->nullable();
            $table->string('source_package')->nullable();
            $table->timestamps();

            $table->unique(['dictionary_version', 'term_key', 'locale']);
            $table->index(['term_key', 'locale']);
        });
    }

    public function down(): void
    {
        // Nothing to undo: the table now matches 2026_11_06_000100, which owns it.
    }
};
