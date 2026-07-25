<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarded
{
    /**
     * Cegah user melewati onboarding via direct URL.
     *
     * PENGECUALIAN:
     * - super_admin dan admin BEBAS total (tidak perlu wa_number / profile)
     * - Route onboarding.* tidak di-block (cegah redirect loop)
     *
     * CEK untuk user biasa:
     * - profile belum ada di tabel user_profiles
     * - wa_number masih null / kosong
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Tidak login — biarkan middleware auth yang handle
        if (! $user) {
            return $next($request);
        }

        // Super Admin dan Admin BEBAS dari onboarding
        if (in_array($user->role, ['super_admin', 'admin'])) {
            return $next($request);
        }

        // Cegah redirect loop — onboarding routes selalu lolos
        if ($request->routeIs('onboarding.*')) {
            return $next($request);
        }

        // Cek kelengkapan onboarding untuk user biasa
        $profileMissing = $user->profile === null;
        $waNumberMissing = empty($user->wa_number);

        if ($profileMissing || $waNumberMissing) {
            return redirect()
                ->route('onboarding.step1')
                ->with('error', 'Silakan selesaikan pengaturan awal akun kamu terlebih dahulu.');
        }

        return $next($request);
    }
}
