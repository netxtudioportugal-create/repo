<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Controlled ABA terminology dictionary (flow_exam_prep_aba_terms).
     *
     * One row per concept key, locale and dictionary version, for the locale
     * set frozen at Gate 0: en-US, pt-BR, es-419, uk-UA, ru-UA. Controlled
     * terms are never overwritten in place: a change needs a new
     * dictionary_version, so earlier versions stay on record.
     *
     * status: canonical (en-US source) | staging (awaiting language review) | approved
     * review_status: not_applicable | required | completed
     */
    public function up(): void
    {
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
        Schema::dropIfExists('terminology_terms');
    }
};
