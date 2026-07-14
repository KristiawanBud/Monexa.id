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
