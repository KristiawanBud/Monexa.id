<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Admin\IconController;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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
        /** @var User|null $user */
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user?->only('id', 'name', 'email', 'role'),
                'subscription' => $user?->subscription?->only('plan', 'status', 'trial_ends_at'),
            ],
            'branding' => [
                'app_name' => $user?->profile?->app_name ?? config('app.name'),
                'app_logo' => $user?->profile?->app_logo_url,
            ],
            'theme' => $user?->profile?->theme,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'unread_notifications' => $user
                ?->appNotifications()->where('is_read', false)->count() ?? 0,
            'icons' => Cache::remember('app_icons_map', 300, function () {
                return collect(IconController::SLOTS)->keys()->mapWithKeys(function ($slug) {
                    $path = SystemSetting::get("icon:{$slug}");

                    return [$slug => $path ? Storage::url($path) : null];
                })->toArray();
            }),
        ]);
    }
}
