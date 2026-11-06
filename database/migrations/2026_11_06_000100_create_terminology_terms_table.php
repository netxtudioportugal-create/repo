<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Controlled ABA terminology dictionary (uk-UA, ru-UA).
     *
     * One row per concept key, locale and dictionary version. Approved terms are
     * never overwritten in place: a change needs a new dictionary_version, so
     * earlier versions stay on record.
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
            $table->string('approved_term');
            $table->string('status', 20)->default('approved');
            $table->date('effective_on');
            $table->string('reviewer');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['dictionary_version', 'term_key', 'locale']);
            $table->index(['term_key', 'locale']);
        });

        // Gate 0 freezes the locale set: ru-RU is not valid anywhere. The
        // dictionary table is new, but older content tables are cleaned too.
        DB::table('item_translations')->where('locale', 'ru-RU')->delete();
        DB::table('learners')->where('locale', 'ru-RU')->update(['locale' => 'ru-UA']);
    }

    public function down(): void
    {
        Schema::dropIfExists('terminology_terms');
    }
};
