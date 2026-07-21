# Spec: Redesign UI Dompet Responsif + Fondasi Theming (Biru/Putih, Hijau/Putih, Dark)

Sumber arahan: CEO AI, referensi visual `storage/athena-refs/monexa-1783840494550.jpg`.
Stack terkonfirmasi dari codebase: **Laravel 13 + Inertia + Vue 3 (Options: `<script setup>`), Tailwind
(preflight OFF, dipakai minim) + custom CSS berbasis CSS Variables di `resources/css/app.css`**. Halaman
target: `resources/js/Pages/App/Dompet.vue`, layout: `resources/js/Layouts/AppLayout.vue`, controller:
`app/Http/Controllers/App/TransactionController.php` (route `dompet.index`) dan
`app/Http/Controllers/App/WalletController.php`.

## Temuan Penting dari Codebase (baca sebelum implementasi)

1. **Fondasi CSS Variables sudah ada** — `:root` di `resources/css/app.css` sudah pakai custom properties
   (`--primary`, `--surface`, `--background`, `--text-primary`, `--radius-*`, `--shadow-*`, dst). Ini modal
   utama untuk theming — **jangan bikin sistem token baru dari nol**, extend yang sudah ada.
2. **`AppLayout.vue` saat ini mobile-only**: `.app-shell { max-width:480px; margin:0 auto; }` dan bottom-nav
   fixed. Untuk tablet/desktop, layout shell ini **harus diubah** (bukan cuma halaman Dompet), karena semua
   halaman App memakai layout ini. Frontend AI perlu menambah breakpoint di `AppLayout.vue` juga, tidak
   cukup hanya redesign `Dompet.vue`.
3. Semua endpoint CRUD dompet, transaksi, transfer, tagihan **sudah ada** dan berfungsi (lihat kontrak di
   bawah). Redesign ini murni **UI + 1 gap kecil di filter tanggal** — tidak perlu bikin resource/controller
   baru, hanya extend query param di `TransactionController@index`.
4. Tidak ada kolom "limit/budget per dompet" di tabel `user_wallets` → progress bar pemakaian dompet
   (opsional di brief) **tidak punya sumber data saat ini**. Asumsi PM: fitur ini dibuat sebagai komponen
   reusable `ProgressBar.vue` yang menerima prop opsional, tapi tidak dirender dengan data real dulu
   (`v-if="wallet.usage_percent !== null"`). Jangan bikin migration baru untuk ini — di luar scope task.
5. Tailwind `darkMode: 'class'` sudah dikonfigurasi di `tailwind.config.js` tapi belum dipakai. Bisa
   dimanfaatkan untuk Dark Mode: toggle class `dark` di `<html>` bersamaan dengan atribut tema.

---

## A. Todo Teknis (breakdown per layer)

### A.1 Fondasi Theming (prioritas pertama — semua kerjaan lain bergantung ke sini)
- [ ] Refactor token warna di `resources/css/app.css` `:root` jadi named palette file terpisah:
  - `resources/css/themes/theme-blue.css` → selector `[data-theme="blue"]` (nilai = token existing saat
    ini, jadi ini tema default/aktif, tidak boleh mengubah visual existing selain pindah lokasi).
  - `resources/css/themes/theme-green.css` → `[data-theme="green"]` (draft, primary hijau, secondary tetap
    dari brand, background/surface putih — sama struktur token dengan tema blue, cuma value primary/success
    /shadow-fab/shadow-focus di-derive dari warna hijau).
  - `resources/css/themes/theme-dark.css` → `[data-theme="dark"]` (draft, background `#0F172A`-ish,
    surface `#1E293B`-ish, text-primary putih, primary tetap biru brand atau disesuaikan kontras AA).
  - Semua 3 file **wajib punya key CSS variable yang identik** (primary, primary-light, primary-dark,
    secondary, success, danger, *-bg varian, background, surface, border, text-primary, text-secondary,
    text-faint, radius-sm/md/lg/xl, shadow-sm/md/lg/card/fab/focus). Ini kontrak wajib supaya switching
    tema tidak pernah menyisakan variable undefined.
  - `:root` default (tanpa `data-theme`) tetap fallback ke tema blue supaya halaman lain yang belum pakai
    `data-theme` tidak pecah.
- [ ] Buat composable `resources/js/Composables/useTheme.js`:
  - Baca urutan prioritas: `?theme=` query param URL → `localStorage.monexa_theme` → `import.meta.env.VITE_DEFAULT_THEME` → default `'blue'`.
  - Whitelist nilai valid: `blue`, `green`, `dark`. Nilai lain → fallback `blue` (jangan render tema
    sembarangan dari input user tanpa validasi, ini vector injection kalau dipakai buat set attribute
    sembarangan — walau cuma CSS attribute, tetap whitelist).
  - Set `document.documentElement.dataset.theme = <value>` dan `document.documentElement.classList.toggle('dark', value === 'dark')`.
  - Expose `currentTheme` (ref) dan `setTheme(name)` untuk dipakai preview internal (misal lewat halaman
    debug `?theme=green`, TIDAK ada toggle UI publik dulu sesuai arahan CEO).
  - Panggil composable ini sekali di root (`resources/js/app.js` saat mount Inertia app), bukan per-page.
- [ ] Tambahkan `VITE_DEFAULT_THEME=blue` ke `.env.example` sebagai flag env untuk preview default tema
  saat build/staging.
- [ ] Dokumentasikan (lihat bagian D) cara nambah tema ke-4 di masa depan tanpa refactor.

### A.2 Layout Shell (AppLayout.vue) — breakpoint dasar
- [ ] Breakpoints: `xs <480px`, `sm 480–767px`, `md 768–1023px`, `lg 1024–1279px`, `xl >=1280px`. Tambahkan
  sebagai CSS custom media atau Tailwind screens config kalau mau dipakai lewat utility class (opsional,
  boleh tetap custom CSS media query mengikuti pola file existing).
- [ ] `.app-shell`: di `xs`/`sm` tetap seperti sekarang (mobile single column, max-width 480px). Di `md`
  ke atas: lepas `max-width:480px`, terapkan container yang lebih lebar dengan grid/sidebar opsional untuk
  filter (lihat A.3).
- [ ] `.bottom-nav`: tetap tampil di `xs`/`sm`/`md` (mobile & tablet). Di `lg`/`xl` (desktop), ganti jadi
  sidebar nav kiri persisten (reuse item nav yang sama, cukup ubah container jadi vertical) — FAB tengah di
  desktop berubah jadi tombol "Tambah Transaksi" biasa di sidebar/header, bukan floating.
- [ ] Pastikan `main-content` padding-bottom mobile-only (`88px` untuk clearance bottom-nav) tidak
  ke-carry ke desktop layout.

### A.3 Halaman Dompet (Dompet.vue) — struktur & layout
- [ ] Header saldo total: tetap gunakan section hero (`dompet-hero-bg`) tapi buat lebar penuh & proporsional
  di breakpoint besar (bukan stack vertikal sempit ala mobile) — ringkasan pemasukan/pengeluaran bulan
  berjalan (`total_income`/`total_expense` sudah tersedia dari backend, saat ini dirender di
  `.range-filter-row`, cukup dipindah/reposisi, bukan data baru).
- [ ] Kartu dompet individual (`CardDompet`, lihat A.4) — grid: `xs` 1 kolom, `sm` 1 kolom (card lebih
  lega), `md` 2 kolom, `lg`/`xl` 2–3 kolom + sidebar filter di kolom kiri (opsional, collapsible).
- [ ] Daftar transaksi terbaru: tetap 1 kolom list di semua breakpoint (list transaksi tidak perlu grid),
  tapi lebar container mengikuti breakpoint (mis. `max-width: 720px` di desktop supaya tidak melar).
- [ ] Aksi utama:
  - Mobile (`xs`/`sm`): FAB / bottom sheet (pola `showQuickAdd` di `AppLayout.vue` sudah ada, reuse).
  - Tablet/Desktop (`md`+): toolbar aksi eksplisit di header halaman — "Tambah Transaksi", "Transfer Antar
    Dompet" (tampil hanya kalau `wallets.length >= 2`, sudah ada di controller sekarang di frontend),
    "Kelola Dompet" (buka tab `dompet` / drawer kelola).
- [ ] Filter: rentang tanggal (baru, lihat B.1), kategori (`category_id`, sudah ada), dompet (`wallet_id`,
  sudah ada), pencarian teks (`search`, sudah ada). Konsolidasi ke komponen `FilterDrawer`/`FilterSheet`
  (lihat A.4) — sheet dari bawah di mobile, drawer dari sisi kanan (atau sidebar statis) di desktop.
- [ ] State kosong, loading skeleton, error state — pola konsisten dipakai di 3 tab (`transaksi`, `dompet`,
  `tagihan`), bukan cuma teks emoji seperti sekarang. Loading skeleton perlu ditambahkan baru (saat ini
  Inertia partial reload tidak menampilkan skeleton sama sekali — halaman langsung re-render tanpa
  indikator, ini gap UX yang harus ditutup).

### A.4 Komponen Reusable Baru (atomic design)
Buat di bawah `resources/js/Components/Wallet/` (folder baru, tidak mengubah `Components/AppIcon.vue` /
`EmojiPicker.vue` / `CuanAI.vue` yang sudah ada):
- `CardDompet.vue` — atom+molecule: nama dompet, saldo (format Rupiah, support `balanceHidden`), ikon/logo
  bank (reuse pattern `bank_color`/`bank_initial`/`logo_url` dari response `wallets`), progress bar
  opsional (prop `usagePercent?: number|null`), slot aksi cepat (edit/hapus/transfer).
- `TransactionItem.vue` — molecule: ikon kategori, nama/catatan, kategori + dompet + tanggal, nominal
  dengan warna debit/kredit. Terima props sesuai shape `transactions.data[]` (lihat kontrak B.1).
- `FilterSheet.vue` (mobile) / dapat 1 komponen `FilterDrawer.vue` yang adaptif berdasarkan breakpoint via
  CSS (bukan 2 komponen terpisah) — organism: form filter (date range, wallet select, category select,
  search input), emit event `apply` dengan payload query param.
- `ProgressBar.vue` — atom: `value` (0-100), `colorVar` (nama CSS var warna, default `--primary`).
- `EmptyState.vue` — atom: prop `icon`, `title`, `actionLabel?`, emit `action`.
- `ErrorState.vue` — atom: prop `message`, `retryLabel?`, emit `retry` (panggil ulang `router.reload()`).
- `SkeletonLoader.vue` — atom: prop `variant` (`'card' | 'list-item' | 'hero'`) untuk 3 bentuk skeleton
  berbeda sesuai konteks pemakaian.

Refactor `Dompet.vue` untuk memakai komponen-komponen ini menggantikan markup inline yang sekarang ada
(baris 114-208 template lama), styling lama di `<style scoped>` yang sudah dipindah ke komponen boleh
dihapus dari `Dompet.vue`.

### A.5 Aksesibilitas & UX
- [ ] Audit kontras semua kombinasi token warna (terutama tema `dark` draft) minimum WCAG AA (4.5:1 teks
  normal, 3:1 teks besar/UI component).
- [ ] Semua target tap (tombol ikon, chip, FAB, item transaksi) minimum 44×44px hit area — cek `.hero-eye-btn`
  (26px) dan `.chip` padding saat ini, kemungkinan perlu diperbesar padding/hit-area tanpa mengubah ukuran
  visual ikon.
- [ ] Tambahkan `aria-label` untuk tombol icon-only: `.hero-add-btn`, `.hero-eye-btn`, `.filter-btn`,
  tombol FAB di `AppLayout.vue`.
- [ ] Focus state: pastikan semua elemen interaktif custom (`.chip`, `.tx-item`, `.wallet-card` yang
  clickable div, bukan button/link asli) punya `:focus-visible` style jelas (pakai `--shadow-focus` yang
  sudah ada) — dan idealnya elemen clickable diubah ke `<button>` semantik kalau memungkinkan tanpa
  mengubah layout.

### A.6 Performa
- [ ] Virtualisasi daftar transaksi kalau data per-halaman besar — evaluasi pakai `vue-virtual-scroller`
  atau setara hanya jika daftar > ~50 item per render (paginasi backend sudah 30/halaman, jadi virtualisasi
  murni optimisasi kalau nanti page size dinaikkan; tidak wajib mengubah `per_page` 30 yang sekarang).
- [ ] Icon: `AppIcon.vue` (existing) dan emoji fallback sudah ringan — pastikan komponen baru tidak
  menambah image asset berat tanpa lazy-load (`loading="lazy"` untuk `<img>` logo bank).

### A.7 Integrasi Data & State
- [ ] Refresh reaktif: setelah `submitTx`, `submitTransfer`, `submitWallet` sukses — pola `onSuccess`
  sudah ada dan Inertia otomatis re-fetch props halaman (karena semua submit adalah request Inertia biasa
  ke controller yang sama, response balik ke halaman `Dompet` dengan props baru). **Tidak perlu perubahan
  backend** untuk ini, pastikan saja komponen baru tetap reaktif terhadap props (jangan simpan copy lokal
  dari `wallets`/`transactions` yang tidak sinkron ulang).
- [ ] Loading state saat request in-flight: gunakan `router.on('start'/'finish')` Inertia event atau flag
  `processing` dari `useForm` yang sudah dipakai — tambahkan skeleton state saat `router.reload()` dipicu
  oleh filter (perubahan baru, sebelumnya filter langsung `preserveState` tanpa indikator loading).

---

## B. Kontrak API

### B.1 Daftar Transaksi + Dompet + Tagihan (existing, extend query param)

#### Endpoint
GET `/dompet` (route name `dompet.index`, controller `TransactionController@index`)

#### Request (query params)
```
{
  range?: 'today' | 'week' | 'month',     // sudah ada
  period?: string,                         // 'YYYY-MM', sudah ada, override range
  start_date?: string,                     // BARU - format 'YYYY-MM-DD'
  end_date?: string,                       // BARU - format 'YYYY-MM-DD', wajib diisi bareng start_date
  wallet_id?: string(ulid),                // sudah ada
  type?: 'income' | 'expense',             // sudah ada
  category_id?: string(ulid),              // sudah ada
  search?: string,                         // sudah ada
  min_amount?: number,                     // sudah ada
  max_amount?: number,                     // sudah ada
  tab?: 'transaksi' | 'dompet' | 'tagihan' | 'in' | 'out' | 'bill', // sudah ada
  page?: number                            // sudah ada (Laravel paginator default)
}
```

#### Response (props Inertia `App/Dompet`, existing shape dipertahankan + tidak ada field yang dihapus)
```
{
  transactions: {
    data: [{
      id: string, type: 'income'|'expense', amount: number, note: string|null,
      category: string|null, category_emoji: string|null, category_icon_url: string|null,
      wallet: string|null, wallet_id: string, category_id: string|null,
      transacted_at: string('YYYY-MM-DD'), transacted_at_label: string,
      transacted_at_time: string|null, source: string
    }],
    links: array,          // standar Laravel paginator (prev/next/page links)
    current_page: number, last_page: number, per_page: number, total: number
  },
  wallets: [{
    id: string, display_name: string, account_number: string|null, type: 'cash_flow'|'saving'|'both'|'investment',
    balance: number, is_saham: boolean, bank_id: string|null, bank_name: string|null,
    bank_color: string, bank_initial: string, logo_url: string|null
  }],
  bills: [{ id, name, emoji, type, amount, due_day, due_date, remind_days, days_until_due, status_color, last_paid_at, is_paid_this_month }],
  banks: [...], categories: [...],
  period: string, range: string, range_label: string,
  start_date?: string, end_date?: string,     // BARU - echo balik filter aktif untuk state FilterDrawer
  total_income: number, total_expense: number, total_balance: number,
  active_wallets_count: number,
  cash_total: number, bank_total: number, ewallet_total: number,
  active_tab: string, search_query: string|null
}
```

#### Database
Tidak ada tabel/kolom baru. Filter `start_date`/`end_date` query langsung ke kolom `transactions.transacted_at`
yang sudah ada (`database/migrations/2025_01_01_000004_create_transactions_table.php`).

#### Validasi (tambahan di atas yang sudah ada)
- `start_date`: `nullable|date`
- `end_date`: `nullable|date|after_or_equal:start_date`
- Kalau `start_date`/`end_date` diisi, prioritas filter tanggal: `start_date`+`end_date` > `period` > `range`.
  Backend AI: terapkan sebagai `match`/`if` tambahan sebelum blok `match ($range)` yang sudah ada di
  `TransactionController@index` (baris ~33-41), pola:
  ```php
  if ($request->start_date && $request->end_date) {
      $query->whereBetween('transacted_at', [$request->start_date, $request->end_date]);
  } elseif ($request->period) {
      $query->forPeriod($request->period);
  } else {
      match ($range) { ... } // existing
  }
  ```
  Terapkan pola yang sama juga ke `$rangeSummaryQuery` (baris ~82-88) supaya ringkasan income/expense ikut
  konsisten dengan filter custom date range.

### B.2 Tambah/Update/Hapus Transaksi (existing, TIDAK berubah)
Endpoint & kontrak sudah final, dipakai apa adanya oleh Frontend AI:
- POST `/dompet` (`dompet.store`) — body: `{ type, amount, wallet_id, category_id?, note?, transacted_at }`
- PUT `/dompet/{transaction}` (`dompet.update`) — body sama seperti store
- DELETE `/dompet/{transaction}` (`dompet.destroy`)
- GET `/dompet/{transaction}/logs` (`dompet.logs`) — JSON array riwayat edit

### B.3 Kelola Dompet (existing, TIDAK berubah)
- POST `/dompet/wallets` (`wallets.store`) — body: `{ bank_id?, display_name, account_number?, initial_balance?, type: 'cash_flow'|'saving'|'both'|'investment', is_saham? }`
- PUT `/dompet/wallets/{wallet}` (`wallets.update`) — body: `{ display_name, account_number?, type, is_active? }`
- DELETE `/dompet/wallets/{wallet}` (`wallets.destroy`) — ditolak (redirect back dengan `error`) kalau saldo != 0
- POST `/dompet/transfer` (`wallets.transfer`) — body: `{ from_wallet_id, to_wallet_id, amount, note?, transferred_at }`

### B.4 Tagihan (existing, TIDAK berubah)
- GET/POST `/bills`, PUT `/bills/{bill}`, POST `/bills/{bill}/pay`, DELETE `/bills/{bill}`

### B.5 Theming — TIDAK ADA endpoint baru
Mekanisme ganti tema murni frontend (query param `?theme=` + `localStorage` + `import.meta.env.VITE_DEFAULT_THEME`,
lihat A.1). **Belum ada** penyimpanan preferensi tema per-user di database — kalau ke depan CEO minta toggle
tema publik yang persisten per user, itu task terpisah (butuh kolom baru `users.theme_preference` + endpoint
`PATCH /account/theme`). Di luar scope task ini, jangan diimplementasikan sekarang.

---

## C. Kriteria Selesai (acceptance, technical)
- [ ] 3 file token tema (`theme-blue.css`, `theme-green.css`, `theme-dark.css`) punya key CSS variable
  identik, tervalidasi via preview `?theme=green` dan `?theme=dark` tanpa console error / variable undefined.
- [ ] `AppLayout.vue` dan `Dompet.vue` tidak overflow/pecah pada lebar 360px–1440px.
- [ ] Semua komponen baru di `resources/js/Components/Wallet/` dipakai ulang minimal di `Dompet.vue`
  (bonus kalau reusable ke halaman lain, tidak wajib untuk task ini).
- [ ] Filter `start_date`/`end_date` berfungsi end-to-end (FilterDrawer → query param → response →
  `range_label` custom, mis. "12 Jun - 12 Jul 2026").
- [ ] Loading skeleton tampil saat `router.reload()`/filter berjalan, bukan blank/flash konten lama.

## D. Dokumentasi Developer (deliverable terpisah dari spec ini)
Frontend AI wajib menulis panduan singkat (boleh di `docs/theming-guide.md` terpisah, bukan bagian dari
spec ini) yang mencakup: cara menambah tema baru (copy salah satu file `theme-*.css`, isi semua key,
tambahkan opsi di whitelist `useTheme.js`), cara override token per-halaman (scope CSS variable di
`<style scoped>` komponen), cara aktifkan preview tema (`?theme=green` di URL, atau set
`VITE_DEFAULT_THEME` di `.env` lalu rebuild).
