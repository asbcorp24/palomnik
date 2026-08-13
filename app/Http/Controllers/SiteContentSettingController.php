<?php

namespace App\Http\Controllers;

use App\Models\SiteContentSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SiteContentSettingController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (array_keys(SiteContentSetting::DEFAULTS) as $key) {
            $rules[$key] = ['nullable', 'string', 'max:1500'];
        }

        $data = $request->validate($rules);
        SiteContentSetting::put(array_replace(SiteContentSetting::values(), $data));

        return back()->with('success', 'Внешний вид и тексты сайта сохранены.');
    }
}
