<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pseudonymous candidate: a random UUID held in an encrypted cookie. No name, no email.
        Schema::create('learners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('locale', 10)->default('en');
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('practice_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('learner_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 10);
            $table->foreignId('domain_id')->nullable()->constrained();
            $table->boolean('is_staff_test')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('ends_at')->nullable();   // timed mode only, enforced server-side
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('session_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('practice_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->unsignedInteger('item_version');
            $table->unsignedSmallInteger('position');
            $table->char('option_order', 4);          // display order in canonical labels, e.g. "CABD"
            $table->timestamp('shown_at')->nullable();
            $table->char('selected_option', 1)->nullable();
            $table->boolean('is_correct')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('checked_at')->nullable(); // domain mode: feedback revealed
            $table->unique(['practice_session_id', 'position']);
        });

        // Append-only response log (Pilot v3.2, Section 8 / Import Checklist).
        Schema::create('responses', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('learner_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('practice_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained();
            $table->unsignedInteger('item_version');
            $table->char('selected_option', 1);
            $table->boolean('is_correct');
            $table->char('option_order_key', 4);
            $table->unsignedInteger('response_time_ms');
            $table->boolean('is_staff_test')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['item_id', 'item_version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responses');
        Schema::dropIfExists('session_items');
        Schema::dropIfExists('practice_sessions');
        Schema::dropIfExists('learners');
    }
};
