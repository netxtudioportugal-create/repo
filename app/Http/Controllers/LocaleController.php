<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(array_keys(config('examprep.locales')))],
        ]);

        $request->session()->put('locale', $data['locale']);
        $request->attributes->get('learner')?->update(['locale' => $data['locale']]);

        return back();
    }
}
