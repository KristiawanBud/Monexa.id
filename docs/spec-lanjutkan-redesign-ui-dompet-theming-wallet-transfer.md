# Spec: Lanjutkan Redesign UI Dompet + Theming + Wallet Transfer

Sumber arahan: CEO AI, 2026-07-14. Branch: **tetap**
`feature/lanjutkan-ui-dompet-responsif-theming-3-mode-enum-wallet-transfer` (lanjutkan, jangan buat branch
baru). HEAD saat spec ini ditulis: commit `221dfa6`.

Stack: Laravel 13 + Inertia + Vue 3 (`<script setup>`), custom CSS berbasis CSS Variables. File inti:
`resources/js/Pages/App/Dompet.vue`, `resources/js/Components/Wallet/*.vue`,
`resources/js/Composables/useTheme.js`, `resources/css/themes/theme-{blue,green,dark}.css`,
`app/Http/Controllers/App/WalletController.php`, `app/Services/WalletService.php`,
`app/Models/{UserWallet,WalletTransfer}.php`, `app/Enums/WalletTransferStatus.php`.

## 0. PENTING — koreksi asumsi dari dokumen spec sebelumnya

Ada **dua branch berbeda** dengan nama sangat mirip di repo ini:
1. `feature/lanjutkan-ui-dompet-responsif-theming-3-mode-enum-wallet-transfer` — **branch ini, branch aktif
   task sekarang**.
2. `feature/lanjutkan-redesign-ui-dompet-responsif-fondasi-theming-3-tema-finalisasi-enum-wallet-transfer`
   — branch **lain**, riwayat kerja terpisah.

Dokumen `docs/spec-lanjutkan-ui-dompet-responsif-theming-3-mode-enum-wallet-transfer.md` (iterasi
sebelumnya) menulis audit "§1 UI Responsif SELESAI" dan "§2 Theming SELESAI" — **audit itu valid untuk
branch #2 di atas (HEAD `ce589c2`), BUKAN untuk branch #1 (branch aktif ini)**. Dicek langsung di kode HEAD
`221dfa6` (branch ini):
- **Tidak ada** kolom `user_profiles.theme`, **tidak ada** route `account.theme`, **tidak ada**
  `UpdateThemeRequest` — persist tema per-user di database **belum ada** di branch ini.
- `resources/js/Composables/useTheme.js` di branch ini **hanya** localStorage + query param + env var —
  **tidak** membaca `prefers-color-scheme`, **tidak** ada toggle UI di halaman Settings/Account.
- Yang **memang sudah benar identik** di kedua branch: 3 file token tema
  (`resources/css/themes/theme-{blue,green,dark}.css`), komponen `resources/js/Components/Wallet/*.vue`
  (CardDompet, TransactionItem, FilterDrawer, EmptyState, ErrorState, SkeletonLoader, dst — sudah ada &
  dipakai di `Dompet.vue`), dan modal Transfer Antar Dompet di `Dompet.vue` (form sudah ada, endpoint sudah
  ada).
- Yang **sudah selesai di branch ini** dan tidak perlu diulang: migration + enum
  `WalletTransferStatus` (`pending`/`completed`/`failed`) di `wallet_transfers.status`, di-cast di model, di-set
  eksplisit `Completed` di `WalletController@transfer`, plus test unit/feature terkait (commit `221dfa6`,
  `fec3839`).

Backend/Frontend AI: **jangan asumsikan** kolom `user_profiles.theme` atau route `account.theme` sudah ada
hanya karena disebut "selesai" di dokumen spec lain — dokumen itu untuk branch berbeda. Ikuti §2 di bawah ini
untuk kontrak yang benar-benar perlu dibuat di branch ini.

Todo riil yang tersisa di branch ini ada 5 area: **§1 Wallet detail & riwayat transfer**, **§2 Theming
(persist + toggle UI)**, **§3 Wallet color/icon & archive**, **§4 Wallet Transfer — hapus/reversal**, **§5
Testing & housekeeping**.

---

## 1. Wallet Detail & Riwayat Transaksi — gap: transfer tidak muncul di riwayat

### 1.1 Temuan
`TransactionController@index` (route `dompet.index`) membangun prop `transactions` **hanya** dari tabel
`transactions` (`buildFilteredQuery`). Transfer antar dompet dicatat di `wallet_transfers` +
`wallet_balance_logs` (`reference_type = 'wallet_transfer'`) — baris ini **tidak pernah** muncul di list
riwayat transaksi manapun di UI. Klik kartu dompet (`CardDompet` di `Dompet.vue`) juga cuma memfilter
`transactions` via `wallet_id`, bukan halaman/summary detail dompet tersendiri.

CEO minta eksplisit: "riwayat transaksi dengan filter" di wallet detail, dan "tampilkan transfer di riwayat
kedua dompet dengan penanda Transfer dan arah". Ini gap nyata, bukan housekeeping kecil.

### 1.2 Todo Teknis
- [ ] `TransactionController@index`: setelah query `transactions` biasa, ambil juga
  `WalletTransfer` milik user (where `user_id` = current user, filter periode/tanggal yang sama dengan
  filter aktif) dan ubah masing-masing jadi 2 "baris virtual" (satu untuk sisi `from_wallet`, satu untuk
  sisi `to_wallet`) dengan shape yang kompatibel dengan `TransactionItem.vue` (lihat kontrak §1.3). Gabung
  (`merge`) dengan collection `transactions` reguler, urutkan ulang berdasar `transacted_at`/`transferred_at`
  desc, baru di-paginate manual (`Illuminate\Pagination\LengthAwarePaginator` manual slice) — **karena
  sumbernya 2 tabel berbeda, tidak bisa pakai `paginate()` bawaan Eloquent langsung di union query**.
- [ ] Field baru per baris transfer: `type` bernilai `'transfer_out'` (di sisi `from_wallet`) atau
  `'transfer_in'` (di sisi `to_wallet`), `transfer_id`, `counterparty_wallet` (nama dompet lawan transaksi).
- [ ] `resources/js/Components/Wallet/TransactionItem.vue`: tambah cabang render untuk
  `type === 'transfer_in' | 'transfer_out'` — badge label "Transfer" (bukan kategori), ikon arah (↗ keluar /
  ↙ masuk), teks "ke {counterparty_wallet}" / "dari {counterparty_wallet}", warna nominal **netral**
  (pakai token `--text-secondary`, BUKAN warna income/expense hijau/merah — transfer bukan pemasukan/
  pengeluaran riil, supaya ringkasan income/expense bulanan yang sudah ada di `total_income`/`total_expense`
  tidak tertukar dengan mutasi transfer).
- [ ] Filter `wallet_id` di request yang sudah ada: pastikan baris transfer ikut ter-filter kalau
  `wallet_id` cocok dengan `from_wallet_id` **atau** `to_wallet_id` (dompet yang sedang dilihat, dari sisi
  manapun), bukan cuma exact match satu kolom.
- [ ] Tombol Transfer di halaman Dompet **sudah ada & jelas** (`.transfer-btn`, `openTransfer()`) — tidak
  perlu perubahan, cukup pastikan tetap terlihat di redesign tampilan.

### 1.3 Kontrak API — Riwayat Transaksi + Transfer (extend response, endpoint sama)

#### Endpoint
GET `/dompet` (`dompet.index`, tidak berubah — request query params sama seperti sebelumnya).

#### Response (tambahan pada `transactions.data[]`, field lain tidak berubah)
```
transactions.data[]: {
  id: string,
  type: 'income' | 'expense' | 'transfer_in' | 'transfer_out',   // union baru
  amount: number,
  note: string | null,
  category: string | null, category_emoji: string | null, category_icon_url: string | null,
  wallet: string | null, wallet_id: string, category_id: string | null,
  transacted_at: string('YYYY-MM-DD'), transacted_at_label: string, transacted_at_time: string | null,
  source: string,
  // BARU — hanya terisi kalau type = transfer_in/transfer_out, null selain itu
  transfer_id: string | null,
  counterparty_wallet: string | null
}
```

#### Database
Tidak ada tabel/kolom baru untuk bagian ini. Baca dari `wallet_transfers` (join `user_wallets` untuk nama
dompet lawan) yang sudah ada.

#### Validasi
Tidak ada validasi baru (query param filter sudah divalidasi di `DompetFilterRequest` yang sudah ada).

---

## 2. Theming — persist per-user + prefers-color-scheme + toggle UI

### 2.1 Kenapa ini gap nyata di branch ini (lihat §0)
Belum ada mekanisme simpan preferensi tema selain localStorage per-browser (hilang kalau ganti device/
browser/incognito), belum menghormati `prefers-color-scheme` OS, dan **belum ada toggle UI** yang bisa
diklik user — padahal acceptance criteria CEO eksplisit: "Switch tema (light/dark) bekerja dan persist antar
sesi". Pola kontrak di bawah **meniru desain yang sudah terbukti jalan** di branch #2 (`ce589c2`) — dipilih
supaya konsisten dengan pola Monexa yang sudah ada (`UserProfile` sudah jadi tempat preferensi per-user
lain), bukan didesain dari nol.

### 2.2 Todo Teknis
- [ ] Migration baru: tambah kolom `theme` (`string`, nullable, tanpa default DB — default `'blue'`
  ditentukan di application layer supaya konsisten dengan `useTheme.js`) ke tabel `user_profiles`.
- [ ] `app/Models/UserProfile.php`: tambah `'theme'` ke `$fillable`, PHPDoc `@property string|null $theme`.
- [ ] Endpoint baru `PUT /account/theme` (`account.theme`) di `AccountController` (atau controller Account
  yang relevan — cek nama controller settings/akun yang sudah ada di `app/Http/Controllers/App/`) — body
  `{ theme: string }`, validasi `Rule::in(['blue','green','dark'])`, simpan ke
  `$request->user()->profile->update(['theme' => $request->theme])`, response redirect `back()` (pola
  Inertia existing, bukan JSON).
- [ ] `app/Http/Middleware/HandleInertiaRequests.php` (atau middleware share props yang sudah ada): share
  `theme` dari `$user->profile?->theme` ke semua halaman Inertia (`shared prop`, bukan per-page prop),
  supaya tersedia di `usePage().props.theme` di frontend tanpa reload.
- [ ] `resources/js/Composables/useTheme.js`: ubah urutan resolusi prioritas jadi: `?theme=` query param →
  `localStorage.monexa_theme` → shared prop Inertia (`usePage().props.theme`) →
  `window.matchMedia('(prefers-color-scheme: dark)').matches` (kalau true → `'dark'`) →
  `import.meta.env.VITE_DEFAULT_THEME` → default `'blue'`. `setTheme(name)` tetap optimistic-update
  localStorage + DOM dulu, lalu panggil `router.put(route('account.theme'), { theme: name }, { preserveScroll: true, preserveState: true })` **hanya kalau user sudah login** (kalau belum login/guest, cukup localStorage
  saja, jangan panggil endpoint yang butuh auth).
- [ ] Tambah toggle UI di halaman Account/Settings yang sudah ada (`resources/js/Pages/App/Account.vue` —
  cek nama file settings yang benar-benar ada di branch ini): 3 opsi radio/segmented control (`role="radiogroup"`,
  `aria-label="Pilih tema"`), instan tanpa reload, memanggil `setTheme()` dari composable.
- [ ] Refactor komponen Dompet yang masih ada warna hardcoded (audit ulang `Dompet.vue` dan
  `Components/Wallet/*.vue` — kalau ditemukan literal hex/rgba baru di luar token, ganti ke `var(--token)`).

### 2.3 Kontrak API — Theme Persistence

#### Endpoint
`PUT /account/theme` (route name `account.theme`)

#### Request
```
{ theme: string }   // required, in:blue,green,dark
```

#### Response
Redirect `back()` dengan flash `success` (pola existing Inertia, bukan JSON).

#### Database
Tabel: `user_profiles`. Kolom baru: `theme` (`string`, nullable).

#### Validasi
- `theme`: `required|string|in:blue,green,dark`
- Authorization: user hanya bisa update `profile` miliknya sendiri (pakai `$request->user()->profile`,
  tidak menerima `profile_id`/`user_id` dari request body).

---

## 3. Wallet — color/icon custom & aksi arsip

### 3.1 Temuan
`UserWallet` tidak punya kolom warna/ikon sendiri — warna & inisial di `CardDompet.vue` selalu diturunkan
dari `bank` relasi (`bank_color`/`bank_initial`), jadi dompet tanpa `bank_id` (cash) semua tampil warna
default sama. CEO minta form tambah/edit dompet bisa "pilih warna/ikon yang sinkron dengan tema". Untuk aksi
"arsip": endpoint `wallets.update` sudah bisa set `is_active=false`, tapi cuma lewat form update lengkap
(butuh `display_name`+`type` ikut dikirim) — bukan aksi cepat satu klik dari kartu dompet seperti yang
diminta CEO ("aksi cepat: lihat, edit, arsip").

### 3.2 Todo Teknis
- [ ] Migration baru: tambah 2 kolom ke `user_wallets`: `icon` (`string`, nullable, simpan 1 karakter emoji
  — reuse `EmojiPicker.vue` yang sudah ada, pola sama seperti `TransactionCategory.emoji`) dan `color`
  (`string`, nullable, simpan **nama token tema**, bukan hex bebas — closed set:
  `primary`, `success`, `danger`, `warning`, `info` — supaya warna kartu dompet otomatis ikut berubah saat
  ganti tema, sesuai maksud "sinkron dengan tema").
- [ ] `app/Models/UserWallet.php`: tambah `'icon'`, `'color'` ke `$fillable`.
- [ ] `WalletController@store` & `@update`: tambah validasi `icon` (`nullable|string|max:8`), `color`
  (`nullable|string|in:primary,success,danger,warning,info`); simpan ke wallet.
- [ ] `CardDompet.vue`: kalau `wallet.icon` ada → render emoji itu menggantikan `bank_initial`; kalau
  `wallet.color` ada → `background: var(--{color})` (mis. `var(--success)`) menggantikan `bank_color` hex.
  Fallback ke logic lama (`bank_color`/`bank_initial`) kalau keduanya null (wallet lama, backward
  compatible tanpa backfill).
- [ ] Form tambah/edit dompet di `Dompet.vue`: tambah field `EmojiPicker` (reuse komponen existing, v-model
  ke `walletForm.icon`) dan 5 swatch warna (`primary`/`success`/`danger`/`warning`/`info`, tampilkan sebagai
  lingkaran kecil pakai `background: var(--{name})`, radio-button semantik supaya aksesibel via keyboard).
- [ ] Aksi cepat "Arsip" di kartu dompet: endpoint baru **ringan**, tidak butuh field lain:
  `PATCH /dompet/wallets/{wallet}/archive` (`wallets.archive`) — toggle `is_active` (true→false atau
  sebaliknya, dua arah lewat 1 endpoint, body kosong). Tombol di `CardDompet` slot `actions`: label
  "Arsipkan" (kalau `is_active`) / "Aktifkan" (kalau tidak).
- [ ] Dompet yang di-arsip (`is_active = false`) **tetap muncul** di tab "Kelola Dompet" (list manajemen,
  dengan badge "Diarsipkan") tapi **tidak muncul** di dropdown pemilihan dompet form transaksi/transfer baru
  (sudah jadi behaviour existing lewat filter `where('is_active', true)` di `TransactionController@index`
  — tidak perlu diubah, cukup pastikan UI kelola dompet punya toggle filter "tampilkan yang diarsipkan").

### 3.3 Kontrak API — Wallet Icon/Color & Archive

#### Endpoint A — extend `wallets.store` / `wallets.update` (existing, tambah field opsional)
```
POST /dompet/wallets        (wallets.store)
PUT  /dompet/wallets/{wallet}  (wallets.update)

Request (tambahan, semua optional):
{
  ...field existing tidak berubah...,
  icon?: string,     // 1 emoji char, max 8 byte (unicode multi-byte safe)
  color?: 'primary' | 'success' | 'danger' | 'warning' | 'info'
}
```

#### Endpoint B — Arsip dompet (BARU)
```
PATCH /dompet/wallets/{wallet}/archive   (wallets.archive)
Request: {}   // tidak ada body
Response: redirect back() + flash success/error (pola existing)
```

#### Database
- Tabel: `user_wallets`. Kolom baru: `icon` (`string`, nullable), `color` (`string`, nullable).

#### Validasi
- `icon`: `nullable|string|max:8`
- `color`: `nullable|string|in:primary,success,danger,warning,info`
- Endpoint arsip: `abort_if($wallet->user_id !== $request->user()->id, 403)` (pola sama seperti
  `update`/`destroy` yang sudah ada) — tidak menerima field apapun dari body, cukup toggle `is_active`
  berdasarkan state saat ini di DB.

---

## 4. Wallet Transfer — hapus/reversal transfer

### 4.1 Temuan
Tidak ada endpoint untuk menghapus/membatalkan `WalletTransfer` yang sudah dibuat. CEO eksplisit minta QA
"verifikasi saldo konsisten setelah create/delete transfer" — artinya fitur delete transfer **harus ada**
untuk bisa diverifikasi, bukan cuma pengecekan test. Ini gap fungsional, bukan cuma test coverage.

### 4.2 Keputusan desain
- Hapus transfer = **reversal penuh**: kembalikan saldo `from_wallet` (+amount) dan `to_wallet` (-amount),
  hapus baris `wallet_transfers` (hard delete — beda dari `Transaction` yang punya `TransactionEditLog`,
  `WalletTransfer` tidak punya audit log terpisah saat ini, di luar scope untuk menambah itu), dan hapus 2
  baris `wallet_balance_logs` terkait (`where reference_type = 'wallet_transfer' and reference_id = transfer.id`).
- **Wajib divalidasi**: kalau `to_wallet` sudah dipakai lagi setelah transfer masuk (saldo sekarang kurang
  dari `amount` transfer ini), reversal akan membuat saldo `to_wallet` jadi negatif — **tolak** operasi ini
  (`InsufficientBalanceException`, reuse exception yang sudah ada), jangan paksa saldo negatif.
- Authorization: hanya `user_id` pemilik transfer yang boleh menghapus (bukan cuma pemilik salah satu
  wallet — transfer dicatat dengan `user_id` sendiri di tabel `wallet_transfers`, pakai kolom itu).
- Proses reversal + delete dibungkus `DB::transaction()` (pola sama seperti `WalletController@transfer`).

### 4.3 Todo Teknis
- [ ] `app/Services/WalletService.php`: tambah method baru `reverseTransfer(WalletTransfer $transfer): void`
  — validasi saldo `to_wallet` cukup untuk di-reverse, lalu decrement `to_wallet`, increment `from_wallet`,
  hapus 2 baris `wallet_balance_logs` (`reference_type = 'wallet_transfer'`, `reference_id = $transfer->id`).
- [ ] `app/Http/Controllers/App/WalletController.php`: tambah method `destroyTransfer(Request $request,
  WalletTransfer $walletTransfer): RedirectResponse` — `abort_if` ownership, `DB::transaction` panggil
  `reverseTransfer()` lalu `$walletTransfer->delete()`, redirect `back()` dengan flash success/error
  (tangkap `InsufficientBalanceException` seperti pola di `transfer()`).
- [ ] Route baru: `Route::delete('/dompet/transfer/{walletTransfer}', [WalletController::class,
  'destroyTransfer'])->name('wallets.transfer.destroy');`
- [ ] UI: di `TransactionItem.vue` untuk baris `type === 'transfer_out'` (cukup satu sisi supaya tidak
  hapus dobel dari 2 tempat), tambah tombol/menu "Batalkan Transfer" yang memanggil endpoint ini dengan
  konfirmasi (`confirm()` sederhana, pola yang sama seperti delete transaksi yang sudah ada di `Dompet.vue`).

### 4.4 Kontrak API — Hapus Transfer

#### Endpoint
`DELETE /dompet/transfer/{walletTransfer}` (`wallets.transfer.destroy`)

#### Request
Tidak ada body.

#### Response
Redirect `back()` dengan flash `success` (transfer dibatalkan, saldo dikembalikan) atau `error`
(kalau saldo `to_wallet` tidak cukup untuk reversal — pesan: "Tidak bisa membatalkan transfer, saldo
{to_wallet} sudah terpakai.").

#### Database
Tidak ada kolom baru. Operasi: `DELETE` 1 baris `wallet_transfers`, `DELETE` 2 baris `wallet_balance_logs`
terkait, `UPDATE` `balance` di 2 baris `user_wallets`.

#### Validasi
- Authorization: `abort_if($walletTransfer->user_id !== $request->user()->id, 403)`.
- Saldo: `to_wallet.balance >= walletTransfer.amount`, kalau tidak → redirect `back()->with('error', ...)`
  tanpa menghapus apapun (bukan 4xx/5xx, konsisten dengan pola error transfer yang sudah ada).

---

## 5. Testing & Housekeeping

### 5.1 Testing (SQLite sudah terkonfigurasi — `phpunit.xml` sudah `DB_CONNECTION=sqlite`,
`DB_DATABASE=:memory:`, tidak perlu diubah)
- [ ] Unit test `WalletService::reverseTransfer()`: saldo `from_wallet` bertambah, `to_wallet` berkurang,
  baris `wallet_balance_logs` terkait terhapus, exception dilempar kalau saldo `to_wallet` tidak cukup.
- [ ] Feature test `tests/Feature/WalletTransferTest.php` (tambah, jangan hapus test lama): skenario delete
  transfer sukses (`assertDatabaseMissing('wallet_transfers', [...])`, assert saldo kedua wallet kembali ke
  nilai sebelum transfer), skenario delete ditolak karena saldo `to_wallet` sudah terpakai (assert
  `wallet_transfers` masih ada, saldo tidak berubah), skenario user lain tidak bisa hapus transfer milik
  user lain (403).
- [ ] Feature test untuk endpoint `PUT /account/theme` (§2.3): berhasil update, ditolak untuk nilai di luar
  whitelist, `user_profiles.theme` user lain tidak ikut berubah.
- [ ] Feature test untuk `PATCH /dompet/wallets/{wallet}/archive` (§3.3): toggle `is_active`, 403 untuk
  wallet milik user lain.
- [ ] Test riwayat gabungan (§1.3): transfer muncul di `transactions.data` untuk **kedua** wallet
  (`from_wallet_id` dan `to_wallet_id`), dengan `type` benar (`transfer_out`/`transfer_in`) dan
  `counterparty_wallet` sesuai.
- [ ] Jalankan `php artisan test` (full suite) setelah semua migration baru (§2, §3) ditambahkan — pastikan
  tidak ada regresi dari factory/seeder lain yang insert ke `user_wallets`/`user_profiles` tanpa field baru
  (kolom baru semua nullable, jadi seharusnya aman, tapi tetap wajib diverifikasi jalan, bukan diasumsikan).
- [ ] `./vendor/bin/pint --test` dan `./vendor/bin/phpstan analyse` wajib bersih sebelum PR final.
- [ ] UI smoke test manual (Frontend AI): Chromium/Firefox/Safari + viewport mobile (375px) — cek toggle
  tema 3 mode tidak flash/flicker, badge Transfer di riwayat tampil benar arah, form tambah dompet dengan
  icon/color baru tidak pecah layout, aksi arsip dari kartu dompet berfungsi tanpa reload penuh. Pastikan
  teks Bahasa Indonesia konsisten (semua label baru: "Arsipkan"/"Aktifkan", "Transfer", "Batalkan Transfer",
  "Pilih tema").

### 5.2 Housekeeping Dokumentasi
- [ ] **Tidak ada `CHANGELOG.md` di branch ini** (berbeda dari branch #2 di §0 yang punya file itu) — jangan
  buat `CHANGELOG.md` baru hanya untuk task ini (di luar scope, bukan pola yang konsisten di branch ini).
- [ ] `README.md`: tambahkan baris singkat yang menyebut `app/Enums/WalletTransferStatus.php` di bagian yang
  sudah menyebut `app/Enums/*` (kalau ada) — pembeda: "`WalletTransferStatus` = status siklus transfer
  (`pending`/`completed`/`failed`)". Kalau `README.md` belum punya bagian yang menyebut enum sama sekali,
  skip — jangan menambah section baru yang tidak relevan.
- [ ] Tulis singkat `docs/theming-guide.md` (baru, kalau belum ada di branch ini) — cara tambah tema ke-4,
  urutan prioritas resolusi tema (§2.2), cara override token per-halaman. Cek dulu apakah file ini sudah ada
  di branch ini sebelum menulis ulang (jangan timpa kalau sudah ada dan sudah benar).

---

## Kriteria Selesai (acceptance)

- [ ] Wallet list: kartu dompet pakai warna/ikon custom kalau di-set, aksi cepat lihat/edit/arsip berfungsi,
  empty & skeleton state (komponen sudah ada, `EmptyState.vue`/`SkeletonLoader.vue` — pastikan dipakai
  konsisten di tab Dompet).
- [ ] Riwayat transaksi tiap dompet menampilkan transfer masuk/keluar dengan badge & arah yang jelas (§1).
- [ ] Toggle tema (blue/green/dark) tersedia di UI Settings, persist ke database per-user (bukan cuma
  localStorage), menghormati `prefers-color-scheme` saat belum ada preferensi tersimpan (§2).
- [ ] Form tambah/edit dompet punya pilihan icon (emoji) & warna (token tema) opsional (§3).
- [ ] Transfer antar dompet bisa dibuat **dan dihapus** dengan saldo tetap konsisten di kedua skenario,
  validasi server-side (ownership + saldo cukup) berjalan di create maupun delete (§4).
- [ ] Semua test baru & lama hijau, `pint`/`phpstan` bersih, dijalankan & hasilnya dicantumkan eksplisit di
  deskripsi PR (§5).
- [ ] PR menyertakan catatan eksplisit soal koreksi branch di §0 (supaya reviewer tidak bingung kenapa
  audit "SELESAI" di spec lain tidak berlaku di sini).
