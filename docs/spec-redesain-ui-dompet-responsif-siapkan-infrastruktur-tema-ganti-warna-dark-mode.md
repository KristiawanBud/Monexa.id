# Spec: Redesain UI Dompet Responsif + Infrastruktur Tema (Ganti Warna & Dark Mode) — Round 3

Sumber arahan: CEO AI. Referensi visual: `storage/athena-refs/monexa-1783903490119.jpg` (hero saldo gradient
biru dengan ilustrasi dompet 3D, 3 kartu ringkasan Saldo Cash/Bank/E-Wallet dengan progress bar, tab
Transaksi/Dompet/Tagihan, filter tanggal dropdown + ringkasan Masuk/Keluar/Saldo, search bar + tombol Filter,
list transaksi harian dengan ikon kategori berwarna, bottom nav dengan FAB tengah).

Stack: Laravel 13 + Inertia + Vue 3 (`<script setup>`), Tailwind (`preflight:false`, minim) + CSS Variables
custom di `resources/css/app.css`. Tidak ada `app/Http/Resources` — pola response tetap **Inertia props**
untuk `GET /dompet` dan **redirect + flash message** untuk mutasi (kecuali 2 endpoint JSON existing:
`dompet.logs`, `dompet.balanceTrend`).

## ⚠️ WAJIB DIBACA SEBELUM IMPLEMENTASI — Status Existing

Brief CEO kali ini secara substansi **sudah dikerjakan hampir seluruhnya** pada 2 putaran sebelumnya di
branch yang sama:
- `docs/spec-redesign-ui-dompet-jadi-responsif-fondasi-theming-biru-putih-hijau-putih-dark.md` (Round 1 —
  fondasi CSS variables/tema, breakpoint layout, komponen `Wallet/*`, filter tanggal custom)
- `docs/spec-redesign-ui-dompet-responsif-sistem-tema-hijau-putih-dark-mode.md` (Round 2 — `is_primary`,
  `currency`, arsip/pulihkan dompet, transfer sebagai 2 transaksi terhubung, mini-chart tren saldo,
  toggle tema di balik feature flag, auto-detect `prefers-color-scheme`)
- `docs/theming-guide.md` dan `docs/dompet-module.md` — dokumentasi developer hasil Round 1/2.

**Jangan kerjakan ulang** apa yang sudah disebut di dua spec itu. Todo di bawah ini **HANYA** mencakup gap
riil yang saya temukan setelah membandingkan implementasi saat ini terhadap brief CEO Round 3 ini. Backend/
Frontend/Database AI cukup baca §A di bawah untuk tahu apa yang benar-benar perlu dikerjakan.

Ringkasan yang **sudah beres** (verifikasi langsung ke kode saat ini, bukan asumsi dari spec lama):
1. Fondasi tema 3 palette (`theme-blue.css`, `theme-green.css`, `theme-dark.css`) + `useTheme.js` + feature
   flag `VITE_ENABLE_THEME_TOGGLE` — **sudah ada dan berfungsi**, default tetap Biru-Putih.
2. Grid kartu dompet: mobile 1 kolom (`display:block` di `Dompet.vue` `.wallet-grid`), tablet (≥481px) 2
   kolom, desktop (≥1025px) 3 kolom — **sudah ada**, cuma perlu 1 penyesuaian kecil (lihat A.1).
3. Filter tanggal custom (`start_date`/`end_date`), filter kategori/dompet/tipe, pencarian transaksi
   (debounced), export CSV, infinite scroll, skeleton loading, pull-to-refresh mobile — **sudah ada**.
4. Sort dompet (Saldo/Terakhir Dipakai/Alfabetis) di tab Dompet — **sudah dispec di Round 2 §1.3, TAPI belum
   ada implementasi di `Dompet.vue`/`CardDompet.vue` saat ini** (tidak ditemukan kontrol sort dompet di kode).
   Ini technically utang dari Round 2, saya masukkan lagi di A.4 supaya tidak hilang dari radar.
5. Badge "Utama"/"Diarsipkan", quick action Jadikan Utama/Arsipkan/Pulihkan/Salin No. Rekening di
   `CardDompet.vue` — **sudah ada**.
6. Mini-chart tren saldo 7/30 hari (`BalanceTrendChart.vue` + `GET /dompet/balance-trend`) — **sudah ada**,
   tapi hanya render sparkline, **belum ada angka persentase perubahan** (lihat A.2 — ini yang diminta
   eksplisit di brief Round 3 "ringkasan cepat total saldo + persentase perubahan 7/30 hari").

Todo teknis di bawah ini hanya mencakup **4 gap konkret** + 1 penyesuaian layout kecil.

---

## A. Todo Teknis (breakdown per gap)

### A.1 Penyesuaian grid desktop & posisi panel filter (Frontend AI)
Brief Round 3 secara eksplisit minta breakpoint acuan **Mobile <640px / Tablet 640–1024px / Desktop >1024px**,
grid desktop **3–4 kolom adaptif**, dan **panel filter/summary di sisi kanan** pada desktop. Implementasi
saat ini sedikit berbeda:
- [ ] `.wallet-grid` di `resources/js/Pages/App/Dompet.vue`: breakpoint desktop (`≥1025px`) saat ini fix 3
  kolom — ubah jadi grid adaptif 3–4 kolom (`grid-template-columns: repeat(auto-fill, minmax(220px, 1fr))`
  atau breakpoint tambahan `≥1400px` → 4 kolom). Breakpoint tablet (2 kolom, ≥481px) sudah sesuai target
  "640–1024px" secara fungsional — **tidak perlu diubah**, cukup pastikan tidak ada lompatan janggal di
  angka 640px persis (audit visual saja, bukan refactor breakpoint existing yang sudah stabil dipakai
  banyak halaman lain lewat `AppLayout.vue`).
- [ ] `.tx-layout` (tab Transaksi, `grid-template-columns: 280px 1fr`) saat ini menaruh `FilterDrawer`/panel
  filter di **kolom kiri**. Brief Round 3 minta di **kolom kanan** pada desktop (`≥1024px` khususnya,
  boleh tetap kiri di tablet 640–1024px kalau lebih pas secara visual — keputusan akhir ada di Frontend AI,
  ini murni penataan CSS `order`/`grid-template-columns: 1fr 280px`, tidak ada perubahan struktur komponen
  atau data). Terapkan hanya di breakpoint desktop (`≥1025px`), pertahankan urutan kiri di tablet supaya
  tidak mengubah layout yang sudah diverifikasi Round 1/2 pada breakpoint itu.

### A.2 Ringkasan cepat: persentase perubahan saldo 7/30 hari (Backend + Frontend AI)
**Gap eksplisit dari brief.** `BalanceTrendChart.vue` sudah fetch `points[]` dari `GET /dompet/balance-trend`
tapi tidak menghitung/menampilkan persentase perubahan. Lihat kontrak B.1 — endpoint existing ditambah field
respons, **bukan endpoint baru**.
- [ ] Backend: `TransactionController@balanceTrend` — tambah `percent_change` (float, bisa negatif) dan
  `absolute_change` (float) ke response, dihitung dari `total_balance` titik pertama vs titik terakhir di
  `points[]` yang sudah dihitung (tidak perlu query tambahan, murni derive dari array yang sudah ada).
- [ ] Frontend: `BalanceTrendChart.vue` — render angka persentase (mis. `+3.2%` hijau / `-1.5%` merah) di
  sebelah judul "Tren Saldo", pakai `--success`/`--danger` token warna existing (bukan warna hardcode).
  Tampilkan juga di `BalanceSummaryCard.vue` (hero) sebagai baris kecil di bawah total saldo, mengikuti pola
  visual referensi (badge kecil hijau/merah dengan panah).

### A.3 Sort transaksi: tambah opsi "Terbesar" (Backend + Frontend AI)
Brief eksplisit minta "sort by terbaru/terbesar" untuk transaksi. Saat ini `TransactionController@index`
selalu `orderByDesc('transacted_at')->orderByDesc('created_at')` — **tidak ada** cara sort by nominal
(terbesar/terkecil), dan tidak ada parameter sort sama sekali di `DompetFilterRequest`. Lihat kontrak B.2.
- [ ] Backend: tambah query param `sort_by` (`'date' | 'amount'`, default `'date'`) di `DompetFilterRequest`
  dan `TransactionController::buildFilteredQuery()`.
- [ ] Frontend: tambah kontrol sort (dropdown/chip "Terbaru" / "Terbesar") di `FilterDrawer.vue` atau di
  header list transaksi, emit ke query param sesuai B.2, `trackEvent('dompet_sort_change', { sort_by })`
  (event baru, tambahkan ke katalog `trackEvent` di `docs/dompet-module.md` saat implementasi selesai).

### A.4 Sort dompet client-side — selesaikan utang Round 2 (Frontend AI)
Sudah dispec di Round 2 §1.3 (`Saldo / Terakhir Dipakai / Alfabetis`, client-side murni di atas array
`wallets` yang sudah dikirim, **tidak butuh endpoint baru**) tapi belum diimplementasikan. Tidak ada
perubahan kontrak API — cukup tambahkan kontrol sort + `computed()` sort di `Dompet.vue` tab "Dompet",
gunakan field yang sudah tersedia di `wallets[]`: `balance`, `last_transaction_at`, `display_name`.

### A.5 Toggle "Sembunyikan Saldo" — persist & konsisten dengan Dashboard (Backend + Frontend AI)
**Gap nyata**: `Dompet.vue` punya toggle sembunyikan saldo (`balanceHidden = ref(false)` baris 491,
diteruskan ke `BalanceSummaryCard`/`CardDompet`) tapi **murni state lokal** — reset tiap reload/navigasi
halaman, dan tidak sinkron dengan toggle yang sama persis di `Dashboard.vue` (yang sudah persist ke kolom
`user_profiles.hide_balance` lewat endpoint `POST /dashboard/toggle-balance`). Akibatnya user bisa
menyembunyikan saldo di Dashboard lalu masih melihat saldo terbuka di Dompet, tidak konsisten. Lihat kontrak
B.3 — **reuse endpoint existing**, bukan endpoint baru.
- [ ] Backend: `TransactionController@index` — tambah `'hide_balance' => $user->profile?->hide_balance ??
  false` ke props Inertia render (pola identik `DashboardController@index` baris ~178).
- [ ] Frontend: `Dompet.vue` — inisialisasi `balanceHidden = ref(props.hide_balance)` (bukan `ref(false)`),
  dan saat toggle diklik, panggil `axios.post(route('dashboard.toggle-balance'))` (nama route existing,
  sudah generic per-user bukan per-halaman) lalu update `balanceHidden.value` dari response `{hidden}` —
  pola sama persis dengan `toggleBalance()` di `Dashboard.vue` baris ~278, salin polanya.

---

## B. Kontrak API

### B.1 Extend `GET /dompet/balance-trend` — tambah persentase perubahan

#### Endpoint
GET `/dompet/balance-trend` (route `dompet.balanceTrend`, existing — **response bertambah field, request
tidak berubah**)

#### Request
Tidak berubah:
```
{ range: '7d' | '30d' }   // wajib, existing
```

#### Response
```json
{
  "range": "7d",
  "points": [
    { "date": "2026-07-06", "total_balance": 10500000 },
    { "date": "2026-07-13", "total_balance": 11821200 }
  ],
  "percent_change": 12.58,
  "absolute_change": 1321200
}
```
- `percent_change`: `round((last.total_balance - first.total_balance) / first.total_balance * 100, 2)`.
  Kalau `first.total_balance == 0`: kembalikan `0.0` kalau `last.total_balance` juga `0`, atau `100.0` kalau
  `last.total_balance > 0` (hindari division by zero, jangan `null`/`NaN` — frontend tidak perlu handle
  kasus khusus).
- `absolute_change`: `last.total_balance - first.total_balance`, boleh negatif.

#### Database
Tidak ada perubahan — field derive dari `points[]` yang sudah dihitung di method yang sama, tidak ada query
tambahan.

#### Validasi
Tidak berubah dari existing (`range: required|in:7d,30d`).

---

### B.2 Extend `GET /dompet` — tambah sort transaksi

#### Endpoint
GET `/dompet` (route `dompet.index`, existing — **tambah 1 query param opsional**)

#### Request (query params tambahan, sisanya tidak berubah dari Round 1 §B.1)
```
{
  ...existing (range, period, start_date, end_date, wallet_id, type, category_id, search, min_amount,
    max_amount, tab, page, include_archived),
  sort_by?: 'date' | 'amount'   // BARU, default 'date' kalau tidak dikirim atau nilai tidak valid
}
```

#### Response
Tidak ada field baru di shape `transactions.data[]` — hanya urutan baris yang berubah. Tambahan 1 field
echo balik untuk state UI (pola sama seperti `start_date`/`end_date` di Round 1):
```json
{
  "sort_by": "amount"
}
```

#### Database
Tidak ada kolom baru. Query: `ORDER BY amount DESC` (ketika `sort_by=amount`) sebagai pengganti
`ORDER BY transacted_at DESC, created_at DESC` (ketika `sort_by=date`, default/existing behavior).

#### Validasi
- `sort_by`: `nullable|in:date,amount` di `DompetFilterRequest` — nilai lain fallback ke `'date'` (pola
  whitelist-longgar sama seperti `range`/`tab` existing di file yang sama, jangan bikin mode gagal 422 baru
  untuk value yang tidak dikenal).

---

### B.3 Toggle Sembunyikan Saldo di Dompet — reuse endpoint existing, tambah prop

#### Endpoint
POST `/dashboard/toggle-balance` (route `dashboard.toggle-balance`, **existing, TIDAK ada perubahan
endpoint/behavior** — dipanggil juga dari halaman Dompet, bukan cuma Dashboard).

Tambahan murni pada `GET /dompet` (`dompet.index`, existing):

#### Request
Toggle — tidak berubah dari existing:
```
POST /dashboard/toggle-balance
{}
```

`GET /dompet` — tidak ada query param baru untuk ini (state dibaca dari profile user, bukan dari URL).

#### Response
Toggle — tidak berubah dari existing:
```json
{ "hidden": true }
```

`GET /dompet` (props Inertia) — tambah 1 field:
```json
{
  "hide_balance": false
}
```

#### Database
Tidak ada kolom baru — reuse `user_profiles.hide_balance` (existing, dipakai `DashboardController`).

#### Validasi
Tidak ada validasi baru — endpoint toggle sudah tidak menerima body sama sekali (existing).

---

## C. Kriteria Selesai Tambahan (di luar yang sudah tercakup Round 1/2 §C dan §4)

- [ ] Grid kartu dompet desktop menampilkan 3–4 kolom adaptif tergantung lebar viewport (bukan fix 3 di
  semua lebar ≥1025px), tetap 2 kolom di 640–1024px dan 1 kolom di <640px.
- [ ] Panel filter transaksi berada di sisi kanan pada layout desktop (≥1025px).
- [ ] `BalanceTrendChart`/`BalanceSummaryCard` menampilkan angka persentase perubahan saldo 7/30 hari yang
  konsisten dengan tanda (+/-) dan warna (`--success`/`--danger`).
- [ ] Transaksi bisa diurutkan "Terbaru" atau "Terbesar" dari UI, hasil urutan berubah sesuai pilihan tanpa
  reload penuh (partial Inertia reload seperti filter lain).
- [ ] Toggle sembunyikan saldo di halaman Dompet persist lintas reload/navigasi dan konsisten dengan state
  di Dashboard (toggle di satu halaman ter-reflect di halaman lain setelah navigasi).
- [ ] Sort dompet (Saldo/Terakhir Dipakai/Alfabetis) berfungsi di tab Dompet.
- [ ] Tidak ada regresi terhadap flow existing (tambah/edit/hapus transaksi, filter tanggal, export CSV,
  transfer, arsip/pulihkan dompet, ganti tema via `?theme=`) — regression check manual sebelum PR dibuka.

## D. Catatan Perubahan Dokumentasi (deliverable Frontend AI, bukan bagian spec ini)
Setelah A.2–A.5 selesai, update:
- `docs/dompet-module.md` — tambah `sort_by` ke tabel props `Dompet.vue` §"Props utama", tambah event
  `dompet_sort_change` ke katalog `trackEvent`, catat bahwa `balanceHidden` sekarang inisialisasi dari
  `props.hide_balance` (bukan lokal).
- `docs/theming-guide.md` — tidak perlu perubahan (tidak ada gap di infrastruktur tema pada Round 3 ini).
- Changelog PR: cantumkan 4 gap A.2–A.5 + 1 penyesuaian layout A.1 sebagai daftar perubahan terhadap laporan
  sebelumnya (CEO minta "changelog/laporan penambahan fitur" di kriteria penerimaan brief ini).

## E. Pertanyaan ke Owner (belum terjawab, tidak menghalangi implementasi A.1–A.5)
- Format tampilan persentase perubahan (A.2): apakah dibandingkan terhadap **awal periode 7/30 hari**
  (asumsi PM di kontrak B.1, konsisten dengan pola "point pertama vs terakhir" yang paling umum untuk
  sparkline) atau terhadap **periode sebelumnya yang setara** (mis. 7 hari ini vs 7 hari sebelumnya)? Asumsi
  PM dipakai dulu karena lebih murah secara query (tidak perlu fetch rentang tambahan) dan selaras dengan
  data yang sudah di-fetch chart — kalau owner minta perbandingan periode-ke-periode, itu perlu query
  tambahan (fetch `2×range` hari lalu split) dan sebaiknya dikonfirmasi dulu sebelum Backend AI implementasi.
- Posisi panel filter kanan vs kiri (A.1) di breakpoint tablet (640–1024px): brief tidak eksplisit sebut
  tablet, hanya desktop. Asumsi PM: tablet tetap kiri (tidak diutak-atik, sudah stabil dari Round 1), kalau
  owner mau konsisten kanan di semua breakpoint ≥640px, ini pekerjaan tambahan kecil di CSS yang sama.
