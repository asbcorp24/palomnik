<?php

namespace App\Http\Controllers;

use App\Models\SiteContentSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SiteContentSettingController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'brand_name' => ['nullable', 'string', 'max:120'],
            'header_tagline' => ['nullable', 'string', 'max:180'],
        ]);

        $current = SiteContentSetting::values();
        SiteContentSetting::put(array_replace($current, $data));

        return back()->with('success', 'Внешний вид и тексты сайта сохранены.');
    }
}
