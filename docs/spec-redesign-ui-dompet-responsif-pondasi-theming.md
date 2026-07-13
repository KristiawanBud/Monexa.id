# Spec: Redesign UI Dompet Responsif + Pondasi Theming — Round 4

Sumber arahan: CEO AI. Referensi visual: `storage/athena-refs/monexa-1783905868539.jpg` (hero saldo gradien
biru dengan ilustrasi dompet 3D + sparkle, badge "X Dompet Aktif", 3 kartu ringkasan Saldo Cash/Bank/E-Wallet
dengan progress bar, tab Transaksi/Dompet/Tagihan, filter tanggal dropdown + ringkasan Masuk/Keluar/Saldo,
search bar + tombol Filter, list transaksi harian dikelompokkan per tanggal dengan ikon kategori berwarna,
bottom nav dengan FAB tengah "Tambah Transaksi").

Stack: Laravel 13 + Inertia + Vue 3 (`<script setup>`), Tailwind (`preflight:false`, minim) + CSS Variables
custom di `resources/css/app.css`. Pola response tetap **Inertia props** untuk `GET /dompet` dan
**redirect + flash message** untuk mutasi (kecuali 2 endpoint JSON existing: `dompet.logs`,
`dompet.balanceTrend`).

## ⚠️ WAJIB DIBACA SEBELUM IMPLEMENTASI — Status Existing

Brief CEO kali ini (task "Redesign UI Dompet (responsif) + pondasi theming") secara substansi **sudah
dikerjakan hampir seluruhnya** di branch yang sama, lewat 3 putaran sebelumnya:
- `docs/spec-redesign-ui-dompet-jadi-responsif-fondasi-theming-biru-putih-hijau-putih-dark.md` (Round 1)
- `docs/spec-redesign-ui-dompet-responsif-sistem-tema-hijau-putih-dark-mode.md` (Round 2)
- `docs/spec-redesain-ui-dompet-responsif-siapkan-infrastruktur-tema-ganti-warna-dark-mode.md` (Round 3)
- `docs/theming-guide.md` dan `docs/dompet-module.md` — dokumentasi developer hasil Round 1-3.

**Jangan kerjakan ulang** apa yang sudah disebut di tiga spec itu. Todo di bawah ini **HANYA** mencakup gap
riil yang saya temukan setelah membandingkan implementasi saat ini (kode di branch ini, sudah termasuk 3
komit terakhir: `166ed72` db, `01759cd` backend, `981d3da` frontend) terhadap brief CEO Round 4 ini.
Database/Backend/Frontend AI cukup baca §A di bawah untuk tahu apa yang benar-benar perlu dikerjakan.

Ringkasan yang **sudah beres** (verifikasi langsung ke kode saat ini, bukan asumsi dari spec lama):
1. Header dompet (judul "Dompet 👛", subjudul, tombol "＋" pojok kanan atas), hero saldo gradien biru,
   toggle sembunyikan saldo (persist via `user_profiles.hide_balance`, konsisten dengan Dashboard), badge
   "N Dompet Aktif", badge persentase perubahan saldo 7 hari — `BalanceSummaryCard.vue`, **match visual
   referensi**. Brief menyebut "tombol pengaturan" di header; tombol yang ada di referensi visual dan di
   kode saat ini adalah tombol "＋" (tambah transaksi cepat), bukan gear/settings — PM anggap ini sudah
   sesuai (lihat §E untuk konfirmasi ke owner), **tidak perlu tombol gear tambahan**.
2. Kartu ringkasan Saldo Cash/Bank/E-Wallet dengan progress bar — `BalanceSummaryCard.vue` breakdown card,
   sudah ada.
3. Aksi cepat (Catat Pemasukan/Pengeluaran, Transfer) — toolbar desktop `QuickActions.vue` (≥481px), FAB
   mobile via `AppLayout.vue` (bottom sheet: Pemasukan/Pengeluaran/Scan Struk/Setor Tabungan/Bayar Tagihan)
   — **sudah sesuai pola "toolbar desktop, FAB mobile"**.
4. Filter Semua/Income/Expense, rentang tanggal custom, kategori, kolom pencarian (debounced) — semua state
   terserialisasi ke query string via `router.get(route('dompet.index'), ...)` dan persisten saat reload —
   **sudah ada** (`FilterDrawer.vue`, `CategoryChipFilter.vue`).
5. Daftar transaksi terkelompok per tanggal, ikon kategori, catatan, jumlah berwarna +/-, edit & hapus
   (lewat modal edit yang dibuka dari klik baris transaksi) — **sudah ada**. **Kecuali**: opsi "duplikasi"
   dari brief **belum ada sama sekali** — lihat gap A.1.
6. Empty/error/skeleton state — `EmptyState.vue`, `ErrorState.vue`, `SkeletonLoader.vue` — sudah ada.
7. Breakpoint layout: kode saat ini pakai batas `481px` (bukan `640px`) dan `1025px` untuk
   mobile/tablet/desktop, grid dompet 1→2→3→4 kolom adaptif, panel filter transaksi pindah ke kanan pada
   desktop (≥1025px) — ini **penyimpangan kecil dari angka literal di brief (640/1024) yang sudah dibahas
   dan sengaja dipertahankan di Round 3** (lihat spec Round 3 §A.1 dan §E) karena breakpoint `481px` dipakai
   bersama oleh banyak halaman lain lewat `AppLayout.vue` — refactor breakpoint global berisiko regresi
   di luar Dompet dan CEO belum pernah menegaskan ulang bahwa angka literal itu wajib. **Tidak dimasukkan
   lagi sebagai todo**, tapi pertanyaan ke owner tetap dibawa maju di §E karena belum pernah dijawab.
8. Tap target ≥44px, teks 13-16px, focus-visible state — audit cepat ke `TransactionItem.vue`,
   `QuickActions.vue` menunjukkan `min-height:44px` sudah konsisten dipakai.
9. Pondasi tema 3 varian (`theme-blue.css`/`theme-green.css`/`theme-dark.css`) via CSS variables, toggle
   dev di `Account.vue` (gated `VITE_ENABLE_THEME_TOGGLE`), override `?theme=light-blue|light-green|dark`
   di URL untuk QA (`useTheme.js` — perhatikan: value internnormalisasi ke `blue|green|dark`, lihat A.2
   catatan naming), auto-detect `prefers-color-scheme` di belakang kondisi "belum ada pilihan manual" —
   **semua sudah ada dan berfungsi**, default tetap Biru-Putih.
10. `docs/dompet-module.md` (struktur komponen, props, katalog `trackEvent`) dan `docs/theming-guide.md`
    (cara ganti tema untuk QA) — **sudah ada**, akan di-extend (bukan dibuat ulang) di §D.

Todo teknis di bawah ini mencakup **5 gap konkret**: 1 fitur produk (duplikasi transaksi), 1 penyesuaian
naming event analytics, 1 penambahan test infrastructure (sebelumnya nol), 1 dokumen laporan baru, dan 1
audit non-kode (Lighthouse/WCAG).

---

## A. Todo Teknis (breakdown per gap)

### A.1 Menu konteks transaksi: tambah opsi "Duplikasi" (Backend + Frontend AI)
Brief eksplisit minta daftar transaksi punya "menu konteks (edit/hapus/duplikasi)". Edit & hapus sudah ada
(lewat modal edit transaksi, tombol "🗑️ Hapus Transaksi" di dalamnya). **Duplikasi belum ada di mana pun**
(tidak ada endpoint, tidak ada tombol/menu). Lihat kontrak B.1.
- [ ] Backend: endpoint baru `POST /dompet/{transaction}/duplicate` di `TransactionController` — buat
  transaksi baru dengan field disalin dari transaksi asal (`wallet_id`, `category_id`, `type`, `amount`,
  `note`), `transacted_at` = **hari ini** (bukan tanggal transaksi asal — lihat asumsi di B.1 dan
  pertanyaan ke owner di §E), `source = 'manual'`, terapkan lewat `WalletService::applyTransaction()` (pola
  identik `store()`), catat `TransactionEditLog` dengan `action = 'create'` (reuse enum existing, **tidak
  perlu migration** — nilai `'duplicate'` tidak ada di enum `transaction_edit_logs.action` dan tidak perlu
  ditambah untuk fitur ini).
- [ ] Frontend: tambah tombol/opsi "📋 Duplikasi" di modal edit transaksi (`Dompet.vue`, sejajar tombol
  "🗑️ Hapus Transaksi" yang sudah ada — pola UI paling murah, tidak perlu bikin komponen context-menu/
  kebab-menu baru dari nol) yang memanggil `router.post(route('dompet.duplicate', transaction.id))`.
  Tutup modal & tampilkan flash message existing pattern setelah sukses. Tambah `trackEvent` sesuai A.2.

### A.2 Analytics: tambah 3 event dengan nama eksak dari brief (Frontend AI, client-only)
Brief eksplisit minta event `wallet_opened`, `transaction_filter_used`, `quick_add_clicked`. Katalog
`trackEvent` yang ada sekarang (lihat `docs/dompet-module.md` §Katalog) pakai nama berbeda
(`dompet_filter_apply`, `dompet_quick_action`, dst.) dan **tidak ada event page-view sama sekali**. Karena
`trackEvent()` masih stub `console.debug` tanpa consumer backend (lihat `lib/analytics.js`), ini **aman
ditambahkan tanpa migrasi/breaking change** — cukup tambah pemanggilan baru di titik yang sudah ada,
**event lama tetap dipertahankan** (additive, bukan rename) supaya tidak menghilangkan granularitas yang
sudah didokumentasikan.
- [ ] `wallet_opened` — panggil sekali di `onMounted()` `Dompet.vue`, payload `{}`.
- [ ] `transaction_filter_used` — panggil di titik pusat yang sama dengan `dompet_filter_apply` (submit
  `FilterDrawer`), **dan juga** di titik `dompet_category_chip`, `dompet_search`, `dompet_sort_change`, ganti
  range (`changeRange()`) — payload `{ filters }` (bentuk sama dengan payload `dompet_filter_apply` yang
  sudah ada, cukup di-reuse).
- [ ] `quick_add_clicked` — panggil di **kedua** titik: (a) `QuickActions.vue` toolbar desktop (titik yang
  sudah punya `dompet_quick_action`, tambahkan pemanggilan kedua di baris yang sama), **dan** (b)
  `AppLayout.vue` — FAB mobile & tombol sidebar desktop "Tambah Transaksi" (`showQuickAdd = true` dan tiap
  `goTo(...)` di bottom sheet) yang **saat ini tidak punya tracking sama sekali**. Payload
  `{ action: 'add-income'|'add-expense'|'transfer'|'scan'|'saving'|'bill', surface: 'dompet-toolbar'|'global-fab' }`.

### A.3 Test infrastructure frontend — belum ada sama sekali (Frontend AI)
Brief eksplisit minta "unit/snapshot komponen utama; e2e sederhana (responsive & theme switch)". Saat ini
**tidak ada test runner frontend terpasang** (`package.json` tidak punya `vitest`/`@vue/test-utils`/
`playwright`, tidak ada file `*.spec.*`/`*.test.*` di `resources/js`). Ini bukan cuma menulis test, tapi
menyiapkan infra dari nol — todo terpisah dari 2 gap fungsional di atas.
- [ ] Tambah devDependency `vitest`, `@vue/test-utils`, `happy-dom` (atau `jsdom`), tambah script
  `"test": "vitest run"` di `package.json`.
- [ ] Unit/snapshot untuk komponen utama: `BalanceSummaryCard.vue`, `TransactionItem.vue`,
  `TransactionDateGroup.vue`, `EmptyState.vue`, `ErrorState.vue`, `SkeletonLoader.vue`, `CardDompet.vue` —
  minimal render dengan props dasar + snapshot output, tidak perlu coverage mendalam tiap cabang logic.
- [ ] E2E sederhana (boleh pakai Playwright, tambah sebagai devDependency baru kalau belum ada) — 2 skenario
  cukup: (a) buka `/dompet` di 3 viewport width (375px/768px/1280px mewakili mobile/tablet/desktop), pastikan
  tidak ada error console dan elemen kunci (`hero-saldo-amount`, `.tab-row`, FAB atau `QuickActions` sesuai
  breakpoint) ter-render; (b) buka `/dompet?theme=light-green` dan `/dompet?theme=dark`, assert
  `document.documentElement.dataset.theme` berubah sesuai query param.
- [ ] Tambah script `"test:e2e": "playwright test"` (atau setara) di `package.json`.

### A.4 Dokumen laporan `docs/monexa/wallet_ui_redesign.md` (Frontend AI, deliverable dokumentasi)
Brief eksplisit minta dokumen laporan terpisah dari `docs/dompet-module.md`/`docs/theming-guide.md` (yang
sudah ada tapi sifatnya referensi teknis internal, bukan laporan ke CEO/owner). File ini **belum ada**
(`docs/monexa/` belum ada sebagai direktori).
- [ ] Buat `docs/monexa/wallet_ui_redesign.md` berisi minimal: (a) link ke referensi desain
  (`storage/athena-refs/monexa-1783905868539.jpg`) dan ringkasan keputusan UI/UX per breakpoint, (b) daftar
  perubahan kumulatif Round 1-4 (ringkas, boleh rujuk ke spec per-round untuk detail, jangan duplikasi
  seluruh isi spec), (c) cara ganti tema untuk QA — cukup rujuk/kutip ringkas dari `docs/theming-guide.md`
  yang sudah ada (jangan tulis ulang penuh), (d) 3 screenshot aktual halaman Dompet pada breakpoint
  mobile/tablet/desktop dengan tema default (Biru-Putih) — ambil dari environment dev setelah A.1-A.3 selesai.
- [ ] Changelog ringkas untuk PR — CEO minta judul PR **"Laporan penambahan"** yang merinci fitur/penyesuaian
  yang ditambahkan pada iterasi (Round 4) ini: duplikasi transaksi, 3 event analytics baru, test
  infrastructure, dokumen ini sendiri.

### A.5 Audit Lighthouse & kontras WCAG AA — bukan kontrak API, checklist QA (Frontend AI)
Brief kasih target angka konkret (Performance/Accessibility ≥90, CLS<0.1) dan syarat kontras WCAG AA untuk
3 tema. Ini audit manual/tooling, bukan perubahan kode terstruktur — masukkan sebagai langkah wajib sebelum
PR dibuka, bukan item kontrak API:
- [ ] Jalankan Lighthouse (Chrome DevTools atau `npx lighthouse`) di `/dompet` build production
  (`npm run build` dulu, jangan audit dev server tanpa minifikasi) untuk 3 tema; kalau skor Accessibility
  <90, perbaiki temuan spesifik (biasanya kontras teks/alt text ikon) sebelum lapor selesai.
  Kontras terutama pada teks putih di atas gradien hero (`--primary`/`--primary-dark`) dan warna
  `--success`/`--danger` di atas `--success-bg`/`--danger-bg` untuk ketiga tema (`theme-blue.css`,
  `theme-green.css`, `theme-dark.css`) — cek pakai kalkulator kontras (mis. WebAIM), rasio teks normal
  minimal 4.5:1.
- [ ] Pastikan CLS<0.1 — skeleton loader (`SkeletonLoader.vue`) harus punya dimensi yang match komponen
  aslinya (hero, card, list-item) supaya tidak ada layout shift saat data selesai fetch.

---

## B. Kontrak API

### B.1 `POST /dompet/{transaction}/duplicate` — duplikasi transaksi (BARU)

#### Endpoint
POST `/dompet/{transaction}/duplicate` (route baru `dompet.duplicate`, prefix `dompet`, middleware
`auth,subscribed,onboarded` sama seperti route `dompet.*` lain)

#### Request
```
POST /dompet/{transaction}/duplicate
{}   // tidak ada body, transaction id dari route param
```

#### Response
Redirect back + flash (pola identik `store()`/`update()`/`destroy()`, **bukan JSON**):
- Sukses: `back()->with('success', 'Transaksi berhasil diduplikasi.')`
- Gagal (`source === 'wallet_transfer'`): `back()->with('error', 'Transaksi hasil transfer antar dompet
  tidak bisa diduplikasi langsung.')`
- Gagal (`InsufficientBalanceException`, mis. duplikasi expense yang membuat saldo dompet negatif kalau
  wallet tidak mengizinkan minus): `back()->with('error', $e->getMessage())` — pola identik `store()`.

#### Database
Tidak ada kolom/tabel baru. Insert 1 row baru ke `transactions` (field: `user_id`, `wallet_id`,
`category_id`, `type`, `amount`, `note` — semua disalin dari transaksi asal; `transacted_at` = **tanggal
hari ini** (`now()->toDateString()`), **bukan** tanggal transaksi asal — lihat asumsi PM di §E; `source` =
`'manual'`; `created_by` = user aktif). 1 row baru ke `transaction_edit_logs` (`action = 'create'`, reuse
enum existing `['create','update','delete']` — **tidak perlu migration**).

#### Validasi
- `abort_if($transaction->user_id !== $request->user()->id, 403)` — pola identik `update()`/`destroy()`.
- `abort_if($transaction->source === 'wallet_transfer', ...)` → redirect error seperti di atas (pola
  identik proteksi yang sudah ada di `update()`/`destroy()` untuk transaksi hasil transfer).
- Tidak ada input request yang divalidasi (tidak ada body) — hanya route-model-binding `{transaction}`
  (implicit `exists` check dari Eloquent route binding, 404 kalau tidak ditemukan/soft-deleted).

---

### B.2 Analytics event tambahan — client-only, tidak ada endpoint baru

Tidak ada kontrak HTTP baru — `trackEvent()` tetap stub `console.debug` (lihat `lib/analytics.js`, belum ada
backend consumer). Tabel di bawah **ditambahkan** ke katalog existing di `docs/dompet-module.md`
(lihat §D), bukan pengganti:

| Event | Titik panggil | Payload |
|---|---|---|
| `wallet_opened` | `onMounted()` `Dompet.vue` | `{}` |
| `transaction_filter_used` | Titik sama dengan `dompet_filter_apply`, `dompet_category_chip`, `dompet_search`, `dompet_sort_change`, `changeRange()` | `{ filters }` (reuse payload existing) |
| `quick_add_clicked` | `QuickActions.vue` (desktop toolbar) & `AppLayout.vue` (FAB mobile + tombol sidebar + tiap opsi bottom sheet `goTo()`) | `{ action, surface: 'dompet-toolbar'\|'global-fab' }` |

#### Database
Tidak ada perubahan.

#### Validasi
Tidak ada — event tracking client-side murni, tidak melalui request HTTP tervalidasi.

---

## C. Kriteria Selesai Tambahan (di luar yang sudah tercakup Round 1-3 §C)

- [ ] Klik transaksi → modal edit menampilkan opsi "Duplikasi" selain "Hapus"; klik Duplikasi membuat
  transaksi baru bertanggal hari ini dengan wallet/kategori/nominal/catatan sama, saldo dompet ter-update
  sesuai, muncul di list tanpa perlu reload manual (Inertia partial reload existing).
- [ ] `console.debug('[analytics]', 'wallet_opened', {})` muncul sekali saat halaman `/dompet` dibuka
  (bisa diverifikasi manual di console browser dev).
- [ ] `console.debug('[analytics]', 'transaction_filter_used', ...)` dan `quick_add_clicked` muncul di
  titik-titik yang disebut B.2.
- [ ] `npm run test` (vitest) dan `npm run test:e2e` (Playwright) jalan tanpa error di CI/lokal.
- [ ] `docs/monexa/wallet_ui_redesign.md` ada dan berisi semua poin A.4.
- [ ] Lighthouse Performance & Accessibility ≥90, CLS<0.1 di halaman `/dompet` (build production, 3 tema).
- [ ] Tidak ada regresi terhadap flow existing (tambah/edit/hapus transaksi, filter tanggal, export CSV,
  transfer, arsip/pulihkan dompet, sort transaksi/dompet, toggle sembunyikan saldo, ganti tema via `?theme=`)
  — regression check manual sebelum PR dibuka.
- [ ] PR final dibuka dengan judul **"Laporan penambahan"**, deskripsi memuat changelog Round 4 (A.1-A.5).

## D. Catatan Perubahan Dokumentasi (deliverable Frontend AI, bukan bagian spec ini)
Setelah A.1-A.5 selesai, update:
- `docs/dompet-module.md` — tambah baris `dompet.duplicate` ke bagian relasi endpoint transaksi, tambah 3
  baris event baru (B.2) ke tabel "Katalog `trackEvent`" (jangan hapus baris existing), tambah 1 baris
  tentang lokasi test (`resources/js/**/*.spec.js`, dijalankan via `npm run test`).
- `docs/theming-guide.md` — tidak perlu perubahan (tidak ada gap infrastruktur tema di Round 4 ini).
- `docs/monexa/wallet_ui_redesign.md` — dokumen baru, lihat isi wajib di A.4.
- Changelog PR "Laporan penambahan" — cantumkan 5 gap A.1-A.5 sebagai daftar perubahan terhadap laporan
  Round 3 sebelumnya.

## E. Pertanyaan ke Owner (belum terjawab, tidak menghalangi implementasi A.1-A.5)
- **Tanggal duplikasi transaksi (B.1)**: PM asumsikan transaksi hasil duplikasi memakai **tanggal hari ini**
  (use case paling umum: user cepat mencatat pengeluaran berulang, mis. jajan kopi tiap hari), bukan tanggal
  transaksi asal. Kalau owner mau opsi tanggal asli dipertahankan (mis. untuk keperluan "salin transaksi lama
  ke bulan ini dengan tanggal sama"), Backend AI perlu tambah 1 baris logic (`transacted_at =
  $transaction->transacted_at` alih-alih `now()`) — perubahan kecil, tidak perlu spec baru, cukup konfirmasi
  sebelum Backend AI mengerjakan B.1.
- **Nama tombol header "pengaturan" (poin 1 brief)**: brief teks minta "tombol pengaturan" di header dompet,
  tapi referensi visual `monexa-1783905868539.jpg` dan implementasi saat ini menampilkan tombol "＋" (tambah
  transaksi cepat), bukan gear/settings. PM asumsikan ini cukup (redundant dengan FAB/toolbar aksi cepat yang
  sudah ada) dan **tidak menambah tombol gear terpisah** kecuali owner konfirmasi butuh halaman pengaturan
  dompet tersendiri (mis. urutan dompet, kategori kustom) — itu di luar scope Round 4, akan jadi task terpisah
  kalau dikonfirmasi.
- **Breakpoint literal 640/1024 vs implementasi 481/1025 (dibawa maju dari Round 3 §E, belum pernah
  dijawab)**: kalau owner memang butuh angka breakpoint persis 640px/1024px (bukan sekadar "3 kelas ukuran
  layar yang berfungsi baik"), ini perlu task terpisah untuk audit ulang breakpoint global di
  `AppLayout.vue` (dipakai banyak halaman lain, bukan cuma Dompet) — di luar scope todo A.1-A.5 di atas.
