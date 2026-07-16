# Spec: Script Playwright — Screenshot UI Otomatis + Output URL Publik

Sumber arahan: CEO AI, 2026-07-17. Ditulis oleh Project Manager AI.

## 0. Cek branch lain

`git branch -a | grep -iE "screenshot|playwright"` — **tidak ditemukan** branch lain (lokal maupun
`origin/*`) yang menyinggung topik ini. Tidak ada rekomendasi merge, lanjut kerja di branch aktif
sekarang (`feature/lanjutkan-redesign-ui-dompet-theming-wallet-transfer-lanjutkan-branch-yang-sama`)
kecuali CEO/reviewer minta branch terpisah.

Task ini **bukan** kontrak HTTP API (tidak ada endpoint baru, tidak ada tabel DB baru) — outputnya
adalah satu script Node.js (`scripts/screenshot-ui.js`) yang dijalankan lewat CLI/npm script dan
memakai automation browser terhadap route yang **sudah ada** (`/login`, dashboard). Format di bawah
ini menyesuaikan (bagian Endpoint/Database/Validasi diisi sesuai konteks nyata, bukan dipaksakan).

Task ini murni Node.js tooling (tidak menyentuh PHP/Vue), jadi paling cocok dikerjakan oleh **Backend
AI** (karena perlu tahu kredensial seed user dan konfigurasi storage/`APP_URL` dari `.env`).

---

## Temuan penting dari pengecekan langsung repo (wajib dibaca sebelum implementasi)

1. **Seed user untuk login**: `database/seeders/SuperAdminSeeder.php` membuat user
   `email: admin@catatcuan.id`, `password: admin123` (role `super_admin`), lengkap dengan
   `UserProfile` + `Subscription` aktif 10 tahun. Artinya user ini **lolos** middleware
   `auth`, `subscribed`, DAN `onboarded` sekaligus — setelah login akan langsung diarahkan ke
   `dashboard` (`/`), bukan ke alur onboarding. Gunakan user ini sebagai default kredensial script,
   tapi **wajib overridable** lewat env var (lihat §3).
2. **Form login** (`resources/js/Pages/Auth/Login.vue`): field `v-model="form.email"`
   (`type="email"`) dan `v-model="form.password"` (`type="password"`), submit via
   `@submit.prevent="submit"` pada `<form class="auth-form">`. Route: `GET /login` (nama `login`),
   `POST /login` diproses `LoginController@store` — validasi `email` + `password` required, rate
   limit 5x/menit per email+IP. Setelah sukses: redirect ke `onboarding.step1` kalau belum ada
   profile, atau ke `dashboard` kalau sudah (seed user di atas sudah onboarded → langsung ke
   dashboard).
3. **`public/storage` BUKAN symlink di environment ini** — sudah dicek langsung
   (`readlink -f public/storage` mengembalikan path itu sendiri, bukan target lain), isinya
   direktori asli kosong berisi `.gitignore` (`*` / `!.gitignore`). Ini **berbeda dari asumsi default
   Laravel** (biasanya `public/storage` adalah symlink hasil `php artisan storage:link` ke
   `storage/app/public`). Konsekuensi: kalau script menulis file ke `storage/app/public/...` lewat
   Laravel Storage facade, file itu **tidak akan** ke-serve di `/storage/...` di environment ini
   kecuali symlink diperbaiki lebih dulu.
   - **Keputusan desain**: script Node berdiri sendiri (bukan lewat Laravel), jadi paling aman dan
     paling sedikit efek samping adalah **tulis file screenshot langsung ke
     `public/storage/screenshots/`** (buat sub-folder ini kalau belum ada) memakai `fs` Node biasa —
     tidak perlu menyentuh `php artisan storage:link` maupun kode PHP sama sekali. File otomatis
     ter-serve langsung oleh webserver karena sudah berada di `public/`.
   - Folder `public/storage/screenshots/` akan otomatis ikut aturan `.gitignore` yang sudah ada di
     `public/storage/.gitignore` (`*` / `!.gitignore`) sehingga file screenshot **tidak ter-commit**
     ke git — tidak perlu entry gitignore tambahan.
4. **`APP_URL` di `.env` environment ini sudah berupa IP publik**: `http://103.247.11.62/` (bukan
   `localhost`). Ini dipakai sebagai base URL untuk konstruksi URL publik hasil screenshot. **Catatan
   risiko**: karena ini kemungkinan server yang sama dengan yang dipakai untuk kerja sehari-hari,
   script yang login sebagai `super_admin` sebaiknya **tidak** dijalankan sembarangan di server yang
   sedang dipakai orang lain — cukup didokumentasikan sebagai catatan operasional, tidak perlu
   dibuatkan mekanisme block khusus (di luar scope task ini).

---

## Todo Teknis

- [ ] `npm install --save-dev playwright` (tambah ke `devDependencies` di `package.json`).
- [ ] `npx playwright install chromium --with-deps` — jalankan sekali di environment
  (catatan: `--with-deps` butuh akses install paket sistem lewat `apt`, biasanya butuh `sudo`/root;
  kalau environment CI/server tidak izinkan, jalankan `npx playwright install chromium` saja dan
  pastikan dependency sistem Chromium sudah ada).
- [ ] Buat file baru `scripts/screenshot-ui.js` (folder `scripts/` belum ada di repo, buat baru).
- [ ] Tambah npm script di `package.json` → `"scripts"`:
  ```json
  "screenshot:ui": "node scripts/screenshot-ui.js"
  ```
- [ ] Pastikan **hanya URL publik final** yang di-`console.log` ke stdout. Semua log progres/error
  pakai `console.error` (stderr), supaya `npm run screenshot:ui` bisa langsung di-pipe/di-capture
  oleh proses lain tanpa noise.

---

## 1. Kontrak Script — `scripts/screenshot-ui.js`

### Cara jalan
```
node scripts/screenshot-ui.js [route]
# atau
npm run screenshot:ui -- [route]
```
- `route` (argumen posisional opsional, default `/`): path relatif tujuan screenshot **setelah**
  login berhasil, contoh `/dompet`, `/report`. Kalau tidak diisi, screenshot halaman dashboard (`/`).

### Env vars (semua opsional, ada default)
| Env var | Default | Keterangan |
|---|---|---|
| `SCREENSHOT_BASE_URL` | nilai `APP_URL` dari `.env` Laravel (`http://103.247.11.62/`), fallback `http://127.0.0.1:8000` kalau `.env` tidak terbaca | Base URL target aplikasi yang di-screenshot |
| `SCREENSHOT_EMAIL` | `admin@catatcuan.id` | Email seed user (`SuperAdminSeeder`) |
| `SCREENSHOT_PASSWORD` | `admin123` | Password seed user |
| `SCREENSHOT_OUTPUT_DIR` | `public/storage/screenshots` (relatif dari root project) | Folder simpan file PNG |
| `SCREENSHOT_PUBLIC_BASE_URL` | sama dengan `SCREENSHOT_BASE_URL` (di-trim trailing slash) | Base URL publik yang dipakai membangun URL hasil, dipisah dari `SCREENSHOT_BASE_URL` kalau butuh reverse-proxy/domain berbeda |

### Alur eksekusi
1. Baca `route` dari `process.argv[2]`, default `/`.
2. Resolve `SCREENSHOT_BASE_URL` — kalau env var tidak diset, coba baca `APP_URL` dari file `.env`
   di root project (parse sederhana, tidak perlu library baru — cukup baca baris `APP_URL=...`).
3. Launch `chromium` Playwright, `headless: true`, buat `page` baru (viewport default cukup, karena
   screenshot pakai `fullPage: true`).
4. `page.goto(`${baseUrl}/login`)`, `waitUntil: 'networkidle'`, timeout 30 detik.
5. Isi form: `page.fill('input[type="email"]', email)`, `page.fill('input[type="password"]', password)`.
6. Submit form (`page.click('button[type="submit"]')` atau `page.locator('.auth-form').press('Enter')` —
   sesuaikan selector nyata saat implementasi, cross-check `Login.vue` untuk pastikan ada
   `type="submit"` pada tombolnya) dan `page.waitForURL(url => !url.pathname.startsWith('/login'), { timeout: 15000 })`.
7. Kalau setelah submit URL **masih** `/login` (login gagal / validasi error tampil) → lempar error
   jelas ke stderr (`Login gagal: ...`) dan `process.exit(1)`, **jangan** lanjut screenshot.
8. Kalau `route` diisi dan berbeda dari halaman hasil redirect saat ini, `page.goto(`${baseUrl}${route}`, { waitUntil: 'networkidle' })`.
9. Buat nama file unik: `ui-${Date.now()}.png` (hindari tabrakan antar run).
10. Pastikan folder `SCREENSHOT_OUTPUT_DIR` ada (`fs.mkdirSync(dir, { recursive: true })`).
11. `page.screenshot({ path: <output_dir>/<filename>, fullPage: true })`.
12. Tutup `browser` (di blok `finally`, supaya tetap tertutup walau error di step manapun).
13. Bangun URL publik: `${trim(SCREENSHOT_PUBLIC_BASE_URL, '/')}/storage/screenshots/${filename}`.
14. `console.log(publicUrl)` — **satu baris ini saja** yang boleh masuk stdout. Semua langkah 1–13
    yang butuh logging progres pakai `console.error`.
15. Exit code `0` kalau sukses; exit code `1` + pesan error ke stderr kalau gagal di titik manapun
    (browser tidak bisa launch, `baseUrl` tidak reachable, login gagal, dsb).

### "Response" (output kontrak)
- **stdout (sukses)**: hanya 1 baris — URL publik penuh, contoh:
  `http://103.247.11.62/storage/screenshots/ui-1752729600000.png`
- **stdout (gagal)**: kosong (tidak boleh ada apapun di stdout kalau gagal).
- **stderr**: bebas — log progres (`Launching browser...`, `Logging in...`, dst) dan pesan error kalau
  gagal. **Jangan** log password ke stderr sekalipun (cukup log email yang dipakai).
- **Exit code**: `0` sukses, `1` gagal.

### Database
Tidak ada migration/tabel baru. Script hanya membaca user yang sudah ada dari seeder
(`SuperAdminSeeder`) lewat form login biasa (bukan query DB langsung).

### Validasi
- Validasi kredensial dilakukan oleh alur login existing (`LoginController@store`) — script tidak
  perlu re-implementasi validasi, cukup deteksi kegagalan lewat URL yang tidak berpindah dari
  `/login` setelah submit (lihat langkah 7).
- Timeout eksplisit di setiap `page.goto`/`waitForURL` (jangan biarkan script hang tanpa batas —
  pakai 15–30 detik) supaya tidak menggantung proses CI/automation lain yang memanggil script ini.
- Pastikan folder output dibuat sebelum `page.screenshot()` dipanggil (Playwright akan error kalau
  parent folder tidak ada).
