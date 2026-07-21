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

## 12. Lanjutan — Finalisasi Pembukaan PR ke `develop` (arahan CEO lanjutan, 2026-07-20)

Arahan lanjutan: CEO AI, task "Buat PR redesign halaman Dompet (mobile) dari
`feature/redesign-halaman-dompet-mobile-sesuai-screenshot` ke `develop`". Ini **elaborasi §11**
dengan detail eksekusi git & metadata PR yang lebih spesifik (judul, struktur body, label, urutan
langkah) — bukan kontrak API/DB baru. Catatan eksplisit dari CEO: *"Bug sistem yang sebelumnya
menghentikan proses sudah diperbaiki, tidak ada tindakan tambahan terkait itu"* — dicatat di sini
untuk konteks eksekutor, tidak menghasilkan todo teknis baru (tidak ada aksi remediasi yang perlu
dipecah karena arahan CEO sendiri menyatakan tidak perlu tindakan tambahan).

Semua batasan §10.0/§11.0 (tidak ada framework i18n, tidak ada Storybook, target branch `develop`,
**jangan** jalankan ulang security scan, implementasi DB/Backend/Frontend sudah selesai & sudah
commit) tetap berlaku.

### 12.1 Todo Teknis (breakdown pelaksanaan)

Catatan lingkup: sesuai batasan peranku (Project Manager AI), bagian ini murni **memecah** arahan
CEO jadi todo konkret per eksekutor. Aku tidak mengeksekusi git command, tidak push, tidak membuka
PR, tidak memasang label/reviewer, tidak merge — semua itu di luar kewenanganku.

**Persiapan branch (eksekutor: CEO AI / DevOps / human)**
- [ ] `git fetch origin`
- [ ] `git checkout feature/redesign-halaman-dompet-mobile-sesuai-screenshot`
- [ ] `git pull`
- [ ] Cek apakah `develop` sudah maju sejak branch ini dibuat (`git log
  feature/redesign-halaman-dompet-mobile-sesuai-screenshot..origin/develop`). Kalau ada commit baru
  di `develop`, sinkronkan (`git rebase origin/develop` atau `merge`, sesuai konvensi repo — repo
  ini belum punya kebijakan tertulis rebase-vs-merge, pilih yang konsisten dengan histori PR
  sebelumnya). Selesaikan konflik bila muncul; kalau konflik menyentuh file di luar scope redesign
  Dompet (§0 daftar file yang disentuh task ini), eskalasi ke Backend/Frontend AI terkait sebelum
  menyelesaikan sepihak.
- [ ] `git push origin HEAD`

**Pembukaan PR (eksekutor: CEO AI / DevOps / human)**
- [ ] Base: `develop`, Compare: `feature/redesign-halaman-dompet-mobile-sesuai-screenshot`.
- [ ] Judul: `Redesign Halaman Dompet (Mobile) — sesuai screenshot desain`.
- [ ] Body PR, mengikuti struktur berikut (isi konkret sudah tersedia dari §10–§11, tinggal disusun
  ulang ke format ini):
  - **Ringkasan** — ambil dari §11.1 poin "Isi body PR" (redesign halaman Dompet mobile sesuai
    `storage/athena-refs/monexa-1784234498463.jpg`, lihat §0).
  - **Perubahan utama** — ringkas dari §1 (Frontend/Backend todo): header saldo & 3 kartu interaktif,
    filter multi-select, Transfer muncul di list, safe-area bottom nav, dark mode header, warna
    E-Wallet ke amber.
  - **Screenshot** — before/after pada 3 resolusi §11.1 (360×640, 390×844, 414×896), dari
    `docs/user-guide-dompet-mobile.md` kalau sudah dibuat sesuai §11.1, atau diambil ulang saat PR
    dibuka kalau belum ada.
  - **Pengujian** — status hijau untuk pint, phpstan, unit/integration test, build, migrate (jalankan
    ulang di branch yang sudah disinkron ke `develop`, bukan sekadar mengklaim status lama).
  - **Keamanan** — cantumkan: hasil security scan sebelumnya LOW non-blocking, **tidak perlu diulang**
    (konsisten dengan §11.1 checklist).
  - **Dampak & kompatibilitas** — tidak ada breaking change data (§2 kompatibilitas backward untuk
    filter lama), tidak ada perubahan schema/DB (§1 Database: nihil migration baru), tidak ada
    perubahan konfigurasi prod di PR ini.
  - **QA steps** — langkah manual: buka `/dompet` di viewport mobile, cek tampilan list transaksi
    (termasuk baris Transfer §4) & empty state (`EmptyState.vue`) & error state (`ErrorState.vue`),
    uji tap 3 kartu saldo → filter (§3), buka filter drawer & uji multi-select tipe/kategori (§2),
    aksi tambah transaksi via FAB, cek dark/light mode header (§9.3), cek safe-area bottom nav (§6)
    di device dengan/tanpa notch.
  - **Dokumentasi** — sebutkan lokasi: `CHANGELOG.md` (entri `[Unreleased]`, §10.1/§11.1),
    `README.md` (bagian halaman Dompet, §11.1), `docs/user-guide-dompet-mobile.md` (§11.1), spec ini
    (`docs/spec-redesign-halaman-dompet-mobile-sesuai-screenshot.md`). Kalau salah satu item §11.1
    dokumentasi belum selesai saat PR dibuka, catat statusnya eksplisit di body PR (jangan klaim
    "lengkap" kalau belum) supaya reviewer tahu apa yang masih outstanding.

**Administrasi PR (eksekutor: CEO AI / DevOps / human)**
- [ ] Label: `feature`, `mobile`, `UI/UX`, `redesign`, `ready-for-review` — pakai nama persis ini;
  kalau salah satu belum terdaftar di repo, buat label baru dengan nama yang sama (jangan substitusi
  ke nama lain tanpa mencatat penyesuaian di body PR).
- [ ] Reviewer: tim frontend/mobile + QA terkait (§11.1 sebelumnya menyebut nama spesifik
  "Kristiawan (owner)" — pertahankan kalau masih relevan, tambahkan reviewer QA lain sesuai
  struktur tim saat ini).
- [ ] Link-kan issue/tiket/desain (screenshot/Figma) bila ada di tracker eksternal — repo lokal tidak
  ada tiket eksplisit (dicek di §11.1), jadi ini bergantung pada tracker di luar repo.
- [ ] Gunakan template PR repo (`.github/PULL_REQUEST_TEMPLATE.md` kalau ada — cek dulu keberadaannya
  sebelum menulis body manual dari nol) dan checklist: CI hijau, dokumentasi lengkap (atau status
  outstanding dicatat jelas), security scan tidak diulang, screenshot terlampir.

**Setelah PR terbuka (eksekutor: CEO AI / DevOps / human)**
- [ ] Pastikan pipeline PR tetap hijau (pint, phpstan, test, build, migrate) — re-run kalau ada
  perubahan setelah sync ke `develop`.
- [ ] Ping reviewer untuk review/approval.
- [ ] Setelah approve: merge ke `develop` sesuai kebijakan repo (squash-merge, konsisten dengan
  §10.1/§11.1), hapus branch feature setelah aman, pindahkan entri `CHANGELOG.md` dari
  `[Unreleased]` ke section versi rilis saat benar-benar dirilis, update milestone terkait bila ada.

### 12.2 Kontrak API
**Tidak ada.** Sama seperti §10.2/§11.2 — task ini murni proses git & administrasi PR. Tidak ada
endpoint, kolom database, atau perubahan request/response yang perlu ditambahkan. Seluruh kontrak
teknis fitur redesign Dompet mobile tuntas di §2–§5 dan sudah diimplementasikan pada commit
`8020f36`/`dd6daae`/`00a7817` (lihat juga commit lanjutan `3c5961b`, `ada8a21`, `91c3db4`, `3728be1`
untuk migration/frontend/docs tambahan di histori branch ini).

## 13. Lanjutan — Buat PR ke `develop` (arahan CEO lanjutan, 2026-07-21)

Arahan lanjutan: CEO AI, task "Buat PR: `feature/redesign-halaman-dompet-mobile-sesuai-screenshot` →
`develop` (Redesign Halaman Dompet - Mobile)". Ini **elaborasi §10–§12** dengan detail baru (langkah
sinkronisasi eksplisit, validasi lokal, struktur body PR, acceptance criteria, checklist, label,
catatan strategi commit). Bukan kontrak API/DB baru — semua kontrak teknis fitur ini tetap tuntas di
§2–§5 dan sudah diimplementasikan. Tidak ada endpoint/tabel baru untuk elaborasi ini.

### 13.0 Temuan repo penting untuk elaborasi ini (baca sebelum eksekusi)
- **Tidak ada script lint di `package.json`** (`scripts` cuma `dev` dan `build` — cek isi file, tidak
  ada `eslint`/`.eslintrc*` di root). Jadi item "Jalankan lint" dari arahan CEO **tidak applicable**
  untuk sisi frontend JS — cukup jalankan `npm run build` (Vite) untuk validasi build. Untuk sisi
  PHP, `vendor/bin/pint` (code style) dan `vendor/bin/phpstan` **tersedia** di repo — pakai `pint`
  sebagai padanan "lint" yang dimaksud arahan CEO.
- Test PHP tersedia via `vendor/bin/phpunit` (`phpunit.xml` ada di root). Tidak ada test runner UI/E2E
  (tidak ada Cypress/Playwright/Vitest di `package.json`) — jadi "UI test" dari arahan CEO **tidak
  applicable** sebagai automated test; yang bisa dilakukan hanya sanity check manual (lihat §13.1
  QA/manual test todo).
- **Konflik dengan keputusan sebelumnya (§10.1/§11.1)**: spec versi lama menetapkan strategi
  "squash-merge" pasca-approve. Arahan CEO kali ini eksplisit: *"Hindari squash commit yang
  menyatukan konteks penting. Gunakan pola conventional commits jika berlaku di repo."* Histori
  commit branch ini memang sudah mengikuti conventional commits (`feat(...)`, `docs(...)`, lihat
  `git log`). Ini **perubahan keputusan proses**, dicatat di sini sebagai penyesuaian: eksekutor
  (CEO AI/DevOps/human) perlu memilih strategi merge **non-squash** (mis. merge commit biasa atau
  rebase-merge) supaya histori per-commit (`feat`/`docs` terpisah untuk migration/backend/frontend)
  tetap terjaga saat merge ke `develop` — ini menggantikan instruksi squash-merge di §10.1/§11.1/§12.1
  untuk task ini.
- **Resolusi screenshot berbeda dari arahan sebelumnya**: §11.1/§12.1 minta 3 resolusi
  (360×640, 390×844, 414×896); arahan kali ini minta 2 resolusi (**360×800, 390×844**). Karena ini
  arahan CEO paling baru, pakai **360×800 dan 390×844** sebagai resolusi wajib; menambahkan
  414×896 tetap boleh (superset), tidak dilarang, tapi bukan lagi wajib.
- **Kriteria responsif baru**: lebar **320–430px** (acceptance criteria eksplisit dari arahan ini,
  belum ada di §10–§12) — perlu dicek manual (resize browser / device emulator) di rentang ini,
  bukan cuma di titik-titik resolusi screenshot.
- Konfirmasi dari CEO: token GitHub untuk automation/CLI sudah diperbaiki dengan permission
  `pull requests: read+write`. Ini konteks autentikasi untuk eksekutor yang membuka PR via
  CLI/automation (`gh` atau API) — tidak mengubah kontrak teknis apa pun di spec ini.

### 13.1 Todo Teknis (breakdown pelaksanaan)

Catatan lingkup: sesuai batasan peranku (Project Manager AI), bagian ini murni **memecah** arahan
jadi todo konkret. Aku tidak mengeksekusi git command, tidak menjalankan build/lint/test, tidak
membuka PR, tidak memasang label/reviewer, tidak merge — semua itu di luar kewenanganku.

**Sinkronisasi branch (eksekutor: CEO AI / DevOps / human)**
- [ ] `git fetch origin`
- [ ] Cek `git log feature/redesign-halaman-dompet-mobile-sesuai-screenshot..origin/develop` — kalau
  ada commit baru di `develop`, sinkronkan branch feature (rebase atau merge dari `develop`, pilih
  yang konsisten dengan histori PR sebelumnya di repo ini).
- [ ] Selesaikan konflik bila muncul; kalau konflik menyentuh file di luar scope redesign Dompet
  (lihat daftar file di §0), eskalasi ke Backend/Frontend AI terkait sebelum menyelesaikan sepihak.
- [ ] Push hasil sinkronisasi ke `origin` (branch feature, bukan `develop`).

**Validasi lokal (eksekutor: CEO AI / DevOps / human, atau Backend/Frontend AI bila diminta run test)**
- [ ] Build frontend: `npm run build` (Vite) — pastikan sukses tanpa error. Tidak ada script `lint`
  terdaftar di `package.json` (lihat §13.0), jadi lewati langkah lint JS kecuali repo menambahkan
  tooling baru (di luar scope task ini untuk menambahkannya sekarang).
  gunakan `vendor/bin/pint --test` (cek tanpa mengubah file) sebagai padanan "lint" PHP.
- [ ] Static analysis PHP: `vendor/bin/phpstan analyse` (kalau ada config `phpstan.neon`/
  `phpstan.neon.dist` — cek keberadaannya dulu sebelum run).
- [ ] Test PHP: `vendor/bin/phpunit` (atau `php artisan test`), pastikan semua lulus. Tidak ada
  automated UI/E2E test runner di repo ini (§13.0) — untuk "UI test" cukup sanity check manual di
  bawah.
- [ ] Sanity check manual fitur (buka `/dompet` di browser dengan viewport mobile emulator):
  - Daftar dompet & saldo total tampil benar (header `BalanceSummaryCard.vue`, 3 kartu Cash/Bank/
    E-Wallet).
  - Tombol tambah dompet berfungsi (alur existing di `WalletController`, tidak disentuh spec ini,
    tapi wajib dipastikan tidak regresi — lihat §0 daftar komponen yang disentuh task ini).
  - Navigasi ke detail dompet tetap berfungsi.
  - Regresi filter (multi-select §2), kartu saldo tap-to-filter (§3), baris Transfer di list (§4),
    export CSV (§5), safe-area bottom nav (§6) — semua sudah dikontrakkan & diimplementasikan
    sebelumnya, tinggal diverifikasi ulang setelah sinkronisasi ke `develop`.
  - Uji lebar viewport **320px sampai 430px** (lihat acceptance criteria §13.0) — tidak ada elemen
    terpotong/overflow horizontal.

**Pembukaan PR (eksekutor: CEO AI / DevOps / human, via `gh` CLI atau UI GitHub)**
- [ ] Base: `develop`, Compare: `feature/redesign-halaman-dompet-mobile-sesuai-screenshot`.
- [ ] Judul: `Redesign Halaman Dompet (Mobile) sesuai Screenshot`.
- [ ] Body PR minimal mencakup:
  - Ringkasan scope: layout/header saldo, kartu dompet, tombol tambah dompet, spacing/typography,
    empty state, dark mode (lihat §9 keputusan desain & §1 todo Frontend untuk detail per-item).
  - Tautan/lampiran ke referensi desain: `storage/athena-refs/monexa-1784234498463.jpg` (lihat §0).
  - Screenshot hasil implementasi pada **360×800** dan **390×844**, light & dark mode (lihat §13.0
    untuk perubahan resolusi dari arahan sebelumnya).
  - Dampak ke bagian lain: catat perubahan token warna E-Wallet ke amber `#F59E0B` (§9.1, dampak ke
    `CardDompet.vue`/badge lain yang masih pakai `--ewallet`), tidak ada perubahan schema/DB (§1
    Database: nihil migration baru untuk elaborasi ini — migration yang sudah ada di commit
    `8020f36`/`3c5961b`/`ada8a21` adalah bagian dari implementasi awal, bukan tambahan baru dari
    task dokumentasi/PR ini).
  - Instruksi uji manual singkat: rujuk ke checklist "Sanity check manual" di atas.
- [ ] Acceptance criteria (cantumkan di body PR sebagai checklist):
  - Tampilan mengikuti desain terbaru (referensi §0) dengan akurasi visual yang baik.
  - Responsif di lebar **320–430px** (§13.0 — kriteria baru, belum ada di §10–§12).
  - Tidak ada regresi pada: daftar dompet, saldo total, aksi tambah/ubah dompet, navigasi ke detail
    dompet.
- [ ] Checklist PR:
  - CI lulus (build, lint/pint, test — lihat §13.0 untuk padanan tooling yang tersedia di repo ini).
  - i18n/strings: **N/A** — repo ini tidak punya framework i18n, single-language id-ID (§10.0),
    catat eksplisit di PR supaya reviewer tidak salah paham item ini "belum dikerjakan".
  - Assets tidak terduplikasi/tidak terpakai dibersihkan (cek folder assets terkait ikon/gambar yang
    disentuh redesign ini, bila ada).
  - Changelog diperbarui: `CHANGELOG.md` entri `[Unreleased]` (§10.1/§11.1 — cek juga status
    formatnya sesuai catatan §11.0, mungkin masih perlu dirapikan).

**Administrasi PR (eksekutor: CEO AI / DevOps / human)**
- [ ] Label: `redesign`, `mobile`, `UI` (atau label setara yang sudah terdaftar di repo — cek daftar
  label yang ada sebelum membuat label baru).
- [ ] Assign reviewer yang relevan (§11.1/§12.1 sebelumnya menyebut "Kristiawan (owner)" — pertahankan
  kalau masih relevan) dan mention owner untuk review.
- [ ] Gunakan token GitHub yang sudah diperbaiki (permission `pull requests: read+write`, §13.0) bila
  membuat PR via CLI/automation.

**Komunikasi (eksekutor: CEO AI / DevOps / human)**
- [ ] Bagikan tautan PR di channel/project dan tag owner untuk konfirmasi.

**Strategi commit/merge (catatan penting, lihat §13.0)**
- [ ] **Jangan squash-merge** dengan cara yang menghilangkan konteks per-commit penting (mis.
  memisahkan `feat(...)database migration`, `feat(...)frontend`, `feat(...)backend`). Pertahankan
  pola conventional commits yang sudah dipakai di histori branch ini. Ini menggantikan instruksi
  "squash-merge pasca-approve" di §10.1/§11.1/§12.1 — gunakan merge commit biasa atau rebase-merge
  sesuai kebijakan repo yang berlaku saat PR ini di-merge.

### 13.2 Kontrak API
**Tidak ada.** Sama seperti §10.2/§11.2/§12.2 — task ini murni proses git, validasi lokal, dan
administrasi PR. Tidak ada endpoint, kolom database, atau perubahan request/response yang perlu
ditambahkan. Seluruh kontrak teknis fitur redesign Dompet mobile tuntas di §2–§5 dan sudah
diimplementasikan pada commit-commit yang tercatat di §10–§12.

## 14. Lanjutan — Lanjutkan Review PR #1: Redesign Halaman Dompet (Mobile) (arahan CEO lanjutan, 2026-07-21)

Arahan lanjutan: CEO AI, task "Lanjutkan Review PR #1: Redesign Halaman Dompet (Mobile)"
(`https://github.com/KristiawanBud/Monexa.id/pull/1`). Ini task **review/QA**, bukan kontrak API/DB
baru — semua kontrak teknis fitur redesign Dompet mobile tetap tuntas di §2–§5 dan sudah
diimplementasikan (lihat commit di §10–§13). Catatan CEO: *"Bug sistem yang sebelumnya menghentikan
proses sebelum review sudah diperbaiki; tidak ada blocker eksternal untuk melanjutkan review"* — murni
konteks, tidak menghasilkan todo remediasi baru.

### 14.0 Temuan repo penting untuk elaborasi ini (baca sebelum eksekusi)
- `gh` belum ter-autentikasi di environment tempat spec ini ditulis (`gh auth status` → belum login,
  tidak ada token di env) — aku (Project Manager AI) tidak bisa membuka/verifikasi isi PR #1 secara
  langsung dari sini. Breakdown di bawah disusun dari state kode branch ini + histori spec §0–§13;
  eksekutor yang menjalankan `gh`/browser dengan akses harus mengonfirmasi ulang detail PR (deskripsi,
  link desain terlampir, komentar reviewer) terhadap breakdown ini sebelum menandai selesai.
- **Tidak ada `.github/workflows` sama sekali** di repo ini (`.github` tidak ada). Jadi "pastikan semua
  check CI lulus" **tidak applicable** sebagai status check GitHub otomatis — padanannya adalah
  menjalankan manual command yang sudah dikontrakkan di §13.0/§13.1 (`vendor/bin/pint --test`,
  `vendor/bin/phpstan analyse`, `vendor/bin/phpunit`, `npm run build`) dan melaporkan hasilnya di
  deskripsi PR, bukan menunggu badge CI yang memang tidak ada.
- **Tidak ada unit test/snapshot existing untuk Dompet/Wallet** — `tests/Feature/` kosong, `tests/Unit/`
  cuma berisi `ExampleTest.php` bawaan Laravel. Item CEO "Perbarui/tambahkan unit test/snapshot yang
  terdampak perubahan UI" berarti **membuat test baru dari nol** untuk area ini, bukan mengupdate test
  lama (karena memang belum pernah ada).
  Tidak ada test runner e2e/UI (tidak ada Cypress/Playwright/Vitest di `package.json`, dikonfirmasi
  ulang — konsisten dengan §13.0). "Jalankan e2e/smoke test... viewport mobile" tidak bisa berupa
  automated suite di repo ini saat ini; padanannya manual QA smoke-test checklist (lihat §14.1).
- **Gap penting — CTA "set default dompet" TIDAK ADA di codebase**: dicek `app/Http/Controllers/App/
  WalletController.php` (cuma ada `store`/`update`/`destroy`/`transfer`) dan skema tabel `user_wallets`
  (`id, user_id, bank_id, display_name, icon, color, account_number, type, balance, initial_balance,
  is_active, is_saham, saham_modal, saham_nilai_sekarang, sort_order, timestamps, deleted_at`) — **tidak
  ada kolom `is_default`, tidak ada route, tidak ada UI** untuk menjadikan satu dompet sebagai default.
  Pola `is_default` memang ada di repo (`WaGateway` admin, `app/Http/Controllers/Admin/
  WaGatewayController.php`), tapi itu entitas gateway WhatsApp yang tidak relevan, bukan `UserWallet`.
  Fitur ini **tidak pernah dikontrakkan** di §0–§13 (scope PR #1 sesuai §0 adalah **redesign tampilan**
  halaman Dompet, bukan menambah kapabilitas wallet baru). Keputusan: **tidak membuat kontrak API baru
  untuk ini di sini** — lihat §14.2 untuk penjelasan & draft opsional kalau CEO mau menugaskan ini
  terpisah nanti.
- "Navigasi ke detail dompet": tidak ada route/halaman detail dompet terpisah. Tap `CardDompet` di
  `Dompet.vue` (`@click="openEditWallet"`) membuka **modal edit inline** (`showAddWallet`/
  `editingWallet` state), bukan navigasi ke URL lain. QA menguji ini sebagai perilaku modal (buka/tutup/
  data terisi benar), bukan mengharapkan halaman detail terpisah yang memang tidak ada di scope PR ini.
- Aksi tambah/edit/hapus dompet **sudah ada & berfungsi** (route `wallets.store`/`wallets.update`/
  `wallets.destroy`, lihat `routes/web.php:138-140`) — ini yang harus benar-benar diuji fungsional oleh
  QA sesuai acceptance criteria CEO, bukan dibuatkan kontrak baru.

### 14.1 Todo Teknis (breakdown pelaksanaan)

Catatan lingkup: sesuai batasan peranku (Project Manager AI), bagian ini murni **memecah** arahan jadi
todo konkret per eksekutor. Aku tidak mengeksekusi apa pun di bawah ini (tidak membuka PR, tidak
menjalankan test/build, tidak menulis kode test, tidak approve/merge, tidak memasang label/reviewer).

**Verifikasi ruang lingkup PR (eksekutor: CEO AI / DevOps / human — akses GitHub)**
- [ ] Buka `https://github.com/KristiawanBud/Monexa.id/pull/1`, konfirmasi title/description menyebut
  redesign halaman Dompet mobile sesuai referensi `storage/athena-refs/monexa-1784234498463.jpg` (§0).
- [ ] Konfirmasi compare branch = `feature/redesign-halaman-dompet-mobile-sesuai-screenshot`, base =
  `develop` (konsisten §13).
- [ ] Cek link desain/asset yang dilampirkan di deskripsi PR benar-benar match referensi §0. Kalau ada
  asset/desain baru yang belum tercatat di spec ini, **jangan ubah spec ini sepihak** — laporkan ke CEO
  sebagai temuan untuk elaborasi spec berikutnya.

**Validasi UI vs desain (eksekutor: Frontend AI / QA)**
- [ ] Cocokkan layout/spacing/tipografi/color tokens/ikon terhadap keputusan desain §9: E-Wallet amber
  `#F59E0B` (§9.1), ilustrasi `AppIcon` slug `dompet_hero` (§9.2), tipografi saldo 28–32sp & tinggi
  header 220–260dp (§9.3).
- [ ] Dark mode: verifikasi gradient header dark (§1 Frontend todo) memang pakai token
  `--primary`/`--primary-dark` dari `[data-theme='dark']`, bukan hex hardcoded.

**Uji fungsional viewport mobile (eksekutor: QA, manual — tidak ada automated e2e runner, §14.0)**
- [ ] Device/emulator: Chrome Android (DevTools device toolbar) & Safari iOS (Simulator/BrowserStack/
  device fisik kalau tersedia; kalau tidak tersedia di environment yang menjalankan review, catat
  eksplisit sebagai limitasi di laporan QA — jangan klaim "sudah diuji Safari iOS" kalau nyatanya tidak
  dijalankan).
- [ ] Viewport width < 768px, fokus khusus rentang 320–430px (§13.0) dan titik resolusi 360×800/390×844
  (opsional 414×896, §13.0).
- [ ] Daftar dompet & saldo: buka tab "Dompet" (`selectTab('dompet')`), cek `CardDompet` menampilkan
  saldo per dompet benar, header (`BalanceSummaryCard.vue`) + 3 kartu ringkasan (Cash/Bank/E-Wallet)
  konsisten dengan total.
- [ ] CTA tambah dompet: `showAddWallet = true` → submit `walletForm.post(route('wallets.store'))` →
  dompet baru muncul tanpa reload penuh, tanpa error console.
- [ ] CTA edit dompet: tap kartu → `openEditWallet` → modal terisi data lama → submit
  `walletForm.put(route('wallets.update', ...))` → perubahan ter-reflect.
- [ ] CTA hapus dompet: `deleteWallet()` → `router.delete(route('wallets.destroy', ...))` → uji 3
  skenario backend (`WalletController::destroy`): (a) saldo ≠ 0 → pesan error tampil, dompet TIDAK
  terhapus; (b) dompet punya riwayat transaksi & saldo 0 → soft-delete + `is_active=false` (hilang dari
  list, riwayat transaksi lama tetap ada); (c) dompet baru tanpa transaksi & saldo 0 → terhapus permanen.
- [ ] CTA "set default dompet": **tandai N/A** di laporan QA (lihat §14.0) — bukan kegagalan, fitur ini
  tidak ada di scope PR #1.
- [ ] "Navigasi ke detail dompet" (via modal edit, §14.0): modal terbuka/tertutup benar; tombol back
  hardware Android / gesture back iOS saat modal terbuka menutup modal dulu (tidak langsung keluar dari
  halaman Dompet secara tidak sengaja) — bagian dari acceptance "back behavior konsisten".
- [ ] Transfer antar dompet (`openTransfer` → `wallets.transfer`, syarat `wallets.length >= 2`): uji
  sebagai regresi fitur existing yang relevan dengan "aksi cepat" halaman Dompet meski tidak eksplisit
  disebut arahan CEO.

**Regresi data fetching/state (eksekutor: QA / Frontend AI)**
- [ ] Console DevTools nihil error/warning saat: load awal `/dompet`, ganti tab, ganti filter
  multi-select (§2), tap kartu saldo (§3), scroll list dengan baris Transfer (§4), submit form
  tambah/edit/hapus dompet.
- [ ] Loading: `isLoading` ref (`router.on('start')`/`finish`) tampil konsisten termasuk untuk header
  saldo & 3 kartu (§1 Frontend todo "Skeleton/empty/error state").
- [ ] Empty: `EmptyState.vue` untuk kombinasi filter 0 hasil (§1 QA todo, termasuk filter
  Transfer+kategori §2).
- [ ] Error/offline: `ErrorState.vue` + `router.on('error')` (`hasError` ref) — matikan network
  (DevTools offline), ulangi aksi CTA, pastikan banner error tampil, bukan crash/blank page.

**Performa (eksekutor: QA / Frontend AI)**
- [ ] Re-render berlebih: Vue Devtools component highlight saat ganti filter/tab — pastikan tidak ada
  komponen tak terkait ikut re-render (mis. list transaksi re-render penuh cuma karena toggle dark
  mode).
- [ ] Ukuran asset: cek hasil build `public/build/assets/Dompet-*.js`/`Dompet-*.css`, pastikan tidak ada
  gambar raster besar tak terkompresi (ilustrasi pakai `AppIcon` terkelola admin, §9.2, seharusnya tidak
  menambah bundle).
- [ ] Scroll: uji list transaksi panjang di perangkat kelas menengah (CPU throttle 4–6x, DevTools
  Performance/Lighthouse mobile).

**Aksesibilitas (eksekutor: QA / Frontend AI)**
- [ ] Screen reader: `aria-label` ikon kategori (§1 Frontend todo terakhir), label tombol
  filter/tambah dompet/transfer, `role="tab"`/`aria-selected` di `.tab-pill` — verifikasi VoiceOver iOS
  & TalkBack Android membacanya benar.
- [ ] Urutan fokus logis: header → tab segmented → search/filter → list → bottom nav.
- [ ] Target sentuh ≥44dp: audit ulang `.chip` & tombol CTA (§1 Frontend todo).

**Internasionalisasi/lokalisasi (eksekutor: QA)**
- [ ] Format angka/mata uang konsisten `Rp` + separator ribuan titik di seluruh halaman termasuk baris
  Transfer baru (§4) — pola sama seperti `number_format($x, 0, ',', '.')` di `WalletController`.
- [ ] String tidak terpotong di lebar 320px (§13.0): label dompet panjang, badge jumlah filter, teks
  tombol. Tidak ada framework i18n di repo ini (single-language id-ID, §10.0/§13.0) — fokus murni ke
  CSS overflow/truncation, bukan terjemahan.

**Pengujian (eksekutor: Backend AI untuk PHP, Frontend AI/QA untuk sisi FE)**
- [ ] Tidak ada test existing (§14.0) — buat Feature test PHP baru di `tests/Feature/` untuk
  `WalletController` (`store`/`update`/`destroy` happy path + 2 skenario error saldo≠0 & ada transaksi)
  dan `TransactionController@index` (filter multi-select §2, `balance_group` §3, union transfer §4).
- [ ] Tidak ada test runner FE (§14.0) — menambah Vitest/Cypress adalah keputusan tooling baru di luar
  scope spec ini; kalau tidak ditambah, cukup dokumentasikan hasil manual QA smoke-test checklist di
  atas sebagai bukti pengujian.
- [ ] "Smoke test e2e viewport mobile" dipenuhi lewat checklist manual "Uji fungsional viewport mobile"
  di atas (tidak applicable sebagai automated suite, §14.0); hasilnya dilampirkan di deskripsi PR.

**Dokumentasi & CI (eksekutor: CEO AI/DevOps/human; konten revisi docs oleh Frontend AI bila perlu)**
- [ ] Tidak ada `.github/workflows` (§14.0) — "semua check CI lulus" dipenuhi lewat command manual
  `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `vendor/bin/phpunit`, `npm run build` (§13.1),
  hasilnya dicantumkan di deskripsi PR.
- [ ] Perbaiki lint/typing bila ada yang gagal dari command di atas — didelegasikan ke Backend AI (PHP)
  atau Frontend AI (Vite build) sesuai jenis kegagalan.
- [ ] Tambahkan/rapikan screenshot before/after di deskripsi PR: resolusi 360×800 & 390×844 (§13.1),
  sertakan versi dark mode.
- [ ] Update `CHANGELOG.md` bila ada perubahan baru hasil temuan reviewer — entri baru di bawah
  `## [Unreleased]` (format §10.0), jangan hapus entri lama.
- [ ] Label PR: gunakan set yang sudah dikontrakkan §13.1 (`redesign`, `mobile`, `UI` atau label setara
  yang sudah terdaftar di repo) — cek daftar label dulu sebelum membuat baru.
- [ ] Mention reviewer: Kristiawan (owner) + tim desain/QA (konsisten §11.1/§13.1).

**Kriteria penerimaan (eksekutor: CEO AI/DevOps/human)**
- [ ] Minimal 2 approval reviewer — pantau via `gh pr view 1 --json reviews` (perlu `gh auth login` di
  environment yang menjalankan, §14.0) atau UI GitHub.
- [ ] Semua komentar review ditangani (reply/resolve) atau diperbaiki via commit baru — tindak lanjuti
  sesuai temuan reviewer, **jangan menambah fitur baru** di luar §0/§9/§14 (termasuk "set default
  dompet" — perlu arahan CEO eksplisit terpisah kalau memang mau dikerjakan, bukan otomatis ditambah di
  sini, §14.0).
- [ ] QA smoke-test checklist di atas lulus tanpa blocker (blocker = bug yang mencegah alur inti: lihat/
  tambah/edit/hapus dompet, lihat transaksi). Item "set default dompet" dikecualikan dari definisi
  blocker karena N/A untuk PR ini.
- [ ] PR siap merge ke `develop` dengan strategi non-squash (pertahankan histori conventional commits,
  §13.0/§13.1).

### 14.2 Kontrak API
**Tidak ada endpoint/tabel/kolom baru.** Seluruh kontrak teknis fitur redesign Dompet mobile tuntas di
§2–§5 dan sudah diimplementasikan. Task review PR #1 ini murni verifikasi & QA — tidak menghasilkan
kontrak API baru.

Catatan eksplisit soal CTA "set default dompet" yang disebut arahan CEO: **tidak dikontrakkan di sini**
karena fitur ini tidak ada di scope PR #1 (§0) maupun di codebase saat ini (§14.0) — ini bukan gap
implementasi yang lupa dikerjakan, melainkan di luar cakupan redesign UI yang sedang direview. Kalau CEO
memang menghendaki kemampuan "dompet default" sebagai fitur baru, berikut draft awal kontrak (**bukan
keputusan final**, perlu arahan CEO terpisah untuk dieksekusi):
- Endpoint usulan: `PATCH /dompet/wallets/{wallet}/default`
- Request: `{}` (wallet id dari route param, user dari auth)
- Response: redirect back dengan flash `success`, konsisten pola `WalletController` yang ada.
- Database: kolom baru `user_wallets.is_default` (boolean, default false), unique constraint logis
  "hanya 1 dompet aktif ber-`is_default=true` per user" (di-enforce di service layer, pola sama seperti
  `WaGatewayController` men-set `is_default=false` ke record lain sebelum set yang baru).
- Validasi: wallet harus milik user yang login (`abort_if($wallet->user_id !== $request->user()->id,
  403)`, pola sudah ada di `update`/`destroy`).
Draft ini **tidak untuk dieksekusi Backend/Database AI** sampai ada arahan CEO eksplisit yang
menugaskannya sebagai task tersendiri.

### 14.3 Revisi #1 (catatan reviewer, 2026-07-21)

Catatan reviewer untuk task "Lanjutkan Review PR #1: Redesign Halaman Dompet (Mobile)":

> Git diff yang dikirimkan kosong atau terduplikasi dengan file konteks (system prompt), sehingga
> tidak ada perubahan kode aktual (seperti file Vue untuk halaman Dompet, Controller, atau spec)
> yang bisa direview. Silakan kirimkan kembali request dengan git diff yang benar
> (`git diff main...HEAD`) agar pengecekan keamanan multi-tenant dan kesesuaian spec redesign dapat
> dilakukan.

Sesuai batasan peranku (Project Manager AI), aku tidak mengeksekusi apa pun dari breakdown ini
(tidak menjalankan `git diff`, tidak membuka/mengomentari PR, tidak menjalankan review keamanan) —
murni memecah catatan reviewer jadi todo konkret untuk eksekutor yang berwenang.

**Temuan verifikasi cepat (dicek dari environment penulisan spec ini, bukan dari sesi reviewer)**
- `git diff main...HEAD --stat` di branch ini **tidak kosong** — ada 17 file berubah (1764
  insertion(+)/188 deletion(-)), termasuk file kode aktual yang disebut reviewer:
  `app/Http/Controllers/App/TransactionController.php`,
  `app/Http/Requests/App/DompetFilterRequest.php`, `app/Services/TransactionFeedService.php`,
  `resources/js/Pages/App/Dompet.vue`, `resources/js/Components/Wallet/BalanceSummaryCard.vue`,
  `resources/js/Components/Wallet/FilterDrawer.vue`,
  `resources/js/Components/Wallet/TransactionItem.vue`, `resources/js/Components/Wallet/
  CategoryChipFilter.vue`, `resources/js/Layouts/AppLayout.vue`, dua file migration index, serta
  dokumentasi (`CHANGELOG.md`, `README.md`, spec ini).
- Jadi masalah yang dilaporkan reviewer kemungkinan besar **bukan** karena branch ini benar-benar
  tanpa perubahan, melainkan pada **cara diff itu dikemas/dikirim ke reviewer** (mis. request
  review menempelkan isi system prompt/file konteks alih-alih output `git diff` yang sebenarnya,
  atau perintah diff yang dijalankan salah — misalnya `git diff` tanpa argumen di working tree
  bersih yang memang menghasilkan output kosong, alih-alih membandingkan terhadap `main`).
- Ini murni temuan proses pengiriman request review, **bukan** temuan kontrak API/DB baru — tidak
  ada perubahan di §2–§5 akibat catatan ini.

**Todo Teknis (breakdown pelaksanaan, eksekutor: CEO AI / DevOps / human yang menjalankan proses
review PR #1)**
- [ ] Jalankan ulang `git diff main...HEAD` (tiga titik, bukan `git diff main` atau `git diff` tanpa
  target) di branch `feature/redesign-halaman-dompet-mobile-sesuai-screenshot` yang sudah
  di-`fetch`/sinkron, pastikan outputnya **bukan kosong** sebelum dikirim ke reviewer.
- [ ] Pastikan payload yang dikirim ke reviewer adalah **output mentah command di atas**, bukan
  ringkasan/rekonstruksi dari system prompt, spec, atau file konteks lain — reviewer perlu melihat
  diff kode asli (Vue, PHP Controller/Request/Service, migration) untuk bisa memverifikasi.
- [ ] Sertakan juga `git diff main...HEAD --stat` sebagai ringkasan cepat di awal request, supaya
  reviewer bisa langsung lihat cakupan file yang berubah sebelum membaca diff penuh.
- [ ] Setelah diff terkirim benar, minta reviewer lanjutkan dua pengecekan yang disebut catatan
  revisi ini secara eksplisit:
  - **Keamanan multi-tenant**: verifikasi query yang disentuh task ini (`TransactionController`,
    `TransactionFeedService`, `DompetFilterRequest` — lihat §2–§5) tetap scoping ke `user_id`/
    kepemilikan wallet milik user yang login, tidak ada kebocoran data lintas tenant akibat
    perubahan filter multi-select atau union `wallet_transfers` (§4).
  - **Kesesuaian spec redesign**: cocokkan isi diff terhadap kontrak §2–§9 (perubahan UI/UX,
    filter, kartu saldo, warna E-Wallet amber, safe-area bottom nav) — pastikan implementasi di
    diff benar-benar merefleksikan spec, bukan cuma dokumentasi yang diperbarui.
- [ ] Kalau setelah diff benar-benar dikirim reviewer masih menemukan bagian yang kurang sesuai
  spec, catat sebagai **Revisi #2** dst di bagian bawah spec ini (bukan menimpa/menghapus §14.3 ini)
  sesuai pola revisi di file ini.

**Batasan**
- Tidak ada perubahan kontrak API/DB dari catatan revisi ini — lihat §14.2, tetap berlaku
  sepenuhnya.
- **Jangan** membuat migration baru, **jangan** membuat branch baru — perbaikan cukup di branch
  `feature/redesign-halaman-dompet-mobile-sesuai-screenshot` yang sudah ada, cukup dengan mengirim
  ulang request review dengan diff yang benar.

## 15. Lanjutan — Jalankan Ulang Pipeline Review PR #1 (arahan CEO lanjutan, 2026-07-21)

Arahan lanjutan: CEO AI, task "Lanjutkan review PR #1: Redesign Halaman Dompet (Mobile)"
(`https://github.com/KristiawanBud/Monexa.id/pull/1`). Catatan CEO: *"Bug sistem yang menghentikan
proses review sebelumnya sudah diperbaiki; jalankan ulang pipeline dari branch PR yang sama."* Ini
**elaborasi §14** (khususnya menindaklanjuti §14.3 — catatan reviewer sebelumnya soal diff kosong)
dengan cakupan pipeline yang lebih rinci: lint, static analysis, test, build, code review kualitas
kode, verifikasi UI/UX (termasuk format mata uang IDR & pembulatan secara eksplisit), visual
regression, dan keputusan Approve/Request Changes + ringkasan di komentar PR. **Bukan kontrak
API/DB baru** — §2–§5 tetap tuntas dan sudah diimplementasikan.

### 15.0 Temuan repo penting untuk elaborasi ini (dicek ulang saat menulis spec ini)

- **Konfirmasi ulang temuan §14.3**: `git diff main...HEAD --stat` di branch ini **tetap tidak
  kosong** — 17 file berubah (1837 insertion(+)/188 deletion(-)), termasuk seluruh file kode yang
  relevan (`TransactionController.php`, `DompetFilterRequest.php`, `TransactionFeedService.php`,
  `Dompet.vue`, `BalanceSummaryCard.vue`, `FilterDrawer.vue`, `TransactionItem.vue`,
  `CategoryChipFilter.vue`, `AppLayout.vue`, 2 migration index). "Bug sistem" yang disebut CEO
  (diff kosong/tidak terkirim benar ke reviewer) berada di **proses pengiriman/tooling review**,
  bukan di kode branch ini — tidak ada tindakan kode tambahan yang perlu dipecah untuk itu, sesuai
  §14.3.
- **Belum ada perubahan tooling sejak §13.0/§14.0**: tetap tidak ada script `lint` di
  `package.json` (cuma `dev`/`build`), tetap tidak ada `.github/workflows`, tetap tidak ada
  test runner e2e/visual-regression (`Cypress`/`Playwright`/`Percy`/`BackstopJS`/`Chromatic` — semua
  dicek ulang, nihil di `package.json` maupun root repo). `vendor/bin/pint`, `vendor/bin/phpstan`
  (kalau ada config), `vendor/bin/phpunit` tetap jadi padanan lint/static-analysis/test PHP.
  **"Visual regression"** yang diminta arahan CEO poin 5 **tidak applicable sebagai automated
  suite** — padanannya adalah perbandingan screenshot manual before/after pada resolusi yang sudah
  dikontrakkan (§13.0: 360×800, 390×844, opsional 414×896) terhadap referensi desain §0, dicatat
  sebagai bagian dari "Validasi UI vs desain" (§14.1), bukan tugas baru.
- `gh auth status` di environment penulisan spec ini **masih belum login** — Project Manager AI
  tetap tidak bisa membuka/mengomentari PR #1 secara langsung (konsisten §14.0). Eksekutor yang
  menjalankan langkah 1, 7, 8 dari arahan CEO (checkout, approve/request changes, komentar ringkasan
  di PR) butuh akses `gh`/browser dengan token yang sudah diperbaiki (dicatat CEO di §13.0).
- **Format mata uang IDR & pembulatan** (poin 4 arahan CEO, belum eksplisit di §14.1 sebelumnya):
  dicek langsung di kode branch ini —
  - Backend: `number_format($amount, 0, ',', '.')` dipakai di `WalletController.php` (pesan flash
    saldo tidak cukup, transfer, hapus dompet) — 0 desimal, pemisah ribuan titik, sesuai konvensi
    Rupiah tanpa sen.
  - Frontend: `Number(x).toLocaleString('id-ID')` dipakai di `Dompet.vue` (baris ~663, ~679, ~777,
    ~806, ~834) untuk tampilan input nominal — juga 0 desimal default untuk `id-ID` locale tanpa
    opsi `minimumFractionDigits`, konsisten dengan backend.
  - Belum dicek: apakah kolom `transactions.amount`/`user_wallets.balance` bertipe desimal (mis.
    `decimal(15,2)`) sehingga berpotensi menyimpan sen meski UI selalu menampilkan bulat — ini murni
    item verifikasi QA (§15.1), **bukan** temuan yang memerlukan migration baru (di luar scope
    redesign UI PR #1, §0).
- Ukuran layar "kecil–menengah" yang diminta arahan CEO konsisten dengan rentang **320–430px** yang
  sudah dikontrakkan di §13.0 — tidak ada rentang baru yang perlu ditambahkan.

### 15.1 Todo Teknis (breakdown pelaksanaan)

Catatan lingkup: sesuai batasan peranku (Project Manager AI), bagian ini murni **memecah** arahan
CEO jadi todo konkret per eksekutor, mengikuti urutan 8 langkah dari arahan CEO. Aku tidak
mengeksekusi apa pun di bawah ini (tidak checkout/pull, tidak menjalankan lint/test/build, tidak
membuka/meng-approve/comment PR).

**Langkah 1 — Checkout & sinkronisasi (eksekutor: CEO AI / DevOps / human)**
- [ ] `git fetch origin`, `git checkout feature/redesign-halaman-dompet-mobile-sesuai-screenshot`,
  `git pull`.
- [ ] Sinkronkan dengan `develop` (target branch, §13.1) bila ada commit baru di `develop` sejak
  branch ini dibuat — rebase/merge sesuai konvensi repo, selesaikan konflik (eskalasi ke Backend/
  Frontend AI kalau konflik menyentuh file di luar §0).
- [ ] Bersihkan cache/artefak build sebelumnya: `rm -rf node_modules/.vite public/build`,
  `php artisan config:clear && php artisan cache:clear && php artisan view:clear`, `composer dump-autoload`
  — supaya hasil lint/test/build berikutnya tidak dipengaruhi cache basi dari run sebelumnya (relevan
  dengan "bug sistem" yang disebut CEO, meski akar masalahnya di tooling review, bukan cache lokal —
  tetap dijalankan sebagai kebersihan standar sebelum re-run pipeline).

**Langkah 2 — Lint, static analysis, test, build (eksekutor: CEO AI / DevOps / human, atau Backend/
Frontend AI bila diminta run)**
- [ ] `vendor/bin/pint --test` (padanan lint PHP, §13.0 — tidak ada lint JS terdaftar).
- [ ] `vendor/bin/phpstan analyse` (kalau ada `phpstan.neon`/`phpstan.neon.dist`).
- [ ] `vendor/bin/phpunit` (atau `php artisan test`) — termasuk test baru yang seharusnya sudah dibuat
  sesuai §14.1 "Pengujian" (`WalletController`, `TransactionController` filter/union). Kalau test itu
  belum ada, catat sebagai gap outstanding di laporan (§15.1 "Dokumentasi temuan"), jangan blokir
  seluruh pipeline karena hal ini kalau bukan regresi baru dari task ini.
- [ ] `npm run build` (Vite) — pastikan sukses tanpa error/warning baru.
- [ ] Tidak ada "instrumentation test" (Android/iOS native) di repo ini — ini aplikasi web Laravel +
  Inertia, bukan native mobile app; item ini dari arahan CEO **tidak applicable**, dipenuhi oleh
  kombinasi `phpunit` (server) + `npm run build` (client) + smoke-test manual viewport mobile (§14.1).

**Langkah 3 — Review kode (eksekutor: Backend AI untuk PHP, Frontend AI untuk Vue, atau reviewer
manusia/CEO AI)**
- [ ] Kepatuhan arsitektur: `TransactionController`/`TransactionFeedService`/`DompetFilterRequest`
  ikuti pola MVC + FormRequest + Service yang sudah ada di Monexa (bandingkan dengan controller lain
  seperti `WalletController`), tidak ada logic bisnis bocor ke Controller yang seharusnya di Service.
- [ ] Modularisasi komponen UI: `BalanceSummaryCard.vue`, `FilterDrawer.vue`, `TransactionItem.vue`,
  `CategoryChipFilter.vue` tetap presentational/reusable (props in, emit out), tidak ada state global
  yang bocor ke komponen anak di luar pola existing.
  Naming/konvensi: penamaan prop/emit/variabel konsisten camelCase (JS)/snake_case (kolom DB) sesuai
  konvensi Monexa yang sudah ada, tidak ada penamaan baru yang menyimpang.
- [ ] Aksesibilitas & i18n/l10n: rujuk checklist yang sudah dikontrakkan §14.1 ("Aksesibilitas" &
  "Internasionalisasi/lokalisasi") — tidak ada item baru, cukup dieksekusi ulang sebagai bagian dari
  re-run pipeline ini.
- [ ] Implikasi performa: rujuk checklist §14.1 "Performa" (re-render berlebih, ukuran asset build,
  scroll list panjang) — dieksekusi ulang, bukan checklist baru.

**Langkah 4 — Verifikasi UI/UX halaman Dompet (eksekutor: Frontend AI / QA)**
- [ ] Layar kecil–menengah (320–430px, §13.0/§15.0), light & dark mode: rujuk §14.1 "Validasi UI vs
  desain" + "Uji fungsional viewport mobile" — dieksekusi ulang di branch yang sudah disinkron.
- [ ] Tata letak & navigasi: header, tab segmented, bottom nav (safe-area §6), modal edit dompet
  (bukan halaman detail terpisah, §14.0) — sesuai checklist §14.1.
- [ ] State kosong/loading/error: `EmptyState.vue`/`SkeletonLoader.vue`/`ErrorState.vue` — rujuk
  §14.1 "Regresi data fetching/state", termasuk kombinasi filter Transfer+kategori yang menghasilkan
  0 hasil (§2).
- [ ] Daftar transaksi & saldo: header saldo + 3 kartu ringkasan + list transaksi termasuk baris
  Transfer (§4) — rujuk §14.1 "Uji fungsional viewport mobile" poin daftar dompet & saldo.
- [ ] **Format mata uang (IDR) & pembulatan** (baru, eksplisit dari arahan CEO poin 4, lihat temuan
  §15.0): verifikasi tampilan `Rp` + separator ribuan titik + 0 desimal konsisten di seluruh halaman
  Dompet — header saldo, 3 kartu ringkasan, tiap item list transaksi (termasuk baris Transfer §4),
  input nominal di form tambah/edit/transfer. Cek eksplisit: kalau ada nilai desimal (sen) di database
  akibat kalkulasi backend (mis. rata-rata/persentase progress bar), pastikan **dibulatkan ke rupiah
  penuh sebelum ditampilkan** (bukan dipotong/`truncate` yang bisa selisih 1 rupiah dari total) —
  laporkan sebagai temuan kalau ditemukan pembulatan yang salah (`floor`/`intval` alih-alih `round`).

**Langkah 5 — Visual regression & responsivitas (eksekutor: Frontend AI / QA)**
- [ ] Tidak ada tooling visual regression otomatis di repo ini (§15.0) — padanannya perbandingan
  screenshot manual before/after pada 360×800, 390×844 (opsional 414×896), light & dark mode,
  terhadap referensi `storage/athena-refs/monexa-1784234498463.jpg` (§0). Catat di laporan sebagai
  "visual regression: manual (tidak ada automated suite)", jangan klaim otomatis.
- [ ] Responsivitas: resize/emulate lebar 320px sampai 430px (§13.0), pastikan tidak ada elemen
  terpotong/overflow horizontal — sama seperti checklist §13.1 "Sanity check manual".

**Langkah 6 — Dokumentasi temuan (eksekutor: eksekutor yang menjalankan langkah 2–5, dikompilasi oleh
CEO AI/DevOps/human)**
- [ ] Kompilasi hasil langkah 2–5 jadi daftar poin (bukan prosa panjang): status lint/static
  analysis/test/build (pass/fail per command), temuan review kode (§ langkah 3), temuan UI/UX
  termasuk format mata uang & pembulatan (§ langkah 4), hasil visual regression manual + catatan
  keterbatasan tooling (§ langkah 5).
- [ ] Sertakan screenshot/rekaman: minimal before/after 360×800 & 390×844 (light & dark), rekaman
  singkat interaksi kunci (tap kartu saldo → filter §3, filter drawer multi-select §2, scroll list
  dengan baris Transfer §4) — konsisten §11.1/§13.1.
- [ ] Rekomendasi perbaikan: kelompokkan per severity (blocker vs non-blocker), rujuk definisi
  blocker yang sudah dikontrakkan §14.1 "Kriteria penerimaan" (bug yang mencegah alur inti: lihat/
  tambah/edit/hapus dompet, lihat transaksi). Item "set default dompet" tetap N/A (§14.0/§14.2).

**Langkah 7 — Keputusan Approve / Request Changes (eksekutor: CEO AI / DevOps / human — reviewer
berwenang, di luar kewenangan Project Manager AI)**
- [ ] Approve **hanya** kalau: langkah 2 (lint/static analysis/test/build) semua hijau ATAU
  kegagalan yang ada sudah didokumentasikan sebagai non-blocker & sudah ada rencana tindak lanjut;
  langkah 3–5 tidak menemukan blocker sesuai definisi §14.1; dan keamanan multi-tenant sudah
  diverifikasi ulang sesuai §14.3 (scoping `user_id`/kepemilikan wallet di
  `TransactionController`/`TransactionFeedService`/`DompetFilterRequest`).
- [ ] Kalau ditemukan blocker (mis. lint/test/build merah tanpa mitigasi, bug alur inti, kebocoran
  data lintas tenant, atau penyimpangan signifikan dari referensi desain §0): **Request Changes**,
  cantumkan detail per temuan (file, baris kalau relevan, langkah reproduksi, rujukan bagian spec
  yang dilanggar).
- [ ] Kalau Request Changes: setelah perbaikan masuk (commit baru di branch yang sama, **jangan**
  squash yang menghilangkan histori, §13.0), ulangi langkah 2–6 sebelum keputusan approve berikutnya
  — dicatat sebagai "Revisi #2" dst di spec ini sesuai pola §14.3, bukan menimpa bagian ini.

**Langkah 8 — Ringkasan di komentar PR (eksekutor: CEO AI / DevOps / human, via `gh pr comment 1` atau
UI GitHub)**
- [ ] Tulis komentar ringkasan di PR #1 mencakup: status pipeline (langkah 2, per command
  pass/fail), ringkasan temuan review kode & UI/UX (langkah 3–4), catatan visual
  regression manual + keterbatasan tooling (langkah 5), keputusan akhir (Approve/Request Changes)
  dengan alasan singkat, dan tautan ke bagian spec ini (§15) untuk detail lengkap.
- [ ] Kalau Request Changes, komentar juga mencantumkan daftar item yang harus diperbaiki sebelum
  re-review (rujuk langkah 6 "Rekomendasi perbaikan").

### 15.2 Kontrak API
**Tidak ada endpoint/tabel/kolom baru.** Sama seperti §14.2 — task ini murni re-run pipeline
review/QA untuk PR #1. Seluruh kontrak teknis fitur redesign Dompet mobile tuntas di §2–§5 dan
sudah diimplementasikan. Catatan "set default dompet" di §14.2 tetap berlaku (di luar scope, draft
opsional tersedia di sana, tidak dieksekusi tanpa arahan CEO terpisah).
