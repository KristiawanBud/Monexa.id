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

Urutan prioritas penentuan tema aktif:
1. Query param `?theme=` di URL
2. `localStorage.monexa_theme`
3. `window.matchMedia('(prefers-color-scheme: dark)').matches` → default `'dark'` kalau `true`
   **dan** user belum pernah pilih tema manual (langkah 2 kosong)
4. `import.meta.env.VITE_DEFAULT_THEME`
5. Fallback `'blue'`

Nilai yang diterima hanya `blue`, `green`, `dark` — nilai lain otomatis fallback ke `blue`
(whitelist ketat di `useTheme.js`, tidak pernah menulis input user langsung ke `dataset.theme`).
Literal `'system'` tidak pernah ditulis ke `localStorage` — auto-detect hanya menentukan tema
awal yang di-apply, bukan sebuah mode tema baru.

`useTheme.js` juga memasang listener `matchMedia('(prefers-color-scheme: dark)').addEventListener
('change', ...)` yang me-re-apply tema secara live saat OS/browser user berpindah light/dark —
**tapi hanya kalau** `localStorage.monexa_theme` masih kosong (user belum pernah pilih manual
lewat `ThemeToggle.vue`). Begitu user memilih tema secara eksplisit, auto-detect berhenti
meng-override selamanya (sampai `localStorage` dibersihkan).

### Toggle tema di UI

Komponen `resources/js/Components/ThemeToggle.vue` menyediakan 3 tombol (Biru/Hijau/Gelap) yang
memanggil `setTheme()` dari `useTheme()`. Render-nya **dibungkus feature flag**
`import.meta.env.VITE_ENABLE_THEME_TOGGLE` (default `false` di `.env.example` — toggle
disembunyikan di UI sampai diaktifkan secara eksplisit oleh owner/CEO). Saat ini dipasang di
halaman Profil/Akun (`resources/js/Pages/App/Account.vue`, bagian "Preferensi Tampilan") karena
tema bersifat app-wide, bukan cuma halaman Dompet.

Untuk mengaktifkan: set `VITE_ENABLE_THEME_TOGGLE=true` di `.env`, lalu rebuild
(`npm run build`/restart `npm run dev`) — tidak perlu deploy kode baru.

Persistensi tema tetap murni `localStorage` (`monexa_theme`), **belum** ada kolom
`users.theme_preference` di database — preferensi tema tidak lintas device untuk saat ini.

## Cara menambah tema baru

1. Copy salah satu file di `resources/css/themes/` (mis. `theme-blue.css`) menjadi
   `theme-<nama>.css`, ganti selector jadi `[data-theme="<nama>"]`.
2. Isi **semua** key CSS variable yang sama — jangan sampai ada yang hilang, karena UI tidak
   punya fallback per-variable saat pindah tema (kontrak wajib):
   `--primary`, `--primary-light`, `--primary-dark`, `--secondary`, `--success`, `--danger`,
   `--primary-bg`, `--secondary-bg`, `--success-bg`, `--danger-bg`, `--amber`, `--amber-bg`,
   `--purple`, `--purple-bg`, `--ewallet`, `--ewallet-bg`, `--background`, `--surface`,
   `--border`, `--text-primary`, `--text-secondary`, `--text-faint`,
   `--radius-sm/md/lg/xl`, `--shadow-sm/md/lg/card/fab/focus`.
3. Import file baru di `resources/css/app.css` (dekat import tema lain, sebelum `@tailwind`).
4. Tambahkan nama tema ke whitelist `VALID_THEMES` di `resources/js/Composables/useTheme.js`.
5. Preview via `?theme=<nama>` di URL, cek 3 breakpoint (360px/768px/1440px) dan pastikan tidak
   ada console error / variable undefined.
6. Audit kontras minimum WCAG AA (4.5:1 teks normal, 3:1 teks besar/UI component) — penting
   khususnya untuk tema gelap.

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
| Toggle Tema UI | `VITE_ENABLE_THEME_TOGGLE` | `false` | Murni frontend (dibaca build-time oleh Vite, tidak perlu share dari Laravel). Kalau `false`, `ThemeToggle.vue` tidak me-render apa pun — auto-detect `prefers-color-scheme` tetap aktif terlepas dari flag ini. |
