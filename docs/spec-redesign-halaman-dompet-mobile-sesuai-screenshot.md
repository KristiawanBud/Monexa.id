# Spec — Redesign Halaman Dompet (Mobile) sesuai Screenshot

Arahan asli: CEO AI, task "Redesign Halaman Dompet (Mobile) sesuai screenshot".
Referensi visual: `storage/athena-refs/monexa-1784234498463.jpg`.

## 0. Konteks penting — ini ENHANCEMENT, bukan halaman baru

Halaman Dompet **sudah ada dan sudah cukup dekat** dengan screenshot rujukan:

- Route: `GET /dompet` → `TransactionController@index` (name: `dompet.index`), render Inertia
  `App/Dompet` (`resources/js/Pages/App/Dompet.vue`).
- Header gradient + saldo + badge dompet aktif → sudah ada di
  `resources/js/Components/Wallet/BalanceSummaryCard.vue`.
- 3 kartu saldo (Cash/Bank/E-Wallet) + progress bar → sudah ada, komponen sama
  (`.breakdown-card` di `BalanceSummaryCard.vue`), datanya sudah dihitung backend
  (`cash_total`, `bank_total`, `ewallet_total`).
- Tab segmented Transaksi/Dompet/Tagihan → sudah ada (`.tab-row` di `Dompet.vue`).
- Search + tombol filter + bottom sheet filter → sudah ada
  (`FilterDrawer.vue`, `CategoryChipFilter.vue`), tapi filter masih single-select
  dan belum ada badge jumlah filter aktif.
- List transaksi dengan ikon kategori & warna nominal → sudah ada
  (`TransactionItem.vue`, `TransactionDateGroup.vue`).
- Bottom navigation fixed → sudah ada (`AppLayout.vue`, `.bottom-nav { position: fixed; bottom: 0; }`),
  tapi **belum** menghitung *safe-area inset* (notch/home indicator iOS).
- Loading/empty/error state components → sudah ada (`SkeletonLoader.vue`, `EmptyState.vue`, `ErrorState.vue`).
- Ilustrasi header → sudah pakai komponen `AppIcon` (slug `dompet_hero`), yaitu sistem icon
  ter-manage lewat admin panel (`app/Http/Controllers/Admin/IconController.php` +
  `HandleInertiaRequests` yang nge-share prop `icons`), dengan fallback emoji `👛` selama admin
  belum upload asetnya.

Karena itu, spec ini **tidak** membuat endpoint/tabel baru untuk hal yang sudah tersedia. Fokus
kontraknya ada di 3 gap fungsional yang diminta CEO tapi belum ada di backend:

1. Filter tipe & kategori **multi-select** (backend saat ini hanya terima 1 value).
2. Filter/tap-card berdasarkan **kelompok saldo** (Cash/Bank/E-Wallet).
3. Transaksi tipe **Transfer** ikut muncul & bisa difilter di list Transaksi (saat ini
   `WalletTransfer` terpisah total dari feed `Transaction`).

Selain itu ada beberapa keputusan desain (bukan kontrak API) yang perlu diketahui Frontend AI —
lihat bagian **9. Keputusan desain non-API**.

## 1. Todo Teknis (breakdown)

### Frontend AI
- [ ] Header (`BalanceSummaryCard.vue`): perbesar tipografi saldo ke 28–32sp/bold, pastikan tidak
  terpotong saat dynamic type besar (`min-height` bukan fixed height), sesuaikan tinggi
  header ~220–260dp responsive.
- [ ] Header: tambahkan varian gradient dark mode (turunkan brightness) — gunakan token
  `--primary`/`--primary-dark` dari `[data-theme='dark']` yang sudah ada, jangan hardcode hex.
- [ ] 3 kartu saldo: pastikan bisa scroll horizontal snap di layar sempit (< 360px), jaga spacing
  8–12dp konsisten. Ganti warna E-Wallet dari token `--ewallet` (ungu) ke `--amber` supaya cocok
  dengan hex yang diminta CEO (`#F59E0B`) — lihat §9.1.
- [ ] 3 kartu saldo: jadikan interaktif (tap → filter `balance_group`, lihat §3), tampilkan state
  aktif pada card yang sedang dipakai sebagai filter.
- [ ] Segmented tab: naikkan jadi gaya pill dengan indicator (bukan cuma chip aktif/nonaktif),
  pastikan tap target ≥44dp (sudah 44dp di beberapa tempat, audit ulang `.chip`).
  Simpan state per-tab (scroll position, search query, filter) — cek `tab` ref di `Dompet.vue`,
  saat ini pindah tab tidak reset apa pun karena semua tab dirender dengan `v-if` terpisah dan
  state disimpan di parent, jadi ini **kemungkinan sudah aman**, tinggal diverifikasi manual.
- [ ] `FilterDrawer.vue`: ubah `form.type` dari string tunggal jadi array (checkbox multi Pengeluaran/
  Pemasukan/Transfer, default semua tercentang), ubah `form.category_id` jadi array (multi-select),
  tambah preset cepat rentang tanggal (Hari ini/Minggu ini/Bulan ini) di dalam drawer selain yang
  sudah ada di `range-dropdown`, dan kirim `min_amount`/`max_amount` (input sudah divalidasi backend,
  UI-nya belum ada).
  Kirim payload sesuai kontrak §2.
- [ ] Tombol filter: tampilkan badge jumlah filter aktif (mis. `● 2`) — dihitung di client dari jumlah
  field non-default di `filters` reactive object, tidak perlu endpoint baru.
- [ ] Search bar: turunkan debounce dari 400ms (lihat `Dompet.vue` baris ~539) ke 300ms sesuai
  acceptance criteria.
- [ ] `TransactionItem.vue`: tambah dukungan `type === 'transfer'` (ikon 🔄, warna abu
  `var(--text-secondary)`, tanpa tanda +/−).
  Field `wallet` untuk transfer akan berisi label gabungan, lihat §4.
- [ ] Bottom nav (`AppLayout.vue`): tambahkan `padding-bottom: env(safe-area-inset-bottom, 0px)` ke
  `.bottom-nav`, dan sesuaikan `padding-bottom` di `.main-content` jadi
  `calc(88px + env(safe-area-inset-bottom, 0px))` supaya konten tidak ketutup di device dengan
  home indicator.
- [ ] Skeleton/empty/error state: pastikan dipakai juga untuk 3 kartu saldo saat loading (saat ini
  `SkeletonLoader` baru dipasang untuk list transaksi, belum untuk header saldo & kartu ringkasan).
- [ ] Analytics: panggil `trackEvent()` (stub yang sudah ada di `resources/js/lib/analytics.js`,
  belum ada backend consumer) dengan nama event di §7 pada titik interaksi terkait.
- [ ] Verifikasi kontras & label aksesibilitas (aria-label ikon kategori, kontras warna nominal)
  untuk light & dark mode.

### Backend AI
- [ ] `App\Http\Requests\App\DompetFilterRequest`: ubah rule `type` & `category_id` jadi array,
  tambah rule `balance_group`. Lihat kontrak lengkap §2 & §3.
- [ ] `TransactionController@buildFilteredQuery`: terima `type[]`/`category_id[]` (bukan lagi
  scalar), tambah filter `balance_group` via join/whereHas ke `wallet.bank`.
- [ ] `TransactionController@index` & `@exportCsv`: gabungkan (union) transaksi dari tabel
  `transactions` dan `wallet_transfers` jadi satu feed berpaginasi saat `type` filter menyertakan
  `transfer` atau tidak mengisi filter tipe sama sekali (default = semua termasuk transfer).
  Lihat pendekatan teknis & keterbatasan di §4.
- [ ] Pastikan `exportCsv` menerima filter yang sama (`type[]`, `category_id[]`, `balance_group`)
  supaya hasil export konsisten dengan hasil yang terlihat di layar.
- [ ] Tidak perlu endpoint analytics baru — §7 murni event client-side (stub sudah ada, belum ada
  consumer backend, di luar scope task ini).

### Database
- [ ] **Tidak ada migration baru.** Semua data yang dibutuhkan (`user_wallets.bank_id`,
  `banks.type`, `wallet_transfers.*`) sudah tersedia. Konfirmasi ke Database AI: kalau nanti mau
  index tambahan untuk query union (`wallet_transfers.transferred_at`), itu **opsional**
  peningkatan performa, bukan prasyarat fungsional.

### QA
- [ ] Regresi navigasi: pastikan link dari FAB "Tambah Transaksi" (di `AppLayout.vue`,
  query `tab=in`/`tab=out`/`tab=bill`) masih membuka tab yang benar setelah filter di-refactor.
  jangan sampai reset filter yang tersimpan di localStorage.
  jangan sampai reset filter yang tersimpan di localStorage (`monexa_dompet_filters`).
- [ ] Uji edge case saldo 0 di 3 kartu (progress bar 0%, tidak divide-by-zero — sudah ada guard
  di `barWidth()`).
- [ ] Uji filter kombinasi: tipe=Transfer saja + kategori dipilih → kategori harus diabaikan untuk
  baris transfer (transfer tidak punya kategori), pastikan tidak menghasilkan list kosong yang
  membingungkan (tampilkan hint di empty state bila kombinasi filter menghasilkan 0 hasil).
- [ ] Uji dark mode di 2 device (iOS notch, Android tanpa notch) untuk safe-area bottom nav.

## 2. Filter Transaksi — Multi-select Tipe & Kategori

Perluasan dari endpoint yang sudah ada, **bukan endpoint baru**.

### Endpoint
GET /dompet

### Request (query params — hanya yang berubah/baru dari kontrak lama)
```
{
  type?: string[],           // in [income, expense, transfer], default semua (3 nilai) kalau tidak dikirim
  category_id?: string[],    // id transaction_categories, boleh kosong = semua kategori
  wallet_id?: string|null,   // TETAP single-select ("salah satu atau semua"), tidak berubah
  balance_group?: 'cash'|'bank'|'ewallet'|null,  // BARU, lihat §3
  start_date?: string (Y-m-d),  // tidak berubah
  end_date?: string (Y-m-d),    // tidak berubah
  range?: 'today'|'week'|'month', // tidak berubah
  min_amount?: number,        // sudah ada, tinggal disambungkan ke UI
  max_amount?: number,        // sudah ada, tinggal disambungkan ke UI
  search?: string,            // tidak berubah, cari di kolom note (title/catatan)
  tab?: string,
  page?: number,
}
```
Kompatibilitas: backend harus tetap menerima `type`/`category_id` sebagai **string tunggal**
(format lama) dan menormalisasinya jadi array 1 elemen — supaya link lama (bookmark, localStorage
`monexa_dompet_filters` user existing) tidak rusak.

### Response (field baru di tiap item `transactions.data[]`)
```
{
  ...field lama tidak berubah (id, type, amount, note, category, category_emoji,
      category_icon_url, wallet, wallet_id, category_id, transacted_at,
      transacted_at_label, transacted_at_time, source),
}
```
Tidak ada field baru di sini — perubahan response untuk tipe `transfer` dijelaskan di §4.

### Database
Tabel: `transactions` (kolom dipakai: `type`, `category_id`, `wallet_id`, `amount`,
`transacted_at`). Tidak ada kolom baru.

### Validasi
- `type.*`: `in:income,expense,transfer`
- `category_id.*`: `exists:transaction_categories,id` (ULID/int sesuai PK — PK-nya
  `unsignedSmallInteger`, jadi `integer`)
- `balance_group`: `nullable|in:cash,bank,ewallet`
- Field lain: aturan validasi lama di `DompetFilterRequest` tidak berubah.
- Kalau `type` hanya berisi `transfer`, abaikan `category_id` di query (transfer tidak
  punya kategori) — jangan kembalikan error, cukup tidak diterapkan ke sub-query transfer.

## 3. Kartu Saldo Interaktif (tap-to-filter)

### Endpoint
GET /dompet (query param tambahan `balance_group`, sama seperti §2)

### Request
```
{ balance_group: 'cash' | 'bank' | 'ewallet' }
```

### Response
Field header (`cash_total`, `bank_total`, `ewallet_total`, `total_balance`,
`active_wallets_count`) **tidak berubah** — dipakai apa adanya untuk mengisi 3 kartu & progress
bar (`saldo_tipe / total_saldo_tiga_tipe`, dihitung di frontend seperti sekarang lewat `barWidth()`,
bukan dikirim backend).

`transactions` terfilter sesuai kelompok dompet yang dipilih (lihat Database di bawah).

### Database
Klasifikasi kelompok saldo, konsisten dengan perhitungan `cash_total`/`bank_total`/`ewallet_total`
yang sudah ada di `TransactionController@index`:
- `cash`: `user_wallets.bank_id IS NULL`
- `bank`: `user_wallets.bank_id IS NOT NULL AND banks.type != 'digital'`
- `ewallet`: `banks.type = 'digital'`

Tidak ada kolom baru — filter ini query tambahan `whereHas('wallet.bank', ...)` atau join manual di
`buildFilteredQuery()`.

### Validasi
- `balance_group`: `nullable|in:cash,bank,ewallet`
- Tap card mengisi `balance_group` DAN mengosongkan `wallet_id` (mutually exclusive dengan
  filter dompet tunggal dari bottom sheet) — aturan UI, tidak perlu validasi server tambahan
  selain di atas.

## 4. Transfer sebagai Tipe Transaksi di List

Ini perubahan paling signifikan secara teknis — dampak `TransactionController@index` &
`@exportCsv`.

### Endpoint
GET /dompet
GET /dompet/export

### Request
Sama seperti §2 (`type[]` bisa termasuk `transfer`).

### Response
Item baru muncul di `transactions.data[]` untuk tiap baris `wallet_transfers` milik user, dengan
bentuk:
```
{
  id: string,                 // id dari wallet_transfers, PREFIX "wt_" supaya tidak collide
                               // dengan id transactions (keduanya ULID char(26))
  type: 'transfer',
  amount: number,
  note: string|null,
  category: null,
  category_emoji: null,
  category_icon_url: null,
  wallet: string,              // label gabungan, format: "{from_wallet.display_name} → {to_wallet.display_name}"
  wallet_id: null,
  category_id: null,
  transacted_at: string (Y-m-d),      // dari wallet_transfers.transferred_at
  transacted_at_label: string,
  transacted_at_time: string|null,
  source: 'wallet_transfer',
}
```
Satu baris transfer TIDAK diduplikasi jadi 2 (debit+kredit) — direpresentasikan sebagai 1 baris
netral (warna abu `#6B7280`, tanpa tanda +/−), sesuai acceptance criteria "abu untuk transfer".

`min_amount`/`max_amount`/`search`(cari di `note`) berlaku sama untuk baris transfer.
`wallet_id` filter (dompet tunggal) untuk transfer: cocokkan bila dompet tsb adalah
`from_wallet_id` ATAU `to_wallet_id`.
`balance_group` filter (§3) untuk transfer: cocokkan bila salah satu dari
`from_wallet`/`to_wallet` masuk kelompok saldo tsb.

### Database
Tabel: `transactions` + `wallet_transfers` (tidak ada kolom/tabel baru).

### Pendekatan teknis yang disarankan (untuk Backend AI, bukan keputusan final)
Pagination gabungan dua tabel heterogen tidak bisa pakai `Model::paginate()` biasa. Opsi yang
disarankan:
1. **Query builder UNION**: bangun 2 `select` dengan kolom yang sudah di-alias sama persis
   (pakai `DB::table('transactions')->select([...])->selectRaw("'income_expense' as _src")`
   dan `DB::table('wallet_transfers')->select([...])->selectRaw("'transfer' as _src")`),
   gabung dengan `->unionAll()`, lalu `orderByDesc('transacted_at')->paginate(30)` di atas hasil
   union (Laravel query builder mendukung ini). Mapping ke shape response dilakukan manual
   setelah `paginate()` (bukan lewat Eloquent `through()` seperti sekarang).
2. Kalau opsi 1 dirasa terlalu berisiko untuk sprint ini, **fallback**: filter default TETAP
   hanya income/expense (perilaku lama), dan `type=transfer` hanya aktif sebagai filter eksplisit
   yang query-nya independen (tidak digabung ke pagination transaksi biasa) — cukup untuk demo
   sesuai screenshot (yang tidak menampilkan baris transfer sama sekali di contoh datanya), tapi
   TIDAK memenuhi acceptance criteria "Tipe: ... Transfer (multi-select, default semua)" secara
   penuh. Keputusan mana yang dipakai perlu dikonfirmasi ke CEO/reviewer sebelum implementasi
   kalau Backend AI menilai opsi 1 out-of-scope untuk task ini.

### Validasi
- Tidak ada input baru di luar §2 (`type[]` sudah mencakup `transfer`).
- `wallet_transfers` yang diambil harus difilter `user_id = auth user` (pola sudah ada di
  `WalletController`).

## 5. Export CSV — konsistensi filter

### Endpoint
GET /dompet/export

### Request
Sama seperti §2 (ditambah `balance_group`).

### Response
CSV, kolom tidak berubah (`Tanggal, Tipe, Kategori, Dompet, Catatan, Jumlah`). Untuk baris
transfer: `Tipe` = `Transfer`, `Kategori` = `-`, `Dompet` = label gabungan dari §4.

### Database
Sama seperti §4.

### Validasi
Sama seperti §2.

## 6. Bottom Navigation — Safe Area

Ini murni perubahan CSS/frontend, **tidak ada kontrak API**, dicatat di sini supaya konsisten
dengan acceptance criteria #1 CEO:
- `.bottom-nav` (di `AppLayout.vue`) perlu `padding-bottom: env(safe-area-inset-bottom, 0px)`.
- `.main-content` padding-bottom perlu ditambah offset yang sama supaya konten scroll tidak
  ketutup nav di iPhone dengan home indicator.
- Tidak ada perubahan meta viewport diperlukan kalau `viewport-fit=cover` sudah di-set di
  `<head>` (cek `resources/views/app.blade.php` — kalau belum ada, itu prasyarat CSS `env()`
  berfungsi, tapi ini murni HTML meta tag, bukan kontrak API).

## 7. Analytics — nama event (client-side stub, tanpa endpoint)

Panggil lewat `trackEvent(name, payload)` yang sudah ada di `resources/js/lib/analytics.js`
(saat ini cuma `console.debug`, belum ada backend consumer — di luar scope task ini untuk
dipasangkan ke provider beneran):
- `wallet_tab_viewed` — payload `{ tab: 'transaksi'|'dompet'|'tagihan' }`
- `wallet_search_used` — payload `{ query: string }`
- `wallet_filter_applied` — payload `{ count: number }` (jumlah filter aktif)
- `wallet_card_clicked` — payload `{ type: 'cash'|'bank'|'ewallet' }`
- `transaction_item_opened` — payload `{ transaction_id: string, type: string }`

Catatan: event lama seperti `dompet_filter_apply`, `dompet_search`, `dompet_quick_action` yang
sudah dipanggil di `Dompet.vue` boleh tetap ada (jangan dihapus), tambahkan event baru di atas
di titik yang relevan — jangan menggantikan nama event lama karena mungkin sudah ada konsumen lain
yang bergantung padanya (walau saat ini stub, prinsip kehati-hatian tetap berlaku).

## 8. State & Edge Cases — tidak ada kontrak API baru

- Loading: `SkeletonLoader.vue` sudah ada, tinggal dipasang juga untuk header saldo + 3 kartu
  (saat ini render langsung dari props tanpa skeleton karena Inertia render server-side — kalau
  mau ada skeleton beneran perlu polling/async load, atau cukup tampilkan skeleton saat
  `router.on('start')` sampai `finish`, sesuai `isLoading` ref yang sudah ada di `Dompet.vue`).
- Empty: `EmptyState.vue` sudah dipakai untuk list kosong, konsisten dengan acceptance criteria.
- Error/offline: `ErrorState.vue` + `router.on('error')` sudah ada (`hasError` ref), tinggal
  pastikan tampil sebagai banner di bawah header sesuai posisi yang diminta CEO (saat ini posisinya
  menggantikan list, cek ulang penempatan visual saat implementasi FE).

## 9. Keputusan desain non-API (untuk Frontend AI, bukan kontrak backend)

### 9.1 Warna E-Wallet
Token yang sudah ada di `resources/css/app.css` untuk E-Wallet adalah `--ewallet: #9333EA` (ungu).
CEO minta hex `#F59E0B` (amber) untuk E-Wallet. Token `--amber: #F59E0B` **sudah ada** di file yang
sama. Keputusan: pakai `--amber`/`--amber-bg` untuk kelompok E-Wallet di kartu saldo & progress bar
supaya sama persis dengan permintaan CEO, JANGAN menambah token baru. `--ewallet`/`--ewallet-bg`
biarkan tetap ada (dipakai di tempat lain seperti ikon di `CardDompet.vue`/badge lain) kecuali
Frontend AI mengonfirmasi tidak ada pemakaian lain yang akan rusak.

### 9.2 Ilustrasi dompet 3D
Jangan bikin mekanisme asset-loading baru. Pakai komponen `AppIcon` yang sudah ada dengan
`slug="dompet_hero"` (sudah dipasang di `BalanceSummaryCard.vue` baris 12). Kalau CEO/desainer
mau assign gambar wallet-3d.png final, itu di-upload lewat admin panel icon
(`Admin/IconController`), bukan file statis di folder assets. Placeholder emoji `👛` yang sekarang
jadi fallback sudah sesuai pola desain sistem ini.

### 9.3 Tinggi header & tipografi
28–32sp untuk saldo, 12–14sp untuk badge/nama dompet, tinggi header 220–260dp — murni perubahan
CSS di `.dompet-hero-bg`/`.hero-saldo-amount` pada `BalanceSummaryCard.vue`, tidak menyentuh props
atau kontrak data.

## 10. Lanjutan — Dokumentasi & Pembukaan PR (arahan CEO lanjutan, 2026-07-20)

Arahan lanjutan: CEO AI, task "Lanjutkan dokumentasi dan buka PR redesign halaman Dompet (mobile)".
Implementasi DB/Backend/Frontend untuk §1–§9 di atas **sudah selesai dan sudah commit** di branch ini
(`8020f36` database, `dd6daae` backend, `00a7817` frontend). Task lanjutan ini murni dokumentasi +
proses PR, **bukan** perubahan kontrak API/DB baru.

### 10.0 Temuan repo penting (baca sebelum eksekusi)
- **Tidak ada `CHANGELOG.md`** di root repo saat ini. Entri "Unreleased" yang diminta CEO berarti
  **membuat file baru** dengan struktur [Keep a Changelog](https://keepachangelog.com/) (`## [Unreleased]`
  di atas), bukan menyisipkan ke file yang sudah ada.
- **Tidak ada Storybook / katalog komponen** di repo ini (`find` untuk `*storybook*` kosong). Langkah
  "perbarui katalog komponen/Storybook" di arahan CEO **tidak applicable** — lewati, jangan bikin
  setup Storybook baru hanya untuk task dokumentasi ini (di luar scope).
- **Tidak ada framework i18n** (tidak ada `vue-i18n`, tidak ada folder `lang/`, tidak ada helper
  `__()`/`trans()` dipakai di `resources/js`). Semua string UI (termasuk yang baru dari §1–§9,
  mis. label "Transfer", badge jumlah filter, preset tanggal "Hari ini/Minggu ini/Bulan ini") adalah
  string Bahasa Indonesia hardcoded langsung di komponen Vue. Jadi langkah "pastikan i18n/terjemahan
  lengkap" **tidak applicable** sebagai kontrak API/DB — cukup dicatat sebagai catatan dokumentasi
  bahwa aplikasi ini single-language (id-ID), tidak ada string yang perlu diterjemahkan ke locale lain.
- Branch target PR di arahan CEO adalah **develop**, tersedia sebagai `origin/develop`.
- **Tidak ada endpoint/tabel baru untuk task ini** — konsisten dengan §0: seluruh perubahan API/DB
  sudah dikontrakkan di §2–§5 dan sudah diimplementasikan. Task lanjutan ini nihil kontrak API baru.

### 10.1 Todo Teknis (breakdown pelaksanaan)

Catatan lingkup: sesuai batasan peranku (Project Manager AI), bagian ini **memecah** arahan CEO jadi
todo konkret per eksekutor. Aku sendiri tidak mengeksekusi todo di bawah (tidak commit ke
`CHANGELOG.md`, tidak membuka PR, tidak assign reviewer, tidak merge) — itu tugas Frontend AI/Backend AI
untuk isi teknis dan CEO AI/DevOps/human untuk aksi proses (git ops, PR, merge, deploy) yang levelnya
di atas kewenanganku.

**Dokumentasi (eksekutor: Frontend AI, karena scope-nya UI/UX halaman Dompet)**
- [ ] Buat `CHANGELOG.md` (kalau memang belum ada) dengan section `## [Unreleased]`, tambahkan entri
  di bawah heading `### Changed`:
  `- Redesign halaman Dompet (mobile): saldo & 3 kartu ringkasan lebih besar & interaktif (tap untuk
  filter), filter tipe/kategori jadi multi-select, transaksi Transfer kini muncul di list Transaksi,
  bottom nav mendukung safe-area (notch/home indicator), dark mode header, ubah warna E-Wallet ke
  amber (#F59E0B).`
- [ ] Update/tulis dokumentasi user mobile halaman Dompet (cari lokasi yang sesuai konvensi repo,
  mis. `docs/` atau folder user-guide kalau ada — kalau tidak ada folder user-guide, buat
  `docs/user-guide-dompet-mobile.md`) mencakup:
  - Screenshot before/after (ambil dari build lokal `npm run dev`/`npm run build` + emulator mobile
    width ~375–414px, sesuai breakpoint di §9.3).
  - Penjelasan perubahan UI/UX: header saldo lebih besar, 3 kartu saldo bisa di-tap untuk filter,
    filter multi-select, badge jumlah filter aktif, Transfer sebagai tipe baru di list (ikon 🔄,
    warna abu, tanpa +/−).
  - Perilaku baru: tap kartu saldo = filter `balance_group` (mutually exclusive dengan filter dompet
    tunggal di bottom sheet, lihat §3), badge filter aktif di tombol filter.
  - Empty state: sudah pakai `EmptyState.vue` — dokumentasikan pesan yang tampil saat kombinasi
    filter menghasilkan 0 hasil (lihat catatan §1 QA soal filter Transfer + kategori).
  - Error/offline state: `ErrorState.vue`, banner di bawah header (lihat §8).
  - Dampak ke pengguna: **tidak ada breaking change data** — filter lama (`type`/`category_id` string
    tunggal, termasuk yang tersimpan di `localStorage` key `monexa_dompet_filters`) tetap kompatibel
    (lihat catatan kompatibilitas di §2), tidak perlu migrasi data pengguna.
  - Catat eksplisit: **tidak ada perubahan API/DB** di luar yang sudah dikontrakkan & diimplementasikan
    di §2–§5 (semua sudah live di branch ini) — task dokumentasi ini tidak menambah endpoint/kolom baru.
  - Catat eksplisit: **tidak ada string baru yang perlu diterjemahkan** — lihat §10.0 (tidak ada
    framework i18n, aplikasi single-language id-ID).
- [ ] Lewati langkah "katalog komponen/Storybook" — tidak applicable, lihat §10.0.

**Proses PR & rilis (eksekutor: CEO AI / DevOps / human — di luar kewenangan Project Manager AI maupun
Frontend/Backend/Database AI, dicatat di sini murni sebagai breakdown todo sesuai arahan CEO)**
- [ ] Sinkronisasi branch `feature/redesign-halaman-dompet-mobile-sesuai-screenshot` dengan `develop`
  (rebase/merge), pastikan pint/phpstan/test/build/migrate tetap hijau setelah sync.
- [ ] Buka PR ke `develop`, judul "Redesign Halaman Dompet (Mobile)", body sesuai template arahan CEO
  (ringkasan, screenshot before/after, checklist status check, catatan keamanan LOW non-blocking
  — **jangan jalankan ulang security scan**, backward compatibility: tidak ada breaking change data).
- [ ] Label: `feature`, `mobile`, `redesign`, `dompet`. Reviewer: Kristiawan + tim mobile/frontend/QA.
- [ ] Sertakan panduan uji manual di deskripsi PR: buka Dompet (mobile), cek saldo & 3 kartu tap-filter,
  daftar transaksi termasuk baris Transfer, pull-to-refresh, empty state (kombinasi filter kosong),
  error state, dark mode, performa scroll list, responsivitas berbagai lebar layar mobile.
- [ ] Sorot area berisiko untuk reviewer: query union `transactions`+`wallet_transfers` di §4
  (performa & correctness pagination), perubahan filter jadi multi-select (state management di
  `FilterDrawer.vue`/`Dompet.vue`), aksesibilitas label ikon kategori & kontras warna (§1 Frontend
  todo terakhir).
- [ ] Pasca-approve: squash-merge, hapus branch feature setelah aman.
- [ ] Pindahkan entri `CHANGELOG.md` dari `[Unreleased]` ke section versi rilis saat benar-benar dirilis.
  - [ ] Pastikan deploy staging jalan & halaman Dompet (mobile) berfungsi sesuai ekspektasi di staging.

### 10.2 Kontrak API
**Tidak ada.** Task ini murni dokumentasi + proses PR. Semua kontrak API/DB untuk fitur redesign
Dompet mobile sudah lengkap di §2 (filter multi-select), §3 (kartu saldo interaktif), §4 (Transfer di
list), §5 (export CSV) — dan seluruhnya sudah diimplementasikan di commit `8020f36`/`dd6daae`/`00a7817`.
Tidak ada endpoint baru, tidak ada kolom database baru, tidak ada perubahan request/response shape
yang perlu ditambahkan untuk menyelesaikan task dokumentasi & PR ini.

## 11. Lanjutan — Finalisasi: Dokumentasi, PR, dan Review (arahan CEO lanjutan, 2026-07-20)

Arahan lanjutan: CEO AI, task "Finalisasi redesign halaman Dompet (mobile): dokumentasi, PR, dan
review". Ini **elaborasi §10** dengan detail lebih spesifik (resolusi uji, isi checklist PR, label,
reviewer) — bukan kontrak API/DB baru. Semua batasan §10.0 (tidak ada `i18n`, tidak ada Storybook,
target branch `develop`, JANGAN ulang security scan) tetap berlaku di sini.

### 11.0 Status implementasi saat ini (dicek sebelum breakdown)
- `CHANGELOG.md` **sudah dibuat & sudah ada entri** redesign Dompet (commit `2308ade`), tapi
  strukturnya belum pakai heading `## [Unreleased]` ala Keep a Changelog yang diminta §10.1 —
  saat ini heading-nya `## 2026-07-20 — Lanjutkan dokumentasi dan buka PR redesign halaman Dompet
  (mobile)`. Bukan blocker fungsional, tapi Frontend AI perlu tahu formatnya menyimpang dari acuan
  §10.0 kalau mau dirapikan.
- `README.md` **belum menyinggung halaman Dompet sama sekali** (`grep -i "dompet\|wallet"` kosong),
  walau pesan commit `2308ade` menyebut "update README & CHANGELOG" — isi commit itu ternyata cuma
  mengubah `CHANGELOG.md` (1 file). Todo README di §10.1 dan di arahan CEO terbaru **masih
  outstanding**.
- File dokumentasi pengguna (`docs/user-guide-dompet-mobile.md` atau sejenis) **belum ada** — todo
  §10.1 terkait ini juga masih outstanding.
- PR ke `develop` **belum dibuka** (`gh pr list` untuk branch ini kosong; `gh auth login` bahkan
  belum dijalankan di environment ini).

### 11.1 Todo Teknis (breakdown pelaksanaan)

Catatan lingkup: sesuai batasan peranku (Project Manager AI), bagian ini murni **memecah** arahan
jadi todo konkret. Aku tidak mengeksekusi apa pun di bawah ini (tidak edit README/CHANGELOG, tidak
buka PR, tidak pasang label, tidak minta review, tidak merge).

**Dokumentasi (eksekutor: Frontend AI)**
- [ ] Rapikan `CHANGELOG.md`: pindahkan/sesuaikan entri existing ke bawah heading `## [Unreleased]`
  (Keep a Changelog) supaya konsisten dengan §10.0/§10.1, tanpa menghapus isi entri yang sudah ada.
- [ ] Update `README.md`: tambahkan bagian singkat tentang halaman Dompet (mobile) — cara akses
  (`/dompet`, butuh login), ringkasan perubahan redesign (saldo & 3 kartu interaktif, filter
  multi-select, Transfer muncul di list, safe-area bottom nav, dark mode), dan tautkan ke dokumen
  user-guide di bawah kalau dibuat.
- [ ] Buat `docs/user-guide-dompet-mobile.md` (lihat isi wajib di §10.1 — screenshot before/after,
  penjelasan UI/UX baru, perilaku tap-to-filter, empty/error state, catatan "tidak ada breaking
  change" dan "tidak ada string yang perlu diterjemahkan").
  - Screenshot/rekaman diambil pada 3 resolusi mobile umum: **360×640, 390×844, 414×896** (sesuai
    permintaan CEO), masing-masing light & dark mode kalau memungkinkan.
- [ ] Kalau ada komponen reusable yang berubah signifikan dari task ini (`BalanceSummaryCard.vue`,
  `FilterDrawer.vue`, `TransactionItem.vue`), tambahkan catatan ringkas props/perilaku barunya di
  `docs/user-guide-dompet-mobile.md` atau komentar singkat di komponen — bukan dokumen API terpisah,
  karena ini bukan endpoint publik.

**Proses PR & rilis (eksekutor: CEO AI / DevOps / human — di luar kewenangan Project Manager AI
maupun Frontend/Backend/Database AI)**
- [ ] Buka PR `feature/redesign-halaman-dompet-mobile-sesuai-screenshot` → `develop` (branch existing,
  **jangan** buat branch baru), judul mis. "Redesign Halaman Dompet (Mobile)".
- [ ] Isi body PR: ringkasan perubahan, alasan redesign (match screenshot referensi), dampak (UI only,
  tanpa perubahan logika/basis data — sesuai §10.2, tidak ada migration baru di §1 Database), lampiran
  screenshot before/after dari 3 resolusi di atas, dan bila memungkinkan rekaman singkat interaksi
  (tap kartu saldo → filter, buka filter drawer, scroll list dengan baris Transfer).
- [ ] Checklist PR (verbatim dari arahan CEO, cantumkan di deskripsi):
  - UI sesuai screenshot pada resolusi mobile umum (360x640, 390x844, 414x896).
  - Dark mode sesuai (jika ada).
  - pint, phpstan, test, build, migrate: hijau.
  - Security scan: dilewati sesuai instruksi owner (temuan LOW non-blocking) — **jangan jalankan
    ulang scan**.
  - Tidak ada perubahan schema/DB (konsisten dengan §1 Database: tidak ada migration baru).
  - Semua string terlokalisasi, tanpa hard-coded — catat pengecualian sesuai §10.0: aplikasi ini
    single-language id-ID, tidak ada framework i18n, jadi item ini secara literal "N/A, string
    Bahasa Indonesia hardcoded adalah pola yang sudah ada di seluruh aplikasi" (bukan regresi baru
    dari task ini).
- [ ] Label: `feature`, `ui`, `mobile`, `ready-for-review`, `skip-security-scan` (pakai label
  setara yang sudah ada di repo kalau salah satu nama di atas belum terdaftar).
- [ ] Reviewer: **Kristiawan (owner)** + tim desain/QA.
- [ ] Tautkan issue/tiket terkait bila ada (belum ditemukan tiket eksplisit di repo ini — cek
  tracker eksternal kalau ada sebelum PR dibuka).
- [ ] Setelah feedback review masuk: tindak lanjuti, push perbaikan **seperlunya** (polish, tanpa
  menambah fitur baru — tetap dalam batas §0/§9 spec ini), jangan jalankan ulang security scan.
- [ ] DoD: PR approved, lalu squash-merge ke `develop` sesuai konvensi repo (lihat §10.1 poin
  "Pasca-approve"), hapus branch feature setelah merge aman, pindahkan entri `[Unreleased]` di
  `CHANGELOG.md` ke section versi rilis saat benar-benar dirilis.

### 11.2 Kontrak API
**Tidak ada.** Sama seperti §10.2 — task ini murni dokumentasi, pembukaan PR, dan proses review.
Tidak ada endpoint, kolom database, atau perubahan request/response yang perlu ditambahkan.
