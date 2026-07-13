# Spec: Redesign UI Dompet Responsif + Sistem Tema (Hijau/Putih & Dark Mode) — Round 2

Sumber arahan: CEO AI. Referensi visual: `storage/athena-refs/monexa-1783861756539.jpg` (hero saldo gradient
biru dengan ilustrasi dompet, 3 kartu ringkasan Saldo Cash/Bank/E-Wallet, tab Transaksi/Dompet/Tagihan,
filter tanggal + ringkasan masuk/keluar/saldo, search bar + tombol Filter, list transaksi harian dengan
ikon kategori berwarna).

Stack terkonfirmasi: Laravel 13 + Inertia + Vue 3 (`<script setup>`), Tailwind (`preflight:false`, dipakai
minim) + CSS Variables custom di `resources/css/app.css`. Tidak ada `app/Http/Resources` di project ini —
pola response adalah **Inertia props** (array manual via `->map()`) untuk `GET /dompet`, dan
**redirect + flash message** (`back()->with('success'/'error', ...)`) untuk semua mutasi. Tidak ada konsep
tenant eksplisit — scoping murni per `user_id`.

## ⚠️ WAJIB DIBACA SEBELUM IMPLEMENTASI — Status Existing

Task dengan judul hampir sama **sudah pernah dikerjakan** (lihat riwayat commit `e5cb669` dst dan spec lama
`docs/spec-redesign-ui-dompet-jadi-responsif-fondasi-theming-biru-putih-hijau-putih-dark.md`). Berikut yang
**SUDAH SELESAI** dan tidak perlu dikerjakan ulang — hanya **extend**, jangan bikin dari nol:

1. **Fondasi theming SUDAH ADA**: `resources/css/themes/theme-blue.css`, `theme-green.css`, `theme-dark.css`
   (key CSS variable identik: `--primary(-light/-dark)`, `--secondary`, `--success`, `--danger`, `--*-bg`,
   `--background`, `--surface`, `--border`, `--text-primary/-secondary/-faint`, `--radius-*`, `--shadow-*`),
   di-import di `resources/css/app.css`. Composable `resources/js/Composables/useTheme.js` (whitelist
   `['blue','green','dark']`, prioritas `?theme=` → `localStorage.monexa_theme` →
   `import.meta.env.VITE_DEFAULT_THEME` → `'blue'`), dipanggil via `initTheme()` di `resources/js/app.js`.
   Dokumentasi cara nambah tema baru sudah ada di `docs/theming-guide.md`. **Belum ada**: toggle UI publik
   (lihat §2.6 di bawah), auto-detect `prefers-color-scheme` (lihat §2.6).
2. **Layout responsif & komponen Dompet SUDAH ADA**: `resources/js/Pages/App/Dompet.vue` sudah dipecah pakai
   komponen di `resources/js/Components/Wallet/` (`CardDompet`, `BalanceSummaryCard`, `TransactionItem`,
   `TransactionDateGroup`, `FilterDrawer`, `CategoryChipFilter`, `EmptyState`, `ErrorState`,
   `SkeletonLoader`, `QuickActions`, `ExportButton`), sudah ada breakpoint grid 2 kolom tablet/desktop,
   filter tanggal custom (`start_date`/`end_date`), search dengan debounce, skeleton loading saat
   `router.reload()`, event tracking dasar (`trackEvent` dari `resources/js/lib/analytics.js`) untuk filter/
   search/quick-action. **Jangan tulis ulang struktur ini** — task Round 2 ini murni menambah fitur di atas
   fondasi yang sudah jalan.
3. **Transfer antar dompet SUDAH ADA** (`WalletController@transfer` + `WalletService::transferBetweenWallets`)
   tapi **HANYA mencatat ke `wallet_balance_logs`**, TIDAK membuat baris di tabel `transactions`. Ini gap
   nyata terhadap requirement CEO "catat sebagai dua transaksi (debit/kredit) yang terhubung" — lihat §2.3,
   perlu diperbaiki (bukan fitur baru, tapi bug/gap terhadap spec).
4. **Tidak ada** kolom `currency`, `is_primary`, unit test, e2e test, atau visual regression sama sekali di
   codebase saat ini (`tests/Feature/` kosong, `tests/Unit/` cuma boilerplate default Laravel). Lihat §5.

Todo teknis di bawah ini HANYA mencakup gap yang belum dikerjakan. Backend/Frontend/Database AI **tidak
perlu** menyentuh ulang fondasi theming atau struktur komponen yang sudah disebut di atas kecuali disebut
eksplisit perlu di-extend.

---

## 1. Todo Teknis (breakdown per gap)

### 1.1 Database (Database AI)
- [ ] Tambah kolom `user_wallets.is_primary` (boolean, default `false`) — dompet utama per user.
- [ ] Tambah kolom `user_wallets.currency` (char(3), default `'IDR'`) — kode mata uang ISO 4217.
- [ ] Tambah kolom `transactions.transfer_id` (nullable, char(26)/ulid, FK → `wallet_transfers.id`
  `nullOnDelete`) — penghubung 2 baris transaksi hasil transfer antar dompet.
- [ ] Extend enum `transactions.source` menambahkan value `'wallet_transfer'` — **pakai pola migration raw
  SQL yang sudah ada**, contoh persis di
  `database/migrations/2026_07_08_000001_add_cuanai_chat_to_transactions_source_enum.php`
  (`ALTER TABLE transactions MODIFY COLUMN source ENUM(...)`), jangan pakai `->change()` Doctrine DBAL.
- [ ] Migration terpisah untuk 3 perubahan di atas, urutan bebas asal setelah migration awal (tanggal file
  harus lebih baru dari `2025_01_01_000012_...`).
- [ ] TIDAK perlu tabel baru untuk mini-chart saldo 7/30 hari — reuse tabel `wallet_balance_logs` yang
  sudah ada (punya `wallet_id`, `balance_after`, `created_at`), lihat kontrak §2.5.
- [ ] TIDAK perlu kolom `last_used_at`/`archived_at` baru — "terakhir digunakan" dihitung on-the-fly via
  `withMax('transactions as last_transaction_at', 'transacted_at')`, dan "arsip" tetap pakai `is_active`
  (boolean existing) + `deleted_at` (soft delete existing), lihat §2.2.

### 1.2 Backend (Backend AI)
- [ ] `WalletController@store` & `@update`: terima & validasi field `currency` baru (§2.1).
- [ ] Endpoint baru set dompet utama (§2.1).
- [ ] Endpoint baru arsip/pulihkan dompet (§2.2), pisah dari `destroy` (yang tetap seperti sekarang).
- [ ] Refactor `WalletController@transfer` + `WalletService::transferBetweenWallets` supaya membuat 2 baris
  `Transaction` yang saling terhubung via `transfer_id`, **bukan cuma** insert ke `wallet_balance_logs`
  (§2.3). **PENTING**: hindari double-mutation saldo — reuse `WalletService::applyTransaction()` untuk kedua
  transaksi baru ini (fungsi itu sudah handle increment/decrement + insert `wallet_balance_logs`), JANGAN
  panggil `increment()`/`decrement()` manual lagi di `transferBetweenWallets()` kalau sudah lewat
  `applyTransaction()`, akan double-count.
- [ ] `TransactionController@update`/`@destroy`: tolak (422/`back()->with('error', ...)`) kalau
  `$transaction->source === 'wallet_transfer'` — transaksi hasil transfer tidak boleh diedit/dihapus satu
  sisi saja (akan merusak keterhubungan saldo dua dompet). Cukup pesan error mengarahkan user untuk tidak
  mengedit transaksi ini secara langsung (fitur "batalkan transfer" di luar scope task ini).
- [ ] `TransactionController@index`: tambah field `wallets[].currency`, `wallets[].is_primary`,
  `wallets[].last_transaction_at` ke response map (§2.1, §2.2).
- [ ] Endpoint baru GET tren saldo 7/30 hari untuk mini-chart (§2.5), lazy — dipanggil terpisah dari
  `dompet.index`, bukan bagian payload utama (payload utama sudah besar, jangan tambah beban di request yang
  paling sering dipanggil).

### 1.3 Frontend (Frontend AI)
- [ ] `CardDompet.vue`: tambah badge "Utama" (`is_primary`), tombol quick action "Jadikan Utama", "Arsipkan",
  "Salin No. Rekening" (clipboard, tidak perlu endpoint baru — pakai `navigator.clipboard.writeText`), format
  saldo mengikuti `wallet.currency` (pakai `Intl.NumberFormat` kalau `currency !== 'IDR'`, tetap pakai
  `formatRupiah` existing kalau `'IDR'`).
- [ ] Tab "Dompet": tambah kontrol sort (Saldo / Terakhir Dipakai / Alfabetis) + search box khusus daftar
  dompet — **client-side saja** di atas array `wallets` yang sudah dikirim (jumlah dompet per user kecil,
  tidak perlu endpoint/pagination baru).
- [ ] Tambah state "Dompet Diarsipkan" (toggle "Tampilkan yang diarsipkan") yang memanggil ulang `dompet.index`
  dengan query param `include_archived=1` (§2.2).
- [ ] `BalanceSummaryCard.vue` atau komponen baru `BalanceTrendChart.vue`: mini sparkline 7/30 hari, fetch
  lazy dari endpoint §2.5 saat card di-scroll ke viewport (tidak lazim di-render sekaligus dengan payload
  utama), skeleton saat loading, sembunyikan chart kalau gagal fetch (jangan blocking UI utama).
- [ ] Infinite scroll daftar transaksi: ganti pagination existing (kalau masih click-through) jadi
  "load more" via `IntersectionObserver` di sentinel bawah list, merge `transactions.data` per page tanpa
  mengubah tinggi elemen yang sudah ada di atas (hindari CLS) — backend TIDAK berubah, tetap pakai `page`
  param existing.
- [ ] Pull-to-refresh mobile: composable baru `resources/js/Composables/usePullToRefresh.js`, trigger
  `router.reload({ only: [...] })` pada gesture tarik-turun di atas scroll container, hanya aktif di viewport
  mobile.
- [ ] Toggle tema UI (§2.6): komponen baru `resources/js/Components/ThemeToggle.vue`, render **hanya** kalau
  `import.meta.env.VITE_ENABLE_THEME_TOGGLE === 'true'` (default `false` di `.env.example` — feature flag
  sesuai arahan CEO "toggle disembunyikan di UI sampai diaktifkan"). Tempatkan di menu Profil/Pengaturan
  (tema bersifat app-wide, bukan cuma halaman Dompet).
- [ ] `useTheme.js`: tambah auto-detect `prefers-color-scheme` (§2.6) + listener perubahan live kalau user
  belum pernah pilih manual.
- [ ] Tambah `trackEvent` baru: `dompet_theme_change`, `dompet_wallet_create`, `dompet_wallet_update`,
  `dompet_wallet_archive`, `dompet_wallet_restore`, `dompet_wallet_set_primary`, `dompet_transfer_submit`,
  `dompet_copy_account_number`, `dompet_pull_to_refresh`, `dompet_infinite_scroll_load_more`.
- [ ] Virtualisasi list transaksi (`vue-virtual-scroller` atau setara) — **hanya kalau** total item yang
  ter-render di DOM (akumulasi infinite scroll) melewati ~100 item, evaluasi setelah infinite scroll §1.3
  jalan, jangan diimplementasi prematur.

### 1.4 Non-functional
- [ ] CLS < 0.1: pastikan skeleton/placeholder mini-chart & infinite scroll punya tinggi tetap sebelum data
  masuk (reserve space).
- [ ] LCP < 2.5s mid-tier: lazy-load `BalanceTrendChart`, jangan block render hero saldo oleh fetch chart.
- [ ] Kontras token warna: audit ulang kombinasi `theme-dark.css` khusus untuk badge "Utama" & status arsip
  baru (elemen baru harus tetap pakai token existing, dilarang hardcode warna).

---

## 2. Kontrak API

### 2.1 Set Dompet Utama + Currency per Dompet

#### Endpoint
PATCH `/dompet/wallets/{wallet}/primary` (route name `wallets.setPrimary`)

Ditambahkan juga field `currency` ke endpoint existing:
- POST `/dompet/wallets` (`wallets.store`)
- PUT `/dompet/wallets/{wallet}` (`wallets.update`)

#### Request
Set primary — tidak ada body, cukup wallet id di URL:
```
PATCH /dompet/wallets/{wallet}/primary
{}
```

Extend body `wallets.store` / `wallets.update` (field lain tetap sama seperti existing, lihat kontrak lama
di `docs/spec-redesign-ui-dompet-jadi-responsif-fondasi-theming-biru-putih-hijau-putih-dark.md` §B.3):
```
{
  ...field existing (bank_id, display_name, account_number, initial_balance, type, is_saham),
  currency?: string   // BARU, default 'IDR' kalau tidak dikirim
}
```

#### Response
Semua tetap `RedirectResponse` (`back()->with('success'/'error', ...)`), konsisten pola existing —
**bukan** JSON. Props `wallets[]` di `GET /dompet` (`dompet.index`) bertambah field:
```
{
  ...field existing (id, display_name, account_number, type, balance, is_saham, bank_id, bank_name,
     bank_color, bank_initial, logo_url),
  currency: string,               // BARU, 'IDR' | 'USD' | 'EUR' | 'SGD' | 'MYR'
  is_primary: boolean,            // BARU
  last_transaction_at: string|null // BARU, format 'YYYY-MM-DD', hasil withMax(), null kalau belum pernah dipakai
}
```

#### Database
Tabel: `user_wallets`. Kolom baru: `is_primary boolean default false`, `currency char(3) default 'IDR'`.

#### Validasi
- `currency`: `nullable|string|in:IDR,USD,EUR,SGD,MYR` (whitelist kecil dulu, tambah currency lain di masa
  depan cukup extend rule ini, tidak perlu migration ulang karena kolom sudah generic char(3)).
- Set primary: wallet harus milik user (`abort_if($wallet->user_id !== $request->user()->id, 403)`), wallet
  harus `is_active = true` (tidak bisa jadikan dompet arsip sebagai utama). Saat set primary sukses, semua
  wallet lain milik user yang sama di-set `is_primary = false` dalam satu `DB::transaction()` (hanya boleh
  1 primary per user).

---

### 2.2 Arsip / Pulihkan Dompet

#### Endpoint
- PATCH `/dompet/wallets/{wallet}/archive` (route name `wallets.archive`)
- PATCH `/dompet/wallets/{wallet}/restore` (route name `wallets.restore`)

Endpoint `DELETE /dompet/wallets/{wallet}` (`wallets.destroy`) **TIDAK berubah** — tetap perilaku existing
(tolak kalau saldo != 0; soft-delete + `is_active=false` kalau punya histori transaksi; `forceDelete()` kalau
belum pernah dipakai). Arsip adalah aksi terpisah yang tidak menghapus, murni menyembunyikan dari pilihan
aktif tanpa syarat saldo.

Extend juga `GET /dompet` (`dompet.index`, existing) dengan query param baru `include_archived`.

#### Request
```
PATCH /dompet/wallets/{wallet}/archive
{}

PATCH /dompet/wallets/{wallet}/restore
{}

GET /dompet?include_archived=1   // BARU, boolean-ish, default tidak dikirim = false
```

#### Response
`archive`/`restore` — `RedirectResponse` flash message, pola sama seperti existing.

`GET /dompet` — `wallets[]` bertambah field `is_archived: boolean` (alias baca dari `!is_active` supaya FE
tidak perlu logic invert manual). Kalau `include_archived=1`, response memuat wallet dengan `is_active=false`
juga (existing query builder di controller memfilter `is_active=true` untuk `wallets`, perlu ditambah cabang
kondisional berdasar query param ini).

#### Database
Tidak ada kolom baru — reuse `user_wallets.is_active` (existing). "Arsip" = `is_active=false` TANPA soft
delete (`deleted_at` tetap null). Ini beda dari `destroy` existing yang soft-delete + nonaktifkan.

#### Validasi
- Wallet harus milik user yang login.
- `archive`: tolak kalau wallet adalah satu-satunya dompet aktif user (`user->wallets()->where('is_active',
  true)->count() <= 1` → error "Tidak bisa mengarsipkan dompet terakhir").
- `archive`: kalau wallet yang diarsipkan adalah `is_primary=true`, otomatis pindahkan status primary ke
  dompet aktif lain yang paling lama dipakai (`sort_order` terkecil) — jangan biarkan user tanpa dompet
  utama.
- `restore`: set `is_active=true`, tidak otomatis jadi primary.
- `include_archived`: `nullable|boolean` di `DompetFilterRequest`.

---

### 2.3 Transfer Antar Dompet — Perbaikan: Catat sebagai 2 Transaksi Terhubung

#### Endpoint
POST `/dompet/transfer` (`wallets.transfer`, **existing, method sama, behavior internal berubah**)

#### Request
Tidak berubah dari existing:
```
{ from_wallet_id: string(ulid), to_wallet_id: string(ulid), amount: number, note?: string, transferred_at: string('YYYY-MM-DD') }
```

#### Response
Tetap `RedirectResponse` flash message existing. Efek samping berubah: sekarang setelah transfer sukses,
2 baris baru muncul di `transactions.data` pada request `GET /dompet` berikutnya:
```
// baris 1 (di dompet asal)
{ type: 'expense', amount, wallet_id: from_wallet_id, source: 'wallet_transfer', category_id: null, note: note ?? 'Transfer ke {nama dompet tujuan}' }
// baris 2 (di dompet tujuan)
{ type: 'income', amount, wallet_id: to_wallet_id, source: 'wallet_transfer', category_id: null, note: note ?? 'Transfer dari {nama dompet asal}' }
```
Kedua baris punya `transfer_id` yang sama (tidak perlu diekspos ke frontend kalau tidak dipakai UI, opsional
ditambahkan ke shape `transactions.data[]` untuk keperluan grouping visual di masa depan).

#### Database
Tabel: `transactions`. Kolom baru dipakai: `transfer_id` (FK → `wallet_transfers.id`), `source` bernilai
`'wallet_transfer'` (value enum baru).

`wallet_balance_logs` **tetap terisi** seperti sekarang, tapi sekarang originnya dari
`WalletService::applyTransaction()` (dipanggil 2x, sekali per sisi) bukan blok insert manual terpisah di
`transferBetweenWallets()` — hindari duplikasi log per §1.2.

#### Validasi
Tidak berubah dari existing (`from_wallet_id` beda dari `to_wallet_id`, `amount` cukup, keduanya milik user
yang sama). Tambahan: `category_id` pada 2 transaksi baru selalu `null` — transfer bukan kategori
pemasukan/pengeluaran biasa, jangan tampilkan di breakdown kategori kalau ada laporan per kategori (di luar
scope task ini untuk mengubah halaman Laporan, cukup dicatat sebagai catatan integrasi).

---

### 2.4 Salin Nomor/Referensi Dompet

Tidak ada endpoint baru — murni aksi client-side (`navigator.clipboard.writeText(wallet.account_number)`)
memakai data `wallets[].account_number` yang sudah ada di response `GET /dompet` existing. Frontend AI cukup
tambah tombol di `CardDompet.vue` + `trackEvent('dompet_copy_account_number', { wallet_id })`.

---

### 2.5 Mini-Chart Tren Saldo 7/30 Hari

#### Endpoint
GET `/dompet/balance-trend` (route name `dompet.balanceTrend`)

#### Request (query params)
```
{ range: '7d' | '30d' }   // wajib
```

#### Response
```json
{
  "range": "7d",
  "points": [
    { "date": "2026-07-06", "total_balance": 10500000 },
    { "date": "2026-07-07", "total_balance": 10800000 },
    ...
  ]
}
```
Response ini JSON biasa (bukan Inertia props — dipanggil via `fetch`/`axios` dari frontend saat chart
di-mount, bukan navigasi halaman), konsisten dengan satu-satunya preseden JSON existing di project
(`TransactionController@editLogs`).

#### Database
Tabel: `wallet_balance_logs` (existing, tidak ada kolom baru). Query: untuk tiap wallet aktif milik user,
ambil `balance_after` terakhir per tanggal dalam rentang (`GROUP BY DATE(created_at), wallet_id ORDER BY
created_at DESC`, ambil row pertama per grup), forward-fill tanggal yang tidak ada log (pakai balance
terakhir sebelum rentang sebagai starting point), lalu jumlahkan `balance_after` semua wallet aktif per
tanggal → jadi satu angka `total_balance` per hari.

#### Validasi
- `range`: `required|in:7d,30d`.
- Kalau user belum punya `wallet_balance_logs` sama sekali dalam rentang (dompet baru dibuat) → `points`
  cukup berisi balance saat ini flat di semua tanggal (tidak error, tidak array kosong — frontend tidak perlu
  handle empty-array khusus untuk chart).

---

### 2.6 Toggle Tema UI + Auto-detect Dark Mode

Tidak ada endpoint baru — murni frontend, extend `useTheme.js` existing.

#### Perubahan composable `useTheme.js`
- Prioritas resolusi tema baru (tambahan langkah antara localStorage dan env var):
  `?theme=` query param → `localStorage.monexa_theme` → **`window.matchMedia('(prefers-color-scheme:
  dark)').matches` (kalau true dan user belum pernah eksplisit pilih tema → default `'dark'`)** →
  `import.meta.env.VITE_DEFAULT_THEME` → `'blue'`.
- Tambah listener `matchMedia(...).addEventListener('change', ...)` yang re-apply tema **hanya kalau**
  `localStorage.monexa_theme` belum pernah di-set (user belum pernah pilih manual via toggle) — begitu user
  set manual via `ThemeToggle.vue`, auto-detect berhenti override.
- Tetap whitelist `['blue', 'green', 'dark']`, tidak menyimpan literal `'system'` ke localStorage.

#### Komponen baru `ThemeToggle.vue`
- Props: tidak ada. Render kondisional di balik feature flag `import.meta.env.VITE_ENABLE_THEME_TOGGLE`.
- 3 opsi (Biru, Hijau, Gelap), memanggil `setTheme(name)` dari `useTheme()` existing, lalu
  `trackEvent('dompet_theme_change', { theme: name })`.
- Persistensi: `localStorage` saja (existing mechanism) — **TIDAK** menambah kolom `users.theme_preference`
  di database untuk task ini. Kalau ke depan CEO minta preferensi tema tersimpan per akun (lintas device),
  itu task terpisah (butuh migration + endpoint `PATCH /account/theme`), di luar scope Round 2 ini.

#### `.env.example`
Tambah `VITE_ENABLE_THEME_TOGGLE=false` (default mati, sesuai arahan CEO "toggle disembunyikan di UI sampai
diaktifkan" — aktifkan nanti dengan set `true` + rebuild, tanpa perlu deploy kode baru).

---

## 3. Kontrak API Existing (tidak berubah, dipakai apa adanya)

Referensi lengkap ada di `docs/spec-redesign-ui-dompet-jadi-responsif-fondasi-theming-biru-putih-hijau-putih-dark.md`
§B — ringkasan:
- GET `/dompet` (`dompet.index`) — daftar transaksi + wallet + bills + ringkasan, extend field per §2.1/§2.2.
- POST/PUT/DELETE `/dompet(/{transaction})` — CRUD transaksi, DELETE/PUT tambah validasi baru di §1.2 untuk
  transaksi bertipe `wallet_transfer`.
- GET `/dompet/{transaction}/logs` (`dompet.logs`) — riwayat edit, tidak berubah.
- GET `/dompet/export` — export CSV, tidak berubah.
- POST/PUT/DELETE `/dompet/wallets(/{wallet})` — CRUD dompet, extend field `currency` di §2.1.
- GET/POST/PUT/DELETE `/bills...` — tidak berubah, di luar scope Round 2 ini.

---

## 4. Kriteria Selesai (acceptance, technical)

- [ ] Kolom `is_primary`, `currency`, `transfer_id` tersedia di database dan ter-refleksi di response
  `GET /dompet`.
- [ ] Transfer antar dompet menghasilkan 2 baris baru di `transactions.data` dengan `source='wallet_transfer'`
  dan `transfer_id` sama, saldo kedua dompet berubah tepat sekali (tidak double-count, dicek via
  `wallet_balance_logs` — jumlah baris log per transfer = 2, bukan 4).
  Try to catch a double-count regression via test: total `SUM(balance_after - balance_before)` per transfer
  harus sama dengan `amount` di kedua sisi.
- [ ] Arsip dompet tidak menghapus data, dompet tetap muncul di `include_archived=1`, tidak bisa
  mengarsipkan dompet aktif terakhir.
- [ ] Set dompet utama: maksimal 1 `is_primary=true` per user pada satu waktu.
- [ ] Mini-chart 7/30 hari tampil tanpa blocking render hero saldo, gagal fetch tidak merusak halaman.
- [ ] Toggle tema tersembunyi secara default (`VITE_ENABLE_THEME_TOGGLE=false`), muncul & berfungsi saat
  diaktifkan; auto-detect `prefers-color-scheme` bekerja untuk user baru yang belum pernah pilih tema manual.
- [ ] Infinite scroll transaksi tidak menyebabkan CLS terukur pada elemen di atas sentinel.
- [ ] Tidak ada regresi pada transaksi/dompet/transfer/tagihan yang sudah ada sebelumnya (regression check
  manual terhadap flow existing sebelum Round 2: tambah transaksi, edit, hapus, filter, export CSV).

---

## 5. Testing & Dokumentasi (gap total — belum ada preseden di codebase)

Codebase saat ini **tidak punya test sama sekali** untuk modul Wallet/Transaction (`tests/Feature/` kosong,
`tests/Unit/` cuma boilerplate). Backend AI perlu membangun pola dari nol:
- [ ] Aktifkan `RefreshDatabase` trait di `tests/TestCase.php` (belum ada).
- [ ] Feature test (PHPUnit murni, project ini **tidak** pakai Pest meski `pestphp/pest-plugin` ada di
  `composer.json allow-plugins` — itu cuma izin plugin, bukan dependency aktif) untuk: `wallets.store`,
  `wallets.update`, `wallets.setPrimary`, `wallets.archive`/`restore`, `wallets.transfer` (termasuk assert
  2 baris transaksi baru + no double-count saldo), `dompet.balanceTrend`.
- [ ] Unit test util formatting currency (`formatRupiah` existing + fungsi baru multi-currency) dan state
  tema (`useTheme.js`: prioritas resolusi, whitelist, auto-detect).
- [ ] E2E smoke test (kalau ada tooling Playwright/Cypress — cek dulu apakah sudah terpasang di project,
  kalau belum, ini scope tambahan yang perlu dikonfirmasi CEO/owner sebelum dikerjakan): tambah dompet,
  transfer antar dompet, ganti tema, cek responsif 3 breakpoint (360px/768px/1440px).
- [ ] Dokumentasi: update `docs/theming-guide.md` existing dengan bagian toggle UI + auto-detect (bukan file
  baru, extend yang sudah ada). README modul Dompet baru di `resources/js/Pages/App/README.md` atau
  `docs/dompet-module.md` merangkum struktur komponen, props, dan daftar `trackEvent` yang dipakai.
- [ ] Changelog penambahan fitur Round 2 ini disiapkan oleh Frontend/Backend AI saat PR dibuka (bukan bagian
  dari spec ini, tapi wajib disertakan di deskripsi PR sesuai kriteria penerimaan CEO).

---

## 6. Dependensi & Pertanyaan ke Owner (belum terjawab, butuh konfirmasi sebelum implementasi §2.5/§2.6)

- Palet hijau brand resmi (hex value) — `theme-green.css` yang sudah ada saat ini masih **draft**
  (asumsi PM sebelumnya), belum dikonfirmasi owner. Kalau owner sudah punya brand hijau resmi, extend
  `theme-green.css` existing, jangan bikin file baru.
- Apakah toggle tema (§2.6) diaktifkan langsung setelah PR merge atau tetap `false` sampai instruksi lebih
  lanjut? Asumsi PM saat ini: **tetap `false`** sampai ada arahan eksplisit CEO/owner (sesuai brief "toggle
  disembunyikan di UI sampai diaktifkan").
  Currency selain IDR/USD/EUR/SGD/MYR — daftar ini asumsi PM berdasar target pasar Monexa (Indonesia +
  negara tetangga), tambahkan ke whitelist §2.1 kalau owner minta lebih banyak.
