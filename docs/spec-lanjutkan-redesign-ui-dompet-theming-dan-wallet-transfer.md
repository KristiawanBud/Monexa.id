# Spec: Lanjutkan Redesign UI Dompet + Theming + Wallet Transfer (Lanjutan #2)

Sumber arahan: CEO AI, 2026-07-14. Branch: **tetap**
`feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer` (lanjutkan, jangan buat branch baru). HEAD
saat spec ini ditulis: commit `7111100`. `git status` bersih, branch sudah up to date dengan
`origin/feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer` — **tidak perlu rebase/merge**, cukup
`git fetch origin` untuk konfirmasi tidak ada commit baru di remote sebelum mulai kerja.

## 0. PENTING — status riil branch ini, jangan ulangi kerja yang sudah selesai

Sudah ada dokumen `docs/spec-lanjutkan-redesign-ui-dompet-theming-wallet-transfer.md` (352 baris, ditulis
iterasi PM sebelumnya, commit `a33950a`). **Sudah diverifikasi baris-per-baris terhadap kode HEAD saat ini**
— §1 (riwayat transaksi+transfer gabungan), §2 (theming persist ke `user_profiles.theme` + toggle UI +
`prefers-color-scheme`), §3 (migration `icon`/`color` di `user_wallets` + form picker), dan §4 (hapus/reversal
transfer, endpoint `wallets.transfer.destroy`) **sudah diimplementasikan penuh** oleh commit `9baa811`
(backend) dan `7111100` (frontend), termasuk test PHPUnit terkait. **Jangan tulis ulang todo untuk bagian
itu.**

Catatan koreksi penting lain:
- **Tidak ada** kolom `status` di tabel `wallet_transfers` dan **tidak ada** `app/Enums/WalletTransferStatus.php`
  di branch ini — kalau ada dokumen/referensi lain yang menyebut enum itu, itu milik branch lain
  (`feature/lanjutkan-ui-dompet-responsif-theming-3-mode-enum-wallet-transfer`), bukan branch aktif ini.
  Jangan asumsikan kolom itu ada.
- Stack: Laravel 13 + Inertia + Vue 3 (`<script setup>`), custom CSS berbasis CSS Variables. File inti:
  `resources/js/Pages/App/Dompet.vue`, `resources/js/Components/Wallet/*.vue`,
  `resources/js/Composables/useTheme.js`, `resources/css/themes/theme-{blue,green,dark}.css`,
  `app/Http/Controllers/App/{WalletController,TransactionController,AccountController}.php`,
  `app/Services/WalletService.php`, `app/Models/{UserWallet,WalletTransfer,UserProfile}.php`.

Spec ini **hanya** berisi gap nyata yang saya temukan dengan membaca kode HEAD `7111100` langsung (bukan
asumsi) yang **belum** ditutup oleh 3 commit sebelumnya, plus langkah verifikasi yang diminta CEO (PHPStan,
test, PR). Ada **4 area**: **§1 BUG — icon/color dompet tidak sampai ke frontend**, **§2 Dompet arsip tidak
bisa dilihat/diaktifkan lagi**, **§3 UX form wallet_transfer — validasi inline & konfirmasi**, **§4 Testing,
verifikasi kualitas, & deliverables**.

---

## 1. BUG — `icon`/`color` dompet custom tidak pernah sampai ke frontend

### 1.1 Temuan
Migration, `$fillable`, validasi `store`/`update`, dan `CardDompet.vue` (baca `wallet.icon`/`wallet.color`,
fallback ke `bank_initial`/`bank_color` kalau null) **sudah lengkap**. Tapi `TransactionController@index`
(route `dompet.index`, sumber prop `wallets` yang dipakai `Dompet.vue`) membangun array wallet secara manual
dan **tidak menyertakan** key `icon`/`color` sama sekali:

```php
$wallets = $walletsRaw->map(fn ($w) => [
    'id' => $w->id, 'display_name' => $w->display_name, 'account_number' => $w->account_number,
    'type' => $w->type, 'balance' => (float) $w->balance, 'is_saham' => $w->is_saham,
    'bank_id' => $w->bank_id, 'bank_name' => $w->bank?->short_name,
    'bank_color' => $w->bank?->logo_color ?? '#2563EB',
    'bank_initial' => $w->bank?->logo_initial ?? strtoupper(substr($w->display_name, 0, 1)),
    'logo_url' => $w->bank?->logo_url ? Storage::url($w->bank->logo_url) : null,
    // 'icon' dan 'color' TIDAK ADA di sini
]);
```

Akibatnya: user bisa pilih emoji+warna di form tambah/edit dompet, tersimpan benar di DB (`user_wallets.icon`,
`user_wallets.color`), tapi **kartu dompet selalu tampil fallback lama** (`bank_initial`/`bank_color`) karena
frontend tidak pernah menerima nilainya. Fitur ini secara end-to-end tidak berfungsi meski migration+backend
+frontend commit sudah "selesai" — gap-nya murni di 1 baris mapping yang lupa disertakan.

### 1.2 Todo Teknis
- [ ] `TransactionController@index`: tambah `'icon' => $w->icon, 'color' => $w->color,` ke closure `map()`
  yang membangun `$wallets` (baris tempat `bank_color`/`bank_initial` didefinisikan).
- [ ] Verifikasi manual di browser: buat dompet baru dengan icon+warna custom → cek kartu dompet di tab
  Dompet langsung tampil emoji+warna itu (bukan fallback bank).

### 1.3 Kontrak API — extend response `GET /dompet` (endpoint sama, tidak ada endpoint baru)

#### Endpoint
GET `/dompet` (`dompet.index`) — request query params tidak berubah.

#### Response (tambahan pada `wallets[]`, field lain tidak berubah)
```
wallets[]: {
  ...field existing tidak berubah (id, display_name, account_number, type, balance, is_saham,
     bank_id, bank_name, bank_color, bank_initial, logo_url)...,
  icon: string | null,   // BARU — emoji custom dari user_wallets.icon
  color: 'primary' | 'success' | 'danger' | 'warning' | 'info' | null   // BARU — dari user_wallets.color
}
```

#### Database
Tidak ada tabel/kolom baru — `user_wallets.icon`/`user_wallets.color` sudah ada (migration
`2026_07_14_000002_add_icon_and_color_to_user_wallets_table.php`), tinggal diwiring ke response.

#### Validasi
Tidak ada validasi baru.

---

## 2. Dompet yang diarsipkan hilang total — tidak bisa dilihat atau diaktifkan lagi

### 2.1 Temuan
`TransactionController@index` mengambil wallet dengan `->where('is_active', true)` sebelum di-map jadi prop
`wallets`. Karena **satu-satunya** sumber daftar dompet di UI adalah prop ini, begitu dompet diarsipkan lewat
`PATCH /dompet/wallets/{wallet}/archive` (`wallets.archive`, sudah ada & berfungsi), dompet itu **langsung
hilang dari tampilan** dan tidak ada jalan baliknya dari UI manapun — tidak ada tab/badge "Diarsipkan", tombol
aksi di `CardDompet` juga selalu berlabel statis **"Arsipkan"** (tidak pernah "Aktifkan", karena wallet yang
sudah diarsipkan memang tak pernah dirender lagi). Endpoint `wallets.archive` sendiri sudah 2 arah (toggle
`is_active`), jadi ini murni gap tampilan, bukan gap endpoint.

CEO brief eksplisit minta aksi cepat "arsip" di kartu dompet — kondisi "arsip tanpa jalan kembali" ini bukan
UX yang bisa diterima.

### 2.2 Todo Teknis
- [ ] `TransactionController@index`: terima query param baru `show_archived` (boolean, optional). Kalau
  truthy, ambil **juga** dompet dengan `is_active = false` milik user, map dengan shape yang sama seperti
  `wallets[]` (termasuk `icon`/`color` dari §1) + tambahan `is_active: boolean`, kirim sebagai prop terpisah
  `archived_wallets` (array, kosong `[]` kalau `show_archived` falsy/tidak dikirim) — **jangan** campur ke
  dalam prop `wallets` yang sudah dipakai di tempat lain (dropdown pemilihan dompet form transaksi/transfer)
  supaya wallet arsip tetap tidak muncul di pilihan itu (behaviour existing, tidak berubah).
- [ ] `Dompet.vue` tab "Dompet": tambah toggle/checkbox kecil "Tampilkan yang diarsipkan" (di atas
  `wallet-grid`, dekat tombol tambah dompet) yang men-trigger Inertia partial reload
  (`router.reload({ data: { show_archived: 1 }, only: ['archived_wallets'] })` atau setara) lalu render
  `archived_wallets` di bawah daftar dompet aktif, dengan badge kecil "Diarsipkan" per kartu.
- [ ] `CardDompet.vue` atau pemanggilnya di `Dompet.vue`: label tombol aksi jadi dinamis —
  `{{ wallet.is_active ? 'Arsipkan' : 'Aktifkan' }}` (bukan teks statis "Arsipkan"), tetap panggil endpoint
  `wallets.archive` yang sama (sudah 2 arah, tidak perlu endpoint baru).

### 2.3 Kontrak API — extend response `GET /dompet` (endpoint sama, param request baru)

#### Endpoint
GET `/dompet?show_archived=1` (`dompet.index`)

#### Request (tambahan, optional)
```
{ show_archived?: '1' | '0' | boolean }
```

#### Response (prop baru, hanya terisi kalau `show_archived` truthy)
```
archived_wallets: Array<{
  id: string, display_name: string, account_number: string | null, type: string,
  balance: number, is_saham: boolean, bank_id: string | null, bank_name: string | null,
  bank_color: string, bank_initial: string, logo_url: string | null,
  icon: string | null, color: 'primary'|'success'|'danger'|'warning'|'info'|null,
  is_active: false
}>
```
Kalau `show_archived` tidak dikirim/falsy: `archived_wallets` tetap ada sebagai `[]` (bukan `null`, supaya
frontend tidak perlu null-check tambahan).

#### Database
Tidak ada kolom baru. Query tambahan: `$user->wallets()->where('is_active', false)->...->get()`.

#### Validasi
- `show_archived`: `nullable|boolean` (via `DompetFilterRequest` yang sudah ada — tambahkan rule ini ke
  request class itu, bukan bikin FormRequest baru).

---

## 3. UX form Wallet Transfer — validasi inline & langkah konfirmasi

### 3.1 Temuan
Endpoint `POST /dompet/transfer` (`wallets.transfer`) sudah lengkap validasi server-side (`different`,
`min:1`, cek saldo). Tapi di modal `Dompet.vue` (§ "MODAL: Transfer Antar Dompet"), form **hanya** mengandalkan
atribut HTML native (`required`, opsi `to_wallet_id` yang sama dengan `from_wallet_id` di-disable di
`<select>`) dan `transferForm.processing` untuk disable tombol submit saat proses berjalan. **Tidak ada**:
pesan error inline bergaya tema saat validasi gagal (mis. jumlah 0 sempat ke-submit lalu baru dapat error dari
server), dan **tidak ada** langkah ringkasan/konfirmasi sebelum submit final seperti diminta CEO ("tunjukkan
ringkasan transfer sebelum konfirmasi bila ada step tersebut di desain").

### 3.2 Todo Teknis (murni frontend, tidak ada perubahan endpoint)
- [ ] Tambah validasi client-side sebelum submit di `submitTransfer()`: `from_wallet_id !== to_wallet_id`,
  `amount > 0`, kedua field wallet terisi, `transferred_at` terisi. Simpan pesan error per field di objek
  reaktif lokal (mis. `transferErrors`), tampilkan `<span class="field-error">` di bawah field terkait
  memakai token warna yang sudah ada (`color: var(--danger)`, background opsional `var(--danger-bg)` untuk
  banner ringkasan error kalau lebih dari 1 field invalid).
  Untuk error yang dikirim balik dari server (mis. saldo tidak cukup, dikirim via flash `error`), tampilkan di
  lokasi yang sama secara konsisten — jangan hanya browser `alert`/`confirm`.
- [ ] Tombol submit (`.btn-primary` "🔄 Transfer Sekarang"): tambah kondisi `:disabled` gabungan —
  `transferForm.processing || !isTransferFormValid` (computed baru berdasarkan validasi §3.2 di atas), bukan
  hanya `transferForm.processing` seperti sekarang.
- [ ] Tambah langkah konfirmasi 2-tahap di modal yang sama (tanpa endpoint baru): klik "Transfer Sekarang"
  pertama kali (kalau valid) mengganti isi modal jadi ringkasan read-only — Dompet Sumber, Dompet Tujuan,
  Jumlah (format `formatRupiah`), Tanggal — dengan 2 tombol "Kembali" (balik ke form isi) dan "Konfirmasi &
  Kirim" (baru di sini `transferForm.post(route('wallets.transfer'))` benar-benar dipanggil). State ini cukup
  `ref` lokal di komponen (`showTransferConfirm`), reset ke `false` setiap kali modal transfer ditutup/dibuka
  ulang.
- [ ] Format jumlah & tanggal di ringkasan konfirmasi ikut konvensi yang sudah dipakai di file yang sama
  (`formatRupiah` dari `@/lib/format`, tanggal native `<input type="date">` cukup ditampilkan apa adanya atau
  di-format `toLocaleDateString('id-ID')` — pilih yang konsisten dengan pemakaian tanggal lain di halaman ini).

### 3.3 Kontrak API — restated untuk referensi Frontend AI (endpoint & validasi TIDAK berubah, sudah ada)

#### Endpoint
POST `/dompet/transfer` (`wallets.transfer`)

#### Request
```
{
  from_wallet_id: string,   // required, exists:user_wallets,id, different:to_wallet_id
  to_wallet_id: string,     // required, exists:user_wallets,id
  amount: number,           // required, numeric, min:1
  note: string | null,      // nullable, max:255
  transferred_at: string    // required, date (format YYYY-MM-DD)
}
```

#### Response
Redirect `back()` dengan flash `success` (format: "Berhasil transfer Rp {amount} dari {from} ke {to}!") atau
`error` (saldo tidak cukup: "Saldo {from_wallet} tidak cukup. Saldo saat ini: Rp {balance}") — pola Inertia
existing, bukan JSON.

#### Database
Tidak ada perubahan. Insert 1 baris `wallet_transfers`, insert 2 baris `wallet_balance_logs`
(`reference_type='wallet_transfer'`), update `balance` di 2 baris `user_wallets` (dibungkus `DB::transaction`,
sudah diimplementasikan di `WalletController@transfer` + `WalletService::transferBetweenWallets`).

#### Validasi
Aturan di atas **harus dicerminkan di sisi client** (§3.2) supaya user dapat feedback instan, tapi server
tetap sumber kebenaran final — jangan hilangkan validasi server yang sudah ada.

---

## 4. Testing, Verifikasi Kualitas, & Deliverables

### 4.1 Verifikasi yang wajib dijalankan ulang (bukan diasumsikan lolos)
- [ ] `git fetch origin` lalu pastikan `feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer` lokal
  masih sinkron dengan remote (per HEAD `7111100` saat spec ditulis, tidak ada divergence) — kalau ternyata
  ada commit baru di remote, `git pull --ff-only` (bukan rebase yang mengubah history commit yang sudah
  di-push).
- [ ] `vendor/bin/phpstan analyse` — sekarang cache permission sudah dibersihkan owner, jalankan ulang dari
  nol. Kalau muncul error yang **bukan** soal permission (mis. type mismatch di kode yang disentuh §1/§2),
  perbaiki sesuai standar project; kalau perlu entry baru di baseline karena false-positive genuine, ikuti
  pola commit `91a34cc`/`d10880e` (regenerate baseline, bukan `--ignore-errors` sembarangan).
- [ ] `./vendor/bin/pint --test` bersih.
- [ ] `php artisan test` — pastikan 5 test file yang sudah ada dari commit `9baa811` (`AccountThemeTest`,
  `DompetTransactionHistoryTest`, `WalletArchiveTest`, `WalletTransferTest`,
  `WalletServiceReverseTransferTest`) masih hijau, plus tambahkan test baru untuk gap §1 dan §2:
  - Feature test: `wallets[].icon`/`wallets[].color` muncul di response Inertia `dompet.index` ketika wallet
    punya nilai itu di DB (assert lewat helper `inertiaProps()` yang sudah ada di test lain).
  - Feature test: `archived_wallets` kosong `[]` kalau `show_archived` tidak dikirim; berisi wallet
    `is_active=false` milik user (bukan milik user lain) kalau `show_archived=1` dikirim.

### 4.2 Testing frontend — keputusan cakupan (bukan diabaikan diam-diam)
`package.json` project ini **tidak punya test runner JS sama sekali** (tidak ada Vitest/Jest/Cypress/
Playwright — hanya `vite`/`vite build` di `scripts`), dan pola ini konsisten di seluruh riwayat project
(composable lain seperti komposabel-komposabel sebelumnya juga tidak punya unit test JS). Menambah test
runner baru adalah keputusan infra yang di luar scope redesign UI/theming/wallet_transfer ini.
- [ ] Untuk "unit test util theming" & "E2E scenario" yang diminta CEO: **tidak** menambah dependency test JS
  baru di iterasi ini. Sebagai gantinya, cakupan dipenuhi lewat:
  - Sisi server (persist tema per-user): sudah tercakup `AccountThemeTest.php` (ada).
  - Sisi client (localStorage, `prefers-color-scheme`, resolusi prioritas `useTheme.js`, validasi inline
    §3.2, langkah konfirmasi transfer §3.2): **checklist smoke-test manual di browser** (Chromium + viewport
    mobile 375px), dijalankan Frontend AI, hasilnya dicatat eksplisit di deskripsi PR:
    1. Ganti tema 3x (blue/green/dark) dari halaman Akun, refresh halaman → tema tetap sama (persist DB).
    2. Buka di mode incognito tanpa login, tanpa `localStorage` → tema ikut `prefers-color-scheme` OS.
    3. Buat dompet dengan icon+warna custom → kartu dompet langsung tampil sesuai (verifikasi fix §1).
    4. Arsipkan dompet dari kartu → dompet hilang dari daftar aktif; centang "Tampilkan yang diarsipkan" →
       muncul dengan badge, klik "Aktifkan" → dompet aktif lagi (verifikasi fix §2).
    5. Coba transfer dengan dompet sumber = tujuan → tombol submit disabled + pesan error inline muncul
       (bukan langsung submit ke server).
    6. Transfer valid → layar ringkasan konfirmasi muncul → klik "Konfirmasi & Kirim" → sukses, saldo kedua
       dompet berubah sesuai.
  - Kalau CEO/reviewer tetap insist butuh test JS otomatis, itu keputusan terpisah yang perlu approval
    eksplisit untuk menambah `vitest`+`@vue/test-utils` sebagai devDependency — **di luar scope todo ini**.

### 4.3 Deliverables
- [ ] Commit ke branch yang sama (`feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer`).
- [ ] Branch sudah `up to date with origin` — kemungkinan PR sudah ada untuk branch ini; **update deskripsi
  PR existing** (jangan buat PR baru) dengan: ringkasan fix §1 (bug icon/color) & §2 (dompet arsip), hasil
  `phpstan`/`pint`/`php artisan test` (paste ringkas, bukan hanya "lulus"), checklist smoke-test manual §4.2.
- [ ] Screenshot/rekaman singkat sebelum/sesudah: kartu dompet dengan icon/color custom, toggle "Tampilkan
  yang diarsipkan", form transfer dengan pesan error inline, layar konfirmasi transfer.
- [ ] `README.md` belum menyebut theming/wallet sama sekali — tambahkan bagian singkat "Theming" (3 tema
  blue/green/dark, cara pilih tema di halaman Akun, urutan prioritas resolusi tema, link ke
  `docs/theming-guide.md` yang sudah ada & lengkap). Jangan buat `CHANGELOG.md` baru (konsisten dengan
  keputusan di `docs/spec-lanjutkan-redesign-ui-dompet-theming-wallet-transfer.md` §5.2 — bukan pola di
  branch ini).

---

## Kriteria Selesai (acceptance) — tambahan di luar yang sudah closed dokumen lama

- [ ] Icon & warna custom dompet benar-benar tampil di kartu dompet (bug §1 fixed & diverifikasi di browser,
  bukan cuma di database).
- [ ] Dompet yang diarsipkan bisa dilihat (dengan badge "Diarsipkan") dan diaktifkan kembali dari UI, label
  tombol aksi berubah sesuai state (§2).
- [ ] Form transfer: validasi inline mencegah submit invalid (sumber=tujuan, jumlah≤0), ada langkah ringkasan
  konfirmasi sebelum kirim final (§3).
- [ ] `phpstan`, `pint`, `php artisan test` semua hijau, hasil dicantumkan eksplisit di deskripsi PR (§4.1).
- [ ] Keputusan cakupan testing frontend (§4.2) dicatat eksplisit di PR — bukan silently skipped.
- [ ] README diperbarui dengan bagian Theming singkat (§4.3).
