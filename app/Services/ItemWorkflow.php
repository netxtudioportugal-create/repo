<?php

namespace App\Services;

use App\Enums\ItemStatus;
use App\Models\Item;
use App\Models\ItemStatusChange;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Moves items through the release gates, one logged step at a time. */
class ItemWorkflow
{
    public function advance(Item $item, User $by, ?string $note = null): Item
    {
        $step = $item->status->forward();
        if ($step === null) {
            throw ValidationException::withMessages(['status' => 'This item has no further step.']);
        }

        [$to, $role] = $step;

        if (! $by->hasRole($role)) {
            throw new AuthorizationException("Only {$role} can take this step.");
        }

        if ($to === ItemStatus::Released && ! config('examprep.release_gate_open')) {
            throw ValidationException::withMessages([
                'status' => 'Release is closed until Gate 0 authorizes it (EXAMPREP_RELEASE_GATE_OPEN).',
            ]);
        }

        return $this->move($item, $to, $by, $note);
    }

    public function returnToDraft(Item $item, User $by, string $note): Item
    {
        if (! $item->status->canReturnToDraft()) {
            throw ValidationException::withMessages(['status' => 'This item cannot be returned to draft.']);
        }

        if (trim($note) === '') {
            throw ValidationException::withMessages(['note' => 'Say what needs to change.']);
        }

        return $this->move($item, ItemStatus::Draft, $by, $note);
    }

    private function move(Item $item, ItemStatus $to, User $by, ?string $note): Item
    {
        return DB::transaction(function () use ($item, $to, $by, $note) {
            $from = $item->status;
            $item->update(['status' => $to]);

            ItemStatusChange::create([
                'item_id' => $item->id,
                'user_id' => $by->id,
                'item_version' => $item->version,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'note' => $note,
            ]);

            return $item;
        });
    }
}
