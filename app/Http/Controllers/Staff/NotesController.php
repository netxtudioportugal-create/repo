<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ItemResponse;

class NotesController extends Controller
{
    public function __invoke()
    {
        return view('staff.notes', [
            'responses' => ItemResponse::with('item:id,code')->latest('id')->limit(25)->get(),
            'total' => ItemResponse::count(),
            'candidateTotal' => ItemResponse::where('is_staff_test', false)->count(),
        ]);
    }
}
