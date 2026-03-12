<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;

class LanguageController extends Controller
{
    public function switchLang($locale): RedirectResponse
    {
        if (in_array($locale, ['en', 'hi', 'es', 'fr', 'de'])) {
            Session::put('locale', $locale);
        }

        return back();
    }
}
