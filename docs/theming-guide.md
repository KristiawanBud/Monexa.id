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
1. Query param `?theme=` di URL (override manual/debug)
2. Shared prop Inertia `theme` (preferensi tersimpan per-user di `user_profiles.theme`, dibaca
   dari JSON halaman awal yang di-embed server lewat `@inertia` — lihat § FOUC di bawah untuk
   alasan tidak memakai `usePage()` langsung di titik ini)
3. `localStorage.monexa_theme` (fallback guest/belum login)
4. `import.meta.env.VITE_DEFAULT_THEME`
5. Fallback `'blue'`

Nilai yang diterima adalah `blue`, `green`, `dark`, `system` — nilai lain otomatis fallback ke
`blue` (whitelist ketat di `useTheme.js`, tidak pernah menulis input user langsung ke
`dataset.theme`).

**Tema dipilih dari halaman Akun** (section "Tampilan", `resources/js/Pages/App/Account.vue`),
disimpan ke `localStorage` (fallback cepat/guest) dan ke `user_profiles.theme` lewat
`PUT /account/theme` (best-effort, tidak blocking UX ganti tema kalau network gagal) sehingga
preferensi ikut lintas device/browser, bukan cuma `localStorage`.

## Opsi tema "System" (ikuti OS)

Selain 3 tema bernama (`blue`/`green`/`dark`), ada nilai preferensi ke-4: `'system'` — bukan
tema baru dengan token sendiri, melainkan instruksi "ikuti `prefers-color-scheme` OS secara
berkelanjutan":
- OS dark → render tema `dark`. OS light → render tema `blue`.
- `user_profiles.theme`/`localStorage` menyimpan literal `'system'` (bukan hasil resolusinya),
  supaya opsi "Ikuti Sistem" di halaman Akun tetap ter-highlight benar setelah reload — resolusi
  ke tema konkret (`resolveSystemTheme()`) selalu dihitung ulang saat dibutuhkan.
- Selama preferensi aktif adalah `'system'`, `useTheme.js` mendaftarkan listener
  `matchMedia('(prefers-color-scheme: dark)').addEventListener('change', ...)` yang re-apply
  tema tanpa reload begitu OS berganti mode gelap/terang — beda dari behaviour lama (OS
  preference cuma dibaca sekali di awal). Listener dilepas otomatis begitu user pindah ke tema
  eksplisit lain.
- Tidak ada "system green" — hijau tidak punya pasangan light/dark, jadi tetap pilihan manual.

## Mencegah FOUC (Flash of Unstyled/Wrong Content)

`initTheme()` dipanggil sebelum Inertia mount (`resources/js/app.js`) supaya `data-theme` di
`<html>` ter-set sebelum paint pertama sebisa mungkin. Dua lapis mitigasi:
1. **Server-side**: `resources/views/app.blade.php` men-set `data-theme`/class `dark` langsung
   di tag `<html>` berdasarkan `auth()->user()?->profile?->theme` (fallback `'blue'` untuk
   guest atau preferensi `'system'`/kosong — server tidak bisa membaca `prefers-color-scheme`
   dari request HTTP biasa). Ini menghilangkan flash untuk mayoritas kasus: user login dengan
   tema eksplisit (`blue`/`green`/`dark`) tersimpan.
2. **Client-side**: karena `initTheme()` berjalan sebelum Inertia mount (sebelum `page.value`
   dari `usePage()` terisi), prioritas #2 di atas (`theme` shared prop) dibaca langsung dari
   JSON yang di-embed server di elemen root (`#app[data-page]` atau `script[data-page="app"]`,
   tergantung konfigurasi `inertia.use_script_element_for_initial_page`), bukan lewat
   `usePage()` — supaya resolusi tema tetap sinkron/sebelum-paint tanpa menunggu Inertia app
   mount. `applyTheme()` juga skip mutasi DOM kalau `dataset.theme` sudah sama dengan hasil
   resolusi (dihitung server), menghindari flicker ganda.

Batasan yang **tidak** ditutup penuh (di luar scope, keterbatasan arsitektur SSR-less Inertia):
guest dengan preferensi `system` + OS dark akan tetap melihat flash singkat (server selalu
fallback `blue` untuk kasus ini karena tidak tahu OS preference saat render pertama), begitu
juga guest dengan preferensi tersimpan hanya di `localStorage` device itu (server tidak bisa
membaca `localStorage`).

## Cara menambah tema baru

1. Copy salah satu file di `resources/css/themes/` (mis. `theme-blue.css`) menjadi
   `theme-<nama>.css`, ganti selector jadi `[data-theme="<nama>"]`.
2. Isi **semua** key CSS variable yang sama — jangan sampai ada yang hilang, karena UI tidak
   punya fallback per-variable saat pindah tema (kontrak wajib):
   `--primary`, `--primary-light`, `--primary-dark`, `--secondary`, `--success`, `--danger`,
   `--warning`, `--info`,
   `--primary-bg`, `--secondary-bg`, `--success-bg`, `--danger-bg`, `--amber`, `--amber-bg`,
   `--purple`, `--purple-bg`, `--ewallet`, `--ewallet-bg`, `--background`, `--surface`,
   `--border`, `--text-primary`, `--text-secondary`, `--text-faint`,
   `--radius-sm/md/lg/xl`, `--shadow-sm/md/lg/card/fab/focus`.

   `--warning`/`--info` dipakai khusus untuk token warna custom dompet (`user_wallets.color`,
   salah satu dari `primary|success|danger|warning|info` — lihat `Wallet/CardDompet.vue` dan
   selector warna di `Pages/App/Dompet.vue`), berbeda dari `--amber` (dekoratif, dipakai badge
   trial/status lain).
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

Belum ada feature flag lain yang aktif di halaman Dompet saat ini.
