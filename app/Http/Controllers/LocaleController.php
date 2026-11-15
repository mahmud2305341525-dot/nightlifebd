<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale)
    {
        abort_unless(in_array($locale, ['en', 'bn'], true), 404);
        $request->session()->put('locale', $locale);
        if ($request->user()) {
            $request->user()->update(['locale' => $locale]);
        }

        return back();
    }
}
