<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email', 'role'),
                'subscription' => $request->user()?->subscription?->only('plan', 'status', 'trial_ends_at'),
            ],
            'branding' => [
                'app_name' => $request->user()?->profile?->app_name ?? config('app.name'),
                'app_logo' => $request->user()?->profile?->app_logo_url,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
            'unread_notifications' => $request->user()
                ?->appNotifications()->where('is_read', false)->count() ?? 0,
            'icons' => Cache::remember('app_icons_map', 300, function () {
                return collect(\App\Http\Controllers\Admin\IconController::SLOTS)->keys()->mapWithKeys(function ($slug) {
                    $path = \App\Models\SystemSetting::get("icon:{$slug}");
                    return [$slug => $path ? \Illuminate\Support\Facades\Storage::url($path) : null];
                })->toArray();
            }),
        ]);
    }
}
