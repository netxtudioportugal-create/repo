<?php

namespace Tests;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\Learner;
use App\Services\ItemImporter;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TaxonomySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function seedBank(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    /** Tasks and domains only, for tests that import files step by step. */
    protected function seedTaxonomy(): void
    {
        $this->seed(TaxonomySeeder::class);
    }

    protected function readJson(string $path): array
    {
        return json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    protected function pilotStep(int $step): array
    {
        return $this->readJson(config('examprep.pilot.path')."/step-{$step}.json");
    }

    protected function importFile(array $file, bool $dryRun = false): array
    {
        return app(ItemImporter::class)->import($file, $dryRun);
    }

    protected function release(string ...$codes): void
    {
        Item::whereIn('code', $codes)->update(['status' => ItemStatus::Released->value]);
    }

    protected function learner(): Learner
    {
        $learner = Learner::create(['locale' => 'en-US', 'acknowledged_at' => now()]);
        $this->withCookie(config('examprep.learner_cookie'), $learner->id);

        return $learner;
    }
}
