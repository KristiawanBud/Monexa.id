<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class IconController extends Controller
{
    public const SLOTS = [
        'admin_panel'     => 'Ikon shortcut Admin Panel (halaman Akun)',
        'nav_dashboard'   => 'Nav bawah — Dashboard',
        'nav_dompet'      => 'Nav bawah — Dompet',
        'nav_report'      => 'Nav bawah — Laporan',
        'nav_account'     => 'Nav bawah — Profil',
        'qa_pemasukan'    => 'Tambah Transaksi — Pemasukan',
        'qa_pengeluaran'  => 'Tambah Transaksi — Pengeluaran',
        'qa_scan'         => 'Tambah Transaksi — Scan Struk',
        'qa_saving'       => 'Tambah Transaksi — Setor Tabungan',
        'qa_bill'         => 'Tambah Transaksi — Bayar Tagihan',
        'qa_import'       => 'Tambah Transaksi — Import Excel',
        'qa_budget'       => 'Tambah Transaksi — Budget',
        'qa_aset'         => 'Tambah Transaksi — Aset',
        'dashboard_hero'  => 'Ilustrasi Card Utama Dashboard (pojok kanan bawah)',
        'dompet_hero'     => 'Ilustrasi Card Utama Halaman Dompet',

        // ── Budget Health Score — Icon per tier ──
        'health_tier_sehat'           => 'Skor Kesehatan Budget — Tier Sehat',
        'health_tier_cukup'           => 'Skor Kesehatan Budget — Tier Cukup',
        'health_tier_perlu_perhatian' => 'Skor Kesehatan Budget — Tier Perlu Perhatian',
    ];

    public function index(): Response
    {
        $icons = collect(self::SLOTS)->map(function ($label, $slug) {
            $path = SystemSetting::get("icon:{$slug}");
            return [
                'slug'  => $slug,
                'label' => $label,
                'url'   => $path ? Storage::url($path) : null,
            ];
        })->values();

        return Inertia::render('Admin/Icons', ['icons' => $icons]);
    }

    public function upload(Request $request, string $slug): RedirectResponse
    {
        abort_unless(array_key_exists($slug, self::SLOTS), 404);

        $request->validate([
            'icon' => ['required', 'image', 'max:1024', 'mimes:png,jpg,jpeg,svg,webp'],
        ]);

        $old = SystemSetting::get("icon:{$slug}");
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $path = $request->file('icon')->store('icons', 'public');

        SystemSetting::set("icon:{$slug}", $path, self::SLOTS[$slug]);

        Cache::forget('app_icons_map');

        return back()->with('success', 'Ikon berhasil diganti!');
    }

    public function reset(string $slug): RedirectResponse
    {
        abort_unless(array_key_exists($slug, self::SLOTS), 404);

        $old = SystemSetting::get("icon:{$slug}");
        if ($old) {
            Storage::disk('public')->delete($old);
        }

        SystemSetting::forget("icon:{$slug}");
        Cache::forget('app_icons_map');

        return back()->with('success', 'Ikon dikembalikan ke default.');
    }
}
