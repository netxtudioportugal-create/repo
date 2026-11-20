<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ethics Pilot v3.8 data model.
     *
     * - items: the English master. master_version is declared by the import
     *   file; when it rises, language records made from an older master turn
     *   stale. Legacy 2026-10-10 records start at master_version 1.
     * - item_translations: one language record per item + locale + version
     *   (natural key). en-US holds the master text. Other locales carry the
     *   master version they were made from and a language review status.
     * - tasks: source of the task outline. official_wording stays NULL (IP hold).
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('master_version')->default(1);
            $table->char('target_misconception', 1)->nullable(); // option label, master-owned
            $table->string('key_concept')->nullable();
            $table->string('question_type', 20)->nullable();
            $table->unsignedTinyInteger('difficulty_author')->nullable();
            $table->string('authority_source')->nullable();
            $table->string('authority_version', 60)->nullable();
            $table->date('source_checked_on')->nullable();
            $table->boolean('is_rule_dependent')->default(false);
            $table->boolean('requires_reverification')->default(false);
            $table->unsignedSmallInteger('cadence_months')->nullable();
            // Review fields. Created with these defaults; an import never changes them.
            $table->string('ip_clearance', 20)->default('pending');
            $table->boolean('source_review_hold')->default(false);
            // Step number of the last import file applied; an older step re-run never undoes a newer one.
            $table->unsignedInteger('import_sequence')->nullable();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->text('official_wording')->nullable(); // always NULL while the IP hold stands
            $table->string('source_version', 60)->nullable();
            $table->string('source_url')->nullable();
        });

        Schema::table('item_translations', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('source_master_version')->default(1);
            $table->string('language_status', 20)->nullable(); // NULL on en-US; draft | in_language_review | approved | stale
            $table->string('language_reviewer')->nullable();
            $table->date('language_reviewed_on')->nullable();
            $table->text('language_notes')->nullable();
            // Set when a reviewer role, not an import, decided the status.
            $table->foreignId('status_set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('import_sequence')->nullable();
        });

        DB::table('item_translations')->where('locale', '!=', 'en-US')
            ->update(['language_status' => DB::raw("case when review_status = 'reviewed' then 'approved' else 'draft' end")]);

        Schema::table('item_translations', function (Blueprint $table) {
            $table->dropUnique(['item_id', 'locale']);
            $table->unique(['item_id', 'locale', 'version']);
        });

        Schema::table('item_translations', function (Blueprint $table) {
            $table->dropColumn('review_status');
        });
    }

    public function down(): void
    {
        Schema::table('item_translations', function (Blueprint $table) {
            $table->string('review_status', 20)->default('draft');
            $table->dropUnique(['item_id', 'locale', 'version']);
        });
        Schema::table('item_translations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_set_by');
            $table->dropColumn(['version', 'source_master_version', 'language_status', 'language_reviewer', 'language_reviewed_on', 'language_notes', 'import_sequence']);
        });
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn(['official_wording', 'source_version', 'source_url']));
        Schema::table('items', fn (Blueprint $table) => $table->dropColumn([
            'master_version', 'target_misconception', 'key_concept', 'question_type', 'difficulty_author', 'authority_source',
            'authority_version', 'source_checked_on', 'is_rule_dependent', 'requires_reverification', 'cadence_months',
            'ip_clearance', 'source_review_hold', 'import_sequence',
        ]));
    }
};
