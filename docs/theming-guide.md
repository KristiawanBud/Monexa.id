# Panduan Theming Monexa

Fondasi theming dibangun di atas CSS Variables yang sudah ada di `resources/css/app.css`
(bukan sistem token baru). Setiap tema adalah satu file CSS di `resources/css/themes/`
yang men-define ulang variable yang sama di bawah selector `[data-theme="<nama>"]`.

## Cara kerja

- `resources/css/app.css` → `:root` berisi tema **blue** sebagai fallback untuk halaman yang
  belum memakai `data-theme` (supaya halaman lama tidak pecah).
- `resources/css/themes/theme-blue.css`, `theme-green.css`, `theme-dark.css` → masing-masing
  men-define variable yang identik di bawah `[data-theme="blue"|"green"|"dark"]`.
- `resources/js/Composables/useTheme.js` → menentukan tema aktif dan menulis
  `document.documentElement.dataset.theme` (+ toggle class `.dark` untuk kompatibilitas
  `darkMode: 'class'` Tailwind saat tema `dark` aktif).
- Dipanggil sekali di `resources/js/app.js` (`initTheme()`) sebelum Inertia app mount — bukan
  per-halaman.
- **Persistensi akun**: kolom `user_profiles.theme` (nullable, migration
  `2026_07_13_000001_add_theme_to_user_profiles_table.php`) menyimpan preferensi tema per-user,
  di-share ke semua halaman via `HandleInertiaRequests::share()` sebagai prop top-level `theme`.
  Endpoint `PUT /account/theme` (`account.theme`, lihat `AccountController::updateTheme()`)
  menyimpannya lewat `UserProfile::updateOrCreate()`. Ini melengkapi (bukan menggantikan)
  `localStorage` — lihat urutan prioritas di bawah untuk interaksi keduanya.

Urutan prioritas penentuan tema aktif (`resolveInitialTheme()` di `useTheme.js`):
1. Query param `?theme=` di URL — override eksplisit tertinggi, dipakai untuk preview.
2. `localStorage.monexa_theme` — preferensi per-device yang sudah pernah dipilih di browser ini.
3. Shared prop Inertia `theme` (dari `user_profiles.theme` di DB) — dipakai kalau localStorage
   device ini masih kosong (mis. login pertama kali di device baru). Kalau valid, langsung
   ditulis ulang ke `localStorage` supaya tidak perlu baca DB lagi di navigasi berikutnya.
   Dibaca dari `document.getElementById('app').dataset.page` (initial page object Inertia)
   supaya tersedia sebelum Vue selesai mount — tidak ada flash tema salah.
4. `window.matchMedia('(prefers-color-scheme: dark)')` — kalau OS/browser user minta dark mode
   dan belum ada preferensi eksplisit (localStorage maupun DB), fallback ke tema `'dark'`.
5. `import.meta.env.VITE_DEFAULT_THEME`
6. Fallback `'blue'`

Nilai yang diterima hanya `blue`, `green`, `dark` — nilai lain otomatis fallback ke `blue`
(whitelist ketat di `useTheme.js`, tidak pernah menulis input user langsung ke `dataset.theme`).

**UI Settings > Appearance** (`resources/js/Pages/App/Account.vue`, section "Tampilan") — 3
pilihan tema, memanggil `useTheme().setTheme(name)` langsung saat dipilih (instan, tanpa tombol
submit terpisah). `setTheme()` menulis `localStorage` + `dataset.theme` secara optimistic, lalu
mengirim `PUT /account/theme` (Inertia `router.put`, `preserveScroll: true, preserveState:
true`) untuk sinkron ke akun. Kalau request gagal (mis. offline), tema tetap berubah secara
lokal — persistensi DB sifatnya best-effort, bukan syarat switching tema instan.

## Cara menambah tema baru

1. Copy salah satu file di `resources/css/themes/` (mis. `theme-blue.css`) menjadi
   `theme-<nama>.css`, ganti selector jadi `[data-theme="<nama>"]`.
2. Isi **semua** key CSS variable yang sama — jangan sampai ada yang hilang, karena UI tidak
   punya fallback per-variable saat pindah tema (kontrak wajib):
   `--primary`, `--primary-light`, `--primary-dark`, `--secondary`, `--success`, `--danger`,
   `--primary-bg`, `--secondary-bg`, `--success-bg`, `--danger-bg`, `--amber`, `--amber-bg`,
   `--purple`, `--purple-bg`, `--ewallet`, `--ewallet-bg`, `--primary-contrast`,
   `--overlay-scrim`, `--background`, `--surface`, `--border`, `--text-primary`,
   `--text-secondary`, `--text-faint`, `--radius-sm/md/lg/xl`,
   `--shadow-sm/md/lg/card/fab/focus`.
3. Import file baru di `resources/css/app.css` (dekat import tema lain, sebelum `@tailwind`).
4. Tambahkan nama tema ke whitelist `VALID_THEMES` di `resources/js/Composables/useTheme.js`
   dan ke `Rule::in([...])` di `app/Http/Requests/App/UpdateThemeRequest.php` (server-side juga
   perlu tahu tema baru supaya `PUT /account/theme` tidak menolaknya).
5. Preview via `?theme=<nama>` di URL, cek 3 breakpoint (360px/768px/1440px) dan pastikan tidak
   ada console error / variable undefined.
6. Audit kontras minimum WCAG AA (4.5:1 teks normal, 3:1 teks besar/UI component) — penting
   khususnya untuk `--primary-contrast` (teks di atas tombol ber-background `--primary`) dan
   untuk tema gelap secara umum. Lihat bagian "Kontras `--primary-contrast`" di bawah untuk
   metode & keputusan yang sudah diambil untuk 3 tema saat ini.

## Kontras `--primary-contrast`

Token `--primary-contrast` adalah warna teks/ikon yang aman dipakai di atas elemen
ber-`background: var(--primary)` (mis. `.btn-primary`, `.chip.active`, `.sb-add-btn`,
`.remind-pill.selected`). **Jangan asumsikan putih selalu aman** — dihitung pakai formula
WCAG relative luminance, teks putih di atas `--primary` masing-masing tema saat ini:

| Tema | `--primary` | Kontras vs putih | Lulus AA teks normal (≥4.5:1)? | `--primary-contrast` dipakai |
|---|---|---|---|---|
| blue  | `#2563EB` | 5.17:1 | ✅ | `#FFFFFF` |
| green | `#16A34A` | 3.30:1 | ❌ (hanya lulus ambang teks besar ≥3:1) | `#0F172A` (5.42:1 vs primary) |
| dark  | `#5B8DF8` | 3.18:1 | ❌ (hanya lulus ambang teks besar ≥3:1) | `#0F172A` (5.62:1 vs primary) |

Kalau nambah tema baru: hitung ulang kontras teks putih vs `--primary` tema itu. Kalau ≥4.5:1,
pakai `#FFFFFF`. Kalau tidak, pakai teks gelap (`#0F172A` sudah terbukti kontras tinggi
terhadap warna primary manapun yang cukup terang untuk dipakai sebagai brand color) dan
verifikasi ulang rasionya sebelum commit.

## Cara override token per-halaman

Scope CSS variable di `<style scoped>` komponen/halaman memakai selector root komponen —
Vue scoped style tetap menghormati CSS custom property cascade, jadi cukup redefine variable
yang ingin dioverride:

```css
<style scoped>
.halaman-khusus {
  --primary: #FF6B00; /* override lokal, tidak memengaruhi halaman lain */
}
</style>
```

Hindari override lebar (mis. di `:root` atau elemen global) di luar file tema — itu akan
memecah kontrak "key identik di 3 tema" dan menyulitkan tema berikutnya.

## Cara mengaktifkan preview tema

- **Sementara (per sesi browser)**: buka halaman dengan `?theme=green` atau `?theme=dark` di
  URL. Nilai ini juga disimpan ke `localStorage.monexa_theme` sehingga tema tetap aktif di
  navigasi berikutnya sampai diganti lagi.
- **Default environment**: set `VITE_DEFAULT_THEME=blue|green|dark` di `.env`, lalu jalankan
  ulang `npm run dev` / `npm run build` (env `VITE_*` dibaca saat build time oleh Vite).

## Feature flag

| Flag | Env var | Default | Keterangan |
|---|---|---|---|
| Tag/Label Transaksi | `FEATURE_TX_TAGS` | `false` | Fase 2, opsional — belum diimplementasikan di redesign UI Dompet ini (lihat kontrak B.6 di spec). Backend AI perlu men-share `config('features.transaction_tags')` via `HandleInertiaRequests` sebelum frontend bisa `v-if` render UI tag. |

Belum ada feature flag lain yang aktif di halaman Dompet saat ini.
