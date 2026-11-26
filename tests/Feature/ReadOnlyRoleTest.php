<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Enums\StaffRole;
use App\Models\Item;
use App\Models\Learner;
use App\Models\PracticeSession;
use App\Models\User;
use Tests\TestCase;

class ReadOnlyRoleTest extends TestCase
{
    private function viewer(): User
    {
        return User::factory()->role(StaffRole::Viewer)->create();
    }

    public function test_viewer_sees_items_statuses_versions_and_screens(): void
    {
        $this->seedBank();
        $this->actingAs($this->viewer());
        $item = Item::where('code', 'A-02')->first();

        $this->get(route('staff.items.index'))->assertOk()->assertSee('A-02');
        $this->get(route('staff.items.show', $item))
            ->assertOk()
            ->assertSee('Read-only access')
            ->assertSee('Versions')
            ->assertDontSee('name="direction"', false);
        $this->get(route('staff.items.preview', [$item, 'locale' => 'pt-BR']))->assertOk()->assertSee('Nothing on this screen is saved');
        $this->get(route('staff.items.preview', [$item, 'reveal' => 1]))->assertOk()->assertSee('opt good', false);
    }

    public function test_preview_writes_nothing(): void
    {
        $this->seedBank();
        $this->actingAs($this->viewer());

        $this->get(route('staff.items.preview', Item::where('code', 'A-02')->first()));

        $this->assertSame(0, PracticeSession::count());
        $this->assertDatabaseCount('session_items', 0);
    }

    public function test_viewer_cannot_move_items_through_any_gate(): void
    {
        $this->seedBank();
        $viewer = $this->viewer();
        $item = Item::where('code', 'A-02')->first();

        foreach ([ItemStatus::Draft, ItemStatus::BcbaReview] as $status) {
            $item->update(['status' => $status]);
            $this->actingAs($viewer)->post(route('staff.items.transition', $item), [
                'direction' => 'forward', 'version' => $item->version,
            ])->assertForbidden();
            $this->actingAs($viewer)->post(route('staff.items.transition', $item), [
                'direction' => 'draft', 'version' => $item->version, 'note' => 'x',
            ])->assertForbidden();
            $this->assertSame($status, $item->fresh()->status);
        }
    }

    public function test_viewer_cannot_see_the_response_log(): void
    {
        $this->actingAs($this->viewer())->get(route('staff.notes'))->assertForbidden();
    }

    public function test_viewer_cannot_create_candidate_data(): void
    {
        $this->seedBank();
        $this->actingAs($this->viewer());

        $this->post(route('onboarding.store'), ['ack' => '1'])->assertForbidden();
        $this->assertSame(0, Learner::count());

        $this->learner();
        $this->post(route('practice.domain', 'A'))->assertForbidden();
        $this->post(route('practice.timed'))->assertForbidden();
        $this->assertSame(0, PracticeSession::count());
    }

    public function test_tester_still_acts_but_viewer_does_not(): void
    {
        $tester = User::factory()->create();

        $this->assertTrue($tester->hasRole(null));
        $this->assertFalse($this->viewer()->hasRole(null));
        $this->assertFalse($this->viewer()->hasRole('admin'));
    }
}
