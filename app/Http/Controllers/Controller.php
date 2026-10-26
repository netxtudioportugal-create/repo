<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use Illuminate\Http\Request;

abstract class Controller
{
    protected function learner(Request $request): Learner
    {
        $learner = $request->attributes->get('learner');
        abort_unless($learner instanceof Learner, 403);

        return $learner;
    }

    /** Signed-in staff see the draft test view; everyone else sees released items only. */
    protected function isStaff(Request $request): bool
    {
        return $request->user() !== null;
    }
}
