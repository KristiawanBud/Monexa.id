# Panduan Theming Monexa

Fondasi theming Monexa berbasis CSS Custom Properties (CSS variables) yang di-scope lewat atribut
`data-theme` di elemen `<html>`. Tidak ada dependency baru, tidak ada endpoint backend — murni CSS +
satu composable kecil di frontend.

## Struktur

```
resources/css/
  app.css                 → meng-import ketiga file tema, lalu Tailwind, lalu CSS umum
  themes/
    theme-blue.css         → [data-theme="blue"]  (default/fallback, juga menempel ke :root)
    theme-green.css        → [data-theme="green"]
    theme-dark.css         → [data-theme="dark"]

resources/js/
  Composables/useTheme.js  → resolve tema aktif & pasang data-theme + class .dark ke <html>
  app.js                   → memanggil useTheme() sekali saat mount Inertia app
```

Setiap file `theme-*.css` **wajib** mendefinisikan key CSS variable yang sama persis (ini kontrak,
supaya switching tema tidak pernah menyisakan variable undefined):

```
--primary, --primary-light, --primary-dark, --secondary, --success, --danger,
--primary-bg, --secondary-bg, --success-bg, --danger-bg, --amber, --amber-bg, --purple, --purple-bg,
--background, --surface, --border,
--text-primary, --text-secondary, --text-faint,
--radius-sm, --radius-md, --radius-lg, --radius-xl,
--shadow-sm, --shadow-md, --shadow-lg, --shadow-card, --shadow-fab, --shadow-focus
```

## Cara kerja resolusi tema (prioritas)

`useTheme()` (dipanggil sekali di `resources/js/app.js`) menentukan tema aktif dengan urutan:

1. Query param `?theme=` di URL
2. `localStorage.monexa_theme` (tema terakhir yang pernah aktif di browser tsb)
3. `import.meta.env.VITE_DEFAULT_THEME` (di-set lewat `.env`)
4. Fallback default: `'blue'`

Nilai yang tidak ada di whitelist (`blue`, `green`, `dark`) otomatis dibuang dan jatuh ke `'blue'` —
ini sengaja supaya `?theme=` dari URL tidak bisa dipakai untuk inject value sembarangan ke atribut HTML.

Hasil akhirnya: `document.documentElement.dataset.theme = '<nama-tema>'` dan class `.dark` ditoggle
di `<html>` (dipakai Tailwind, karena `darkMode: 'class'` sudah dikonfigurasi di `tailwind.config.js`).

## Cara preview tema

- **Lewat URL** (tidak perlu rebuild): buka halaman mana saja dengan `?theme=green` atau `?theme=dark`,
  misalnya `https://app.test/dompet?theme=dark`. Berlaku sampai localStorage di-clear atau ganti tema lain.
- **Lewat env** (untuk staging/build tertentu): set `VITE_DEFAULT_THEME=green` di `.env`, lalu build ulang
  asset (`npm run build` / restart `npm run dev`). Ini jadi default kalau user belum pernah override lewat
  URL atau localStorage.

Belum ada toggle UI publik untuk ganti tema (sengaja, sesuai arahan CEO saat task ini dibuat) — mekanisme
di atas murni untuk kebutuhan preview internal/staging.

## Cara menambah tema baru (tema ke-4, ke-5, dst.)

1. Copy salah satu file di `resources/css/themes/theme-*.css` menjadi `theme-<nama>.css`.
2. Ganti selector-nya ke `[data-theme='<nama>']` dan isi ulang **semua** key CSS variable di daftar kontrak
   di atas — jangan sampai ada key yang hilang.
3. Import file barunya di `resources/css/app.css`, sejajar dengan 3 import tema yang sudah ada:
   ```css
   @import './themes/theme-blue.css';
   @import './themes/theme-green.css';
   @import './themes/theme-dark.css';
   @import './themes/theme-<nama>.css';
   ```
4. Tambahkan `'<nama>'` ke whitelist `VALID_THEMES` di `resources/js/Composables/useTheme.js`.
5. Preview lewat `?theme=<nama>` untuk cek kontras & tidak ada variable undefined di console.

## Cara override token per-halaman

Kalau satu halaman butuh nuansa warna berbeda tanpa bikin tema baru, override CSS variable-nya di
`<style scoped>` komponen halaman tersebut, scope ke root elemen halaman (bukan `:root` global):

```vue
<style scoped>
.halaman-khusus {
  --primary: #7C3AED;
  --primary-bg: #F3E8FF;
}
</style>
```

Semua komponen/child di dalam `.halaman-khusus` yang memakai `var(--primary)` otomatis ikut berubah,
tanpa memengaruhi halaman lain — karena CSS variable di-resolve secara cascade per elemen, bukan global.

## Batasan saat ini

- Preferensi tema hanya tersimpan di `localStorage` browser, **belum** per-user di database. Kalau ke
  depan dibutuhkan toggle tema publik yang persisten per akun, itu perlu kolom baru
  (`users.theme_preference`) + endpoint (`PATCH /account/theme`) — di luar scope dokumen ini.
- Tema `green` dan `dark` masih berstatus draft (kontras & pairing warna sudah dicek dasar WCAG AA,
  tapi belum divalidasi penuh di semua kombinasi komponen).
