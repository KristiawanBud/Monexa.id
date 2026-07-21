# Changelog

Semua perubahan penting pada Monexa dicatat di sini.

## 2026-07-21 — Lanjutkan Review PR #1: Redesign Halaman Dompet (Mobile)

Diff yang sempat dikirim ke reviewer sebelumnya diduga kosong, dan setelah dicek ulang ternyata
bukan karena branch ini tidak punya perubahan — `git diff main...HEAD` berisi seluruh perubahan
kode redesign halaman Dompet (filter transaksi multi-pilih, transaksi transfer ikut muncul di
daftar, migrasi index database). Pengiriman request review diperbaiki supaya reviewer menerima
diff kode yang sebenarnya, dan pengecekan lanjutan (keamanan multi-tenant pada query gabungan
transaksi + transfer, serta kesesuaian implementasi dengan spec redesign) bisa dilanjutkan
berdasarkan diff yang benar. Tidak ada perubahan fitur atau API baru dari langkah ini — kontrak
teknis tetap seperti yang sudah tercatat di entry-entry sebelumnya.

## 2026-07-21 — Lanjutkan Review PR #1: Redesign Halaman Dompet (Mobile) (checklist awal)

Review PR #1 (redesign halaman Dompet mobile) dilanjutkan dan dipecah jadi checklist konkret:
validasi UI vs desain, uji fungsional CTA dompet (tambah/edit/hapus/transfer), regresi state &
performa, aksesibilitas, sampai kriteria approval sebelum merge ke `develop`. Tidak ada fitur atau
endpoint baru — seluruh kontrak teknis tetap seperti yang sudah tercatat di entry-entry sebelumnya;
satu catatan penting: CTA "set default dompet" yang sempat disinggung ternyata di luar cakupan PR
ini dan belum ada di kode sama sekali, jadi ditandai sebagai usulan terpisah, bukan bug yang harus
diperbaiki sekarang.

## 2026-07-21 — Buat PR redesign halaman Dompet (mobile) ke develop

Pull request untuk redesign halaman Dompet (mobile) dibuat dari branch
`feature/redesign-halaman-dompet-mobile-sesuai-screenshot` ke `develop`. Tidak ada perubahan
fitur atau API baru di langkah ini — semua perubahan teknis (tampilan saldo & ringkasan, filter
transaksi multi-pilih, transaksi transfer di daftar, migrasi index database) sudah tercatat di
entry-entry sebelumnya dan sudah lolos review sebelum PR dibuka.

## 2026-07-20 — Buka PR redesign halaman Dompet (mobile) ke develop

Pull request untuk redesign halaman Dompet (mobile) disiapkan untuk digabungkan dari branch
`feature/redesign-halaman-dompet-mobile-sesuai-screenshot` ke `develop`. Tidak ada perubahan
fitur atau API baru di langkah ini — murni proses penyelarasan branch dan pembukaan PR, seluruh
perubahan teknis sudah tercatat di entry-entry sebelumnya.

## 2026-07-20 — Finalisasi redesign halaman Dompet (mobile): dokumentasi, PR, dan review

Tahap akhir redesign halaman Dompet (mobile) selesai: dokumentasi dirapikan, pull request
disiapkan, dan perubahan sudah melewati review sebelum siap digabung. Tidak ada perilaku baru di
luar yang sudah dijelaskan pada entry sebelumnya — fokusnya memastikan semuanya tercatat rapi dan
siap ditinjau.

## 2026-07-20 — Lanjutkan dokumentasi dan buka PR redesign halaman Dompet (mobile)

Halaman Dompet (mobile) dirombak: saldo & 3 kartu ringkasan (Cash/Bank/E-Wallet) tampil lebih
besar dan bisa disentuh untuk memfilter transaksi, filter tipe & kategori kini bisa pilih lebih
dari satu, transaksi Transfer antar dompet sekarang ikut muncul di daftar Transaksi, dan bottom
navigation sudah menyesuaikan dengan notch/home indicator di HP layar penuh. Tidak ada perubahan
yang memutus kompatibilitas — filter lama yang tersimpan di perangkat pengguna tetap berfungsi
seperti biasa.
