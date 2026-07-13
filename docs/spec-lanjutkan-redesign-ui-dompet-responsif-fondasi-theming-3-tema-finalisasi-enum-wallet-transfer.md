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

---

## D. Audit Lanjutan (Iterasi 2 — 2026-07-14)

CEO memberi arahan lanjutan yang isinya (Scope A/B/C) pada dasarnya sama persis dengan spec di atas.
Sejak spec di atas ditulis, riwayat commit menunjukkan **Database → Backend → Frontend AI sudah
mengerjakan hampir seluruh isi bagian A/B/C** (commit `c24b98b` database migration, `5b32d22` backend,
`0825862` frontend — semua di branch ini). Bagian ini adalah audit ulang: apa yang **sudah terverifikasi
selesai** vs apa yang **masih jadi gap nyata**, supaya iterasi berikutnya tidak mengulang kerjaan yang
sudah ada maupun salah asumsi bahwa semuanya sudah 100% selesai.

### D.1 Terverifikasi SELESAI (dicek langsung ke kode, bukan asumsi dari commit message)
- **A.1/A.2 Breakpoint** — `Dompet.vue`, `BalanceSummaryCard.vue`, `FilterDrawer.vue`, `QuickActions.vue`
  semua sudah konsisten pakai `640px`/`1024px` (bukan lagi campuran 480/481/768/1025). `AppLayout.vue`
  tetap 481px/1025px sesuai keputusan (shell app-wide, di luar scope).
- **A.1 Overlay scrim** — token `--overlay-scrim: rgba(15,23,42,.45)` sudah ada di 3 file tema
  (`resources/css/themes/theme-{blue,green,dark}.css`) dan dipakai (bukan hardcoded lagi).
- **A.1 Tap-target 44px** — `.filter-btn`, `.tx-item`, `.wallet-card`, `.qa-btn` (QuickActions ≥640px)
  semua eksplisit `min-height: 44px`. Tombol aksi di scope Dompet (`.chip`, `.transfer-btn`,
  `.add-wallet-btn`, dst) berlabel teks (bukan icon-only), jadi tidak butuh `aria-label` tambahan untuk
  accessible name. Tidak ditemukan gap baru di audit ulang ini.
- **B — Migration `user_profiles.theme`** — `database/migrations/2026_07_13_000001_add_theme_to_user_profiles_table.php`
  persis sesuai kontrak B.2 (nullable, tanpa default DB, `after('app_name')`). `UserProfile::$fillable`
  sudah include `'theme'`.
- **B — Endpoint `PUT /account/theme`** — `routes/web.php:133`, `AccountController::updateTheme()`,
  `UpdateThemeRequest` (`Rule::in(['blue','green','dark'])`) — semua sesuai kontrak B.2 persis.
- **B — `useTheme.js`** — urutan resolusi sudah tepat sesuai spec: `?theme=` → `localStorage` → shared
  prop Inertia (dibaca dari `#app[data-page]` sebelum hydrate, ditulis balik ke localStorage) →
  `prefers-color-scheme: dark` → `VITE_DEFAULT_THEME` → `'blue'`. `setTheme()` sudah kirim
  `router.put(route('account.theme'), ...)` optimistic, tidak revert kalau gagal.
  `HandleInertiaRequests.php:38` share `theme` dari `$user->profile?->theme`.
- **B — UI Settings > Appearance** — `Account.vue` punya section "Tampilan" (`role="radiogroup"`, 3
  opsi, tap target 44px, instan tanpa submit terpisah) — sesuai spec.
- **B — Keputusan #2 (kontras `--primary-contrast`) SUDAH DIPUTUSKAN & DITERAPKAN**: tema blue pakai
  `#FFFFFF` (5.17:1, lulus AA), tema green & dark pakai `#0F172A` (masing-masing 5.42:1 & 5.62:1 vs
  primary, lulus AA) — opsi (a) di Keputusan #2 yang dipilih. Terdokumentasi lengkap di
  `docs/theming-guide.md` bagian "Kontras `--primary-contrast`". `docs/theming-guide.md` juga sudah
  diperbarui dengan bagian persistensi DB, urutan prioritas baru, cara pakai Settings > Appearance, dan
  "Cara menambah tema baru" (checklist token wajib + langkah audit kontras) — item todo A.2 "update
  theming-guide.md" **selesai**.
- **C — Enum & refactor** — `app/Enums/WalletTransfer.php` (native backed enum, `Debit`/`Credit`) persis
  sesuai kontrak. `app/Models/WalletBalanceLog.php` (baru) dengan cast `'type' => WalletTransfer::class`
  sudah ada. `WalletService.php` sudah 100% pakai `WalletBalanceLog::create()` + `WalletTransfer::Debit`/
  `::Credit` di 4 titik (`applyTransaction`, `depositToSaving` — debit & credit, `transferBetweenWallets`)
  — **tidak ada lagi** `DB::table('wallet_balance_logs')->insert()` maupun magic string `'credit'`/
  `'debit'` di file ini. `UserWallet` & `WalletTransfer` model sudah pakai `HasFactory`.
- **C — Test** — `tests/Unit/Enums/WalletTransferTest.php`, `tests/Unit/Models/WalletBalanceLogTest.php`,
  `tests/Feature/WalletTransferTest.php` (110 baris, cover: transfer sukses + 2 log debit/credit,
  saldo kurang, wallet bukan milik user, `from_wallet_id === to_wallet_id`) — semua ada, isinya sesuai
  kontrak test yang diminta di A.3. `database/factories/UserWalletFactory.php` dan
  `WalletTransferFactory.php` sudah dibuat.

### D.2 ⚠️ Temuan Baru — Perlu Ditindaklanjuti (bukan bagian dari spec asli, ditemukan saat audit ulang)

**Kolom `theme` DUPLIKAT & YATIM di tabel `users`** — migration
`database/migrations/2026_07_14_000018_add_theme_to_users_table.php` (`string('theme')->default('blue')
->after('id')`) menambahkan kolom `theme` KEDUA, kali ini di tabel `users`, di commit `315b9df` — commit
ini **di luar pola pipeline AI** (author `root@server1.gammakaryaconstruction.co.id`, bukan
`Athena AI Dev`; pesan commit `feat: add theme column to users table`, bukan format
`feat(<task-slug>): <role>` yang dipakai 3 commit sebelumnya) dan dibuat **setelah** migration
`user_profiles.theme` sudah ada & sudah dipakai penuh oleh Backend/Frontend (kontrak B.2 di atas).

Sudah diverifikasi lewat pencarian kode: kolom `users.theme` ini **tidak dipakai di mana pun** —
tidak ada di `User::$fillable`, tidak ada di `casts()`, tidak direferensikan controller/request/factory/
seeder/Vue manapun. Ini kolom mati yang membingungkan (dua sumber kebenaran untuk hal yang sama) dan
bertentangan langsung dengan keputusan eksplisit di bagian B audit lama ("BUKAN tabel `users` — ikuti
pola existing, `UserProfile` sudah jadi rumah untuk preferensi user lain").

- [ ] **Todo baru (Database AI)**: buat migration baru untuk `dropColumn('theme')` dari tabel `users`
  (jangan edit/hapus file migration `2026_07_14_000018` yang sudah ter-commit — Laravel migration yang
  sudah jalan di environment manapun tidak boleh diubah retroaktif, buat migration baru bertanggal
  setelahnya khusus untuk drop). Down-nya: tambahkan kembali kolom (`string('theme')->default('blue')
  ->after('id')`) supaya rollback aman.
- [ ] Sertakan catatan di PR: kolom ini kemungkinan hasil eksperimen/commit tidak sengaja di luar
  pipeline, minta konfirmasi CEO sebelum drop dieksekusi di production (meski di semua environment lokal
  yang dicek kolom ini kosong/tidak dipakai, tetap ada risiko in-flight write dari proses lain yang tidak
  terlihat dari repo ini).

### D.3 Gap Tersisa dari Catatan Implementasi CEO (belum ada sama sekali di kode)
CEO minta "Sertakan CHANGELOG ringkas dan update README/DEVNOTES tentang theming dan enum
wallet_transfer" — dicek: **tidak ada file `CHANGELOG.md`** di root repo, dan `README.md` **tidak
menyebut** theming maupun `wallet_transfer`/enum sama sekali.
- [ ] **Todo baru (Backend/Frontend AI, siapa pun yang membuat PR final)**: buat `CHANGELOG.md` baru
  (atau tambah entri kalau CEO ternyata ingin dibuatkan lebih dulu oleh role lain) berisi ringkasan 3
  scope (UI Dompet responsif, theming 3-tema, enum `WalletTransfer`) dengan referensi commit.
- [ ] Tambahkan section singkat di `README.md` yang menunjuk ke `docs/theming-guide.md` (sudah lengkap,
  tidak perlu ditulis ulang) untuk theming, dan menyebutkan `app/Enums/WalletTransfer.php` sebagai
  sumber kebenaran tipe log saldo dompet.

### D.4 Belum Terverifikasi Otomatis di Iterasi Ini
PM AI tidak menjalankan `pint`/`phpstan analyse`/`php artisan test` pada iterasi audit ini (di luar
kewenangan role — PM AI hanya menulis spec, tidak mengeksekusi perintah build/test terhadap kode). Audit
ini murni pembacaan kode statis. **Wajib dijalankan ulang oleh Backend AI sebelum PR final**:
- [ ] `./vendor/bin/pint --test` — bersih.
- [ ] `./vendor/bin/phpstan analyse` — tidak ada error baru di luar `phpstan-baseline.neon`.
- [ ] `php artisan test --filter=WalletTransfer` dan `--filter=WalletBalanceLog` — hijau semua.
- [ ] Migration baru di D.2 (drop `users.theme`) dijalankan di environment test, pastikan tidak ada
  proses lain yang bergantung pada kolom itu sebelum drop di production.

### D.5 Item Opsional yang Masih Belum Dikerjakan (sesuai spec asli, prioritas rendah, tidak wajib)
- Quick toggle tema di header `AppLayout.vue` — masih belum ada (dicek ulang: tidak ditemukan
  `cycleTheme`/toggle 2-state di `AppLayout.vue`). Sesuai spec asli A.2 ini **opsional/prioritas
  rendah**, boleh di-skip untuk PR ini kalau waktu terbatas — Settings > Appearance sudah cukup memenuhi
  acceptance criteria "bisa diganti oleh pengguna, persist antar reload".

### D.6 Housekeeping Minor (bukan bagian scope CEO, hanya dicatat saat audit)
Ditemukan banyak file backup stray `*.bak_YYYYMMDD_HHMMSS` di `resources/js/Pages/App/` (mis.
`Dompet.vue.bak_20260708_082752`, `Account.vue.bak_20260708_025736`, belasan `Report.vue.bak_*`,
`Dashboard.vue.bak_*`). Ini tidak memengaruhi build (bukan entrypoint yang di-import), jadi **tidak
wajib** dibersihkan sebagai bagian task ini — hanya dicatat sebagai potensi task housekeeping terpisah
di masa depan, jangan dikerjakan sekarang (di luar scope, hindari PR yang menyentuh file tidak relevan).

### D.7 Kriteria Selesai Tambahan (melengkapi bagian C di atas)
- [ ] Kolom `users.theme` sudah di-drop (atau CEO eksplisit konfirmasi untuk dipertahankan dengan alasan
  yang jelas) — tidak ada 2 sumber kebenaran untuk preferensi tema.
- [ ] `CHANGELOG.md` ada dan berisi ringkasan 3 scope task ini.
- [ ] `README.md` menyebut theming (link ke `docs/theming-guide.md`) dan enum `WalletTransfer`.
- [ ] `pint`, `phpstan analyse`, dan test suite wallet transfer dijalankan ulang dan hijau (dikonfirmasi
  di PR, bukan hanya diasumsikan dari audit statis PM AI).
