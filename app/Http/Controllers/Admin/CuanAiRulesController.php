<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Services\CuanAiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CuanAiRulesController extends Controller
{
    public function index(): Response
    {
        $current = SystemSetting::get('cuan_ai_system_prompt');

        return Inertia::render('Admin/CuanAiRules', [
            'prompt'         => $current ?? CuanAiService::defaultPromptTemplate(),
            'is_custom'      => $current !== null,
            'default_prompt' => CuanAiService::defaultPromptTemplate(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'prompt' => ['required', 'string', 'max:8000'],
        ]);

        SystemSetting::set(
            'cuan_ai_system_prompt',
            $request->prompt,
            'System prompt / aturan CuanAI — diedit via Admin Panel'
        );

        return back()->with('success', 'Aturan CuanAI berhasil disimpan!');
    }

    public function reset(): RedirectResponse
    {
        SystemSetting::forget('cuan_ai_system_prompt');

        return back()->with('success', 'Aturan CuanAI dikembalikan ke default.');
    }
}
