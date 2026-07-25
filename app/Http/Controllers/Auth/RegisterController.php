<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\User;
use App\Services\WaGatewayService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function __construct(private WaGatewayService $gatewayService) {}

    // ─────────────────────────────────────────────────────────────
    // Tampilkan halaman register
    // ─────────────────────────────────────────────────────────────
    public function show(): Response
    {
        return Inertia::render('Auth/Register');
    }

    // ─────────────────────────────────────────────────────────────
    // Proses registrasi akun baru
    // Kriteria password diperketat sesuai standar aplikasi finansial:
    //   - min 8 karakter
    //   - mengandung huruf besar + kecil (mixedCase)
    //   - mengandung angka
    //   - mengandung simbol/karakter khusus
    // ─────────────────────────────────────────────────────────────
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:150', 'unique:users,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()   // wajib huruf besar + kecil
                    ->numbers()     // wajib minimal 1 angka
                    ->symbols(),    // wajib minimal 1 simbol (!@#$% dll)
            ],
        ], [
            // Pesan validasi bahasa Indonesia
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
            'is_active' => true,
        ]);

        // Buat subscription trial 7 hari
        Subscription::create([
            'user_id' => $user->id,
            'plan' => 'trial',
            'status' => 'active',
            'trial_ends_at' => now()->addDays(7),
            'starts_at' => now(),
        ]);

        // Dispatch event registered (untuk verifikasi email di masa depan)
        event(new Registered($user));

        Auth::login($user);

        // Arahkan ke onboarding (Step 1: isi nomor WA)
        return redirect()->route('onboarding.step1');
    }
}
