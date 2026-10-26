<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Six content domains of the RBT Test Content Outline (3rd ed.).
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->char('code', 1)->unique();
            $table->json('names');            // {"en": "...", "pt": "...", ...}
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Internal task labels only. Official task wording is never stored (IP hold).
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10)->unique(); // e.g. F.1
            $table->json('labels');
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();  // e.g. A-01, F-05-001-R
            $table->foreignId('domain_id')->constrained();
            $table->foreignId('task_id')->nullable()->constrained();
            $table->unsignedInteger('version')->default(1);
            $table->string('status', 20)->default('draft')->index();
            $table->char('key', 1);                // canonical keyed option, A-D. Never sent to the browser before grading.
            $table->string('source_ref')->nullable(); // e.g. RBT Ethics Code (2.0) standard
            $table->char('content_hash', 64);
            $table->timestamps();
        });

        Schema::create('item_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 10);
            $table->text('stem');
            $table->json('options');               // {"A": "...", "B": "...", "C": "...", "D": "..."} canonical labels
            $table->text('rationale');
            $table->json('option_rationales')->nullable(); // per distractor, canonical labels
            $table->string('review_status', 20)->default('draft'); // draft | reviewed
            $table->timestamps();
            $table->unique(['item_id', 'locale']);
        });

        // Immutable snapshot of every version an item has had.
        Schema::create('item_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->char('content_hash', 64);
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['item_id', 'version']);
        });

        Schema::create('item_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('item_version');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_status_changes');
        Schema::dropIfExists('item_versions');
        Schema::dropIfExists('item_translations');
        Schema::dropIfExists('items');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('domains');
    }
};
