<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemResponse;
use App\Models\Learner;
use App\Models\PracticeSession;
use App\Models\User;
use Tests\TestCase;

class PracticeFlowTest extends TestCase
{
    public function test_onboarding_comes_first(): void
    {
        $this->get('/')->assertRedirect(route('onboarding'));

        $this->post(route('onboarding.store'), ['ack' => '1'])
            ->assertRedirect(route('home'))
            ->assertCookie(config('examprep.learner_cookie'));

        $this->assertSame(1, Learner::whereNotNull('acknowledged_at')->count());
    }

    public function test_candidates_see_only_released_items(): void
    {
        $this->seedBank();
        $this->learner();

        $this->get('/')->assertOk()->assertSee(__('app.emptyTitle'));

        $this->release('A-01', 'A-02');
        $this->get('/')->assertOk()->assertSee(__('app.answered', ['n' => 0, 't' => 2]));
    }

    public function test_key_and_rationale_stay_on_the_server_until_checked(): void
    {
        $this->seedBank();
        $this->release('A-02');
        $this->learner();

        $this->post(route('practice.domain', 'A'));
        $session = PracticeSession::firstOrFail();
        $rationale = Item::where('code', 'A-02')->first()->translations->firstWhere('locale', 'en')->rationale;

        $this->get(route('practice.question', [$session, 1]))
            ->assertOk()
            ->assertDontSee($rationale)
            ->assertDontSee('opt good');

        $this->post(route('practice.answer', [$session, 1]), ['action' => 'check', 'option' => 'B'])
            ->assertRedirect(route('practice.question', [$session, 1]));

        $this->get(route('practice.question', [$session, 1]))
            ->assertSee($rationale)
            ->assertSee(__('app.incorrect'));

        $log = ItemResponse::sole();
        $this->assertSame('B', $log->selected_option);
        $this->assertFalse($log->is_correct);
        $this->assertSame($session->sessionItems()->first()->option_order, $log->option_order_key);
    }

    public function test_a_checked_answer_cannot_be_changed(): void
    {
        $this->seedBank();
        $this->release('A-02');
        $this->learner();
        $this->post(route('practice.domain', 'A'));
        $session = PracticeSession::firstOrFail();

        $this->post(route('practice.answer', [$session, 1]), ['action' => 'check', 'option' => 'B']);
        $this->post(route('practice.answer', [$session, 1]), ['action' => 'check', 'option' => 'A'])
            ->assertSessionHasErrors('option');

        $this->assertFalse($session->sessionItems()->first()->is_correct);
    }

    public function test_sessions_belong_to_their_learner(): void
    {
        $this->seedBank();
        $this->release('A-01');
        $this->learner();
        $this->post(route('practice.domain', 'A'));
        $session = PracticeSession::firstOrFail();

        $this->learner(); // a different cookie
        $this->get(route('practice.question', [$session, 1]))->assertNotFound();
    }

    public function test_timed_set_is_enforced_on_the_server(): void
    {
        $this->seedBank();
        $this->release('A-01', 'A-02', 'B-01');
        $this->learner();

        $this->post(route('practice.timed'));
        $session = PracticeSession::firstOrFail();
        $this->assertEquals(4, (int) round($session->started_at->diffInMinutes($session->ends_at)));

        $this->post(route('practice.answer', [$session, 1]), ['action' => 'next', 'option' => 'A']);
        $this->assertSame(0, ItemResponse::count()); // logged on submit, not on every click

        $this->travel(5)->minutes();
        $this->post(route('practice.answer', [$session, 2]), ['action' => 'next', 'option' => 'A'])
            ->assertRedirect(route('practice.results', $session));

        $session->refresh();
        $this->assertNotNull($session->finished_at);
        $this->assertSame(1, ItemResponse::count());
        $this->assertNull($session->sessionItems()->where('position', 2)->value('selected_option'));
    }

    public function test_clearing_data_deletes_learner_sessions_and_responses(): void
    {
        $this->seedBank();
        $this->release('A-01');
        $learner = $this->learner();
        $this->post(route('practice.domain', 'A'));
        $session = PracticeSession::firstOrFail();
        $this->post(route('practice.answer', [$session, 1]), ['action' => 'check', 'option' => 'A']);

        $this->delete(route('about.clear'))->assertRedirect(route('onboarding'));

        $this->assertNull(Learner::find($learner->id));
        $this->assertSame(0, PracticeSession::count());
        $this->assertSame(0, ItemResponse::count());
    }

    public function test_staff_test_view_sees_drafts_and_is_flagged(): void
    {
        $this->seedBank();
        $this->learner();
        $this->actingAs(User::factory()->create());

        $this->get('/')->assertOk()->assertSee(__('app.testView'));
        $this->post(route('practice.domain', 'F'));

        $this->assertTrue(PracticeSession::sole()->is_staff_test);
    }

    public function test_untranslated_items_fall_back_to_english_with_a_note(): void
    {
        $this->seedBank();
        $this->release('F-01-001');
        $this->learner();
        $this->post(route('locale'), ['locale' => 'pt']);
        $this->post(route('practice.domain', 'F'));
        $session = PracticeSession::firstOrFail();

        $this->get(route('practice.question', [$session, 1]))
            ->assertSee(__('app.pending', [], 'pt'))
            ->assertSee('lang="en"', false);
    }
}
