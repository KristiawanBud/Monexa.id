# Spec: Perbaiki White Flicker/Putih-Putih Saat Swipe/Scroll di Monexa

## Ringkasan Brief CEO
Muncul area/kelipan putih saat layar digeser (swipe/scroll) di app Monexa (Dashboard, Dompet/Transaksi,
Anggaran, Laporan, dan navigasi tab). Harus hilang di light & dark mode, di berbagai device, tanpa
regresi performa.

**Catatan penting:** ini murni bug rendering frontend (CSS/theme/DOM), **tidak ada endpoint API atau
tabel database yang terlibat**. Kontrak di bawah menggantikan format "Endpoint/Request/Response" standar
dengan kontrak teknis file/selector/CSS variable yang harus disentuh Frontend AI, karena tidak relevan
membuat endpoint API palsu untuk bug visual.

## Root cause hasil investigasi (kode existing, sudah diverifikasi)
1. **`<html>` tidak punya `background-color`.** Di `resources/css/app.css:71`, selector `html { font-size:
   16px; -webkit-text-size-adjust: 100%; }` — tidak ada `background`. Hanya `body` (app.css:73-78) dan
   `.app-shell` (AppLayout.vue) yang diberi `var(--background)`. Saat rubber-band overscroll bounce di iOS
   Safari (dan sebagian browser Android), area yang tampil di luar `<body>` adalah background default
   `<html>` → putih, di kedua theme (blue/green maupun dark), karena `[data-theme]` CSS variables
   diterapkan lewat `document.documentElement.dataset.theme` (lihat `resources/js/Composables/useTheme.js:26`)
   tapi elemen `html` sendiri tidak pernah diberi `background`.
2. **Tidak ada `overscroll-behavior` di mana pun** (`grep overscroll-behavior` di seluruh `resources/`
   nihil). Ini yang memungkinkan rubber-band bounce/pull glow native browser muncul sama sekali. Semakin
   parah karena custom pull-to-refresh di `resources/js/Pages/App/Dompet.vue:826-846` memasang
   `touchmove` dengan `{ passive: true }` tanpa `preventDefault`/containment, jadi native overscroll
   browser tetap jalan berbarengan dengan custom pull indicator.
3. **Hardcoded `background: white` di kartu utama Dashboard** —
   `resources/js/Pages/App/Dashboard.vue:335` (`.hero-card { background: white; border-radius: 22px 22px
   0 0; ... }`). Kartu ini full-width, langsung di bawah hero gradient, dan selalu putih walau theme aktif
   `dark` (harusnya ikut `var(--surface)` yang di dark theme = `#1E293B`). Saat scroll/transisi di dark
   mode, blok putih besar ini yang paling terlihat sebagai "kelipan putih".
4. **`meta[name=theme-color]` statis `#0F0F0F`** di `resources/views/app.blade.php:6`, tidak mengikuti
   theme aktif (blue/green/dark dipilih user via `useTheme.js`, tersimpan di localStorage). Browser
   mobile (Chrome Android) memakai `theme-color` untuk mewarnai UI system bar & sebagian area overscroll —
   mismatch ini bisa menambah kesan "warna aneh"/flicker saat scroll, walau kontribusinya sekunder
   dibanding poin 1-2.

**Bukan penyebab (sudah dicek, tidak perlu disentuh):**
- `resources/js/Components/Wallet/SkeletonLoader.vue` — shimmer sudah pakai `var(--border)`/`var(--background)`, theme-aware, aman.
- Tidak ada penggunaan `<canvas>`/chart.js/apexcharts di `Report.vue` (chart pakai inline `<svg>`), jadi tidak ada canvas dengan default fill putih.
- Tidak ada `<Transition>` Inertia page-to-page yang menimbulkan frame kosong; navigasi antar page adalah swap komponen biasa.
- File-file `Admin/*.vue` (`background:white` pada `.modal-box`, `.hamburger`) adalah design system Admin panel yang terpisah, **di luar scope** brief CEO (brief hanya menyebut Dashboard, Dompet/Transaksi, Anggaran, Laporan).
- `.slider:before` (knob toggle switch putih) di `Account.vue`/Admin — elemen kecil dekoratif, bukan penyebab flicker layar penuh, di luar scope.

---

## Todo Teknis (breakdown untuk Frontend AI)

1. Set `background: var(--background)` pada selector `html` di `resources/css/app.css` (dekat baris 71),
   supaya area overscroll di luar `body`/`.app-shell` selalu mengikuti theme, bukan putih default browser.
2. Tambahkan `overscroll-behavior-y: none` (atau `contain`) pada `html` dan `body` di
   `resources/css/app.css`, untuk mematikan rubber-band bounce native yang membuka celah putih saat swipe
   vertikal cepat. Terapkan juga `overscroll-behavior: contain` pada container scroll internal yang punya
   overflow sendiri (mis. `.main-content` di `AppLayout.vue` bila ada overflow-y, dan sheet/modal seperti
   `.qa-sheet`) supaya scroll di dalam sheet tidak "bocor" men-trigger bounce halaman di baliknya.
3. Ganti `background: white` → `background: var(--surface)` pada `.hero-card` di
   `resources/js/Pages/App/Dashboard.vue:335`, supaya kartu ini ikut theme (putih di light theme, gelap
   di dark theme) — konsisten dan tidak flash putih di dark mode.
4. Audit ulang `.avatar-btn` (`Dashboard.vue:331`) dan `.hero-add-btn`
   (`resources/js/Components/Wallet/BalanceSummaryCard.vue:111`) — keduanya tombol bulat kecil di atas
   hero gradient berwarna, `background:white` di sini kemungkinan **disengaja** (kontras ikon di atas
   background biru gradient), jadi jangan diubah kecuali terbukti dari review visual bahwa itu juga ikut
   nge-flash saat scroll. Cukup dicek visual, tidak wajib diubah.
5. Buat `meta[name=theme-color]` di `resources/views/app.blade.php:6` dinamis mengikuti theme aktif
   (`--background` dari theme yang tersimpan di localStorage `monexa_theme`, fallback `blue`), dengan
   meng-update `content` attribute-nya di `applyTheme()` (`resources/js/Composables/useTheme.js:23-28`)
   memakai value background masing-masing theme:
   - blue → `#F8FAFC`
   - green → (lihat `--background` di `resources/css/themes/theme-green.css`)
   - dark → `#0F172A`
6. Pastikan list transaksi (Dompet.vue, virtualized/grouped list) dan grid card di Budget/Report tidak
   punya container dengan `background` default (cek elemen yang tidak memakai var(--surface)/var(--background))
   selama proses perbaikan — quick visual audit saat testing, bukan perubahan kode terpisah kalau sudah
   tercover oleh poin 1-3.
7. Uji regresi manual (lihat Test Matrix) di light & dark mode, ≥2 device berbeda, semua layar terdampak:
   Dashboard, Dompet (list transaksi + tab swipe Transaksi/Dompet/Tagihan), Budget/Anggaran, Report/Laporan.
8. Rekam video before/after sebagai bukti, lampirkan link video + commit/PR di laporan penyelesaian task.

---

## Kontrak Teknis per Perbaikan

### 1. Root background mengikuti theme
**File:** `resources/css/app.css`
**Selector:** `html`
**Before:**
```css
html { font-size: 16px; -webkit-text-size-adjust: 100%; }
```
**After (kontrak):**
```css
html {
  font-size: 16px;
  -webkit-text-size-adjust: 100%;
  background: var(--background);
  overscroll-behavior-y: none;
}
```
Tambahkan juga `overscroll-behavior-y: none;` pada rule `body` yang sudah ada (app.css:73-78).

### 2. Containment scroll internal (sheet/overlay)
**File:** `resources/js/Layouts/AppLayout.vue` (`<style scoped>`)
**Target selector:** `.qa-sheet`, dan `.main-content` jika memiliki `overflow-y: auto/scroll`.
**Kontrak:** tambahkan `overscroll-behavior: contain;` pada selector tersebut agar scroll di dalam
komponen tidak memantul ke body di baliknya.

### 3. Hero card Dashboard ikut theme
**File:** `resources/js/Pages/App/Dashboard.vue`
**Selector:** `.hero-card` (baris ±335)
**Before:** `background: white;`
**After:** `background: var(--surface);`
**Validasi visual:** di `[data-theme='dark']`, `--surface` = `#1E293B` (lihat
`resources/css/themes/theme-dark.css`) — kartu harus tampil gelap, teks di dalamnya (`--text-primary`
dsb.) sudah otomatis kontras karena sudah pakai CSS var.

### 4. `theme-color` dinamis
**File:** `resources/views/app.blade.php` — beri `id="theme-color-meta"` pada tag meta agar mudah
ditarget dari JS:
```html
<meta name="theme-color" id="theme-color-meta" content="#F8FAFC">
```
**File:** `resources/js/Composables/useTheme.js`, di dalam `applyTheme(name)`:
tambahkan mapping `THEME_BACKGROUND = { blue: '#F8FAFC', green: '#F7FCF9', dark: '#0F172A' }` (nilai
diambil dari `--background` masing-masing di `resources/css/themes/theme-*.css`) dan set
`document.getElementById('theme-color-meta')?.setAttribute('content', THEME_BACKGROUND[theme])`.

### Database
Tidak ada tabel/kolom yang terlibat — murni perubahan CSS/Vue/Blade di frontend, tidak ada migration.

### Validasi (acceptance criteria, dipakai QA/Frontend AI untuk self-check)
- Tidak ada kelipan/area putih yang terlihat saat swipe/scroll cepat (fling) di Dashboard, Dompet (list
  transaksi + swipe antar tab Transaksi/Dompet/Tagihan), Budget, Report — light & dark mode.
- Overscroll bounce (jika ada) menampilkan warna `var(--background)` theme aktif, bukan putih, di semua
  layar terdampak.
- `.hero-card` Dashboard tampil gelap (`#1E293B`) saat `data-theme="dark"`, putih saat `data-theme="blue"`/`"green"`.
- Tidak ada frame drop signifikan tambahan akibat `overscroll-behavior`/perubahan CSS (bandingkan
  scroll FPS sebelum/sesudah secara kualitatif).
- Tidak ada regresi visual pada komponen lain yang sengaja putih di atas background berwarna
  (`.avatar-btn`, `.hero-add-btn`) — biarkan seperti semula kecuali terbukti bermasalah saat testing.
- Diuji minimal 2 device/browser berbeda (idealnya 1 iOS Safari + 1 Android Chrome, atau 2 resolusi
  berbeda kalau device fisik tidak tersedia) sebelum ditandai selesai.

### Test Matrix (wajib dicatat Frontend AI saat lapor selesai)
| Layar | Light mode | Dark mode | Device 1 | Device 2 |
|---|---|---|---|---|
| Dashboard/Overview | ☐ | ☐ | ☐ | ☐ |
| Dompet — list transaksi (scroll vertikal) | ☐ | ☐ | ☐ | ☐ |
| Dompet — swipe tab Transaksi/Dompet/Tagihan | ☐ | ☐ | ☐ | ☐ |
| Anggaran/Budget | ☐ | ☐ | ☐ | ☐ |
| Laporan/Report (grafik) | ☐ | ☐ | ☐ | ☐ |
| Pull-to-refresh (Dompet, mobile ≤480px) | ☐ | ☐ | ☐ | ☐ |

Lampirkan video before/after + hasil matrix ini di laporan penyelesaian task.
