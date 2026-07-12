<?php

function writeFile(string $path, string $content): void
{
    $dir = dirname($path);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (file_exists($path)) {
        copy($path, $path.'.bak_'.date('Ymd_His'));
    }
    file_put_contents($path, $content);
    echo "Ditulis: $path\n";
}

function patchFile(string $path, array $replacements): void
{
    if (! file_exists($path)) {
        echo "SKIP (tidak ditemukan): $path\n";

        return;
    }
    $content = file_get_contents($path);
    $backupMade = false;
    $changed = 0;

    foreach ($replacements as $old => $new) {
        if (strpos($content, $old) !== false) {
            if (! $backupMade) {
                copy($path, $path.'.bak_'.date('Ymd_His'));
                $backupMade = true;
            }
            $content = str_replace($old, $new, $content);
            $changed++;
        }
    }

    if ($changed > 0) {
        file_put_contents($path, $content);
        echo "OK ($changed patch diterapkan): $path\n";
    } else {
        echo "SKIP (semua pattern sudah diterapkan/tidak ketemu): $path\n";
    }
}

// ─────────────────────────────────────────────
// 1. IconController.php — TIMPA, tambah SLOTS baru
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/app/Http/Controllers/Admin/IconController.php', <<<'EOT'
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
    // Daftar "slot" ikon yang bisa diganti dari Admin Panel.
    // Tambah entry baru di sini kalau mau bikin ikon lain jadi bisa diganti,
    // lalu pakai <AppIcon slug="nama_slot"> di Vue-nya.
    public const SLOTS = [
        'admin_panel'     => 'Ikon shortcut Admin Panel (halaman Akun)',

        // ── Bottom Navigation ──
        'nav_dashboard'   => 'Nav bawah — Dashboard',
        'nav_dompet'      => 'Nav bawah — Dompet',
        'nav_report'      => 'Nav bawah — Laporan',
        'nav_account'     => 'Nav bawah — Profil',

        // ── Menu Tambah Transaksi (tombol + di tengah nav bawah) ──
        'qa_pemasukan'    => 'Tambah Transaksi — Pemasukan',
        'qa_pengeluaran'  => 'Tambah Transaksi — Pengeluaran',
        'qa_scan'         => 'Tambah Transaksi — Scan Struk',
        'qa_saving'       => 'Tambah Transaksi — Setor Tabungan',
        'qa_bill'         => 'Tambah Transaksi — Bayar Tagihan',
        'qa_import'       => 'Tambah Transaksi — Import Excel',
        'qa_budget'       => 'Tambah Transaksi — Budget',
        'qa_aset'         => 'Tambah Transaksi — Aset',

        // ── Dashboard ──
        'dashboard_hero'  => 'Ilustrasi Card Utama Dashboard (pojok kanan bawah)',
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

EOT
);

// ─────────────────────────────────────────────
// 2. AppIcon.vue — TIMPA, tambah sizing otomatis (1em) buat gambar upload
// ─────────────────────────────────────────────
writeFile('/var/www/monexa/resources/js/Components/AppIcon.vue', <<<'EOT'
<template>
  <img v-if="iconUrl" :src="iconUrl" :alt="slug" :class="$attrs.class" />
  <span v-else :class="$attrs.class"><slot /></span>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({ slug: { type: String, required: true } })

const page = usePage()
const iconUrl = computed(() => page.props.icons?.[props.slug] ?? null)
</script>

<style scoped>
/* Gambar upload otomatis nyesuain ukuran teks/emoji di sekitarnya */
img { width: 1em; height: 1em; object-fit: contain; vertical-align: -0.15em; display: inline-block; }
</style>

EOT
);

// ─────────────────────────────────────────────
// 3. AppLayout.vue — patch nav bawah & menu tambah transaksi
// ─────────────────────────────────────────────
patchFile('/var/www/monexa/resources/js/Layouts/AppLayout.vue', [
    "import CuanAI from '@/Components/CuanAI.vue'" => "import CuanAI from '@/Components/CuanAI.vue'\nimport AppIcon from '@/Components/AppIcon.vue'",

    '<span class="bn-icon">🏠</span>' => '<AppIcon slug="nav_dashboard" class="bn-icon">🏠</AppIcon>',
    '<span class="bn-icon">👛</span>' => '<AppIcon slug="nav_dompet" class="bn-icon">👛</AppIcon>',
    '<span class="bn-icon">📊</span>' => '<AppIcon slug="nav_report" class="bn-icon">📊</AppIcon>',
    '<span class="bn-icon">👤</span>' => '<AppIcon slug="nav_account" class="bn-icon">👤</AppIcon>',

    '<span class="qac-icon">💵</span>' => '<AppIcon slug="qa_pemasukan" class="qac-icon">💵</AppIcon>',
    '<span class="qac-icon">🔥</span>' => '<AppIcon slug="qa_pengeluaran" class="qac-icon">🔥</AppIcon>',
    '<span class="qac-icon">📷</span>' => '<AppIcon slug="qa_scan" class="qac-icon">📷</AppIcon>',
    '<span class="qac-icon">🎯</span>' => '<AppIcon slug="qa_saving" class="qac-icon">🎯</AppIcon>',
    '<span class="qac-icon">📋</span>' => '<AppIcon slug="qa_bill" class="qac-icon">📋</AppIcon>',
    '<span class="qac-icon">📥</span>' => '<AppIcon slug="qa_import" class="qac-icon">📥</AppIcon>',
    '<span class="qac-icon">💡</span>' => '<AppIcon slug="qa_budget" class="qac-icon">💡</AppIcon>',
    '<span class="qac-icon">💎</span>' => '<AppIcon slug="qa_aset" class="qac-icon">💎</AppIcon>',
]);

// ─────────────────────────────────────────────
// 4. App/Dashboard.vue — patch quick actions row + ilustrasi hero card
// ─────────────────────────────────────────────
patchFile('/var/www/monexa/resources/js/Pages/App/Dashboard.vue', [
    "import AppLayout from '@/Layouts/AppLayout.vue'" => "import AppLayout from '@/Layouts/AppLayout.vue'\nimport AppIcon from '@/Components/AppIcon.vue'",

    '<div class="qa-icon pemasukan">💵</div>' => '<div class="qa-icon pemasukan"><AppIcon slug="qa_pemasukan">💵</AppIcon></div>',
    '<div class="qa-icon pengeluaran">🔥</div>' => '<div class="qa-icon pengeluaran"><AppIcon slug="qa_pengeluaran">🔥</AppIcon></div>',
    '<div class="qa-icon scan">📷</div>' => '<div class="qa-icon scan"><AppIcon slug="qa_scan">📷</AppIcon></div>',
    '<div class="qa-icon aset">💎</div>' => '<div class="qa-icon aset"><AppIcon slug="qa_aset">💎</AppIcon></div>',

    '<!-- Hero Budget Card -->
      <div class="hero-card">' => '<!-- Hero Budget Card -->
      <div class="hero-card">
        <AppIcon slug="dashboard_hero" class="hero-illustration"></AppIcon>',

    '.hero-card { background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color:white; border-radius:var(--radius-xl); padding:22px; margin-bottom:14px; box-shadow:0 10px 30px rgba(37,99,235,.25); }' => '.hero-card { position:relative; overflow:hidden; background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color:white; border-radius:var(--radius-xl); padding:22px; margin-bottom:14px; box-shadow:0 10px 30px rgba(37,99,235,.25); }
.hero-illustration { position:absolute; right:12px; bottom:8px; width:90px; height:90px; opacity:.9; pointer-events:none; }',
]);

echo "\n=== SELESAI ===\n";
