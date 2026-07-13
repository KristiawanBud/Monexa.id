# Changelog

## Redesign UI Dompet Responsif + Fondasi Theming (3 Tema) + Enum `wallet_transfer`

Scope: `docs/spec-lanjutkan-redesign-ui-dompet-responsif-fondasi-theming-biru-putih-hijau-putih-dark-dan-enum-wallet-transfer.md`.

### UI Dompet Responsif
- Breakpoint konten halaman Dompet dirapikan jadi satu skema konsisten (mobile `<640px`, tablet
  `640–1023px`, desktop `>=1024px`) di `Dompet.vue` dan seluruh `Components/Wallet/*.vue`.
  `AppLayout.vue` (shell app-wide) tetap 481px/1025px, di luar scope.
- Overlay scrim modal/drawer ditokenisasi jadi `--overlay-scrim` di 3 file tema (sebelumnya hardcoded
  `rgba(15,23,42,.45)`).
- Audit tap-target 44px untuk semua tombol ikon di scope Dompet.
- Commits: `c24b98b`, `5b32d22`, `0825862`.

### Fondasi Theming (3 Tema: Biru, Hijau, Gelap)
- Kolom `user_profiles.theme` (nullable, string) untuk persistensi preferensi tema per akun.
- Endpoint baru `PUT /account/theme` (`account.theme`) — validasi `Rule::in(['blue','green','dark'])`.
- `useTheme.js` composable: urutan resolusi `?theme=` → `localStorage` → shared prop Inertia `theme` →
  `prefers-color-scheme: dark` → `VITE_DEFAULT_THEME` → default `'blue'`.
- UI Settings > Appearance di `Account.vue` (ganti tema instan, tanpa reload).
- Token `--primary-contrast` ditambahkan ke 3 file tema (`#FFFFFF` untuk tema blue, `#0F172A` untuk
  tema green/dark, memenuhi kontras WCAG AA — detail perhitungan di `docs/theming-guide.md`).
- Lihat `docs/theming-guide.md` untuk mekanisme lengkap dan cara menambah tema baru.
- Commits: `c24b98b`, `5b32d22`, `0825862`.

### Enum `wallet_transfer`
- `App\Enums\WalletTransfer` (native backed enum, `string`): `Debit = 'debit'`, `Credit = 'credit'`,
  di-cast pada kolom `wallet_balance_logs.type` (kolom DB `enum('credit','debit')` sudah ada, tanpa
  migrasi konversi).
- Model Eloquent baru `App\Models\WalletBalanceLog` menggantikan akses `DB::table('wallet_balance_logs')`
  mentah di `WalletService.php`.
- `WalletService.php` (`applyTransaction`, `depositToSaving`, `transferBetweenWallets`) sekarang
  menggunakan `WalletBalanceLog::create()` dan `WalletTransfer::Debit`/`::Credit`, bukan magic string.
- Kontrak API `POST /dompet/transfer` (`wallets.transfer`) tidak berubah — arah debit/kredit tetap
  ditentukan server, bukan input user.
- Test baru: `tests/Unit/Enums/WalletTransferTest.php`, `tests/Unit/Models/WalletBalanceLogTest.php`,
  `tests/Feature/WalletTransferTest.php`, plus factory `UserWalletFactory` dan `WalletTransferFactory`.
- Commits: `c24b98b`, `5b32d22`.

### Housekeeping
- Migration `2026_07_14_000018_add_theme_to_users_table.php` menambahkan kolom `theme` duplikat &
  tidak terpakai di tabel `users` (di luar pola pipeline AI, lihat catatan PR). Kolom ini di-drop lewat
  migration baru `2026_07_14_000019_drop_theme_from_users_table.php` — sumber kebenaran preferensi
  tema tetap satu-satunya di `user_profiles.theme`.
