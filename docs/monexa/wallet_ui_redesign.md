# Laporan: Redesign UI Dompet (Responsif) + Pondasi Theming

Laporan ke owner/CEO untuk task "Redesign UI Dompet (responsif) + pondasi theming", dikerjakan lewat
4 putaran (Round 1-4) di branch `feature/redesign-ui-dompet-responsif-pondasi-theming`. Dokumen ini
melengkapi (bukan menggantikan) referensi teknis internal `docs/dompet-module.md` (struktur komponen
& katalog event) dan `docs/theming-guide.md` (cara kerja & cara ganti tema).

## Referensi desain

Referensi visual: `storage/athena-refs/monexa-1783905868539.jpg` — hero saldo gradien biru dengan
ilustrasi dompet 3D + sparkle, badge "X Dompet Aktif", 3 kartu ringkasan Saldo Cash/Bank/E-Wallet
dengan progress bar, tab Transaksi/Dompet/Tagihan, filter tanggal dropdown + ringkasan
Masuk/Keluar/Saldo, search bar + tombol Filter, list transaksi harian dikelompokkan per tanggal
dengan ikon kategori berwarna, bottom nav dengan FAB tengah "Tambah Transaksi".

## Keputusan UI/UX per breakpoint

Halaman `/dompet` (`resources/js/Pages/App/Dompet.vue` + `AppLayout.vue`) memakai 3 kelas ukuran
layar:

- **Mobile (≤480px)** — 1 kolom, bottom navigation + FAB tengah "＋" untuk quick-add (bottom sheet:
  Pemasukan/Pengeluaran/Scan Struk/Setor Tabungan/Bayar Tagihan/dst), filter transaksi dibuka lewat
  tombol "▤ Filter" (drawer), grid dompet 1 kolom.
- **Tablet (481-1024px)** — toolbar aksi cepat eksplisit muncul (`QuickActions.vue`: Tambah
  Pemasukan/Pengeluaran/Transfer), panel filter transaksi pindah jadi sidebar kiri persisten
  (`tx-layout` 2 kolom: `280px` + fleksibel), grid dompet 2 kolom, bottom-nav tetap ada.
- **Desktop (≥1025px)** — sidebar navigasi kiri persisten menggantikan bottom-nav (termasuk tombol
  "＋ Tambah Transaksi" yang membuka bottom sheet quick-add yang sama dengan mobile), grid dompet 3
  kolom, keyboard shortcut (`/` fokus cari, `n`/`Shift+N` tambah pemasukan/pengeluaran, `t` transfer,
  `Esc` tutup overlay).

**Catatan breakpoint**: implementasi memakai `481px`/`1025px` (bukan `640px`/`1024px` literal dari
brief awal) — keputusan ini dipertahankan dari Round 3 karena breakpoint tsb dipakai bersama oleh
banyak halaman lain lewat `AppLayout.vue`, dan mengubahnya berisiko regresi di luar modul Dompet.
Pertanyaan konfirmasi ke owner soal ini masih terbuka (lihat spec Round 4 §E) — belum ada jawaban
yang mengubah keputusan ini.

Semua tap target ≥44px, ukuran teks 13-16px, dan `focus-visible` state konsisten di komponen utama
(`TransactionItem.vue`, `QuickActions.vue`, `CardDompet.vue`, dll).

## Ringkasan perubahan kumulatif Round 1-4

Ringkas — detail lengkap per putaran ada di spec masing-masing (`docs/spec-redesign-ui-dompet-*.md`).

- **Round 1** (`spec-redesign-ui-dompet-jadi-responsif-fondasi-theming-biru-putih-hijau-putih-dark.md`)
  — fondasi awal: hero saldo gradien biru, breakdown Cash/Bank/E-Wallet, tab
  Transaksi/Dompet/Tagihan, grid responsif dasar, CSS Variables + 3 file tema
  (`theme-blue.css`/`theme-green.css`/`theme-dark.css`).
- **Round 2 & 3** — perluasan sistem tema (`useTheme.js`, whitelist `blue|green|dark`, prioritas
  `?theme=` → `localStorage` → `VITE_DEFAULT_THEME` → fallback biru), toggle dev tema di
  `Account.vue` (gated `VITE_ENABLE_THEME_TOGGLE`), filter transaksi lengkap (rentang tanggal
  custom, kategori, pencarian debounced, drawer di mobile / sidebar di tablet-desktop), empty/error/
  skeleton state, audit tap-target & breakpoint (keputusan `481px`/`1025px` dikunci di Round 3).
- **Round 4 (iterasi ini)** — 5 gap konkret hasil audit ulang terhadap brief:
  1. **Duplikasi transaksi** — tombol "📋 Duplikasi" di modal edit transaksi (`Dompet.vue`), memanggil
     `POST /dompet/{transaction}/duplicate` (endpoint baru, backend). Transaksi baru disalin dengan
     `transacted_at` = hari ini, saldo dompet ter-update otomatis lewat `WalletService`.
  2. **3 event analitik baru** (nama eksak dari brief, additive — event lama tetap ada):
     `wallet_opened` (sekali per buka halaman), `transaction_filter_used` (tiap kali filter/kategori/
     pencarian/rentang tanggal berubah), `quick_add_clicked` (toolbar desktop & FAB/sidebar/bottom
     sheet mobile-desktop).
  3. **Test infrastructure frontend** — sebelumnya nol, sekarang ada Vitest + `@vue/test-utils` +
     `happy-dom` (unit/snapshot 7 komponen utama) dan Playwright (e2e 3 viewport + ganti tema).
  4. **Dokumen ini** (`docs/monexa/wallet_ui_redesign.md`) sebagai laporan terpisah dari referensi
     teknis internal.
  5. **Audit Lighthouse & kontras WCAG AA** — lihat bagian di bawah.

## Cara ganti tema untuk QA

Ringkas dari `docs/theming-guide.md` (rujuk dokumen itu untuk detail lengkap): buka halaman dengan
`?theme=blue|green|dark` di URL (nilai lain otomatis fallback ke `blue`). Pilihan tersimpan di
`localStorage.monexa_theme` sehingga tetap aktif di navigasi berikutnya. Untuk default environment,
set `VITE_DEFAULT_THEME` di `.env` lalu build ulang.

## Audit Lighthouse & kontras WCAG AA

**Kontras** — dihitung manual (formula relative luminance WCAG 2.1) untuk pasangan warna yang
disebut brief (teks di atas gradien hero, dan `--success`/`--danger` di atas `--success-bg`/
`--danger-bg`):

| Pasangan | Tema | Rasio | Status (AA teks normal ≥4.5:1) |
|---|---|---|---|
| Teks putih di atas `--primary` (ujung gradien hero yang lebih terang) | Blue | 5.17:1 | ✅ Lolos |
| Teks putih di atas `--primary-dark` | Blue | 6.64:1 | ✅ Lolos |
| Teks putih di atas `--primary` | Green | 3.30:1 | ❌ **Gagal** (lolos ambang teks besar/UI 3:1 saja) |
| Teks putih di atas `--primary-dark` | Green | 5.02:1 | ✅ Lolos |
| `--text-primary` di atas `--primary` | Dark | 3.04:1 | ❌ **Gagal** (lolos ambang teks besar/UI 3:1 saja) |
| `--text-primary` di atas `--primary-dark` | Dark | 4.46:1 | ⚠️ Borderline (di bawah 4.5:1, perlu verifikasi alat seperti WebAIM) |
| `--success` di atas `--success-bg` | Blue & Green (nilai identik) | 2.12:1 | ❌ **Gagal** |
| `--danger` di atas `--danger-bg` | Blue & Green (nilai identik) | 3.30:1 | ❌ **Gagal** untuk teks normal |
| `--success` di atas `--success-bg` | Dark | 7.22:1 | ✅ Lolos |
| `--danger` di atas `--danger-bg` | Dark | 5.61:1 | ✅ Lolos |

**Temuan 1 — teks di atas hero gradien**: di ketiga tema, ujung gradien yang lebih terang (`--primary`
saja, sebelum di-blend ke `--primary-dark`) gagal ambang normal-text 4.5:1 untuk Green & Dark (Blue
masih lolos). Karena hero-nya gradien (bukan warna solid), area teks besar (`hero-saldo-amount`,
28-30px) kemungkinan masih masuk kategori "teks besar" WCAG (ambang 3:1, lolos di semua tema), tapi
teks kecil di hero (`hero-saldo-label`, `hero-page-sub`) berisiko gagal AA di tema Green & Dark —
perlu verifikasi visual langsung (alat seperti WebAIM) karena kalkulasi ini memakai warna solid, bukan
gradien sungguhan.

**Temuan 2 — `--success`/`--danger` di atas `--success-bg`/`--danger-bg`**: dipakai nyata di
`QuickActions.vue` (teks "💵 Tambah Pemasukan"/"🔥 Tambah Pengeluaran") dan `Saving.vue`
`.completed-badge`, gagal ambang AA 4.5:1 untuk tema Blue & Green, konsisten dengan kekhawatiran
brief. `--success` bahkan gagal AA saat dipakai langsung di atas `--surface` putih polos (dipakai
luas di seluruh app, mis. `tx-amt.up`, `rs-val.up` — rasio ~2.28:1). Ini masalah palet warna brand
yang sudah ada sejak sebelum Round 4 (bukan regresi dari redesign ini), berdampak ke banyak halaman
di luar Dompet. **Tidak diperbaiki langsung di iterasi ini** — mengubah `--success`/`--danger`/
`--primary` (Green/Dark) global berisiko regresi visual besar di seluruh aplikasi dan merupakan
keputusan desain brand yang perlu sign-off owner, bukan sekadar todo QA lokal. Direkomendasikan jadi
task terpisah: "audit & revisi kontras warna brand (`--primary` Green/Dark, `--success`/`--danger`)
lintas halaman".

**Lighthouse (Performance/Accessibility ≥90) & CLS<0.1** — audit ini butuh build production
(`npm run build`) dan browser nyata (`npx lighthouse http://<host>/dompet`), yang **belum bisa
dijalankan di lingkungan pengerjaan iterasi ini** (izin `npm install`/eksekusi skrip belum tersedia
saat laporan ini ditulis — lihat catatan di PR). Perbaikan struktural yang relevan untuk CLS sudah
ada: `SkeletonLoader.vue` punya 3 varian (`hero`/`card`/`list-item`) dengan dimensi yang mendekati
komponen aslinya untuk meminimalkan layout shift. **Langkah lanjutan (manual, sebelum PR final
di-merge)**: jalankan `npm run build && npx lighthouse http://<host>/dompet --view` untuk 3 tema,
verifikasi skor Accessibility ≥90 dan sesuaikan bila skor rendah menunjuk ke temuan kontras di atas.

## Screenshot

Screenshot 3 breakpoint (mobile/tablet/desktop, tema default Biru-Putih) **belum dilampirkan** —
butuh dev server + browser nyata untuk capture, yang belum bisa dijalankan di lingkungan pengerjaan
iterasi ini (lihat catatan Lighthouse di atas, kendala yang sama). Langkah lanjutan: jalankan
`npm run dev`, buka `/dompet` di viewport 375px/768px/1280px, screenshot, lampirkan di sini sebelum
PR final di-merge.
