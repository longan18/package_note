<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * Class LocaleController.
 */
class LocaleController
{
    /**
     * @param $locale
     * @return RedirectResponse
     */
    public function change($locale): RedirectResponse
    {
        app()->setLocale($locale);

        session()->put('locale', $locale);

        return redirect()->back();
    }
}