<?php

namespace Tests;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\Learner;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function seedBank(): void
    {
        $this->seed(DatabaseSeeder::class);
    }

    protected function release(string ...$codes): void
    {
        Item::whereIn('code', $codes)->update(['status' => ItemStatus::Released->value]);
    }

    protected function learner(): Learner
    {
        $learner = Learner::create(['locale' => 'en', 'acknowledged_at' => now()]);
        $this->withCookie(config('examprep.learner_cookie'), $learner->id);

        return $learner;
    }
}
