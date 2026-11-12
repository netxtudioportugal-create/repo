<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1, step 1 of the Ethics Pilot import (Checklist v1.7, Section 1).
     *
     * The locale set is exactly en-US, pt-BR, es-419, uk-UA, ru-UA. Earlier
     * codes are renamed in place (es-LATAM to es-419, ru-RU to ru-UA, and the
     * short app codes en, pt, es, uk). Nothing is created or deleted: row
     * counts before must equal row counts after, and item ids do not change.
     */
    private const MAP = [
        'en' => 'en-US',
        'pt' => 'pt-BR',
        'es' => 'es-419',
        'es-LATAM' => 'es-419',
        'uk' => 'uk-UA',
        'ru-RU' => 'ru-UA',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $before = $this->counts();

            foreach (self::MAP as $from => $to) {
                $clash = DB::table('item_translations as a')
                    ->join('item_translations as b', 'a.item_id', '=', 'b.item_id')
                    ->where('a.locale', $from)->where('b.locale', $to)->count();
                if ($clash > 0) {
                    throw new RuntimeException("Locale migration stopped: {$clash} item(s) have both {$from} and {$to} records. Resolve them by hand; nothing was changed.");
                }

                DB::table('item_translations')->where('locale', $from)->update(['locale' => $to]);
                DB::table('learners')->where('locale', $from)->update(['locale' => $to]);
            }

            foreach (['domains' => 'names', 'tasks' => 'labels'] as $table => $column) {
                foreach (DB::table($table)->get(['id', $column]) as $row) {
                    $values = json_decode($row->{$column}, true) ?: [];
                    $renamed = [];
                    foreach ($values as $locale => $text) {
                        $renamed[self::MAP[$locale] ?? $locale] ??= $text;
                    }
                    if ($renamed !== $values) {
                        DB::table($table)->where('id', $row->id)->update([$column => json_encode($renamed, JSON_UNESCAPED_UNICODE)]);
                    }
                }
            }

            if ($this->counts() !== $before) {
                throw new RuntimeException('Locale migration stopped: row counts changed. Nothing was changed.');
            }
        });

        Schema::table('learners', function (Blueprint $table) {
            $table->string('locale', 10)->default('en-US')->change();
        });
    }

    public function down(): void
    {
        // One way: the earlier codes are not valid any more.
    }

    private function counts(): array
    {
        return [
            'items' => DB::table('items')->count(),
            'item_translations' => DB::table('item_translations')->count(),
            'learners' => DB::table('learners')->count(),
            'domains' => DB::table('domains')->count(),
            'tasks' => DB::table('tasks')->count(),
        ];
    }
};
