<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // Tampilkan halaman login
    // ─────────────────────────────────────────────────────────────
    public function show(): Response
    {
        return Inertia::render('Auth/Login');
    }

    // ─────────────────────────────────────────────────────────────
    // Proses login dengan proteksi rate limiting (max 5x / menit)
    // ─────────────────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Cek apakah sudah ter-throttle sebelum attempt
        $this->ensureIsNotRateLimited($request);

        if (! Auth::attempt(
            $request->only('email', 'password'),
            $request->boolean('remember')
        )) {
            // Catat percobaan gagal — decay 60 detik
            RateLimiter::hit($this->throttleKey($request), 60);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        // Login berhasil — bersihkan rate limit counter
        RateLimiter::clear($this->throttleKey($request));
        $request->session()->regenerate();

        $user = Auth::user();

        // Arahkan ke onboarding jika profile belum ada
        if (! $user->profile) {
            return redirect()->route('onboarding.step1');
        }

        return redirect()->intended(route('dashboard'));
    }

    // ─────────────────────────────────────────────────────────────
    // Logout
    // ─────────────────────────────────────────────────────────────
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    // ─────────────────────────────────────────────────────────────
    // STUB: Redirect ke Google OAuth
    // Uncomment setelah: composer require laravel/socialite
    // ─────────────────────────────────────────────────────────────
    public function redirectToGoogle(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        // return \Laravel\Socialite\Facades\Socialite::driver('google')->redirect();
        abort(501, 'Google OAuth belum dikonfigurasi.');
    }

    // ─────────────────────────────────────────────────────────────
    // STUB: Callback dari Google OAuth
    // ─────────────────────────────────────────────────────────────
    public function handleGoogleCallback(): RedirectResponse
    {
        // $googleUser = \Laravel\Socialite\Facades\Socialite::driver('google')->stateless()->user();
        //
        // $user = \App\Models\User::updateOrCreate(
        //     ['google_id' => $googleUser->getId()],
        //     [
        //         'name'              => $googleUser->getName(),
        //         'email'             => $googleUser->getEmail(),
        //         'email_verified_at' => now(),
        //         'google_id'         => $googleUser->getId(),
        //     ]
        // );
        //
        // Auth::login($user, remember: true);
        //
        // if (! $user->profile) {
        //     return redirect()->route('onboarding.step1');
        // }
        //
        // return redirect()->intended(route('dashboard'));

        abort(501, 'Google OAuth belum dikonfigurasi.');
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE: Validasi rate limit — lempar exception jika throttled
    // ─────────────────────────────────────────────────────────────
    private function ensureIsNotRateLimited(Request $request): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request), maxAttempts: 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // PRIVATE: Throttle key unik per email + IP address
    // ─────────────────────────────────────────────────────────────
    private function throttleKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower($request->input('email')) . '|' . $request->ip()
        );
    }
}
