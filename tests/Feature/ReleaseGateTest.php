<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Enums\StaffRole;
use App\Models\Item;
use App\Models\User;
use Tests\TestCase;

class ReleaseGateTest extends TestCase
{
    private function advance(User $user, Item $item, string $direction = 'forward', ?string $note = null)
    {
        return $this->actingAs($user)->post(route('staff.items.transition', $item), [
            'direction' => $direction,
            'version' => $item->fresh()->version,
            'note' => $note,
        ]);
    }

    public function test_each_step_needs_its_role_and_release_waits_for_gate_zero(): void
    {
        $this->seedBank();
        $item = Item::where('code', 'C-02')->first();
        $tester = User::factory()->create();
        $bcba = User::factory()->role(StaffRole::BcbaReviewer)->create();
        $fair = User::factory()->role(StaffRole::FairnessReviewer)->create();
        $admin = User::factory()->role(StaffRole::Admin)->create();

        $this->advance($tester, $item);
        $this->assertSame(ItemStatus::BcbaReview, $item->fresh()->status);

        $this->advance($tester, $item)->assertForbidden();
        $this->advance($bcba, $item);
        $this->assertSame(ItemStatus::FairnessReview, $item->fresh()->status);

        $this->advance($fair, $item);
        $this->assertSame(ItemStatus::Approved, $item->fresh()->status);

        config(['examprep.release_gate_open' => false]);
        $this->advance($admin, $item)->assertSessionHasErrors('status');
        $this->assertSame(ItemStatus::Approved, $item->fresh()->status);

        config(['examprep.release_gate_open' => true]);
        $this->advance($admin, $item);
        $this->assertSame(ItemStatus::Released, $item->fresh()->status);
        $this->assertSame(5, $item->statusChanges()->count()); // import + 4 steps
    }

    public function test_sending_back_needs_a_note(): void
    {
        $this->seedBank();
        $item = Item::where('code', 'C-02')->first();
        $item->update(['status' => ItemStatus::BcbaReview]);
        $bcba = User::factory()->role(StaffRole::BcbaReviewer)->create();

        $this->advance($bcba, $item, 'draft')->assertSessionHasErrors('note');
        $this->advance($bcba, $item, 'draft', 'Distractor C is also defensible.');

        $this->assertSame(ItemStatus::Draft, $item->fresh()->status);
    }

    public function test_review_area_requires_sign_in(): void
    {
        $this->get(route('staff.items.index'))->assertRedirect(route('staff.login'));
    }
}
