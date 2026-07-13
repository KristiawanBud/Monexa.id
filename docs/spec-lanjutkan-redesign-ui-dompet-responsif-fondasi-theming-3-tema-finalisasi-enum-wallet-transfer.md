# Spec: Lanjutkan Redesign UI Dompet (Responsif) + Fondasi Theming (3 Tema) + Finalisasi Enum wallet_transfer

Sumber arahan: CEO AI. Spec ini adalah **kelanjutan** dari
`docs/spec-redesign-ui-dompet-jadi-responsif-fondasi-theming-biru-putih-hijau-putih-dark.md` (spec lama,
tidak dihapus). Sudah ada progres nyata di codebase sejak spec lama ditulis — bagian "Temuan Audit" di
bawah ini memetakan status **selesai vs belum**, supaya Backend/Frontend AI tidak mengulang kerjaan yang
sudah ada dan tahu persis apa yang harus dilanjutkan.

Stack: Laravel 13 + Inertia + Vue 3 (`<script setup>`), Tailwind (preflight OFF, dipakai minim), custom CSS
berbasis CSS Variables. Halaman target: `resources/js/Pages/App/Dompet.vue`, layout:
`resources/js/Layouts/AppLayout.vue`, controller: `app/Http/Controllers/App/TransactionController.php` +
`app/Http/Controllers/App/WalletController.php`.

---

## Temuan Audit Progres (baca sebelum mengerjakan apapun)

### A. UI Dompet Responsif — SUDAH JAUH LEBIH MAJU dari spec lama
- `Dompet.vue` sekarang sudah memakai komponen: `BalanceSummaryCard`, `CardDompet`, `TransactionItem`,
  `TransactionDateGroup`, `FilterDrawer`, `QuickActions`, `CategoryChipFilter`, `ExportButton`,
  `EmptyState`, `ErrorState`, `SkeletonLoader` (semua di `resources/js/Components/Wallet/`). Ini semua
  sudah **lebih lengkap** dari checklist A.4 di spec lama (ada tambahan `QuickActions`,
  `CategoryChipFilter`, `TransactionDateGroup`, `ExportButton` yang tidak diminta di spec lama).
- Tabs (`Transaksi`/`Dompet`/`Tagihan`) sudah punya `role="tab"`/`aria-selected`. Pull-to-refresh sudah ada
  (`pullDistance`/`refreshing`).
- Hero saldo (`BalanceSummaryCard`) sudah 100% token-driven: `background: linear-gradient(160deg,
  var(--primary) 0%, var(--primary-dark) 100%)`. Tombol `.hero-eye-btn` sudah 44×44px (tap target OK).
- `AppLayout.vue` **sudah punya** breakpoint tablet/desktop, didokumentasikan eksplisit di komentar kode
  ("Breakpoints kontrak QA: mobile ≤480px, tablet 481-1024px, desktop ≥1025px"): `@media (min-width: 481px)`
  (app-shell lebar penuh, bottom-nav max-width 600px) dan `@media (min-width: 1025px)` (sidebar kiri
  persisten, bottom-nav + FAB disembunyikan). Ini beda angka dari breakpoint yang diminta CEO sekarang
  (640/1024) — lihat keputusan di bawah.
- **Breakpoint di dalam `Dompet.vue` dan komponen `Components/Wallet/*.vue` sendiri ternyata tidak
  konsisten satu sama lain**: `Dompet.vue` pakai 481px/1025px (4 blok `@media`), `FilterDrawer.vue` pakai
  480px/481px, `BalanceSummaryCard.vue` pakai 768px, `QuickActions.vue` pakai 481px. Jadi bukan cuma beda
  dari angka yang diminta CEO (640/1024), tapi juga sudah beda-beda antar file dalam scope Dompet itu
  sendiri — ini gap kerapian yang sekalian perlu dirapikan (lihat keputusan breakpoint di A.1).
- Audit warna hardcoded: **tidak ditemukan** warna brand hardcoded yang bermasalah. Yang ada cuma
  `rgba(255,255,255,.x)` (overlay teks putih di atas hero gradient — aman, bukan warna brand) dan
  `rgba(15,23,42,.45)` (scrim modal/drawer overlay netral, dipakai di `Dompet.vue` & `FilterDrawer.vue`).
  Ini bukan pelanggaran "no hardcoded color" dalam artian brand color, tapi untuk kerapian token, jadikan
  1 variable overlay (lihat todo B.4).
- **Belum diverifikasi** (todo lanjutan, bukan sudah-selesai): kontras WCAG AA lengkap di tema dark/green
  untuk semua kombinasi teks, dan audit tap-target 44px untuk SEMUA tombol ikon (baru dicek
  `.hero-eye-btn`, belum semua `.chip`, `.filter-btn`, FAB).

### B. Fondasi Theming — SUDAH ADA FONDASI TEKNIS, BELUM ADA UI & PERSISTENSI DB
Sudah ada dan **berfungsi**:
- `resources/css/themes/theme-blue.css`, `theme-green.css`, `theme-dark.css` — 3 file, key CSS variable
  identik di ketiganya (diverifikasi manual): `--primary`, `--primary-light`, `--primary-dark`,
  `--secondary`, `--success`, `--danger`, `--primary-bg`, `--secondary-bg`, `--success-bg`, `--danger-bg`,
  `--amber`, `--amber-bg`, `--purple`, `--purple-bg`, `--ewallet`, `--ewallet-bg`, `--background`,
  `--surface`, `--border`, `--text-primary`, `--text-secondary`, `--text-faint`, `--radius-sm/md/lg/xl`,
  `--shadow-sm/md/lg/card/fab/focus`.
- `resources/js/Composables/useTheme.js` — prioritas resolusi: `?theme=` query → `localStorage.monexa_theme`
  → `import.meta.env.VITE_DEFAULT_THEME` → default `'blue'`. Whitelist ketat `['blue','green','dark']`.
  Dipanggil sekali di `resources/js/app.js` via `initTheme()`.
- `docs/theming-guide.md` sudah menjelaskan mekanisme + cara nambah tema baru.
- Tailwind `darkMode: 'class'` sudah dipakai — `useTheme.js` toggle class `.dark` di `<html>` saat tema
  `'dark'` aktif.

**Belum ada** (ini gap yang diminta CEO sekarang, jadi scope utama iterasi ini):
- Tidak ada kolom preferensi tema di database sama sekali (bukan di `users`, bukan di `user_profiles`).
- Tidak ada UI Settings > Appearance. Halaman settings yang sudah ada:
  `resources/js/Pages/App/Account.vue` + `app/Http/Controllers/App/AccountController.php` (route
  `account.profile`, `account.password`, `account.reset-data`) — ini home yang tepat untuk section baru
  "Appearance", bukan halaman baru.
- Tidak ada quick toggle tema di header manapun.
- Tidak ada `prefers-color-scheme: dark` fallback — saat ini default keras ke `'blue'` kalau semua sumber
  kosong.
- Nama token CSS variable existing (`--primary`, `--background`, `--surface`, dst) **berbeda penamaan**
  dari yang diminta CEO (`--color-primary`, `--color-bg`, `--color-surface`, dst). Keputusan: **jangan
  rename token existing** (dipakai luas di banyak file, blast radius besar, dan `docs/theming-guide.md`
  eksplisit bilang "jangan bikin sistem token baru dari nol"). Sebagai gantinya, lihat tabel pemetaan di
  bagian Kontrak B.1.
- Tidak ada token `--primary-contrast` (warna teks di atas tombol primary) — perlu ditambahkan, dan
  perhitungan kontras di bawah ini **menemukan masalah nyata** yang perlu diputuskan (lihat Keputusan
  Diperlukan #2).

### C. Enum wallet_transfer — BELUM ADA SAMA SEKALI, dan tidak ada kolom `type` di tabel `wallet_transfers`
- `app/Enums/` **belum ada** direktorinya sama sekali di repo ini.
- Tabel `wallet_transfers` (migration `2025_01_01_000011_create_wallet_transfers_table.php`) kolomnya:
  `id, user_id, from_wallet_id, to_wallet_id, amount, note, transferred_at, timestamps`. **Tidak ada kolom
  `type`.** Model `App\Models\WalletTransfer` juga tidak punya field `type`.
- Nilai enum-like konkret yang **benar-benar ada** di database terkait transfer dompet:
  - Tabel `wallet_balance_logs` (migration `2025_01_01_000003_create_banks_table.php`, dibuat bareng
    `user_wallets`) punya kolom `type` bertipe `enum('credit','debit')` — ini kolom native DB enum yang
    **sudah cocok persis** kalau mau di-cast ke PHP enum, TANPA migrasi konversi apapun.
  - Kolom `reference_type` di `wallet_balance_logs` adalah string bebas (bukan DB enum) dengan 3 nilai
    magic string yang dipakai di `app/Services/WalletService.php`: `'transaction'`, `'saving_deposit'`,
    `'wallet_transfer'`. Ini konsep BEDA dari `type` (credit/debit) — ini penanda "log ini dipicu oleh apa",
    bukan arah kredit/debit.
  - `app/Services/WalletService.php::transferBetweenWallets()` menulis 2 baris ke `wallet_balance_logs`
    langsung pakai `DB::table(...)->insert()` (bukan Eloquent) dengan `'type' => 'debit'` (wallet asal) dan
    `'type' => 'credit'` (wallet tujuan), `'reference_type' => 'wallet_transfer'`.
  - Kolom lain yang juga punya nilai enum-like tapi **beda konsep** (jangan tertukar): `user_wallets.type`
    = `enum('cash_flow','saving','both','investment')` — ini kategori dompet, bukan transfer. Divalidasi
    inline di `WalletController@store`/`@update` (`'type' => ['required','in:cash_flow,saving,both,investment']`).
    `transactions.type` = `enum('income','expense')` — juga sudah lama & dipakai luas.
- **Tidak ada** `app/Http/Requests/App/WalletTransferRequest.php` — validasi transfer saat ini inline
  `$request->validate([...])` di dalam `WalletController@transfer` (tidak ada field `type` di request body
  transfer sama sekali — arah debit/kredit ditentukan otomatis oleh server, bukan input user).
- **Tidak ada test sama sekali** untuk wallet transfer (`tests/` tidak punya file terkait). Tidak ada
  factory untuk `UserWallet` maupun `WalletTransfer` (`database/factories/` cuma ada `UserFactory.php`).
  Kedua model ini juga belum pakai trait `HasFactory`.

---

## Keputusan yang Perlu Dikonfirmasi CEO/Reviewer (jangan diimplementasikan sampai dikonfirmasi bagian yang ditandai ⚠️)

1. **⚠️ Definisi "enum wallet_transfer" ambigu** — tidak ada kolom `type` di tabel `wallet_transfers` di
   database saat ini, jadi tidak ada "kesepakatan sebelumnya" yang bisa dirujuk. Kandidat konkret yang
   memang ada di kode/DB:
   - **Opsi A (direkomendasikan, dipakai sebagai default di spec ini):** Enum `App\Enums\WalletTransfer`
     dengan cases `Debit = 'debit'` / `Credit = 'credit'`, di-cast pada kolom `wallet_balance_logs.type`
     (persis nilai yang sudah ada di DB hari ini, **tanpa migrasi konversi**). Ini paling dekat secara
     harfiah dengan "wallet_transfer" karena dipakai langsung oleh `transferBetweenWallets()`. Trade-off:
     kolom yang sama juga dipakai untuk `reference_type='transaction'` dan `'saving_deposit'` (bukan cuma
     transfer), jadi penamaan "WalletTransfer" untuk arah kredit/debit generik ini sedikit longgar secara
     semantik — tapi CEO secara eksplisit minta nama file `app/Enums/WalletTransfer.php`, jadi opsi ini
     yang diikuti.
   - **Opsi B (BUKAN scope task ini, hanya dicatat):** `user_wallets.type` (cash_flow/saving/both/investment)
     — ini punya pola validasi user-input + FormRequest + model cast yang lebih mirip deskripsi CEO
     ("Validasi: ... Rule::enum", "hanya menerima nilai enum yang valid"), tapi ini konsep "tipe dompet",
     bukan "transfer". Kalau CEO memang maksud ini, perlu enum terpisah `App\Enums\WalletType`, bukan
     `WalletTransfer` — task terpisah, tidak dikerjakan sekarang.
   - Backend AI: implementasikan **Opsi A** sesuai kontrak C di bawah, dan tulis catatan di deskripsi PR
     yang meminta konfirmasi CEO soal Opsi A vs B, persis seperti arahan CEO ("gunakan nilai aktual di
     DB/kode sebagai acuan... beri catatan di PR untuk konfirmasi").

2. **⚠️ Kontras token `--primary` sebagai warna tombol dengan teks putih di atasnya — GAGAL WCAG AA di 2
   dari 3 tema untuk teks normal.** Hasil hitung rasio kontras (formula WCAG relative luminance, teks putih
   `#FFFFFF` di atas `--primary` masing-masing tema):
   - Tema **blue** (`--primary: #2563EB`): kontras **5.17:1** → LULUS AA teks normal (≥4.5:1). ✅
   - Tema **green** (`--primary: #16A34A`): kontras **3.30:1** → GAGAL AA teks normal, hanya lulus ambang
     teks besar/bold (≥3:1). ❌ untuk teks kecil.
   - Tema **dark** (`--primary: #5B8DF8`): kontras **3.18:1** → GAGAL AA teks normal, hanya lulus ambang
     teks besar/bold. ❌ untuk teks kecil.
   - Implikasi: token `--primary-contrast` (baru, lihat B.4) **tidak bisa** selalu `#FFFFFF` di 3 tema.
     Perlu salah satu: (a) pakai `--primary-contrast` gelap (mis. `#0F172A`) khusus untuk tema green/dark
     pada elemen teks kecil di atas primary, atau (b) darken sedikit `--primary` khusus varian
     "button/contrast" (token baru `--primary-action`, terpisah dari `--primary` yang dipakai gradient
     hero), atau (c) pastikan semua teks putih-di-atas-primary di UI Dompet memang ukuran besar/bold
     (≥18.66px reguler atau ≥14px bold) sehingga ambang 3:1 berlaku, bukan 4.5:1 — perlu audit ukuran font
     aktual per elemen. Frontend AI wajib pilih salah satu & dokumentasikan pilihannya di PR, jangan
     asumsikan `#FFFFFF` aman di semua tema tanpa cek ini.

---

## A. Todo Teknis

### A.1 Redesign UI Dompet Responsif
- [ ] Terapkan 3-tier breakpoint **khusus konten halaman Dompet** (BUKAN mengubah breakpoint global
  `AppLayout.vue` yang dipakai SEMUA halaman lain di aplikasi, bukan cuma Dompet — resiko regresi terlalu
  besar untuk task ini, biarkan tetap 481px/1025px): mobile `<640px`, tablet `640–1023.98px`, desktop
  `>=1024px`. Terapkan sebagai `@media` di `<style scoped>` `Dompet.vue` + komponen
  `resources/js/Components/Wallet/*.vue`, konsisten dengan pola custom-CSS-media-query yang sudah dipakai
  di repo ini (bukan utility class Tailwind `sm:`/`lg:` — repo saat ini 0% pakai itu, jangan campur 2
  pendekatan sekaligus di file yang sama).
  - **Rapikan sekalian breakpoint yang sudah ada tapi tidak konsisten** di dalam scope Dompet: `Dompet.vue`
    (481px/1025px, 4 blok), `FilterDrawer.vue` (480px/481px), `BalanceSummaryCard.vue` (768px),
    `QuickActions.vue` (481px) — semua diganti ke 640px/1024px supaya satu skema breakpoint dipakai
    konsisten di semua file dalam `Pages/App/Dompet.vue` + `Components/Wallet/*`. `AppLayout.vue` (shell
    global: bottom-nav vs sidebar desktop) **tidak ikut diubah**, tetap 481px/1025px seperti sekarang —
    beda tanggung jawab (shell nav app-wide vs konten halaman Dompet).
  - Catatan: breakpoint 640/1024 ini kebetulan identik dengan default Tailwind `sm`/`lg`, tapi tetap tulis
    sebagai custom media query untuk konsistensi gaya file (bukan utility class).
- [ ] Audit ulang & pastikan 3 tier berikut benar-benar berbeda (saat ini `.tx-layout` tampaknya baru
  punya 2 varian tampilan — sidebar+main vs stack — verifikasi apakah sudah ada tier tablet yang
  benar-benar beda dari mobile & desktop, tambahkan kalau belum):
  - Mobile (`<640px`): header ringkas (`BalanceSummaryCard` compact), `FilterDrawer` sebagai bottom sheet,
    `QuickActions` sebagai FAB/tombol mengambang (pola `showQuickAdd` yang sudah ada di `AppLayout.vue`).
  - Tablet (`640–1023px`): 2 kolom (ringkasan di kolom kiri sempit + daftar transaksi kolom kanan), toolbar
    filter terlihat (bukan tersembunyi di sheet).
  - Desktop (`>=1024px`): grid ringkasan penuh di atas, daftar transaksi full-width dengan max-width
    terbatas (≈720px) supaya tidak melar, toolbar aksi (`Tambah Transaksi`, `Transfer`, `Kelola Dompet`)
    terlihat eksplisit di header, bukan FAB.
- [ ] Selesaikan audit aksesibilitas yang belum tuntas dari spec lama (A.5): tap target 44px untuk SEMUA
  tombol ikon (bukan cuma `.hero-eye-btn` yang sudah OK) — cek `.chip`, `.filter-btn`, FAB di
  `AppLayout.vue`, ikon aksi di `TransactionItem.vue`/`CardDompet.vue`. Tambahkan `aria-label` untuk semua
  tombol icon-only yang belum punya. Pastikan `:focus-visible` pakai `var(--shadow-focus)` di semua elemen
  interaktif custom (div yang berperan sebagai tombol).
- [ ] Tokenisasi overlay scrim yang masih hardcoded: ganti `rgba(15,23,42,.45)` (dipakai di
  `Dompet.vue` `.modal-overlay` dan `FilterDrawer.vue`) jadi variable baru `--overlay-scrim` yang
  didefinisikan di 3 file tema (lihat B.4) — supaya scrim juga konsisten kalau ke depan ada tema
  tambahan dengan warna scrim berbeda.
- [ ] Verifikasi 3 breakpoint × 3 tema tidak menyebabkan layout shift/overflow di lebar 360px, 768px,
  1440px (checklist QA, lihat bagian E).

### A.2 Theming — Persistensi & UI
- [ ] Migration baru: tambah kolom `theme` (`string`, nullable, tanpa default DB — default logis `'blue'`
  ditentukan di application layer, bukan di kolom) ke tabel `user_profiles` (BUKAN tabel `users` — ikuti
  pola existing, `UserProfile` sudah jadi rumah untuk preferensi user lain: `currency`, `timezone`,
  `notif_wa_enabled`, dst). Down: drop kolom.
- [ ] `App\Models\UserProfile`: tambah `'theme'` ke `$fillable`.
- [ ] `HandleInertiaRequests::share()`: tambahkan `'theme' => $request->user()?->profile?->theme,` sebagai
  top-level shared prop (dipakai composable untuk sinkron saat login).
- [ ] Endpoint baru `PUT /account/theme` (lihat kontrak B.2) di `AccountController` — method baru
  `updateTheme()`, terpisah dari `updateProfile()` supaya ganti tema tidak perlu kirim ulang
  name/wa_number/dll dan terasa instan.
- [ ] Update `resources/js/Composables/useTheme.js`:
  - Tambah fallback `prefers-color-scheme: dark` — urutan resolusi baru: `?theme=` query (override
    eksplisit, tetap tertinggi untuk keperluan preview) → `localStorage.monexa_theme` → shared prop Inertia
    `theme` dari DB (kalau ada & valid, tulis ulang ke `localStorage` supaya sinkron) → `window.matchMedia
    ('(prefers-color-scheme: dark)').matches ? 'dark' : null` → `import.meta.env.VITE_DEFAULT_THEME` →
    `'blue'`.
  - `setTheme(name)` sekarang juga mengirim `PUT /account/theme` (Inertia `router.put`, `preserveScroll:
    true, preserveState: true`) selain menulis `localStorage` — supaya tersimpan ke akun, bukan cuma
    device ini. Kalau request gagal (mis. offline), tema tetap berubah secara lokal (optimistic), tidak
    di-revert (persistensi DB hanya best-effort sync, bukan syarat switching tema instan).
- [ ] UI baru: section "Appearance" di `resources/js/Pages/App/Account.vue` — 3 pilihan radio/dropdown
  (label: "Biru & Putih (Default)", "Hijau & Putih", "Gelap"), memanggil `useTheme().setTheme()` langsung
  saat dipilih (tidak perlu tombol submit terpisah, instan).
- [ ] Quick toggle di header (`AppLayout.vue`) — **opsional/prioritas rendah** untuk iterasi ini (belum
  ada progres sebelumnya untuk ini, beda dari asumsi CEO). Kalau dikerjakan: 1 tombol ikon cycle
  light(blue)/dark saja (bukan cycle ke green, terlalu ambigu untuk quick toggle 2-state), reuse
  `setTheme()`.
- [ ] Tambahkan token `--primary-contrast` (dan opsional `--primary-action` kalau keputusan #2 di atas
  memilih opsi darken-varian) ke 3 file tema — nilai final menunggu keputusan CEO/Frontend AI di atas.
- [ ] Update `docs/theming-guide.md` (bukan bikin file baru): tambahkan bagian persistensi DB
  (`user_profiles.theme`), urutan prioritas baru, dan cara pakai UI Settings > Appearance.

### A.3 Enum wallet_transfer (mengikuti Opsi A yang direkomendasikan di atas)
- [ ] Buat `app/Enums/WalletTransfer.php` (PHP 8.1 native backed enum, `string`):
  ```php
  enum WalletTransfer: string
  {
      case Debit = 'debit';
      case Credit = 'credit';
  }
  ```
- [ ] Buat model Eloquent baru `app/Models/WalletBalanceLog.php` (BELUM ADA — saat ini akses tabel
  `wallet_balance_logs` selalu lewat `DB::table()` mentah):
  - `$fillable = ['wallet_id','type','amount','balance_before','balance_after','reference_type','reference_id']`
  - `casts()`: `'type' => WalletTransfer::class`, `'amount' => 'decimal:2'`, `'balance_before' =>
    'decimal:2'`, `'balance_after' => 'decimal:2'`.
  - Tabel tidak punya `updated_at` → set `const UPDATED_AT = null;`, `created_at` tetap dipakai (isi manual
    `now()` seperti sekarang, atau pakai `CREATED_AT` default + `$timestamps = true` dengan `UPDATED_AT =
    null` dan pastikan migration tidak butuh perubahan karena kolom `created_at` sudah ada).
  - Relasi `wallet(): BelongsTo` ke `UserWallet`.
  - Tidak perlu migrasi skema baru — kolom `type` di DB sudah `enum('credit','debit')`, cocok 1:1 dengan
    backing value enum PHP di atas.
- [ ] Refactor `app/Services/WalletService.php` — ganti semua `DB::table('wallet_balance_logs')->insert(...)`
  (3 tempat: `applyTransaction`, `depositToSaving`, `transferBetweenWallets`) jadi `WalletBalanceLog::create([...])`,
  dan ganti string magic `'credit'`/`'debit'` jadi `WalletTransfer::Credit`/`WalletTransfer::Debit` (Eloquent
  cast otomatis serialize ke value string saat disimpan). `reference_type` (`'transaction'`,
  `'saving_deposit'`, `'wallet_transfer'`) **tetap string biasa** — di luar scope enum ini, jangan diubah
  (hindari scope creep, itu konsep beda: "dipicu oleh apa", bukan arah kredit/debit).
- [ ] `app/Models/UserWallet.php`: tambah relasi baru `balanceLogs(): HasMany` ke `WalletBalanceLog` (untuk
  keperluan test & pelaporan), tidak mengubah relasi existing.
- [ ] Validasi: **tidak ada endpoint user-facing yang menerima field `type` mentah untuk
  `wallet_balance_logs`** saat ini (arah debit/kredit selalu ditentukan server, bukan input user), jadi
  tidak ada FormRequest baru yang perlu `Rule::enum(WalletTransfer::class)` untuk field ini. Proteksi cukup
  dari native enum casting (nilai invalid otomatis `\ValueError` saat hydration Eloquent). Kalau ke depan
  ada endpoint baru yang menerima `type` sebagai input user, WAJIB pakai `Rule::enum(WalletTransfer::class)`
  saat itu — dicatat di sini sebagai constraint desain, bukan todo yang dikerjakan sekarang.
- [ ] Test baru (tidak ada test wallet transfer sama sekali sebelumnya):
  - `database/factories/UserWalletFactory.php` (baru) + tambah `use HasFactory;` di `App\Models\UserWallet`.
  - `database/factories/WalletTransferFactory.php` (baru) + tambah `use HasFactory;` di
    `App\Models\WalletTransfer`.
  - `tests/Unit/Enums/WalletTransferTest.php`: assert `WalletTransfer::Debit->value === 'debit'`,
    `WalletTransfer::Credit->value === 'credit'`, `WalletTransfer::from('credit') ===
    WalletTransfer::Credit`, `WalletTransfer::tryFrom('invalid') === null`.
  - `tests/Unit/Models/WalletBalanceLogTest.php`: buat record dengan `type` raw string `'debit'`, assert
    `$log->type instanceof WalletTransfer && $log->type === WalletTransfer::Debit`.
  - `tests/Feature/WalletTransferTest.php` (baru):
    - Transfer sukses antar 2 wallet milik user sendiri → assert baris `wallet_transfers` dibuat, 2 baris
      `wallet_balance_logs` dibuat dengan `type` masing-masing `Debit` (wallet asal) & `Credit` (wallet
      tujuan), saldo kedua wallet ter-update benar.
    - Transfer gagal (redirect back dengan `error`) kalau saldo wallet asal kurang dari `amount`.
    - Transfer ditolak 403 kalau salah satu wallet bukan milik user yang request.
    - Transfer ditolak validasi 422 kalau `from_wallet_id === to_wallet_id` (rule `different` sudah ada).

### A.4 Non-fungsional
- [ ] Jalankan `./vendor/bin/pint` dan `./vendor/bin/phpstan analyse` (level 5, baseline di
  `phpstan-baseline.neon`) — tidak boleh ada error baru di luar baseline yang sudah ada.
- [ ] Visual QA: screenshot/GIF 3 breakpoint (360px/768px/1440px) × 3 tema (blue/green/dark) untuk: hero
  ringkasan saldo, daftar transaksi, modal transfer.
- [ ] Cross-browser: Chrome, Safari, Firefox terbaru. Fallback tanpa JS: `useTheme.js` tidak jalan → HTML
  tanpa `data-theme` attribute → jatuh ke `:root` fallback di `app.css` (= tema blue) — ini sudah otomatis
  benar by design, tinggal diverifikasi (disable JS di devtools, cek halaman tetap tema blue).

---

## B. Kontrak API

### B.1 Redesign UI Dompet Responsif — TIDAK ADA endpoint baru
Murni perubahan CSS/markup Vue, mengonsumsi endpoint yang sudah ada dan tidak berubah kontraknya:
- GET `/dompet` (`dompet.index`) — response shape tetap seperti didokumentasikan di spec lama bagian B.1.
- POST/PUT/DELETE `/dompet`, `/dompet/wallets`, `/dompet/transfer` — tidak berubah.

Tabel pemetaan token CEO (nama generik) → token existing repo (dipakai apa adanya, tidak di-rename):

| Nama diminta CEO | Token existing dipakai | Catatan |
|---|---|---|
| `--color-bg` | `--background` | sudah ada, identik di 3 tema |
| `--color-surface` | `--surface` | sudah ada |
| `--color-text` | `--text-primary` | sudah ada |
| `--color-muted` | `--text-secondary` | sudah ada (ada juga `--text-faint` untuk level lebih pudar) |
| `--color-primary` | `--primary` | sudah ada |
| `--color-primary-contrast` | `--primary-contrast` | **BARU**, lihat Keputusan #2 sebelum isi nilainya |
| `--color-border` | `--border` | sudah ada |
| `--color-success` | `--success` | sudah ada |
| `--color-danger` | `--danger` | sudah ada |
| `--radius-sm/md/lg` | `--radius-sm/md/lg` (+ ekstra `--radius-xl`) | sudah ada, nama identik |
| `--shadow-sm/md` | `--shadow-sm/md` (+ ekstra `--shadow-lg/card/fab/focus`) | sudah ada, nama identik |
| (scrim overlay, belum diminta CEO tapi ditemukan saat audit) | `--overlay-scrim` | **BARU**, lihat A.1 |

### B.2 Theming — Endpoint Baru: Simpan Preferensi Tema
#### Endpoint
`PUT /account/theme` (nama route: `account.theme`)

#### Request
```
{
  theme: 'blue' | 'green' | 'dark'   // required
}
```

#### Response
Redirect `back()` (pola Inertia existing di `AccountController`, bukan JSON) dengan:
```
flash.success: "Tema berhasil diganti!"
```
Shared prop `theme` (dari `HandleInertiaRequests`) otomatis ter-update di response berikutnya karena
Inertia me-reload shared props setelah redirect.

#### Database
Tabel: `user_profiles`. Kolom baru: `theme` (`string`, nullable). Migration baru diperlukan (lihat A.2).
Tidak ada tabel baru.

#### Validasi
- `theme`: `['required', 'string', Rule::in(['blue', 'green', 'dark'])]` (bukan `Rule::enum` karena tidak
  ada PHP enum untuk tema — kalau mau konsisten dengan gaya enum di scope C, boleh juga buat
  `App\Enums\Theme` kecil terpisah dan pakai `Rule::enum(Theme::class)`; opsional, tidak wajib, jangan
  campur dengan enum `WalletTransfer` yang konsepnya beda total).
- Simpan via `UserProfile::updateOrCreate(['user_id' => $user->id], ['theme' => $request->theme])` (pola
  sama seperti `updateProfile()` yang sudah ada).

### B.3 Wallet Transfer (existing, TIDAK berubah kontrak request/response)
- POST `/dompet/transfer` (`wallets.transfer`) — body: `{ from_wallet_id, to_wallet_id, amount, note?,
  transferred_at }`. **Tidak ada field `type` di request ini** — arah debit/kredit ditentukan server.
- Response: tetap `back()` dengan flash `success`/`error`, tidak berubah.

#### Database (perubahan internal, bukan kontrak API)
- Tabel `wallet_balance_logs` — kolom `type` (sudah `enum('credit','debit')`, tidak berubah skema) sekarang
  diakses lewat model Eloquent baru `WalletBalanceLog` dengan cast ke `App\Enums\WalletTransfer`, bukan
  lagi lewat `DB::table()` mentah.
- Tabel `wallet_transfers` — tidak ada perubahan skema.

#### Validasi
Tetap seperti existing di `WalletController@transfer` (inline `$request->validate()`):
`from_wallet_id: required|exists:user_wallets,id|different:to_wallet_id`,
`to_wallet_id: required|exists:user_wallets,id`, `amount: required|numeric|min:1`,
`note: nullable|string|max:255`, `transferred_at: required|date`. Tidak ada penambahan field.

---

## C. Kriteria Selesai (acceptance)
- [ ] 3 breakpoint (mobile <640 / tablet 640-1023 / desktop >=1024) diverifikasi tidak overflow di halaman
  Dompet pada 360px, 768px, 1440px, untuk ketiga tema.
- [ ] `user_profiles.theme` tersimpan & terbaca ulang setelah logout-login (persistensi lintas device).
- [ ] `prefers-color-scheme: dark` jadi fallback yang benar ketika user belum pernah memilih tema (tidak
  ada di localStorage maupun DB) — verifikasi dengan clear localStorage + OS dark mode aktif.
- [ ] Settings > Appearance di `Account.vue` bisa ganti 3 tema, instan, tanpa reload penuh/flicker.
- [ ] `App\Enums\WalletTransfer` dipakai di `WalletBalanceLog::$casts`, semua magic string `'credit'`/
  `'debit'` di `WalletService.php` sudah diganti ke enum cases.
- [ ] Semua test baru (unit enum, unit model cast, feature transfer) hijau. Tidak ada regresi test lama.
- [ ] `pint` dan `phpstan analyse` bersih (tidak ada error baru di luar baseline existing).
- [ ] PR menyertakan catatan eksplisit meminta konfirmasi CEO untuk Keputusan #1 (definisi enum
  wallet_transfer: Opsi A vs B) dan Keputusan #2 (nilai final `--primary-contrast` per tema).
