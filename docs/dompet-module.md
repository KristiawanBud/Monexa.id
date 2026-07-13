# Modul Dompet — Struktur, Props, & Event Tracking

Ringkasan struktur frontend halaman Dompet (`resources/js/Pages/App/Dompet.vue`) setelah redesign
UI responsif + tema (lihat `docs/spec-redesign-ui-dompet-responsif-sistem-tema-hijau-putih-dark-mode.md`
dan spec Round 1-nya). Dokumen ini murni referensi struktur — kontrak API ada di spec, bukan di sini.

## Struktur komponen

```
Pages/App/Dompet.vue                 — halaman utama, 3 tab: Transaksi / Dompet / Tagihan
Components/Wallet/
  BalanceSummaryCard.vue             — hero saldo + breakdown Cash/Bank/E-Wallet + badge persentase
                                        perubahan saldo 7 hari (fetch sendiri saat mount, terpisah dari
                                        BalanceTrendChart karena hero tampil di semua tab)
  BalanceTrendChart.vue              — mini sparkline tren saldo 7/30 hari (lazy fetch on-scroll) + label
                                        persentase perubahan di header (`percent_change` dari respons API)
  CardDompet.vue                     — kartu 1 dompet: badge Utama/Diarsipkan, quick actions
  CategoryChipFilter.vue             — chip filter kategori cepat
  EmptyState.vue / ErrorState.vue    — state kosong/gagal generik
  ExportButton.vue                   — export CSV transaksi
  FilterDrawer.vue                   — filter lengkap (tipe, dompet, tanggal, kategori)
  QuickActions.vue                   — toolbar tambah pemasukan/pengeluaran/transfer (desktop)
  SkeletonLoader.vue                 — skeleton generik (variant: hero/card/list-item)
  TransactionDateGroup.vue           — grup transaksi per tanggal
  TransactionItem.vue                — baris 1 transaksi
Components/ThemeToggle.vue           — toggle tema (Biru/Hijau/Gelap), gated VITE_ENABLE_THEME_TOGGLE
Composables/
  useTheme.js                        — resolusi tema aktif + auto-detect prefers-color-scheme
  usePullToRefresh.js                — gesture tarik-turun mobile (≤480px) → router.reload()
lib/
  format.js                          — formatRupiah, formatShort, formatCurrency (multi-currency)
  analytics.js                       — trackEvent() stub (console.debug, belum ada backend consumer)
```

## Props utama `Dompet.vue` (dari `TransactionController@index`)

| Prop | Tipe | Keterangan |
|---|---|---|
| `transactions` | Object (paginator) | `data[]`, `current_page`, `last_page` — dipakai infinite scroll |
| `wallets` | Array | tiap item: `currency`, `is_primary`, `is_archived`, `last_transaction_at` (BARU Round 2) |
| `bills` | Array | tagihan aktif |
| `banks`, `categories` | Array | referensi untuk form |
| `total_income`/`total_expense`/`total_balance` | Number | ringkasan sesuai range aktif |
| `include_archived` | Boolean | state awal toggle "Tampilkan yang diarsipkan" |
| `sort_by` | String (`'date'`\|`'amount'`) | state awal dropdown sort transaksi (Round 3 §A.3), default `'date'` |
| `hide_balance` | Boolean | state awal toggle sembunyikan saldo, dibaca dari `user_profiles.hide_balance` (Round 3 §A.5) — `balanceHidden` di `Dompet.vue` diinisialisasi dari prop ini, bukan lokal, dan disinkronkan lewat endpoint `dashboard.toggle-balance` yang sama dipakai `Dashboard.vue` |

`CardDompet.vue` menerima 1 wallet object dan meng-emit `set-primary` / `archive` / `restore` /
`click` (untuk edit) ke parent — parent yang memanggil `router.patch(...)` ke endpoint
`wallets.setPrimary` / `wallets.archive` / `wallets.restore`. Salin nomor rekening murni
client-side (`navigator.clipboard.writeText`), tidak emit ke parent.

## Pola infinite scroll (transaksi)

`Dompet.vue` menyimpan akumulasi halaman di `displayedTransactions` (local ref), terpisah dari
`props.transactions` (yang selalu berisi 1 halaman terakhir dari server). Sentinel
(`<div ref="sentinelRef">`) di-observe via `IntersectionObserver`; saat terlihat →
`router.get(..., { page: next, only: ['transactions'] })` → watcher pada `props.transactions`
membedakan "load more" (append) vs "filter berubah" (reset) lewat flag internal
`appendingNextPage`.

## Katalog `trackEvent`

Semua lewat `lib/analytics.js` — saat ini stub `console.debug`, siap diisi provider analytics
tanpa mengubah titik panggil di UI.

| Event | Titik panggil | Payload |
|---|---|---|
| `dompet_filter_apply` | `FilterDrawer` submit | filter form |
| `dompet_category_chip` | Chip kategori cepat | `{ category_id }` |
| `dompet_search` | Search transaksi (debounced) | `{ query }` |
| `dompet_quick_action` | Tombol tambah cepat | `{ action: 'add-income'\|'add-expense'\|'transfer' }` |
| `dompet_export_csv` | `ExportButton` | `{ filters }` |
| `dompet_tx_list_scroll_depth` | Scroll depth >80% tab Transaksi | `{ percent: 80 }` |
| `dompet_infinite_scroll_load_more` | Sentinel infinite scroll terlihat | `{ page }` |
| `dompet_pull_to_refresh` | Gesture tarik-turun mobile | — |
| `dompet_wallet_create` | Submit tambah dompet baru | — |
| `dompet_wallet_update` | Submit edit dompet | `{ wallet_id }` |
| `dompet_wallet_set_primary` | `CardDompet` → "Jadikan Utama" | `{ wallet_id }` |
| `dompet_wallet_archive` | `CardDompet` → "Arsipkan" | `{ wallet_id }` |
| `dompet_wallet_restore` | `CardDompet` → "Pulihkan" (dompet diarsipkan) | `{ wallet_id }` |
| `dompet_copy_account_number` | `CardDompet` → "Salin No. Rekening" | `{ wallet_id }` |
| `dompet_transfer_submit` | Submit form transfer antar dompet | `{ from_wallet_id, to_wallet_id }` |
| `dompet_theme_change` | `ThemeToggle` pilih tema | `{ theme }` |
| `dompet_sort_change` | Dropdown sort transaksi (header list, tab Transaksi) | `{ sort_by: 'date'\|'amount' }` |

## Catatan scope

- Virtualisasi list transaksi (`vue-virtual-scroller` atau setara) **belum diimplementasikan** —
  sesuai spec, ini hanya perlu dievaluasi kalau akumulasi item di DOM lewat ~100 baris setelah
  infinite scroll berjalan di produksi, bukan diimplementasikan preventif.
- Form tambah/edit dompet belum punya input `currency` di UI (selalu default `IDR` dari backend)
  — spec Round 2 hanya meminta field ini bisa **ditampilkan** (`CardDompet.vue`), bukan diedit dari
  form tambah/edit. Kalau owner minta wallet non-IDR bisa dibuat dari UI, itu extension terpisah.
