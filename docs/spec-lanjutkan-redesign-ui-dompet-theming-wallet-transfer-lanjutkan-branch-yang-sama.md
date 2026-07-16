# Spec: Lanjutkan Redesign UI Dompet + Theming + Wallet Transfer (Lanjutkan Branch yang Sama)

Sumber arahan: CEO AI, 2026-07-14. Ditulis oleh Project Manager AI setelah membaca kode langsung di
kedua branch yang relevan (bukan asumsi) — lihat §0 untuk temuan kritis soal branch mana yang benar.

> **REVISI 2026-07-14 (iterasi lanjutan)** — CEO mengirim arahan lanjutan di branch yang sekarang
> benar-benar sedang di-checkout (`...-lanjutkan-branch-yang-sama`), dan menyatakan `tests/Feature`/
> `tests/Unit` sudah dibuat ulang dengan kepemilikan benar. PM iterasi ini memverifikasi ulang state
> branch tersebut secara langsung (bukan asumsi) dan menemukan gap baru yang **lebih mendasar** dari
> §1–§7 di bawah: branch yang sedang di-checkout **belum berisi kode kerja sama sekali** dari §1–§7,
> hanya dokumen spec ini + direktori test kosong. **Baca §8 (paling bawah) dulu sebelum mengerjakan
> §1–§7** — §8 berisi arahan konsolidasi branch yang wajib dilakukan lebih dulu. Isi §1–§7 di bawah
> (ditulis iterasi PM sebelumnya) tetap valid sebagai kontrak teknis begitu konsolidasi §8 selesai,
> tidak perlu ditulis ulang.

---

## 0. PENTING — Klarifikasi branch aktif (WAJIB dibaca sebelum mulai kerja)

CEO minta lanjutkan "branch yang sama yang dipakai sebelumnya". Ada **dua branch** dengan nama mirip
dan history yang sama-sama menyebut task ini, tapi isinya sangat berbeda:

| Branch | HEAD | Isi riil |
|---|---|---|
| `feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer` (**tanpa** "dan") | `7111100` | 3 commit kerja nyata: `a33950a` (migration: `user_profiles.theme`, `user_wallets.icon`/`color`), `9baa811` (backend: theming persist, wallet transfer + reversal, icon/color CRUD), `7111100` (frontend: `useTheme.js`, 3 file tema CSS, komponen `Wallet/*`, redesign `Dompet.vue`/`AppLayout.vue`, toggle tema di `Account.vue`), plus test suite (`AccountThemeTest`, `WalletArchiveTest`, `WalletTransferTest`, `WalletServiceReverseTransferTest`, `DompetTransactionHistoryTest`) dan `docs/theming-guide.md` lengkap. |
| `feature/lanjutkan-redesign-ui-dompet-theming-dan-wallet-transfer` (**dengan** "dan") — **branch yang sedang di-checkout saat ini** | `77b905e` | **Kosong dari kode.** Commit `93969d9` ("database migration") isinya cuma 1 file dokumentasi (`docs/spec-lanjutkan-redesign-ui-dompet-theming-dan-wallet-transfer.md`, 286 baris) — **tidak ada migration/backend/frontend sama sekali**. Commit `77b905e` cuma restore 2 file `.gitkeep` kosong di `tests/Feature`/`tests/Unit`. Tidak ada `useTheme.js`, tidak ada migration `add_theme_to_user_profiles`, tidak ada test. Branch ini dibuat dari base yang sama (`91a34cc`) tapi implementasinya tidak pernah masuk ke sini — kemungkinan besar salah checkout/salah cherry-pick oleh iterasi sebelumnya. |

**Keputusan**: branch riil yang "dipakai sebelumnya" untuk task redesign+theming+transfer ini adalah
**`feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer`** (tanpa "dan"), karena di situlah semua
kode nyata berada. Database AI/Backend AI/Frontend AI **wajib**:
1. `git fetch origin`, lalu kerja di atas `origin/feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer`
   (checkout branch itu, atau kalau sudah terlanjur ada branch lokal `...-dan-wallet-transfer` yang jadi
   working branch tim, **merge/rebase branch itu di atas** `feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer`
   supaya tidak kehilangan 3 commit kerja nyata di atas). **Jangan** lanjut menulis kode baru di atas
   `...-dan-wallet-transfer` apa adanya (HEAD `77b905e`) — itu akan mengulang dari nol pekerjaan yang
   sudah selesai.
2. Konfirmasi tidak ada commit baru di remote sejak `7111100` sebelum mulai (`git log origin/feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer -5`).
3. PR/MR yang diupdate di deliverable akhir adalah PR untuk branch `feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer`.
4. Sudah ada dokumen `docs/spec-lanjutkan-redesign-ui-dompet-theming-wallet-transfer.md` (352 baris, iterasi
   PM sebelumnya) yang berisi analisis detail 4 gap yang **masih terbuka** di HEAD `7111100` — sudah
   diverifikasi ulang baris-per-baris oleh PM iterasi ini (lihat §1–§3 di bawah, isinya konsisten dengan
   dokumen itu). Spec ini (`docs/spec-...-lanjutkan-branch-yang-sama.md`) adalah dokumen **kontrak resmi**
   untuk iterasi sekarang sesuai permintaan CEO — §1–§3 merangkum ulang gap lama supaya Backend/Frontend AI
   tidak perlu buka 2 dokumen, §4–§6 adalah item **baru** dari brief CEO kali ini yang belum tercakup di
   dokumen sebelumnya (tema "System", anti-FOUC, klarifikasi multi-mata uang).

Stack terkonfirmasi: Laravel 13 + Inertia + Vue 3 (`<script setup>`), custom CSS berbasis CSS Variables.
File inti: `resources/js/Pages/App/{Dompet,Account}.vue`, `resources/js/Components/Wallet/*.vue`,
`resources/js/Composables/useTheme.js`, `resources/css/themes/theme-{blue,green,dark}.css`,
`resources/views/app.blade.php`, `app/Http/Controllers/App/{WalletController,TransactionController,AccountController}.php`,
`app/Http/Middleware/HandleInertiaRequests.php`, `app/Services/WalletService.php`,
`app/Models/{UserWallet,WalletTransfer,UserProfile}.php`.

---

## 1. BUG — `icon`/`color` dompet custom tidak sampai ke frontend

### Temuan
`TransactionController@index` (route `dompet.index`) membangun prop `wallets` secara manual dan tidak
menyertakan key `icon`/`color`, walau kolomnya sudah ada di DB (`user_wallets.icon`, `user_wallets.color`,
migration `2026_07_14_000002_add_icon_and_color_to_user_wallets_table.php`) dan sudah bisa diisi lewat
`wallets.store`/`wallets.update`. Akibat: user pilih emoji+warna custom saat tambah/edit dompet, tersimpan
benar di DB, tapi kartu dompet di UI selalu fallback ke `bank_initial`/`bank_color` karena frontend tidak
pernah menerima nilainya.

### Todo Teknis
- [ ] `TransactionController@index`: tambahkan `'icon' => $w->icon, 'color' => $w->color,` ke closure
  `map()` yang membangun `$wallets` (baris tempat `bank_color`/`bank_initial` didefinisikan).
- [ ] Verifikasi manual: buat dompet baru dengan icon+warna custom → kartu dompet tampil emoji+warna itu.

### Kontrak API — extend response `GET /dompet` (endpoint sama, tidak ada endpoint baru)

**Endpoint**: GET `/dompet` (`dompet.index`) — query params tidak berubah.

**Response** (tambahan pada `wallets[]`, field lain existing tidak berubah):
```
wallets[]: {
  ...existing (id, display_name, account_number, type, balance, is_saham, bank_id, bank_name,
     bank_color, bank_initial, logo_url)...,
  icon: string | null,   // BARU
  color: 'primary' | 'success' | 'danger' | 'warning' | 'info' | null   // BARU
}
```

**Database**: tidak ada tabel/kolom baru — `user_wallets.icon`/`color` sudah ada.

**Validasi**: tidak ada validasi baru.

---

## 2. Dompet yang diarsipkan hilang total — tidak bisa dilihat/diaktifkan lagi

### Temuan
`TransactionController@index` mengambil wallet dengan `->where('is_active', true)` saja. Endpoint
`wallets.archive` (`PATCH /dompet/wallets/{wallet}/archive`) sudah 2 arah (toggle `is_active`), tapi karena
satu-satunya sumber daftar dompet di UI hanya prop `wallets` (yang selalu difilter aktif), begitu dompet
diarsipkan dia langsung hilang tanpa jalan balik dari UI manapun. Tombol aksi di kartu dompet juga berlabel
statis "Arsipkan" (tidak pernah "Aktifkan").

### Todo Teknis
- [ ] `TransactionController@index`: terima query param baru `show_archived` (boolean, optional). Kalau
  truthy, ambil **juga** dompet `is_active = false` milik user, map dengan shape sama seperti `wallets[]`
  (termasuk `icon`/`color` dari §1) + `is_active: boolean`, kirim sebagai prop terpisah `archived_wallets`
  (array, `[]` kalau `show_archived` falsy). **Jangan** campur ke prop `wallets` (supaya dropdown pemilihan
  dompet di form transaksi/transfer tetap tidak menampilkan wallet arsip — behaviour existing).
- [ ] Halaman Dompet, tab "Dompet": tambah toggle "Tampilkan yang diarsipkan" (di atas grid kartu dompet)
  yang trigger Inertia partial reload (`router.reload({ data: { show_archived: 1 }, only: ['archived_wallets'] })`)
  lalu render `archived_wallets` di bawah daftar aktif, badge kecil "Diarsipkan" per kartu.
- [ ] Kartu dompet: label tombol aksi dinamis — `{{ wallet.is_active ? 'Arsipkan' : 'Aktifkan' }}`, tetap
  panggil endpoint `wallets.archive` yang sama.

### Kontrak API — extend response `GET /dompet` (endpoint sama, param request baru)

**Endpoint**: GET `/dompet?show_archived=1` (`dompet.index`)

**Request** (tambahan, optional): `{ show_archived?: '1' | '0' | boolean }`

**Response** (prop baru, hanya terisi kalau `show_archived` truthy):
```
archived_wallets: Array<{
  id, display_name, account_number, type, balance, is_saham, bank_id, bank_name,
  bank_color, bank_initial, logo_url, icon, color, is_active: false
}>
```
Kalau `show_archived` tidak dikirim/falsy: `archived_wallets` tetap `[]` (bukan `null`).

**Database**: tidak ada kolom baru. Query tambahan: `$user->wallets()->where('is_active', false)->...->get()`.

**Validasi**: `show_archived: nullable|boolean` — tambahkan rule ini ke `DompetFilterRequest` yang sudah ada
(jangan bikin FormRequest baru).

---

## 3. UX form Wallet Transfer — validasi inline & langkah konfirmasi

### Temuan
`POST /dompet/transfer` (`wallets.transfer`) sudah lengkap validasi server-side (`different`, `min:1`, cek
saldo — lihat kontrak §3 di bawah). Tapi modal transfer di `Dompet.vue` hanya mengandalkan atribut HTML
native (`required`, opsi `to_wallet_id` sama di-disable) dan `transferForm.processing` untuk disable tombol
submit. **Tidak ada** pesan error inline bergaya tema, dan **tidak ada** langkah ringkasan/konfirmasi
sebelum submit final.

### Todo Teknis (murni frontend, endpoint tidak berubah)
- [ ] Validasi client-side sebelum submit di `submitTransfer()`: `from_wallet_id !== to_wallet_id`,
  `amount > 0`, kedua field wallet terisi, `transferred_at` terisi. Simpan pesan error per field di objek
  reaktif lokal (`transferErrors`), tampilkan `<span class="field-error">` di bawah field terkait
  (`color: var(--danger)`). Error dari server (flash `error`, mis. saldo tidak cukup) ditampilkan di lokasi
  yang sama secara konsisten — **bukan** `alert`/`confirm` browser.
- [ ] Tombol submit: `:disabled="transferForm.processing || !isTransferFormValid"` (computed baru).
- [ ] Langkah konfirmasi 2-tahap di modal yang sama (tanpa endpoint baru): klik "Transfer Sekarang" pertama
  (kalau valid) → modal berganti jadi ringkasan read-only (Dompet Sumber, Dompet Tujuan, Jumlah format
  `formatRupiah`, Tanggal) dengan tombol "Kembali" dan "Konfirmasi & Kirim" (baru di sini
  `transferForm.post(route('wallets.transfer'))` benar-benar dipanggil). State `showTransferConfirm` (ref
  lokal), reset `false` tiap modal dibuka/ditutup.
- [ ] Format jumlah/tanggal di ringkasan ikut konvensi yang sudah dipakai di file yang sama.
- [ ] **Aksesibilitas** (baru dari brief CEO iterasi ini): banner error ringkasan (>1 field invalid) pakai
  `role="alert"` supaya screen reader announce otomatis; field error individual pakai `aria-describedby`
  menunjuk ke `id` span error terkait, dan `aria-invalid="true"` di elemen `<select>`/`<input>` saat
  invalid. Tombol "Kembali"/"Konfirmasi & Kirim" dapat `aria-label` eksplisit kalau teksnya ambigu tanpa
  konteks visual.

### Kontrak API — restated untuk referensi (endpoint & validasi TIDAK berubah, sudah ada)

**Endpoint**: POST `/dompet/transfer` (`wallets.transfer`)

**Request**:
```
{
  from_wallet_id: string,   // required, exists:user_wallets,id, different:to_wallet_id
  to_wallet_id: string,     // required, exists:user_wallets,id
  amount: number,           // required, numeric, min:1
  note: string | null,      // nullable, max:255
  transferred_at: string    // required, date (YYYY-MM-DD)
}
```

**Response**: Redirect `back()` dengan flash `success` ("Berhasil transfer Rp {amount} dari {from} ke
{to}!") atau `error` (saldo tidak cukup: "Saldo {from_wallet} tidak cukup. Saldo saat ini: Rp {balance}") —
pola Inertia existing, bukan JSON.

**Database**: tidak ada perubahan. Insert 1 baris `wallet_transfers`, insert 2 baris `wallet_balance_logs`
(`reference_type='wallet_transfer'`), update `balance` di 2 baris `user_wallets` (dalam `DB::transaction`,
sudah diimplementasikan di `WalletController@transfer` + `WalletService::transferBetweenWallets`).

**Pembatalan transfer** (sudah ada, dipertahankan): DELETE `/dompet/transfer/{walletTransfer}`
(`wallets.transfer.destroy`) — reversal penuh saldo kedua dompet via `WalletService::reverseTransfer`
(ditolak kalau `to_wallet` sudah dipakai lagi hingga saldo akan negatif). Tidak ada perubahan kontrak di
bagian ini, cukup dipertahankan.

**Validasi**: aturan di atas harus dicerminkan di client (poin Todo di atas), server tetap sumber kebenaran
final.

---

## 4. BARU — Opsi tema "System" (ikuti `prefers-color-scheme` OS secara dinamis)

### Klarifikasi penting (baca sebelum implementasi)
Brief CEO minta "Light/Dark/System". Produk Monexa **tidak** memakai model biner Light/Dark — sistem tema
yang sudah dibangun (dan sudah dipakai luas di `Dompet.vue`, `Account.vue`, `AppLayout.vue`) adalah **3 tema
bernama**: `blue` (terang, default), `green` (terang, aksen hijau), `dark` (gelap). Mengganti ini jadi model
Light/Dark generik akan merombak seluruh token & keputusan desain yang sudah final — **di luar scope**.
Asumsi PM (paling masuk akal mengikuti pola existing): map "Light" → tetap 2 pilihan `blue`/`green` yang
sudah ada (user tetap pilih salah satu secara eksplisit), tambahkan **1 nilai baru** `'system'` yang berarti
"ikuti OS secara berkelanjutan" (bukan cuma fallback sekali di awal seperti sekarang) — mapping: OS dark →
render tema `dark`, OS light → render tema `blue`. Tema `green` tetap pilihan manual saja (tidak ada
"system green" karena tidak ada pasangan light/dark untuk hijau).

Perbedaan dengan behaviour **existing** (`useTheme.js` prioritas #4, `prefers-color-scheme` sebagai fallback
resolusi awal): saat ini OS preference hanya dibaca **sekali** saat load kalau tidak ada
`localStorage`/preferensi tersimpan, dan **tidak** ada listener — kalau user ganti dark mode OS di tengah
sesi, tema aplikasi tidak ikut berubah kecuali reload. Behaviour baru untuk `'system'`: selama nilai
tersimpan adalah `'system'`, aplikasi **listen** ke perubahan `prefers-color-scheme` (event `change` pada
`matchMedia`) dan re-apply tema tanpa reload.

### Todo Teknis
- [ ] `useTheme.js`: tambah `'system'` ke `VALID_THEMES` (whitelist tetap ketat, sekarang 4 nilai:
  `blue`, `green`, `dark`, `system`).
- [ ] Fungsi baru `resolveSystemTheme()`: return `'dark'` kalau `matchMedia('(prefers-color-scheme: dark)').matches`,
  else `'blue'`.
- [ ] `applyTheme(name)`: kalau `name === 'system'`, set `document.documentElement.dataset.theme` ke hasil
  `resolveSystemTheme()` (**bukan** literal string `'system'` — supaya CSS `[data-theme="..."]` tetap cuma
  perlu 3 selector existing, tidak perlu selector ke-4), tapi simpan **preferensi user** (`localStorage`,
  DB) tetap sebagai `'system'` supaya pilihan "System" di UI tetap ter-highlight benar setelah reload.
- [ ] Tambah listener: kalau tema aktif tersimpan adalah `'system'`, daftarkan
  `matchMedia('(prefers-color-scheme: dark)').addEventListener('change', ...)` yang panggil ulang
  `applyTheme('system')` (recompute) tanpa reload; lepas listener kalau user pindah ke tema lain (`blue`/
  `green`/`dark` eksplisit).
- [ ] `Account.vue`: tambah 1 opsi ke `themeOptions` — `{ value: 'system', label: 'Ikuti Sistem', swatch: ... }`
  (swatch bisa gradient/split blue-dark sebagai indikator visual "otomatis"). Tetap `role="radiogroup"`/
  `aria-checked` pattern yang sudah ada (§ Account.vue existing, sudah aksesibel).
- [ ] Server (persist per-user): `UpdateThemeRequest` — ubah `Rule::in(['blue', 'green', 'dark'])` jadi
  `Rule::in(['blue', 'green', 'dark', 'system'])`. `user_profiles.theme` menyimpan literal `'system'` apa
  adanya (bukan hasil resolusi) — resolusi ke tema konkret tetap kerja client-side seperti di atas, supaya
  server tidak perlu tahu OS preference user.

### Kontrak API — extend `PUT /account/theme` (endpoint sama, whitelist value baru)

**Endpoint**: PUT `/account/theme` (`account.theme`)

**Request**: `{ theme: 'blue' | 'green' | 'dark' | 'system' }` (sebelumnya cuma 3 value, tambah `'system'`)

**Response**: redirect `back()` (pola existing, tidak berubah).

**Database**: tidak ada kolom baru, `user_profiles.theme` (`char`/`string`) sudah cukup menampung nilai
`'system'` (pastikan panjang kolom migration `2026_07_14_000001_add_theme_to_user_profiles_table.php`
cukup — cek definisi kolom, kalau enum-based di level DB harus ditambah value `system` juga, kalau
`string`/`varchar` biasa tidak perlu migration baru).

**Validasi**: `theme: required|string|in:blue,green,dark,system` (ganti rule `UpdateThemeRequest`).

---

## 5. BARU — Cegah FOUC (Flash of Unstyled/Wrong Content) tema

### Temuan
`initTheme()` dipanggil di `resources/js/app.js`, dieksekusi oleh JS **setelah** module script ter-load
(Vite module scripts default `type="module"`, defer). `resources/views/app.blade.php` (root Blade view yang
dirender server) **tidak** menyertakan `data-theme`/class `dark` di tag `<html>` sama sekali — server tidak
tahu tema user saat HTML pertama kali dikirim. Akibat: ada jendela waktu singkat antara HTML pertama
ter-render (pakai default `:root` browser/tema fallback) dan JS selesai load+eksekusi `initTheme()` di mana
tema yang tampil bisa salah/berkedip, terutama untuk user yang preferensinya `dark`/`system`-dark (paling
kentara: latar putih sekilas sebelum berubah gelap).

Nilai tema untuk user yang sedang login **sudah tersedia di server** — `HandleInertiaRequests::share()`
sudah mengirim `'theme' => $request->user()?->profile?->theme` sebagai shared prop, tinggal dipakai juga di
level Blade (bukan cuma di props Inertia yang baru terbaca di client setelah hydration).

### Todo Teknis
- [ ] `resources/views/app.blade.php`: set atribut `data-theme` dan class `dark` langsung di tag `<html>`
  berdasarkan `auth()->user()?->profile?->theme` (Blade punya akses helper `auth()` langsung, tidak perlu
  passing variable tambahan dari controller):
  ```blade
  @php($initialTheme = in_array(auth()->user()?->profile?->theme, ['blue','green','dark'], true) ? auth()->user()->profile->theme : 'blue')
  <html lang="id" data-theme="{{ $initialTheme }}" class="{{ $initialTheme === 'dark' ? 'dark' : '' }}">
  ```
  Kalau preferensi tersimpan adalah `'system'` atau user belum login/belum punya preferensi, biarkan
  fallback `'blue'` di server (resolusi akhir `system`→OS tetap terjadi di client lewat `initTheme()`
  seperti biasa, tidak butuh deteksi OS di server — server tidak bisa tahu `prefers-color-scheme` request
  HTTP biasa). Ini tidak menghilangkan flash 100% untuk kasus `system`+OS dark, tapi menghilangkan untuk
  mayoritas kasus (user yang eksplisit pilih `blue`/`green`/`dark`, termasuk semua user existing sebelum
  fitur `system` §4 ditambahkan).
  Guest tanpa auth: tetap fallback `'blue'` di server (tidak ada cara baca `localStorage` di server-side
  render), `initTheme()` client tetap bisa override dari `localStorage`/OS setelah JS load seperti sekarang
  — jendela flash untuk guest dengan preferensi tersimpan di localStorage tetap ada (batasan arsitektur
  SSR-less Inertia, di luar scope untuk ditutup penuh iterasi ini).
- [ ] `useTheme.js` `initTheme()`: tambahkan guard — kalau `document.documentElement.dataset.theme` sudah
  sama dengan hasil resolusi (karena sudah di-set server di atas), **jangan** re-apply/re-trigger transisi
  CSS apapun (hindari flicker ganda kalau nanti ada CSS transition di properti warna).
- [ ] Pastikan tidak ada CSS `transition` global di properti warna yang berlaku saat `data-theme` pertama
  kali di-set oleh server (transisi cukup untuk pergantian tema **setelah** app sudah mount, misal saat
  user klik opsi tema lain di Account.vue) — kalau perlu, tambah `transition: background-color .2s ease,
  color .2s ease` scoped ke elemen yang relevan (bukan `*`), diaktifkan lewat class yang ditambahkan
  `useTheme.js` **setelah** initial mount selesai (bukan dari awal).

### Kontrak
Tidak ada endpoint baru. Perubahan murni di `app.blade.php` (baca data yang sudah di-share
`HandleInertiaRequests`) + `useTheme.js` (guard kecil). Tidak ada tabel/kolom baru, tidak ada validasi baru.

---

## 6. Klarifikasi — Multi-mata uang pada `wallet_transfer`: TIDAK APPLICABLE

### Temuan
Brief CEO minta: "Multi-mata uang: ... Jika mendukung konversi, tampilkan kurs/estimasi ...; jika tidak
mendukung, blok dengan pesan yang informatif." Setelah membaca skema data: **mata uang di Monexa adalah
atribut per-user** (`user_profiles.currency`, diisi sekali saat onboarding, salah satu dari `IDR`/`USD`/
`SGD` — lihat `OnboardingController` & migration `2025_01_01_000002_create_user_profiles_table.php`), **bukan
atribut per-dompet** (`user_wallets` tidak punya kolom `currency` sama sekali). Semua dompet milik satu user
otomatis berbagi 1 mata uang yang sama.

Konsekuensi: `wallet_transfer` selalu antar dua dompet milik **user yang sama** (`WalletController@transfer`
sudah `abort_if($fromWallet->user_id !== $user->id)` dan sama untuk `$toWallet`), jadi transfer **tidak
pernah** lintas mata uang secara struktural — tidak ada skenario di mana kurs/konversi relevan pada fitur
ini. Menambah UI kurs/estimasi untuk kasus yang tidak bisa terjadi akan membingungkan user dan menambah kode
mati.

### Keputusan
- [ ] **Tidak ada** perubahan kode untuk konversi mata uang di alur `wallet_transfer`. Todo teknis §3 di
  atas (validasi inline + konfirmasi) sudah cukup menutup acceptance criteria CEO soal "ringkasan sebelum
  eksekusi" — ringkasan itu tidak perlu baris kurs karena tidak relevan.
- [ ] Kalau ke depan CEO ingin dompet multi-currency sungguhan (kolom `currency` per `user_wallets` + logika
  konversi), itu adalah task terpisah yang jauh lebih besar (butuh sumber kurs, migration baru, perubahan
  `WalletService`, dan keputusan bisnis apakah transfer lintas currency dalam 1 user diperbolehkan) — **di
  luar scope** iterasi ini, jangan diimplementasikan sekarang.

---

## 7. Testing, Verifikasi Kualitas, & Deliverables

### 7.1 Verifikasi wajib (dijalankan ulang, bukan diasumsikan lolos)
- [ ] `git fetch origin`, pastikan branch kerja (`feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer`,
  lihat §0) masih sinkron dengan remote sebelum mulai.
- [ ] `vendor/bin/phpstan analyse` bersih (cache permission sudah dibereskan owner — kalau muncul error baru
  di kode yang disentuh §1/§2/§4/§5, perbaiki; kalau perlu baseline entry karena false-positive genuine,
  regenerate baseline mengikuti pola commit `91a34cc`/`d10880e`, bukan `--ignore-errors` sembarangan).
- [ ] `./vendor/bin/pint --test` bersih.
- [ ] `php artisan test` — pastikan test existing (`AccountThemeTest`, `DompetTransactionHistoryTest`,
  `WalletArchiveTest`, `WalletTransferTest`, `WalletServiceReverseTransferTest`) tetap hijau, plus test baru:
  - Feature test §1: `wallets[].icon`/`wallets[].color` muncul di response `dompet.index` saat wallet punya
    nilai itu di DB.
  - Feature test §2: `archived_wallets` `[]` kalau `show_archived` tidak dikirim; berisi wallet
    `is_active=false` milik user (bukan user lain) kalau `show_archived=1`.
  - Feature test §4: `PUT /account/theme` menerima `theme=system` (200/redirect sukses, `user_profiles.theme`
    tersimpan literal `'system'`); menolak value di luar whitelist (422) — extend `AccountThemeTest` yang
    sudah ada, jangan bikin file test baru kalau bisa nambah `it()`/method baru ke situ.

### 7.2 Testing frontend — keputusan cakupan (dicatat eksplisit, bukan silently skipped)
`package.json` tidak punya test runner JS (tidak ada Vitest/Jest/Cypress/Playwright), konsisten dengan
seluruh riwayat project. Menambah test runner baru adalah keputusan infra di luar scope task UI/theming/
transfer ini. Cakupan "unit test util/validasi/konversi" & "E2E alur transfer + ganti tema" yang diminta
CEO dipenuhi lewat:
- Sisi server (persist tema, validasi transfer, saldo): sudah tercakup test PHPUnit di §7.1.
- Sisi client: **checklist smoke-test manual di browser** (Chromium + viewport mobile 375px), dijalankan
  Frontend AI, hasil dicatat eksplisit di deskripsi PR:
  1. Ganti tema `blue`/`green`/`dark`/`system` dari halaman Akun, refresh halaman → tema tetap sama
     (persist DB), kecuali `system` yang ikut OS saat itu.
  2. Pilih `system`, lalu ubah dark mode OS (tanpa reload halaman) → tema app ikut berubah otomatis
     (verifikasi listener §4).
  3. Buka di mode incognito tanpa login → tema ikut `prefers-color-scheme` OS, tidak ada flash warna salah
     yang mencolok di first paint (verifikasi §5 untuk kasus user login dengan tema eksplisit tersimpan).
  4. Buat dompet dengan icon+warna custom → kartu dompet langsung tampil sesuai (verifikasi §1).
  5. Arsipkan dompet dari kartu → hilang dari daftar aktif; centang "Tampilkan yang diarsipkan" → muncul
     dengan badge, klik "Aktifkan" → aktif lagi (verifikasi §2).
  6. Transfer dengan dompet sumber = tujuan → tombol submit disabled + error inline (bukan submit ke
     server); screen reader (VoiceOver/NVDA) announce error banner (verifikasi `role="alert"` §3).
  7. Transfer valid → ringkasan konfirmasi muncul → "Konfirmasi & Kirim" → sukses, saldo kedua dompet
     berubah, transfer muncul di riwayat.
- Kalau CEO/reviewer tetap insist butuh test JS otomatis, itu keputusan terpisah yang perlu approval
  eksplisit untuk menambah `vitest`+`@vue/test-utils` sebagai devDependency — di luar scope todo ini.

### 7.3 Dokumentasi
- [ ] `docs/theming-guide.md` (sudah ada, lengkap) — tambahkan 1 bagian baru: cara kerja opsi `system`
  (mapping OS dark→`dark`, OS light→`blue`, listener `matchMedia` §4) dan catatan FOUC (§5, kenapa
  `app.blade.php` set `data-theme` server-side untuk user login, batasannya untuk guest/kasus `system`).
  Ini pemenuhan "catatan implementasi theming" yang diminta CEO — **jangan** buat file `CHANGELOG.md` baru
  (konsisten dengan keputusan iterasi sebelumnya, bukan pola yang dipakai project ini; riwayat perubahan
  cukup lewat commit message + deskripsi PR).
- [ ] `README.md` — kalau belum ada bagian yang menyebut theming/wallet (cek dulu, mungkin sudah ditambah di
  iterasi commit `9baa811`/`7111100`), tambahkan bagian singkat "Theming" (4 opsi
  blue/green/dark/system, cara pilih di halaman Akun, urutan prioritas resolusi, link ke
  `docs/theming-guide.md`).

### 7.4 Deliverables
- [ ] Commit terstruktur (per layer: migration/backend/frontend, ikuti pola commit sebelumnya) di branch
  `feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer` (§0).
- [ ] Update deskripsi PR **existing** untuk branch itu (jangan buat PR baru) dengan: ringkasan fix §1–§3,
  fitur baru §4–§5, klarifikasi §6, hasil `phpstan`/`pint`/`php artisan test` (paste ringkas, bukan cuma
  "lulus"), checklist smoke-test manual §7.2.
- [ ] Screenshot/GIF: tema Light(`blue`)/Dark/System berdampingan, kartu dompet dengan icon/color custom,
  toggle "Tampilkan yang diarsipkan", form transfer dengan error inline, layar konfirmasi transfer.

---

## Kriteria Selesai (acceptance)

- [ ] Semua layar Dompet (daftar, detail via kartu, tambah/edit, transfer) konsisten memakai komponen
  `Wallet/*` & token tema yang sudah ada (fondasi ini sudah selesai di commit `7111100`, tidak perlu
  dirombak — cukup pastikan §1–§3 tidak merusaknya).
- [ ] Icon & warna custom dompet tampil di kartu dompet, bukan cuma di database (§1).
- [ ] Dompet arsip bisa dilihat (badge "Diarsipkan") & diaktifkan lagi dari UI, label tombol dinamis (§2).
- [ ] Form transfer: validasi inline mencegah submit invalid (sumber=tujuan, jumlah≤0), ada langkah
  ringkasan konfirmasi sebelum kirim final, aksesibel (§3).
- [ ] Theming: 4 opsi (`blue`/`green`/`dark`/`system`) berfungsi, preferensi tersimpan per-user, `system`
  mengikuti OS secara berkelanjutan (bukan cuma sekali di awal) (§4).
- [ ] Tidak ada FOUC mencolok untuk user login dengan tema eksplisit tersimpan (§5).
- [ ] Multi-mata uang: didokumentasikan sebagai tidak applicable untuk `wallet_transfer` dengan alasan
  jelas, tidak ada UI kurs palsu yang dibangun (§6).
- [ ] `phpstan`, `pint`, `php artisan test` hijau, dicantumkan eksplisit di PR (§7.1).
- [ ] Keputusan cakupan testing frontend dicatat eksplisit di PR, bukan silently skipped (§7.2).
- [ ] Dokumentasi theming-guide & README diperbarui (§7.3).

---

## 8. REVISI — Branch yang di-checkout sekarang kosong dari kode kerja (WAJIB dibaca duluan)

### Temuan (diverifikasi langsung via `git log`/`git diff`/`git show`, bukan asumsi)

Branch yang **sedang di-checkout saat arahan CEO ini diterima** adalah
`feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer-lanjutkan-branch-yang-sama` (HEAD
`7567fd1`, sudah sinkron dengan `origin/` branch yang sama). Ini **bukan** salah satu dari 2 branch
yang dianalisis di §0 (yang tanpa "dan" HEAD `7111100`, dan yang dengan "dan" HEAD `77b905e`) — ini
branch **ketiga** yang dibuat setelah spec §0 ditulis.

Isi riil branch ini di atas base `91a34cc` cuma 2 commit:
- `025e93c` — **hanya** menambah file `docs/spec-lanjutkan-...-lanjutkan-branch-yang-sama.md` (spec
  ini sendiri, 400 baris). **Tidak ada** migration, tidak ada perubahan PHP/Vue apapun, walau pesan
  commit-nya "database migration".
- `7567fd1` — cuma restore `tests/Feature/.gitkeep` & `tests/Unit/.gitkeep` (0 byte, sesuai catatan
  CEO soal kepemilikan `athena` — ini sudah benar dan terverifikasi: `ls -la` menunjukkan owner
  `athena:athena`). Tidak ada file test lain.

Working tree tests saat ini **hanya**: `tests/TestCase.php` (bawaan Laravel), `tests/Unit/ExampleTest.php`
(bawaan Laravel), dan 2 `.gitkeep`. **Tidak ada** `tests/Concerns/CreatesAppUser.php`,
`AccountThemeTest.php`, `WalletArchiveTest.php`, `WalletTransferTest.php`,
`WalletServiceReverseTransferTest.php`, `DompetTransactionHistoryTest.php` — semua yang disebut §7.1
sebagai "test existing yang harus tetap hijau" **tidak eksis di branch ini**. Begitu juga kode
`useTheme.js` di branch ini masih versi lama (whitelist 3 tema, tanpa `'system'`, tanpa
`resolveSharedTheme()`/listener OS), `WalletService.php` masih versi lama tanpa `reverseTransfer()`,
dan tidak ada migration `add_theme_to_user_profiles` / `add_icon_and_color_to_user_wallets`. Semua
temuan §1–§6 di atas (bug icon/color, arsip hilang, UX transfer, tema system, FOUC) **valid secara
konsep**, tapi kode yang menjadi rujukan baris/fungsi di §1–§6 itu **belum ada di branch ini** —
kode itu hanya ada di `feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer` (tanpa "dan",
tanpa suffix "lanjutkan-branch-yang-sama"), commit `a33950a`/`9baa811`/`7111100`.

### Kenapa ini penting

CEO eksplisit bilang **"jangan membuat branch baru"** dan "lanjutkan pekerjaan pada branch yang
sama". Branch yang sedang di-checkout sekarang **sudah** bernama sesuai task ini — jadi "branch yang
sama" yang dimaksud CEO, secara harfiah, adalah branch ini. Konsekuensinya: Database/Backend/Frontend
AI **tidak boleh** membuat branch baru lagi untuk menyelesaikan §1–§7 (itu akan jadi branch keempat
dengan masalah yang sama). Tapi kalau langsung mulai coding §1–§7 di branch ini apa adanya, mereka akan
mengulang dari nol seluruh fondasi (migration, `WalletService::transferBetweenWallets`/`reverseTransfer`,
`useTheme.js`, komponen `Wallet/*`, test suite) yang **sudah selesai dikerjakan** di commit
`a33950a`/`9baa811`/`7111100` pada branch sebelah.

### Todo Teknis — konsolidasi branch (WAJIB, lakukan sebelum todo §1–§7)

- [ ] `git fetch origin` dulu untuk pastikan tidak ada commit baru di kedua branch sejak snapshot ini
  (`7567fd1` untuk branch aktif, `7111100` untuk branch sumber kerja nyata).
- [ ] Di branch aktif (`feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer-lanjutkan-branch-yang-sama`,
  **jangan checkout branch lain**), gabungkan 3 commit kerja nyata dari
  `feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer` (`a33950a`, `9baa811`, `7111100`) ke
  branch aktif — pakai `git merge feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer`
  (merge biasa, bukan rebase, supaya history 3 commit itu tetap utuh dan tidak menulis ulang commit
  yang mungkin sudah didorong ke remote). Ini **bukan branch baru** — hasil akhirnya tetap branch yang
  sama, cuma isinya bertambah.
- [ ] Selesaikan konflik kalau ada (kemungkinan besar tidak ada konflik berarti karena branch aktif
  cuma menambah 1 file dokumentasi + 2 `.gitkeep`, yang tidak overlap dengan perubahan kode branch
  sumber — tapi **cek ulang** `docs/theming-guide.md`, karena kedua sisi sama-sama menyentuh file itu:
  branch sumber (`7111100`) mengedit isinya, branch aktif tidak menyentuhnya sama sekali, jadi harus
  aman fast-path, tapi verifikasi manual tetap wajib).
- [ ] Setelah merge, jalankan ulang verifikasi §7.1 (`phpstan`, `pint`, `php artisan test`) di branch
  gabungan untuk pastikan semua test lama (`AccountThemeTest`, dst.) benar-benar hijau di branch ini,
  baru lanjut ke todo §1–§6 (yang baru, belum pernah dikerjakan di branch manapun).
- [ ] Setelah §1–§6 selesai di branch gabungan ini, `git push` ke
  `origin/feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer-lanjutkan-branch-yang-sama`
  (branch yang sama, sesuai arahan CEO), lalu buka/update PR **dari branch ini** ke `main`.

### Kontrak
Tidak ada endpoint/tabel/kolom baru di §8 — ini murni operasi git (merge branch), dieksekusi oleh
Backend AI atau Database AI di awal sesi kerja mereka, sebelum menyentuh kode apapun untuk §1–§7.

---

## 9. REVISI 2026-07-14 (iterasi ke-3) — Konsolidasi §8 SELESAI, verifikasi ulang §1–§6 langsung di kode

CEO mengirim ulang brief yang isinya sama persis dengan task ini (redesign UI Dompet, theming, wallet
transfer, branch yang sama). PM iterasi ini **tidak berasumsi §8 sudah dikerjakan** — dicek ulang langsung
lewat `git log`/`git branch` dan pembacaan file kode nyata (bukan cuma commit message) di working tree
saat ini. Hasilnya: **§8 sudah selesai**, tapi verifikasi baris-per-baris terhadap §1, §2, §4 menemukan
gap yang **berbeda** dari yang didiagnosis §1/§2/§4 di atas — sebagian sudah fixed di sisi backend, tapi
ada gap baru di sisi **frontend** yang belum pernah ditulis jadi Todo eksplisit. §3, §5, §6, §7 di atas
tetap valid apa adanya, tidak perlu dibaca ulang.

### 9.0 Konfirmasi status §8 (konsolidasi branch)
- `git log --oneline -5` di branch aktif (`feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer-lanjutkan-branch-yang-sama`)
  menunjukkan HEAD `b5e90c3` = `merge: gabungkan progress dari branch feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer`.
  Ini persis merge yang diminta §8. **Sudah selesai, jangan diulang, jangan merge lagi.**
- Dikonfirmasi lewat isi kode nyata (bukan cuma pesan commit): `app/Services/WalletService.php` sudah
  punya `transferBetweenWallets()` dan `reverseTransfer()`; migration `2026_07_14_000001_add_theme_to_user_profiles_table.php`
  dan `2026_07_14_000002_add_icon_and_color_to_user_wallets_table.php` sudah ada; test suite
  `tests/Feature/{AccountThemeTest,DompetTransactionHistoryTest,WalletArchiveTest,WalletTransferTest}.php`
  sudah ada dan berisi test nyata (bukan `.gitkeep`).
- **Todo turunan**: Backend/Frontend AI **tidak perlu** mengerjakan Todo konsolidasi §8 lagi. Langsung ke
  §9.1–§9.4 di bawah (menggantikan sebagian Todo §1, §2, §4 — baca catatan "Koreksi" di tiap bagian).

### 9.1 KOREKSI §1 — Backend sudah fix, tapi `CardDompet.vue` tidak pernah merender `icon`/`color`

**Temuan (dicek langsung di kode, bukan diasumsikan)**: `TransactionController@index`
(`app/Http/Controllers/App/TransactionController.php` baris 80-81) **sudah** mengirim `'icon' => $w->icon,
'color' => $w->color,` di prop `wallets[]` — Todo §1 versi lama **sudah selesai**, jangan dikerjakan ulang.

Tapi `resources/js/Components/Wallet/CardDompet.vue` (komponen yang benar-benar merender kartu dompet di
`Dompet.vue`) **tidak pernah membaca** `wallet.icon`/`wallet.color` sama sekali — baris 4:
`<div class="wallet-logo" :style="`background:${wallet.bank_color}`">` lalu `<img v-if="wallet.logo_url">`
atau fallback `<span v-else>{{ wallet.bank_initial }}</span>`. Tidak ada cabang untuk `wallet.icon`
(emoji custom) atau `wallet.color` (token warna custom) sama sekali. Akibat: user pilih icon+warna custom
saat tambah dompet (kalau field itu ada di form — lihat §9.2, **field itu sendiri belum ada**), data
tersimpan di DB, terkirim ke frontend, tapi kartu dompet tetap selalu tampil `bank_initial`/`bank_color`.
Root cause **berbeda** dari diagnosis §1 lama (dulu dikira bug backend, ternyata backend sudah benar,
gap-nya di komponen kartu dompet frontend).

### Todo Teknis (menggantikan Todo §1 lama — Todo §1 lama sudah selesai, jangan dikerjakan ulang)
- [ ] `CardDompet.vue`: ubah `.wallet-logo` supaya prioritas render: (1) `wallet.icon` kalau ada — render
  sebagai emoji besar (`<span class="wallet-logo-emoji">{{ wallet.icon }}</span>`, font-size ~22px, mirip
  pola `.emoji-current` di `EmojiPicker.vue`), background pakai token warna dari `wallet.color` (map ke CSS
  var, lihat kontrak di bawah) — **bukan** style inline hex; (2) kalau `wallet.icon` kosong, fallback ke
  logic lama (`logo_url` → `bank_initial`/`bank_color`, tidak berubah).
- [ ] Style `.wallet-logo` background: ganti dari `:style="background:${wallet.bank_color}"` (inline hex
  dari data bank) jadi kondisional — untuk kasus `wallet.color` (custom, salah satu dari 5 token warna
  semantik `primary|success|danger|warning|info`), pakai `:style="wallet.color ? { background: \`var(--${wallet.color})\` } : { background: wallet.bank_color }"`.
  Ini menyelaraskan dengan arahan CEO "hindari inline style/hardcode warna" untuk kasus yang punya token
  (`wallet.color`), sementara `bank_color` (warna brand bank pihak ketiga, bukan token desain) tetap boleh
  dinamis karena itu bukan bagian dari sistem tema — bukan pelanggaran token, itu data warna brand eksternal.

### Kontrak API — TIDAK ADA perubahan endpoint (murni konsumsi field yang sudah dikirim)
**Endpoint**: GET `/dompet` (`dompet.index`) — tidak berubah, `wallets[].icon`/`wallets[].color` sudah ada
di response sejak commit `9baa811` (dibawa masuk lewat merge §8).
**Database**: tidak ada perubahan.
**Validasi**: tidak ada perubahan.

---

### 9.2 KOREKSI §1 (lanjutan) — BARU: form tambah/edit dompet tidak punya field icon/color sama sekali

**Temuan**: `StoreWalletRequest`/`UpdateWalletRequest` sudah menerima `icon` (`nullable|max:10`) dan
`color` (`nullable|in:primary,success,danger,warning,info`) — backend siap sepenuhnya. Tapi modal
"Tambah/Edit Dompet" di `Dompet.vue` (baris 250-287) **tidak punya field UI apapun** untuk `icon`/`color` —
`walletForm = useForm({...})` (baris 639) tidak menyertakan key `icon`/`color` sama sekali. User **tidak
bisa** memilih icon/warna custom dari UI manapun saat ini — gap ini tidak pernah disebut eksplisit di §1
lama (§1 lama fokus ke "data tersimpan tapi tidak tampil", padahal kenyataannya user bahkan belum bisa
mengisi data itu dari awal).

### Todo Teknis (BARU, murni frontend)
- [ ] `walletForm` (`Dompet.vue`): tambah key `icon: ''` dan `color: ''` (atau `null`) ke `useForm({...})`,
  sertakan di payload `submitWallet()` (POST `wallets.store`/PUT `wallets.update`, endpoint tidak berubah).
- [ ] Modal Tambah/Edit Dompet: tambah 2 field baru di form (di bawah field "Fungsi", sebelum tombol
  submit): (1) `<EmojiPicker v-model="walletForm.icon" />` (reuse komponen yang sudah ada, dipakai persis
  sama seperti di form Tagihan baris 344, tidak perlu komponen baru); (2) selector warna — 5 swatch bulat
  (`primary`/`success`/`danger`/`warning`/`info`), tiap swatch pakai `background: var(--{token})`,
  `aria-label` nama warna dalam Bahasa Indonesia (mis. "Biru", "Hijau", "Merah", "Kuning", "Cyan" — sesuaikan
  ke palet token aktual di `theme-blue.css`), state terpilih pakai `border`/`box-shadow: var(--shadow-focus)`,
  `role="radiogroup"`/`aria-checked` per swatch (pola aksesibilitas yang sama seperti `themeOptions` di §9.3).
  Saat edit dompet existing, pre-fill `walletForm.icon`/`walletForm.color` dari `editingWallet.icon`/`.color`.
- [ ] Saat `editingWallet` di-set (fungsi buka modal edit, cari di `<script setup>` sekitar variabel
  `editingWallet`), pastikan `walletForm.icon`/`walletForm.color` di-assign dari data wallet yang diklik
  (`openEditWallet` handler), bukan cuma field yang sudah ada (`display_name`, `type`, dst.).

### Kontrak API — TIDAK ADA endpoint baru (field sudah diterima backend, cuma belum dikirim dari form)
**Endpoint**: POST `/dompet/wallets` (`wallets.store`), PUT `/dompet/wallets/{wallet}` (`wallets.update`)
— tidak berubah.
**Request** (field `icon`/`color` sudah ada di validasi, restated untuk kejelasan Frontend AI):
```
{
  ...existing (bank_id?, display_name, account_number?, initial_balance?, type, is_saham?)...,
  icon?: string,   // max 10 char, emoji tunggal — sudah divalidasi backend, form belum mengirimnya
  color?: 'primary' | 'success' | 'danger' | 'warning' | 'info'   // sudah divalidasi backend, form belum mengirimnya
}
```
**Database**: tidak ada kolom baru — `user_wallets.icon`/`.color` sudah ada.
**Validasi**: tidak berubah (`StoreWalletRequest`/`UpdateWalletRequest` sudah benar).

---

### 9.3 KOREKSI §4 — Bukan "tambah 1 opsi ke `themeOptions`", tapi bangun UI pemilih tema dari nol

**Temuan (koreksi signifikan atas asumsi §4 lama)**: §4 lama menulis Todo "`Account.vue`: tambah 1 opsi ke
`themeOptions`" — asumsi ini **salah**. Dicek langsung: `Account.vue` **tidak punya `themeOptions` sama
sekali**, dan tidak ada elemen UI apapun (`grep` untuk `setTheme`/`useTheme`/`account.theme`/"Tema" di
semua file `resources/js/Pages/App/*.vue` dan `resources/js/Layouts/*.vue` kosong total). Fakta di lapangan:
- Backend **sudah lengkap**: route `PUT /account/theme` (`account.theme`) →
  `AccountController::updateTheme()` → `UserProfile::updateOrCreate(['theme' => $request->theme])`.
  `UpdateThemeRequest` **sudah** whitelist `Rule::in(['blue', 'green', 'dark', 'system'])` (4 nilai, sudah
  termasuk `system` — bukan cuma 3 seperti asumsi §4 lama).
  `HandleInertiaRequests::share()` (baris 32) **sudah** mengirim shared prop
  `'theme' => $request->user()?->profile?->theme` ke setiap halaman Inertia.
- Frontend **belum ada sama sekali**: tidak ada tombol/radio/dropdown di halaman manapun yang memanggil
  `useTheme().setTheme()` atau submit ke route `account.theme`. `useTheme.js` (`resources/js/Composables/useTheme.js`)
  cuma dipanggil sekali (`initTheme()` di `resources/js/app.js` baris 9) dan **resolusinya sama sekali
  tidak membaca** shared prop Inertia `theme` dari server (baca urutan cuma: `?theme=` URL param →
  `localStorage.monexa_theme` → `import.meta.env.VITE_DEFAULT_THEME` → default `'blue'` — prop server
  `theme` yang sudah di-share `HandleInertiaRequests` **tidak pernah dipakai** di client). `VALID_THEMES`
  masih `['blue', 'green', 'dark']`, belum ada `'system'`.

Konsekuensi praktis: preferensi tema per-user yang tersimpan di DB (`user_profiles.theme`) **tidak pernah
bisa diisi** (tidak ada UI submit) dan **tidak pernah dibaca balik** oleh client di sesi/device baru (cuma
`localStorage` device itu sendiri yang dipakai) — kolom `theme` di DB saat ini murni dekoratif, cuma dipakai
`app.blade.php` untuk render awal `data-theme` di server (§5, itu pun nilainya akan selalu `null`/default
karena tidak pernah ada yang menulis ke sana).

### Todo Teknis (menggantikan seluruh Todo §4 lama — lebih besar dari yang tertulis di sana)
- [ ] `useTheme.js`: tambah `'system'` ke `VALID_THEMES` (4 nilai: `blue`, `green`, `dark`, `system`).
- [ ] `useTheme.js`: fungsi baru `resolveSystemTheme()` → `'dark'` kalau
  `matchMedia('(prefers-color-scheme: dark)').matches`, else `'blue'`. `applyTheme(name)`: kalau
  `name === 'system'`, set `dataset.theme` ke hasil `resolveSystemTheme()` (bukan literal `'system'`),
  tapi `currentTheme.value`/nilai yang disimpan tetap `'system'` supaya UI picker tetap ter-highlight benar.
- [ ] `useTheme.js`: tambah listener `matchMedia('(prefers-color-scheme: dark)').addEventListener('change', ...)`
  yang re-apply tema tanpa reload **hanya kalau** preferensi aktif adalah `'system'`; lepas listener kalau
  user pindah ke tema eksplisit lain.
- [ ] `useTheme.js` `resolveInitialTheme()`: tambah 1 prioritas baru **di atas** `localStorage` — baca
  shared prop Inertia `theme` (`usePage().props.theme`) kalau user login dan nilainya valid (salah satu
  dari `VALID_THEMES`). Urutan prioritas baru: `?theme=` URL param (override manual/debug) → **prop server
  `theme` (preferensi tersimpan per-user, BARU)** → `localStorage.monexa_theme` (fallback guest/belum
  login) → `import.meta.env.VITE_DEFAULT_THEME` → default `'blue'`. Ini **wajib** supaya tema tersimpan di
  DB benar-benar berefek lintas device/browser, bukan cuma `localStorage`.
- [ ] `useTheme.js`: `setTheme(name)` — setelah `applyTheme` + simpan `localStorage` (behaviour lama tetap
  ada, untuk guest/fallback cepat), **tambah** pemanggilan Inertia `router.put(route('account.theme'), { theme: name }, { preserveScroll: true, preserveState: true })` kalau user sedang login (cek lewat
  `usePage().props.auth?.user`), supaya preferensi persist ke server. Kalau gagal (network error), tetap
  biarkan `localStorage`/state client jalan (server persist adalah best-effort, tidak boleh blocking UX
  ganti tema).
- [ ] **BARU** — bangun UI pemilih tema di `Account.vue` (belum ada sama sekali, bukan cuma nambah 1
  opsi): section baru "Tampilan" dengan `role="radiogroup"` `aria-label="Pilih tema aplikasi"`, 4 opsi
  (`blue` label "Biru", `green` label "Hijau", `dark` label "Gelap", `system` label "Ikuti Sistem"), tiap
  opsi tombol dengan swatch preview warna (`system` pakai gradient split biru/gelap sebagai indikator),
  `role="radio"` + `aria-checked="{{ currentTheme === opt.value }}"` per opsi, `@click="setTheme(opt.value)"`
  dari composable `useTheme()` (baris `const { currentTheme, setTheme } = useTheme()` di `<script setup>`).
  Styling pakai token existing (`var(--surface)`, `var(--border)`, `var(--radius-md)`, `var(--shadow-focus)`
  untuk focus-visible) — konsisten dengan pola komponen lain di file yang sama, tidak bikin class CSS baru
  yang tidak pakai variable tema.
- [ ] `app.blade.php` §5 (sudah benar secara kode, tidak perlu diubah) otomatis mulai berfungsi penuh
  begitu Todo di atas selesai — karena baru saat itu `user_profiles.theme` benar-benar terisi nilai non-null
  dari alur nyata.

### Kontrak API — extend `PUT /account/theme` (endpoint sama; whitelist backend SUDAH `system`, tidak perlu diubah)
**Endpoint**: PUT `/account/theme` (`account.theme`) — **tidak ada perubahan backend**, restated untuk
kejelasan Frontend AI karena ini pertama kalinya endpoint ini benar-benar dipanggil dari UI:
**Request**: `{ theme: 'blue' | 'green' | 'dark' | 'system' }` — validasi sudah benar di `UpdateThemeRequest`.
**Response**: redirect `back()` (pola Inertia existing).
**Database**: tidak ada kolom baru, `user_profiles.theme` sudah cukup.
**Validasi**: tidak berubah, `Rule::in(['blue','green','dark','system'])` sudah benar di kode saat ini.
**Shared prop** (dipakai baru oleh Todo di atas, sudah ada di backend, tidak perlu diubah):
`HandleInertiaRequests::share()` → `'theme' => $request->user()?->profile?->theme` — field ini tersedia di
`usePage().props.theme` pada semua halaman Inertia untuk user yang login.

---

### 9.4 KOREKSI §2 — Backend sudah fix, tapi TIDAK ADA konsumsi frontend sama sekali (bukan cuma "UI toggle")

**Temuan**: `TransactionController@index` **sudah** mengimplementasikan persis seperti kontrak §2 lama:
param `show_archived` (baris 88, `$request->boolean('show_archived')`), query dompet `is_active=false`,
prop `archived_wallets` (baris 125) dengan shape yang sama seperti `wallets[]`. Todo backend §2 **sudah
selesai**, jangan dikerjakan ulang.

Tapi `Dompet.vue` **tidak menyentuh prop `archived_wallets` sama sekali** (`grep` untuk `archived_wallets`,
`show_archived`, "Diarsipkan", "Aktifkan", "Arsipkan" di file itu — nol hasil). Tidak ada toggle "Tampilkan
yang diarsipkan", tidak ada rendering wallet arsip, dan **tidak ada tombol arsip/aktifkan di kartu dompet
manapun** — `CardDompet.vue` punya slot `actions` (baris 23-25, `<slot name="actions" v-if="$slots.actions">`)
tapi `Dompet.vue` memanggil `<CardDompet>` (baris 151-157) **tanpa** mengisi slot itu sama sekali — tidak
ada cara apapun dari UI untuk mengarsipkan dompet, apalagi melihat yang sudah diarsip. Ini gap yang jauh
lebih besar dari yang ditulis §2 lama (§2 lama cuma bilang "label tombol statis 'Arsipkan'" — kenyataannya
tombol arsip **tidak ada sama sekali** di UI).

### Todo Teknis (menggantikan Todo frontend §2 lama — lebih besar dari yang tertulis di sana)
- [ ] `Dompet.vue` tab "Dompet": tambah toggle checkbox/switch "Tampilkan yang diarsipkan" di atas
  `.wallet-grid` (baris ~150), `v-model` ke `ref` lokal baru `showArchived`, `@change` trigger
  `router.reload({ data: { show_archived: showArchived ? 1 : undefined }, only: ['archived_wallets'], preserveScroll: true })`.
- [ ] Render `archived_wallets` (props sudah dikirim backend) di bawah grid dompet aktif, `v-if="showArchived && archived_wallets.length"`,
  pakai `<CardDompet>` yang sama dengan badge kecil "Diarsipkan" (mis. `<span class="badge-archived">Diarsipkan</span>`
  di pojok kartu, pakai token `var(--text-faint)`/`var(--border)`, bukan warna baru).
- [ ] `CardDompet.vue`: isi slot `actions` dari `Dompet.vue` untuk **setiap** kartu (aktif maupun arsip) —
  tombol aksi cepat: "Edit" (`@click="openEditWallet(w)"`, sudah ada handler-nya via `@click` di kartu,
  pindahkan/duplikasi ke tombol eksplisit di slot actions supaya tidak cuma bisa diakses lewat klik seluruh
  kartu), dan tombol arsip/aktifkan dengan **label dinamis**: `{{ w.is_active ? '📦 Arsipkan' : '✅ Aktifkan' }}`,
  `@click="toggleArchive(w)"` (fungsi baru, panggil `router.patch(route('wallets.archive', w.id), {}, { preserveScroll: true })`,
  endpoint sudah ada dan sudah 2 arah/toggle, tidak perlu perubahan backend).
- [ ] Pastikan wallet arsip **tidak muncul** di dropdown pemilihan dompet manapun (form transaksi, form
  transfer, form bayar tagihan) — ini sudah otomatis benar karena dropdown itu semua pakai props `wallets`
  (bukan `archived_wallets`), backend sudah memisahkan keduanya (§2 lama), tidak perlu perubahan tambahan,
  cukup pastikan tidak ada regresi saat menambah rendering `archived_wallets` (jangan gabung ke array
  `wallets` di client manapun).

### Kontrak API — TIDAK ADA perubahan endpoint (murni konsumsi prop yang sudah dikirim + endpoint archive yang sudah ada)
**Endpoint**: GET `/dompet?show_archived=1` (`dompet.index`, tidak berubah), PATCH
`/dompet/wallets/{wallet}/archive` (`wallets.archive`, tidak berubah, sudah toggle 2 arah).
**Response**: `archived_wallets[]` — shape sudah didefinisikan lengkap di §2 lama, tidak berubah.
**Database**: tidak ada perubahan.
**Validasi**: tidak ada perubahan.

---

### 9.5 Ringkasan prioritas kerja untuk iterasi ini (urutan disarankan)
1. §9.3 (UI pemilih tema) — paling berdampak ke acceptance CEO "theming berfungsi... switching tema
   langsung tercermin", dan saat ini **benar-benar tidak ada** cara user mengganti tema dari UI sama sekali.
2. §9.4 (konsumsi `archived_wallets` + tombol arsip di kartu) — tanpa ini, fitur arsip yang sudah dibangun
   backend sejak iterasi sebelumnya sama sekali tidak bisa diakses user.
3. §9.1 + §9.2 (render icon/color di kartu + field picker di form tambah/edit dompet) — pasangan gap yang
   saling melengkapi, kerjakan bersamaan supaya bisa diverifikasi end-to-end dalam 1 smoke test manual
   (buat dompet dengan icon+warna custom → langsung tampil di kartu).
4. §3 lama (validasi inline + konfirmasi 2-tahap form transfer) — dikonfirmasi ulang **masih** sepenuhnya
   belum dikerjakan (tidak ada `showTransferConfirm`/`transferErrors`/`isTransferFormValid` di `Dompet.vue`),
   Todo & kontrak di §3 lama tetap berlaku apa adanya, kerjakan setelah 1–3 di atas.
5. §7.1 (jalankan ulang `phpstan`/`pint`/`php artisan test`) setelah semua di atas selesai, extend §7.1
   dengan smoke-test manual tambahan: ganti tema dari `Account.vue` lalu **reload halaman** → tema tetap
   sama (baru bisa diverifikasi setelah §9.3 selesai, sebelumnya mustahil di-test karena UI-nya belum ada).

### Kriteria Selesai tambahan (melengkapi bagian "Kriteria Selesai (acceptance)" di atas)
- [ ] User bisa mengganti tema dari halaman Akun (4 opsi: Biru/Hijau/Gelap/Ikuti Sistem), tersimpan ke DB,
  dan termuat kembali dengan benar di reload/device lain (bukan cuma `localStorage`) (§9.3).
- [ ] Dompet dengan icon+warna custom tampil sesuai di kartu, bisa diisi dari form tambah/edit dompet
  (§9.1, §9.2).
- [ ] Dompet arsip bisa dilihat & diaktifkan lagi dari UI nyata (toggle + tombol di kartu, bukan cuma
  tersedia di response API) (§9.4).

---

## 10. REVISI 2026-07-15 (iterasi ke-4) — Brief CEO lebih detail: fee, idempotensi, audit/telemetri,
    theming komponen dasar, i18n, 3 PR terpisah

CEO mengirim ulang brief untuk task yang sama, kali ini jauh lebih rinci (fee/biaya transfer, idempotensi
request key, audit log minimal, event telemetri, dokumentasi token tema, testing policy/idempotensi, i18n,
deliverable 3 PR terpisah). PM iterasi ini **tidak berasumsi §1–§9 sudah beres** — diverifikasi ulang
langsung ke kode & git log sebelum menulis bagian baru ini.

### 10.0 Verifikasi status §1–§9 (dicek langsung, bukan asumsi)

`git log --oneline -5` di branch aktif menunjukkan HEAD `74d6bdc` ("fix: add generic type annotations for
Model relations to satisfy PHPStan") di atas `b110180`/`ad19b2a` ("frontend") dan `e792ba8` ("database
migration"), di atas `b5e90c3` (merge konsolidasi §8). Dicek langsung isi kode (bukan cuma pesan commit):

- **Selesai, jangan dikerjakan ulang**: `CardDompet.vue` sudah merender `wallet.icon`/`wallet.color` dengan
  prioritas benar (§9.1); form tambah/edit dompet di `Dompet.vue` sudah punya field icon (EmojiPicker) +
  color swatch (§9.2); `Account.vue` sudah punya UI pemilih tema 4 opsi (`blue`/`green`/`dark`/`system`)
  dengan `role="radiogroup"`, dan `useTheme.js` sudah membaca shared prop `theme` dari server + listener
  OS untuk `system` (§9.3); `Dompet.vue` sudah mengonsumsi `archived_wallets` dengan toggle "Tampilkan yang
  diarsipkan" + tombol Arsipkan/Aktifkan dinamis di slot `actions` `CardDompet.vue` (§9.4); form transfer
  di `Dompet.vue` sudah punya `showTransferConfirm`, `transferErrors`, `isTransferFormValid`, langkah
  ringkasan konfirmasi 2-tahap, dan atribut ARIA (`aria-invalid`/`aria-describedby`/`role="alert"`) (§3).
  `WalletTransfer::fromWallet()`/`toWallet()` sudah bertipe generik `BelongsTo<UserWallet, $this>` — item
  PHPStan yang disebut CEO di catatan brief ini sudah tuntas di commit `74d6bdc`.
- **Genuinely belum ada** (dicek via `grep`/`find`, bukan diasumsikan) — ini yang §10 di bawah tutup:
  kolom `fee`/`request_id` di `wallet_transfers` tidak ada (`database/migrations/2025_01_01_000011_create_wallet_transfers_table.php`
  cuma punya `amount`, `note`, `transferred_at`); `TransferWalletRequest` tidak punya rule `fee`/`request_id`;
  `WalletService::transferBetweenWallets()` (app/Services/WalletService.php baris 96-130) tidak punya
  parameter `fee`; tidak ada satupun direktori `app/Events`/`app/Listeners`, tidak ada `EventServiceProvider`
  (Laravel 13 di app ini pakai auto-discovery event, jadi listener baru otomatis terdaftar cukup dengan
  method `handle()` bertipe-hint event, tanpa perlu registrasi manual); tidak ada `lang/`/`resources/lang`
  sama sekali (i18n backend maupun `vue-i18n` frontend, nol infra); tidak ada `Button.vue`/`Input.vue`/
  `Select.vue`/`Checkbox.vue`/`Radio.vue`/`Modal.vue`/`Drawer.vue`/`Tabs.vue`/`Alert.vue`/`Toast.vue` di
  `resources/js/Components` — semua halaman pakai elemen HTML native + class CSS ad-hoc per file, tidak ada
  library komponen dasar bersama sama sekali.

### 10.1 BARU — Fee/biaya transfer + idempotensi (`request_id`) di `wallet_transfer`

**Keputusan default (sesuai Open Question CEO, dipakai karena tidak ada respons owner lain)**: fee dipotong
dari dompet **sumber**, di luar `amount` yang diterima dompet tujuan (dompet tujuan selalu menerima persis
`amount`, dompet sumber berkurang `amount + fee`). Fee bersifat opsional (default 0) — merepresentasikan
biaya admin transfer bank riil yang ingin dicatat user, **bukan** komisi platform (Monexa tidak fungsi
sebagai pihak ketiga penerima fee), sehingga fee dicatat sebagai debit tunggal tanpa pasangan kredit di
dompet manapun (uang "keluar sistem", sama pola dengan pengeluaran/expense biasa).

**Database** (migration baru — dibutuhkan nyata, bukan sekadar nice-to-have, karena fee & idempotency key
tidak bisa direpresentasikan di kolom yang sudah ada):
```
Migration: 2026_07_15_000001_add_fee_and_request_id_to_wallet_transfers_table.php
Tabel: wallet_transfers
Kolom baru:
  fee         DECIMAL(15,2) NOT NULL DEFAULT 0
  request_id  VARCHAR(64)   NULL
Index baru: UNIQUE (user_id, request_id)  -- composite, bukan unique global, karena request_id
            digenerate client per sesi form (UUID v4), hanya perlu unik per user untuk mencegah
            duplikasi submit dari user yang sama; NULL diperbolehkan (MySQL: multiple NULL boleh
            di unique index) untuk baris lama pre-migration.
```
Tidak perlu kolom `rate` (lihat §10.4 — transfer selalu 1 mata uang per user, kurs tidak pernah relevan).

**`app/Services/WalletService.php` — `transferBetweenWallets()` (ubah signature, baris ~96)**:
```
transferBetweenWallets(UserWallet $fromWallet, UserWallet $toWallet, float $amount, string $transferId, float $fee = 0): void
```
- Cek saldo: `$fromWallet->balance >= $amount + $fee` (sebelumnya cuma `>= $amount`), pesan
  `InsufficientBalanceException` sebutkan breakdown ("...butuh Rp {amount} + biaya Rp {fee} = Rp {total}").
- Insert 2 baris `wallet_balance_logs` yang sudah ada (`reference_type='wallet_transfer'`) tidak berubah.
- **Baru**: kalau `$fee > 0`, insert 1 baris tambahan: `wallet_id=$fromWallet->id, type='debit', amount=$fee,
  balance_before=(saldo setelah baris debit amount di atas), balance_after=balance_before-$fee,
  reference_type='wallet_transfer_fee', reference_id=$transferId, created_at=now()`.
- `$fromWallet->decrement('balance', $amount + $fee)` (gabungkan jadi 1 statement, bukan 2 decrement
  terpisah, supaya hanya 1 query update per wallet — jaga pola atomic existing).
- `reverseTransfer()` (baris ~152): kalau transfer yang dibatalkan punya `fee > 0`, kembalikan juga fee ke
  `fromWallet` (baris balance-log tambahan `reference_type='wallet_transfer_reversal'`, amount=fee) — supaya
  simetris dengan `transferBetweenWallets`. **Tidak** perlu menyentuh `toWallet` untuk fee (fee tidak pernah
  masuk ke `toWallet`).

**`app/Http/Controllers/App/WalletController.php::transfer()` (baris 118-160) — tambah idempotensi**:
Sebelum blok `DB::transaction(...)` (baris 135): cek dulu
`WalletTransfer::where('user_id', $user->id)->where('request_id', $request->request_id)->first()`. Kalau
ketemu (retry/double-submit dari request_id yang sama): **jangan** eksekusi ulang apapun (tidak insert
balance log baru, tidak ubah saldo) — langsung `return back()->with('success', ...)` dengan pesan sukses
yang sama seperti transfer aslinya (pakai data `$existing->amount`/`fromWallet`/`toWallet` dari record lama).
Kalau tidak ketemu, lanjut proses seperti biasa, sertakan `'fee' => $request->fee ?? 0` dan
`'request_id' => $request->request_id` saat `WalletTransfer::create()` (baris ~139), dan pass `$request->fee
?? 0` sebagai argumen ke-5 `transferBetweenWallets()` (baris ~148).

**Kontrak API — extend `POST /dompet/transfer` (`wallets.transfer`, endpoint sama, field baru)**

**Request**:
```
{
  from_wallet_id: string,     // tidak berubah
  to_wallet_id: string,       // tidak berubah
  amount: number,             // tidak berubah
  fee?: number,                // BARU, default 0 kalau tidak dikirim
  note: string | null,        // tidak berubah
  transferred_at: string,     // tidak berubah
  request_id: string          // BARU, wajib — UUID v4 digenerate client (crypto.randomUUID()) sekali saat
                               // modal transfer dibuka, dikirim apa adanya tiap retry submit yang sama
                               // (regenerate hanya kalau modal ditutup lalu dibuka lagi / transfer baru)
}
```

**Response**: tidak berubah (redirect `back()` + flash `success`/`error`, pola Inertia existing). Untuk
request_id yang sudah pernah sukses: response identik dengan response sukses aslinya (idempotent replay),
**bukan** error — user tidak boleh melihat error kalau cuma retry jaringan pada transfer yang sebenarnya
sudah berhasil.

**Database**: migration §10.1 di atas (`fee`, `request_id` di `wallet_transfers`), plus baris
`wallet_balance_logs` baru `reference_type='wallet_transfer_fee'` (hanya kalau `fee > 0`).

**Validasi** (`TransferWalletRequest`, tambah rule):
```
fee: ['nullable', 'numeric', 'min:0'],
request_id: ['required', 'string', 'max:64'],
```
Validasi saldo cukup (`amount + fee <= fromWallet.balance`) tetap di level service (`InsufficientBalanceException`),
bukan di `FormRequest` (butuh akses model wallet, pola existing sudah begini untuk cek saldo `amount` saja).

**Frontend (`Dompet.vue`) — field & ringkasan baru, endpoint sama**:
- Tambah input `fee` opsional (default kosong = 0) di form transfer, di bawah field `amount`.
- Generate `request_id` (crypto.randomUUID()) saat modal transfer dibuka (`openTransferModal()`/fungsi
  sejenis), simpan di `transferForm.request_id`, **jangan** regenerate saat validasi gagal/kembali dari
  langkah konfirmasi (harus tetap sama across retries dalam 1 sesi modal, itu esensi idempotensi) — hanya
  regenerate kalau modal ditutup penuh lalu dibuka lagi untuk transfer baru.
- Langkah ringkasan konfirmasi (§3, sudah ada) tambah baris: "Biaya admin: Rp {fee}" (kalau `fee > 0`) dan
  "Total dipotong dari {from_wallet}: Rp {amount+fee}".
- Validasi client `isTransferFormValid`: tambah `fee >= 0` (kalau diisi) ke aturan yang sudah ada.

### 10.2 BARU — Telemetri event `wallet_transfer_initiated` / `_succeeded` / `_failed`

Tidak ada pola event/listener sama sekali di app ini sebelumnya — ini pengenalan pola baru, dijaga seminimal
mungkin (dispatch sinkron, listener nge-log lewat `Log` facade, tidak ada queue/broadcast) supaya tidak
menambah kompleksitas infra di luar yang diminta CEO ("event dengan properti dasar").

**Events baru** (`app/Events/`, plain `Dispatchable` classes, tidak perlu `ShouldQueue`):
```
WalletTransferInitiated(string $userId, string $fromWalletId, string $toWalletId, float $amount, float $fee, string $requestId)
WalletTransferSucceeded(string $userId, string $fromWalletId, string $toWalletId, float $amount, float $fee, string $requestId, string $walletTransferId, int $durationMs)
WalletTransferFailed(string $userId, string $fromWalletId, string $toWalletId, float $amount, float $fee, string $requestId, string $reason, int $durationMs)
```

**Listener baru** (`app/Listeners/LogWalletTransferTelemetry.php`) — 3 method `handleInitiated()`/
`handleSucceeded()`/`handleFailed()` (atau 1 class dengan `handle()` overload per tipe event via union — pola
sederhana: 1 listener class, 3 method publik, tiap method type-hint event yang berbeda, Laravel auto-discovery
mendaftarkan tiap method sebagai listener terpisah lewat naming `handle{EventClass}` **atau** cukup 3 listener
class terpisah kalau auto-discovery App ini butuh 1-method-per-class — Backend AI cek konvensi
auto-discovery Laravel 13 yang dipakai, ambil yang lebih simpel). Isi tiap method: `Log::info('wallet_transfer_initiated', [...properti event...])` (nama event jadi log message literal, properti jadi context array) — dipakai channel log default (`config('logging.default')`), tidak perlu channel baru.

**Dispatch points — `WalletController::transfer()`**:
- `$start = microtime(true);` di awal method, sebelum idempotency check.
- Setelah validasi lolos & idempotency check **tidak** menemukan duplikat (baru akan eksekusi transfer
  baru): `event(new WalletTransferInitiated(...))`.
- Setelah `DB::transaction` sukses: `event(new WalletTransferSucceeded(..., durationMs: (int) ((microtime(true) - $start) * 1000)))`.
- Kalau `InsufficientBalanceException` tertangkap (bungkus `DB::transaction` dengan try/catch, existing
  belum ada try/catch di sini — tambahkan): `event(new WalletTransferFailed(..., reason: $e->getMessage(), durationMs: ...))`
  sebelum `return back()->with('error', ...)` seperti pola `destroyTransfer()` (baris ~168-172) yang sudah
  ada.

**Kontrak**: tidak ada endpoint/tabel baru untuk telemetri ini sendiri — event murni untuk observability
(log terstruktur), tidak mengubah response HTTP maupun skema DB.

### 10.3 Audit/log minimal — sudah tercakup, tidak perlu tabel audit terpisah

Field yang diminta CEO (`user_id, wallet_source_id, wallet_target_id, amount, fee, rate, timestamp,
request_id`) sudah tercakup penuh oleh kombinasi: baris `wallet_transfers` (§10.1, kolom `user_id`,
`from_wallet_id`, `to_wallet_id`, `amount`, `fee`, `request_id`, `transferred_at`/`created_at`) + event log
terstruktur (§10.2). **Tidak perlu tabel `wallet_transfer_audit_logs` baru** — itu duplikasi data yang sudah
ada di 2 tempat itu, menambah kompleksitas tanpa manfaat baru. `rate` sengaja tidak ada kolomnya (lihat
§10.4 — transfer selalu 1 mata uang, kurs tidak pernah relevan secara struktural, kolom yang selalu `null`
adalah dead weight).

### 10.4 Multi-mata uang — keputusan §6 lama tetap berlaku, tidak ada perubahan

Restated dari §6 (masih valid, diverifikasi ulang §10.0 tidak ada kolom `currency` baru di `user_wallets`
sejak spec itu ditulis): mata uang adalah atribut per-user (`user_profiles.currency`), bukan per-dompet,
jadi `wallet_transfer` **tidak pernah** lintas mata uang secara struktural. Jawaban Open Question CEO
("apakah transfer lintas mata uang diperbolehkan?"): **tidak applicable** — bukan soal "diblokir dengan
pesan", tapi skenario itu tidak bisa terjadi sama sekali di model data saat ini (kedua wallet selalu milik
user yang sama, otomatis 1 currency). Tidak ada Todo teknis baru di sini.

### 10.5 I18n — keputusan cakupan (baca dulu sebelum implementasi, jangan retrofit seluruh app)

**Temuan**: nol infra i18n di seluruh codebase (tidak ada `lang/`, tidak ada `vue-i18n` di `package.json`,
semua string Indonesia hardcoded literal di Blade/Vue/PHP). Brief CEO minta "seluruh string UI masuk ke
berkas lokalisasi" — kalau ditafsirkan literal (retrofit semua string di seluruh app, termasuk yang sudah
ada jauh di luar scope Dompet/Theming/Transfer), itu refactor besar berisiko tinggi (ratusan string,
puluhan file) yang **bukan** bagian dari task redesign ini dan berisiko regresi luas. Mengikuti pola
keputusan yang sudah dipakai PM sebelumnya untuk keputusan cakupan serupa (§7.2, soal test runner JS), PM
membuat keputusan cakupan berikut:

- [ ] **Cakupan iterasi ini**: hanya string **baru** yang ditambahkan oleh §10.1 (pesan error saldo tidak
  cukup dengan breakdown fee, pesan validasi `fee`/`request_id`) dipindah ke berkas lokalisasi Laravel baru
  `lang/id/wallet.php` (array asosiatif, mis. `'insufficient_balance_with_fee' => 'Saldo :wallet tidak
  cukup...'`), dipanggil via helper `__('wallet.insufficient_balance_with_fee', ['wallet' => ..., ...])` di
  `WalletService`/`WalletController`. Ini pola native Laravel (bukan infra baru), tidak butuh dependency
  tambahan.
- [ ] String Vue yang sudah ada (label tombol, header, dst.) **tidak** dipindah ke i18n di iterasi ini —
  tetap literal Indonesia seperti sekarang, konsisten dengan seluruh halaman lain yang belum disentuh.
  Menambah `vue-i18n` sebagai dependency baru untuk retrofit penuh frontend adalah **keputusan infra
  terpisah** yang butuh approval eksplisit CEO (sama seperti keputusan §7.2 soal Vitest) — **jangan**
  ditambahkan diam-diam di PR ini.
- [ ] Kalau CEO insist retrofit penuh, itu jadi task/PR terpisah di luar 3 PR deliverable §10.8 di bawah.

### 10.6 BARU — Theming: komponen dasar (Button, Input, Select, Checkbox/Radio, Modal/Drawer, Tabs, Alert, Toast)

**Temuan**: dicek `resources/js/Components/` — **tidak ada satupun** komponen generik ini. Yang ada cuma
komponen spesifik-Wallet (`CardDompet.vue`, `FilterDrawer.vue`, dst.) dan tiap halaman menulis `<button>`/
`<input>`/`<select>` native + class CSS ad-hoc masing-masing (mis. form transfer di `Dompet.vue` pakai
`<select>` native langsung, bukan komponen `Select` bersama). Flash message (`flash.success`/`flash.error`
dari `HandleInertiaRequests`) dirender ad-hoc di `AppLayout.vue`, bukan lewat komponen `Toast`/`Alert`
reusable. Ini gap paling besar dari brief CEO bagian "Theming" (poin 2) — **belum ada satupun** dari 8
komponen dasar yang diminta, ini kerja greenfield penuh, bukan penyelarasan komponen existing.

### Todo Teknis (BARU, murni frontend — komponen baru + 1 audit token CSS)
- [ ] Audit `resources/css/themes/theme-{blue,green,dark}.css` — pastikan ada token state untuk: hover
  (mis. `--surface-hover`, `--primary-hover`), active/pressed, focus (`--shadow-focus` sudah ada, dipakai
  ulang), disabled (`--disabled-bg`, `--disabled-text`, atau opacity token `--disabled-opacity: .5`), border
  default vs. error (`--border`, `--danger` sudah ada). Kalau token yang dibutuhkan komponen baru di bawah
  belum ada di salah satu dari 3 file tema, tambahkan **di ketiganya** sekaligus (konsisten lintas tema),
  ikuti pola penamaan yang sudah ada. **Jangan** hardcode warna di komponen manapun di bawah ini — semua
  warna wajib lewat `var(--token)`.
- [ ] `Button.vue` (baru): props `variant: 'primary'|'secondary'|'danger'|'ghost'` (default `'primary'`),
  `size: 'sm'|'md'|'lg'` (default `'md'`), `disabled: boolean`, `loading: boolean` (tampil spinner inline,
  tetap `disabled` secara fungsional saat loading), slot default = label, `type` prop diteruskan ke
  `<button type>` (default `'button'`). Emit `click` (native, tidak fire kalau disabled/loading). State
  hover/active/focus-visible/disabled pakai token dari poin di atas.
- [ ] `Input.vue` (baru): props `modelValue`, `label?`, `type` (default `'text'`), `error?: string | null`,
  `disabled: boolean`, `placeholder?`, `id?` (auto-generate kalau tidak dikirim, untuk `<label for>`).
  Pola aksesibilitas **reuse** dari form transfer §3 (`aria-invalid="!!error"`, `aria-describedby` menunjuk
  `id`-error kalau `error` truthy, `<span class="field-error" :id="...">{{ error }}</span>`).
- [ ] `Select.vue` (baru): props `modelValue`, `options: Array<{value, label}>`, `label?`, `error?`,
  `disabled`. Pola ARIA sama seperti `Input.vue`.
- [ ] `Checkbox.vue` / `Radio.vue` (baru, 2 komponen kecil terpisah): props `modelValue`, `label`,
  `disabled`. `Radio` dipakai berpasangan (bukan grouping sendiri) — pola `role="radiogroup"` di parent
  (persis seperti `themeOptions` di `Account.vue` §9.3) tetap ditulis oleh consumer, `Radio.vue` cuma
  render 1 opsi.
- [ ] `Modal.vue` (baru): props `show: boolean`, `title?`. Slot default = body, slot `footer`. Emit
  `close`. Wajib: focus trap sederhana (focus ke modal saat dibuka, kembalikan fokus ke trigger saat
  ditutup), `Escape` key → emit `close`, `role="dialog"` `aria-modal="true"` `aria-labelledby` menunjuk
  judul. Modal transfer di `Dompet.vue` (§3) **boleh** dimigrasikan untuk pakai komponen ini di PR yang
  sama atau PR terpisah — tidak wajib di iterasi ini kalau berisiko regresi, tapi didorong sebagai referensi
  pola untuk modal-modal baru ke depan.
- [ ] `Drawer.vue` (baru, generalisasi dari `FilterDrawer.vue` yang sudah ada) — props/slot mirip `Modal.vue`
  tapi slide-in dari sisi (kanan/bawah tergantung breakpoint, ikuti pola visual `FilterDrawer.vue` existing).
- [ ] `Tabs.vue` (baru): props `modelValue` (key tab aktif), `tabs: Array<{key, label}>`. Emit
  `update:modelValue`. `role="tablist"`/`role="tab"`/`aria-selected` per tab, navigasi panah kiri/kanan
  keyboard antar tab (persyaratan aksesibilitas brief CEO). Tab Dompet/Transaksi/Tagihan di `Dompet.vue`
  **boleh** dimigrasikan ke komponen ini (rekomendasi, tidak wajib kalau berisiko regresi luas mengingat
  kompleksitas state yang sudah ada di file itu).
- [ ] `Alert.vue` (baru): props `variant: 'success'|'danger'|'warning'|'info'`, `dismissible: boolean`
  (default `false`). Slot default = pesan. `role="alert"` kalau `variant` `danger`/`warning` (butuh
  announce), `role="status"` untuk `success`/`info` (tidak interupsi screen reader untuk info non-kritis).
- [ ] `Toast.vue` + composable `useToast()` (baru) — `useToast().push({ variant, message, duration? })`
  menambah entry ke queue reaktif (module-level `ref([])`, singleton), `ToastContainer.vue` (1 instance,
  mount sekali di `AppLayout.vue`) merender queue sebagai stack toast auto-dismiss. **Migrasikan** flash
  message existing (`flash.success`/`flash.error` dari `HandleInertiaRequests`) di `AppLayout.vue` supaya
  lewat `useToast()` juga (ganti render ad-hoc yang sudah ada) — ini satu-satunya migrasi wajib dari 8
  komponen di atas (existing behaviour flash message harus tetap identik secara fungsional, cuma pindah
  mekanisme rendering).
- [ ] `docs/theming-guide.md` (sudah ada) — tambah 1 bagian baru: daftar 8 komponen dasar di atas, token
  state yang dipakai tiap komponen, dan contoh override per-brand (kalau ada kebutuhan produk lain pakai
  desain sistem yang sama dengan token berbeda — cukup dokumentasikan pola `[data-theme='x']` yang sudah
  ada, jangan bikin mekanisme override baru).

### Kontrak
Tidak ada endpoint/tabel/kolom baru untuk §10.6 — murni komponen Vue baru + kemungkinan tambahan token CSS
custom-property (bukan skema DB).

### 10.7 Testing tambahan (melengkapi §7.1, bukan menggantikan)

- [ ] Feature test **fee**: transfer dengan `fee > 0` → dompet sumber berkurang `amount+fee`, dompet
  tujuan bertambah `amount` persis (tidak kena fee); saldo sumber cukup untuk `amount` tapi tidak cukup
  untuk `amount+fee` → ditolak dengan pesan error yang menyebut breakdown; reversal transfer ber-fee
  mengembalikan `amount+fee` penuh ke sumber.
- [ ] Feature test **idempotensi**: 2x `POST /dompet/transfer` dengan `request_id` sama & payload sama →
  cuma 1 baris `wallet_transfers`, saldo cuma berubah 1x (bukan 2x), response ke-2 tetap sukses (bukan
  error) dengan pesan yang sama. `request_id` beda dengan payload sama → 2 baris terpisah (bukan dianggap
  duplikat, sesuai desain: idempotency key eksplisit, bukan dedup by content).
- [ ] Feature test **otorisasi** (menutup permintaan CEO "Policy/Authorization" — app ini tidak punya
  Policy class formal, pola existing pakai `abort_if` ad-hoc §10.0, jadi test ini memverifikasi pola itu
  tetap benar, **bukan** memperkenalkan Laravel Policy baru yang di luar pola existing): user tidak bisa
  transfer pakai `from_wallet_id`/`to_wallet_id` milik user lain (403), tidak bisa reversal transfer milik
  user lain (403) — kemungkinan sudah ada sebagian di `WalletTransferTest.php`, extend kalau belum lengkap
  untuk kombinasi `from` milik orang lain vs. `to` milik orang lain (2 skenario terpisah).
- [ ] Unit test `WalletService`: `transferBetweenWallets()` dengan fee menghasilkan 3 baris
  `wallet_balance_logs` (2 pasangan debit/credit `amount` + 1 debit `fee` tanpa pasangan), total debit =
  total credit + fee (persamaan neraca eksplisit di assertion).
- [ ] Test event: `Event::fake()` assert `WalletTransferInitiated`/`Succeeded` dipatch pas 1x per transfer
  sukses, `WalletTransferFailed` dipatch saat saldo tidak cukup (bukan `Succeeded`).

### 10.8 Deliverables — 3 PR terpisah (rekomendasi pemecahan, sesuai brief CEO)

Kerja §1–§9 sudah menyatu di riwayat commit branch ini (tidak dipecah retroaktif — itu akan menulis ulang
history yang mungkin sudah didorong ke remote, di luar scope PM). Rekomendasi pemecahan untuk kerja **baru**
mulai §10 ini, disusun supaya tiap PR bisa direview & di-merge independen:

1. **PR 1 — Theming & komponen dasar**: §10.6 penuh (8 komponen + audit token state) + migrasi `Toast`
   untuk flash message. Tidak bergantung pada PR 2/3, bisa dikerjakan & di-merge duluan.
2. **PR 2 — Redesign UI Dompet**: sudah mayoritas selesai di §1–§9 (landed di branch ini) — PR ini isinya
   dokumentasi/cleanup kalau ada, atau dilewati kalau §1–§9 sudah masuk PR existing untuk branch ini
   (cek PR existing sebelum buka PR baru, sesuai §7.4 lama: "jangan buat PR baru").
3. **PR 3 — Alur wallet_transfer & logic**: §10.1–§10.3 + §10.7 (fee, idempotensi, event telemetri, test).
   Bergantung pada migration §10.1 — pastikan migration itu jalan duluan sebelum PR ini di-deploy.

Urutan merge disarankan: PR 1 dulu (komponen dasar dipakai PR 3 untuk form fee/error state), lalu PR 3.
PR 2 hanya perlu dibuka kalau memang belum ada PR aktif untuk branch ini di GitHub.

### Kriteria Selesai tambahan (melengkapi bagian sebelumnya)
- [ ] Transfer dengan fee tercatat akurat (sumber berkurang `amount+fee`, tujuan bertambah `amount` persis),
  dan reversal mengembalikan keduanya (§10.1).
- [ ] Retry/double-submit transfer dengan `request_id` sama tidak pernah menggandakan pemindahan saldo
  (§10.1, §10.7).
- [ ] Event `wallet_transfer_initiated`/`_succeeded`/`_failed` ter-log dengan properti dasar yang diminta
  CEO (§10.2).
- [ ] 8 komponen dasar (Button/Input/Select/Checkbox/Radio/Modal/Drawer/Tabs/Alert/Toast) tersedia, pakai
  token tema (bukan hardcode warna), state hover/active/focus/disabled konsisten di 3 tema (§10.6).
- [ ] Cakupan i18n didokumentasikan eksplisit di PR (string baru masuk `lang/id/wallet.php`, retrofit
  penuh frontend dicatat sebagai keputusan terpisah butuh approval, bukan silently skipped) (§10.5).
- [ ] `phpstan`/`pint`/`php artisan test` tetap hijau termasuk test baru §10.7.

---

## 11. REVISI 2026-07-16 (iterasi ke-5) — Arahan CEO: rerun pipeline CI + lanjutkan sisa redesign

CEO mengonfirmasi migrasi `cuanai_chat` sudah diperbaiki manual (sqlite-compatible) dan sudah di-commit,
menyebut "sebelumnya 25 test hijau", minta verifikasi ulang lewat pipeline CI di branch ini, lalu lanjutkan
sisa redesign UI Dompet/theming/Wallet Transfer sampai selesai. PM iterasi ini memverifikasi ulang langsung
ke `git log`, isi migration, dan isi kode (bukan asumsi) sebelum menulis bagian ini — lihat §11.0–§11.1 untuk
temuan yang **mengubah cara Todo di bawah harus dieksekusi**.

### 11.0 Verifikasi klaim CEO (dicek langsung, bukan diasumsikan benar)

- **Migrasi `cuanai_chat` sudah sqlite-compatible** — dikonfirmasi: `database/migrations/2026_07_08_000001_add_cuanai_chat_to_transactions_source_enum.php`
  (commit `a208765`, HEAD saat ini) sudah bercabang `DB::getDriverName() === 'sqlite'` → pakai
  `$table->string('source')->default('manual')->change()` (SQLite tidak dukung `ALTER ... MODIFY COLUMN ENUM`),
  else → `DB::statement('ALTER TABLE ... ENUM(...)')` untuk MySQL. `up()` dan `down()` keduanya sudah
  bercabang sama. **Klaim CEO benar, tidak ada Todo perbaikan lagi untuk migration ini.**
- **"Sebelumnya 25 test hijau"** — dikonfirmasi by count: `grep -rE "public function test_|#\[Test\]" tests`
  = **25** method test, tersebar di `tests/Feature/{WalletTransferTest,AccountThemeTest,DompetTransactionHistoryTest,WalletArchiveTest}.php`
  + `tests/Unit/WalletServiceFeeTest.php` + `tests/Unit/ExampleTest.php`. Angka yang disebut CEO cocok
  dengan state kode saat ini — **PM tidak menjalankan test ini sendiri** (di luar batasan peran PM, lihat
  `docs/agents/project-manager-ai.md` §Batasan), tapi keberadaan & jumlahnya terverifikasi by static count.
  Database/Backend AI tetap **wajib** menjalankan `php artisan test` sungguhan (§11.2) — angka statis ini
  bukan pengganti eksekusi nyata.
- **Item §10 (fee, idempotensi, telemetri, 8 komponen UI, string i18n) sudah landed**, jangan dikerjakan
  ulang — dikonfirmasi lewat `git show --stat` pada 3 commit setelah §10 ditulis:
  - `90ab4ab` (database migration): `2026_07_15_000001_add_fee_and_request_id_to_wallet_transfers_table.php` ada.
  - `6530db7` (backend): `app/Events/WalletTransfer{Initiated,Succeeded,Failed}.php`,
    `app/Listeners/LogWalletTransferTelemetry.php`, `lang/id/wallet.php`, perubahan
    `WalletController@transfer`/`WalletService::transferBetweenWallets` untuk fee+idempotency, plus
    `tests/Feature/WalletTransferTest.php` (203 baris baru) & `tests/Unit/WalletServiceFeeTest.php` — semua
    sesuai kontrak §10.1/§10.2/§10.7.
  - `221b5c7` (frontend): 8 komponen `resources/js/Components/UI/{Button,Input,Select,Checkbox,Radio,Modal,Drawer,Tabs,Alert,Toast,ToastContainer}.vue`
    + `useToast.js` + migrasi flash message di `AppLayout.vue` ke `useToast()` + `docs/theming-guide.md`
    ditambah 48 baris (bagian komponen dasar) — sesuai kontrak §10.6.
  Kesimpulan: **§1–§10 secara substansi sudah dikerjakan di kode**, bukan cuma spec di atas kertas. Sisa
  pekerjaan riil ada di §11.1 (gap infra CI) dan §11.3–§11.5 (item baru dari brief CEO kali ini yang belum
  pernah masuk spec manapun sebelumnya: kategori transfer, batas maksimum, CHANGELOG).

### 11.1 TEMUAN KRITIS — Tidak ada pipeline CI di repository ini sama sekali

**Temuan**: `find` untuk `.github/workflows/*`, `.gitlab-ci.yml`, `.circleci/*` di root repo — **nol hasil**
di semua tiga. Tidak ada satupun file konfigurasi pipeline CI (GitHub Actions, GitLab CI, maupun CircleCI)
yang ter-commit di repository ini, di branch manapun (dicek juga `git log --all -- '.github' '.gitlab-ci.yml'`
— kosong). Arahan CEO poin 2 ("Trigger ulang testing pipeline untuk branch ini (rerun latest pipeline)...
Pantau hasil pipeline. Lampirkan link pipeline...") **tidak bisa dieksekusi secara harfiah** karena tidak ada
pipeline yang bisa di-rerun — tidak ada "latest pipeline" yang eksis untuk branch ini maupun branch manapun.

**Ini bukan hal yang boleh PM putuskan sendiri untuk dibuatkan** — menulis file `.github/workflows/ci.yml`
adalah perubahan infrastruktur/konfigurasi CI, di luar cakupan "kontrak API" yang jadi tugas PM, dan juga
bukan migration/PHP/Vue murni yang jadi domain Database/Backend/Frontend AI di bawah PM. Ini butuh keputusan
eksplisit: apakah CI dijalankan lewat sistem eksternal yang konfigurasinya **tidak** disimpan di repo ini
(mis. pipeline didefinisikan di UI platform CI, bukan file-as-code) — kalau begitu, "rerun pipeline" adalah
aksi di platform tersebut (GitHub Actions UI / GitLab UI / dst.), bukan sesuatu yang bisa diverifikasi lewat
isi repo. PM **tidak punya akses** ke platform CI eksternal untuk mengecek mana yang benar.

### Todo Teknis — eskalasi, bukan implementasi (WAJIB dilakukan sebelum §11.2 "rerun pipeline" bisa dipenuhi)
- [ ] **Eskalasi ke CEO/DevOps**: konfirmasi apakah pipeline CI untuk repo ini didefinisikan di luar repo
  (platform CI eksternal) atau memang belum pernah dibuat sama sekali. Kalau belum pernah dibuat, "rerun
  pipeline" tidak applicable — yang bisa dipenuhi cuma verifikasi lokal (§11.2), dan definisi pipeline baru
  jadi task infra terpisah (di luar scope redesign Dompet/theming/transfer ini), butuh keputusan eksplisit
  CEO sebelum dikerjakan (analog §10.5 soal keputusan cakupan i18n/vue-i18n, §7.2 soal Vitest — pola yang
  sama: PM tidak menambah infra baru diam-diam tanpa approval).
- [ ] **Kalau CEO konfirmasi pipeline memang belum ada**: rekomendasi minimal dari PM (bukan implementasi) —
  workflow GitHub Actions sederhana yang menjalankan persis 4 langkah §11.2 di bawah (migrate SQLite,
  `php artisan test`, `phpstan`, `pint --test`) tiap push ke branch ini, disimpan di `.github/workflows/ci.yml`.
  Ini keputusan/implementasi untuk Backend AI/DevOps sesudah CEO approve, **bukan** dikerjakan oleh PM.
- [ ] Sampai keputusan di atas ada, **deliverable "link pipeline hijau" di deskripsi PR (§7.4/§10.8) diganti**
  dengan output verifikasi lokal §11.2 (paste hasil command, bukan link) — supaya Definition of Done tidak
  terhambat oleh gap infra yang di luar kendali Database/Backend/Frontend AI.

### Kontrak
Tidak ada endpoint/tabel/kolom untuk temuan ini — ini murni gap infrastruktur CI, dicatat eksplisit supaya
tidak silently diasumsikan "pipeline sudah ada tapi gagal" (beda akar masalah, beda solusi).

---

### 11.2 Todo verifikasi lokal (Database/Backend AI, dieksekusi nyata — bukan diasumsikan dari §11.0)

Checklist ini **menggantikan** "rerun pipeline CI" selama gap §11.1 belum diputuskan CEO. Jalankan di branch
aktif (`feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer-lanjutkan-branch-yang-sama`, **jangan
checkout/buat branch baru**, `git pull` dulu untuk pastikan tidak ada commit baru dari sesi lain):

- [ ] `php artisan migrate:fresh` di environment SQLite lokal — pastikan **semua** migration termasuk
  `2026_07_08_000001_add_cuanai_chat_to_transactions_source_enum.php` dan
  `2026_07_15_000001_add_fee_and_request_id_to_wallet_transfers_table.php` apply bersih tanpa error.
- [ ] `php artisan migrate:rollback` lalu `php artisan migrate` lagi (siklus down/up penuh, bukan cuma up)
  — verifikasi eksplisit yang diminta brief CEO poin 1, mengonfirmasi `down()` migration `cuanai_chat` (yang
  juga sudah dicabang sqlite vs mysql, §11.0) tidak error.
- [ ] `php artisan test` — target **25/25 hijau minimum** (angka existing, §11.0), test baru dari §11.3/§11.4
  di bawah (kalau dikerjakan) akan menambah jumlah ini, semua harus tetap pass.
- [ ] `vendor/bin/phpstan analyse` dan `./vendor/bin/pint --test` — bersih (pola existing §7.1/§10, sudah
  didukung `.claude/settings.json` yang mengizinkan command ini, commit `be78526`).
- [ ] Paste hasil ke-4 command di atas (ringkas, bukan cuma "lulus") ke deskripsi PR/MR existing untuk
  branch ini — **jangan buat PR baru** (pola konsisten §7.4).

### Kontrak
Tidak ada endpoint/tabel baru — murni checklist eksekusi verifikasi, dijalankan oleh Database/Backend AI.

---

### 11.3 BARU — Kategori opsional pada Wallet Transfer

**Temuan**: brief CEO poin 3 (Wallet Transfer) eksplisit minta "Opsi catatan/kategori opsional". `note`
sudah ada (`wallet_transfers.note`, §3), tapi **tidak ada** kategori. Skema existing punya
`transaction_categories` (dipakai `transactions.category_id`, lihat `app/Models/Transaction.php` baris 39),
tapi `wallet_transfers` **tidak** punya kolom `category_id` sama sekali — transfer antar dompet sendiri
secara konsep beda dari transaksi (pemasukan/pengeluaran), tapi user tetap mungkin mau menandai *alasan*
transfer (mis. "Tabungan", "Modal Usaha") pakai kategori yang sudah mereka kenal dari transaksi.

**Keputusan**: reuse tabel `transaction_categories` yang sudah ada (bukan bikin tabel kategori terpisah
khusus transfer — akan duplikasi konsep tanpa manfaat), tambah kolom nullable `category_id` di
`wallet_transfers`. Opsional penuh — transfer tanpa kategori tetap valid (perilaku existing tidak berubah).

**Database** (migration baru):
```
Migration: 2026_07_16_000001_add_category_id_to_wallet_transfers_table.php
Tabel: wallet_transfers
Kolom baru:
  category_id  unsignedSmallInteger NULL, foreign key → transaction_categories.id, nullOnDelete()
```

**`app/Models/WalletTransfer.php`**: tambah relasi `category(): BelongsTo` → `TransactionCategory::class`
(pola sama seperti `Transaction::category()`).

**Kontrak API — extend `POST /dompet/transfer` (`wallets.transfer`, endpoint sama, field baru opsional)**

**Request**:
```
{
  ...existing (from_wallet_id, to_wallet_id, amount, fee?, note, transferred_at, request_id)...,
  category_id?: number | null   // BARU, opsional — nullable|exists:transaction_categories,id
}
```

**Response**: tidak berubah (redirect `back()` + flash, pola existing).

**Database**: migration di atas. Tidak ada perubahan pada `wallet_balance_logs` (kategori murni metadata
pencatatan, tidak memengaruhi kalkulasi saldo).

**Validasi** (`TransferWalletRequest`, tambah rule):
```
category_id: ['nullable', 'integer', 'exists:transaction_categories,id'],
```

**Frontend (`Dompet.vue`)**: tambah `<Select>` (reuse komponen `Select.vue` dari §10.6, **jangan** `<select>`
native baru) untuk `category_id` di form transfer, di bawah field `note`, opsional (placeholder "Tanpa
kategori"), pakai daftar kategori yang sama dengan yang sudah dipakai form transaksi di halaman ini (props
`categories` — cek nama prop existing yang dikirim `TransactionController@index` untuk form transaksi, reuse
persis, jangan minta prop baru dari backend kalau `categories` sudah dikirim). Tampilkan di ringkasan
konfirmasi (§3) sebagai baris "Kategori: {nama}" hanya kalau dipilih (baris disembunyikan kalau kosong).

---

### 11.4 BARU — Batas maksimum jumlah transfer (klarifikasi, bukan fitur baru berisiko)

**Temuan**: brief CEO minta "batas minimum/maximum" pada input jumlah transfer. Minimum sudah ada (`min:1`
di `TransferWalletRequest`, §3) plus batas implisit saldo tersedia (`amount + fee <= fromWallet.balance`,
§10.1) — ini **sudah** berfungsi sebagai batas maksimum alami (tidak mungkin transfer melebihi saldo).
Tidak ada konfigurasi batas maksimum **eksplisit** (mis. limit fraud-control per transaksi) di manapun di
codebase (`grep` untuk `max_transaction`/`transfer_limit`/`MAX_TRANSFER` di `config/`+`app/Http/Requests` —
kosong).

**Keputusan**: PM **tidak** mengarang angka limit spesifik (mis. "maks Rp 50.000.000") tanpa dasar bisnis —
itu keputusan produk/risiko yang harus datang dari CEO, bukan asumsi teknis. Assumption paling masuk akal
mengikuti pola Monexa existing: sediakan **hook konfigurasi opsional**, bukan hardcode angka. Kalau CEO tidak
memberi angka, batas maksimum tetap = saldo tersedia (perilaku existing, sudah cukup memenuhi "batas
maksimum" secara fungsional — user tidak pernah bisa transfer lebih dari yang dia punya).

### Todo Teknis
- [ ] `config/wallet.php` (file config baru, pola Laravel native, bukan hardcode di kelas): tambah key
  `'max_transfer_amount' => env('WALLET_MAX_TRANSFER_AMOUNT', null)` — `null` berarti tidak ada batas
  eksplisit selain saldo (perilaku default/existing tidak berubah).
- [ ] `TransferWalletRequest::rules()`: tambah `'amount' => [..., function ($attr, $value, $fail) { if
  (config('wallet.max_transfer_amount') && $value > config('wallet.max_transfer_amount')) { $fail(__('wallet.validation.amount_exceeds_max', ['max' => config('wallet.max_transfer_amount')])); } }]`
  — closure rule, aktif hanya kalau config diisi (default tidak mengubah perilaku existing, aman untuk semua
  environment yang belum set `WALLET_MAX_TRANSFER_AMOUNT`).
- [ ] `lang/id/wallet.php`: tambah key `validation.amount_exceeds_max` (pola sama dengan key `validation.*`
  yang sudah ada dari §10.5).
- [ ] **Eskalasi ke CEO**: kalau memang perlu limit fraud-control nyata, minta angka konkret (bisa beda per
  tier user/jenis dompet) — di luar scope PM untuk menentukan angkanya sendiri. Sampai ada angka, fitur ini
  **tidak aktif** (config `null` = off), tidak mengubah behaviour transfer existing.

**Kontrak API — extend `POST /dompet/transfer` (endpoint sama, tidak ada field request baru)**

**Response tambahan**: kalau `amount > config('wallet.max_transfer_amount')` (dan config diisi): validasi
422 dengan pesan `wallet.validation.amount_exceeds_max` — pola error existing (Inertia validation errors),
tidak ada perubahan shape response.

**Database**: tidak ada kolom/tabel baru — batas maksimum murni di level config, bukan data.

**Validasi**: closure rule di atas, ditambahkan ke `TransferWalletRequest`.

---

### 11.5 CHANGELOG — pembalikan keputusan §7.3 lama (baca alasan sebelum eksekusi)

**Konteks**: §7.3 (iterasi lama) eksplisit memutuskan **tidak** membuat `CHANGELOG.md` baru, dengan alasan
"bukan pola yang dipakai project ini". Brief CEO kali ini (poin 5, "Dokumentasi & housekeeping") eksplisit
minta **"Update CHANGELOG dan dokumentasi user/developer"**. Ini instruksi CEO langsung, bukan sekadar
kelanjutan brief lama — PM iterasi ini **membalik** keputusan §7.3 khusus untuk poin CHANGELOG, karena
instruksi eksplisit CEO lebih tinggi otoritasnya daripada keputusan cakupan yang diambil PM sendiri
sebelumnya. Bagian lain §7.3 (README, `theming-guide.md`) tetap berlaku, sudah dikerjakan (§11.0, konfirmasi
`221b5c7`).

### Todo Teknis
- [ ] Buat `CHANGELOG.md` baru di root repo (belum ada — dikonfirmasi `find . -maxdepth 1 -iname CHANGELOG*`
  kosong), format [Keep a Changelog](https://keepachangelog.com/) sederhana (`## [Unreleased]` di atas,
  section `### Added`/`### Fixed`/`### Changed`). Isi entri untuk **seluruh** kerja §1–§11 di branch ini
  (bukan cuma §11) karena ini pertama kalinya file ini dibuat — ringkas per fitur (theming 4 opsi, redesign
  kartu dompet icon/color custom, arsip dompet, wallet transfer + fee + idempotensi + kategori, komponen UI
  dasar, migration `cuanai_chat`), bukan 1 baris per commit.
- [ ] Dokumentasi developer: extend `docs/theming-guide.md` (sudah ada bagian komponen dasar dari §10.6) dan
  README (sudah ada bagian "Theming" dari §7.3) dengan 1 sub-bagian baru masing-masing: cara kerja kategori
  transfer opsional (§11.3) dan catatan `WALLET_MAX_TRANSFER_AMOUNT` (§11.4, opsional/off by default).
- [ ] Dokumentasi migration: catatan singkat di `CHANGELOG.md` (bukan file terpisah) soal migration
  `cuanai_chat` — kenapa perlu percabangan SQLite vs MySQL (SQLite tidak dukung `MODIFY COLUMN ENUM`), supaya
  developer lain yang menulis migration `ENUM` baru ke depan tahu polanya tanpa harus menemukan ulang.

### Kontrak
Tidak ada endpoint/tabel — murni dokumentasi. `CHANGELOG.md` baru di root, bukan di `docs/`.

---

### 11.6 Testing tambahan (melengkapi §7.1/§10.7, bukan menggantikan)

- [ ] Feature test **kategori transfer** (§11.3): transfer dengan `category_id` valid milik user →
  tersimpan & muncul di response riwayat; `category_id` milik kategori yang tidak ada / bukan milik siapapun
  yang relevan → 422; transfer tanpa `category_id` (opsional) → tetap sukses seperti sebelumnya (regresi
  check eksplisit, karena field ini baru).
- [ ] Feature test **batas maksimum** (§11.4): `WALLET_MAX_TRANSFER_AMOUNT` di-set di `.env.testing` untuk
  1 test spesifik (pakai `config(['wallet.max_transfer_amount' => ...])` di dalam test, bukan ubah
  `.env.testing` global supaya tidak memengaruhi test lain) → transfer melebihi batas ditolak 422 dengan
  pesan yang benar; transfer di bawah batas tetap sukses; config default (`null`, tidak di-set) → tidak ada
  batas selain saldo (test regresi eksplisit untuk memastikan default **tidak** mengubah behaviour existing).
- [ ] Migration test **cuanai_chat down/up cycle** (§11.2 poin 2, dijadikan test otomatis bukan cuma manual):
  test yang menjalankan `Artisan::call('migrate:rollback', ['--step' => 1])` lalu `Artisan::call('migrate')`
  di sekitar migration ini tidak wajib kalau `php artisan test` sudah jalan di atas skema yang sudah
  ter-migrate penuh (pola RefreshDatabase existing sudah implisit menutup ini tiap test run) — **cukup**
  verifikasi manual §11.2, tidak perlu test PHPUnit khusus baru untuk 1 migration ini (menghindari duplikasi
  cakupan yang sudah otomatis ter-cover oleh `RefreshDatabase` di semua Feature test yang sudah ada).

### Kriteria Selesai tambahan (melengkapi bagian sebelumnya, khusus iterasi §11)
- [ ] Gap CI pipeline (§11.1) dieskalasi ke CEO secara eksplisit di deskripsi PR — bukan silently diasumsikan
  beres atau silently diabaikan.
- [ ] Verifikasi lokal §11.2 (migrate fresh, rollback+migrate, `php artisan test`, `phpstan`, `pint`)
  dijalankan nyata dengan hasil di-paste ke PR, bukan diasumsikan dari angka statis §11.0.
- [ ] Kategori opsional pada transfer berfungsi end-to-end, tidak mengubah perilaku transfer tanpa kategori
  (§11.3).
- [ ] Batas maksimum transfer terdokumentasi sebagai off-by-default + hook config, bukan angka yang
  dikarang PM (§11.4).
- [ ] `CHANGELOG.md` baru dibuat mencakup seluruh riwayat fitur §1–§11 (§11.5), README & `theming-guide.md`
  di-extend dengan sub-bagian baru untuk §11.3/§11.4.
- [ ] Test baru §11.6 pass, total test count bertambah dari 25 (baseline §11.0) tanpa ada yang regresi.
