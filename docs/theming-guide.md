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
- Preferensi tema dipersist per-user di kolom `user_profiles.theme`, di-share ke semua halaman
  Inertia lewat prop `theme` (`HandleInertiaRequests`), dan diupdate lewat `PUT /account/theme`
  (`account.theme`) — lihat toggle di halaman `resources/js/Pages/App/Account.vue`.

Urutan prioritas penentuan tema aktif:
1. Query param `?theme=` di URL
2. `localStorage.monexa_theme`
3. Shared prop Inertia `usePage().props.theme` (preferensi tersimpan di database, kalau user
   login dan sudah pernah memilih tema — dibaca dari payload awal `data-page` karena composable
   ini jalan sebelum Inertia mount)
4. `window.matchMedia('(prefers-color-scheme: dark)').matches` → `'dark'` kalau `true`
5. `import.meta.env.VITE_DEFAULT_THEME`
6. Fallback `'blue'`

Nilai yang diterima hanya `blue`, `green`, `dark` — nilai lain otomatis fallback ke `blue`
(whitelist ketat di `useTheme.js`, tidak pernah menulis input user langsung ke `dataset.theme`).

**Toggle tema ada di halaman Account** (bagian "Tampilan", 3 pilihan `blue`/`green`/`dark`).
`setTheme(name)` langsung update localStorage + DOM (optimistic, tanpa reload), lalu — kalau user
sedang login — kirim `router.put(route('account.theme'), { theme: name })` untuk persist ke
`user_profiles.theme`. Guest (belum login) hanya dapat localStorage, tidak memanggil endpoint
yang butuh auth.

## Cara menambah tema baru

1. Copy salah satu file di `resources/css/themes/` (mis. `theme-blue.css`) menjadi
   `theme-<nama>.css`, ganti selector jadi `[data-theme="<nama>"]`.
2. Isi **semua** key CSS variable yang sama — jangan sampai ada yang hilang, karena UI tidak
   punya fallback per-variable saat pindah tema (kontrak wajib):
   `--primary`, `--primary-light`, `--primary-dark`, `--secondary`, `--success`, `--danger`,
   `--warning`, `--info`,
   `--primary-bg`, `--secondary-bg`, `--success-bg`, `--danger-bg`, `--warning-bg`, `--info-bg`,
   `--amber`, `--amber-bg`, `--purple`, `--purple-bg`, `--ewallet`, `--ewallet-bg`,
   `--background`, `--surface`, `--border`, `--text-primary`, `--text-secondary`, `--text-faint`,
   `--radius-sm/md/lg/xl`, `--shadow-sm/md/lg/card/fab/focus`.

   `--warning`/`--info` adalah closed-set warna yang bisa dipilih user untuk kartu dompet
   (`user_wallets.color`, lihat `resources/js/Components/Wallet/CardDompet.vue`) — set ini sengaja
   dipisah dari `--amber`/`--purple`/`--ewallet` (dipakai token lain di UI) supaya nama token warna
   dompet stabil (`primary`/`success`/`danger`/`warning`/`info`) walau warna aktualnya berubah
   per tema.
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
