# Modul Dompet — Referensi Teknis (Frontend)

Dokumen referensi internal untuk halaman `/dompet` (Vue 3 `<script setup>` + Inertia). Ditulis
untuk developer yang perlu menambah/mengubah komponen di modul ini. Untuk panduan tema, lihat
`docs/theming-guide.md`. Untuk laporan perubahan per iterasi ke owner/CEO, lihat
`docs/monexa/wallet_ui_redesign.md`.

## Struktur komponen

```
resources/js/Pages/App/Dompet.vue        — halaman utama (tab Transaksi/Dompet/Tagihan, semua modal)
resources/js/Layouts/AppLayout.vue       — shell aplikasi: sidebar desktop, bottom-nav + FAB mobile,
                                            bottom sheet "Tambah Transaksi"
resources/js/Components/Wallet/
  BalanceSummaryCard.vue   — hero saldo gradien + breakdown Cash/Bank/E-Wallet
  QuickActions.vue         — toolbar aksi cepat (desktop ≥481px): income/expense/transfer
  CardDompet.vue           — kartu 1 dompet di tab Dompet (grid 1→2→3 kolom)
  TransactionDateGroup.vue — heading tanggal + daftar TransactionItem
  TransactionItem.vue      — 1 baris transaksi (ikon kategori, catatan, jumlah +/-)
  FilterDrawer.vue         — panel filter (rentang tanggal custom, dompet, tipe, kategori)
  CategoryChipFilter.vue   — chip kategori quick-filter
  ExportButton.vue         — tombol export CSV (pakai filter aktif)
  EmptyState.vue / ErrorState.vue / SkeletonLoader.vue — state kosong/gagal/loading generik
```

## Props penting

| Komponen | Props kunci | Catatan |
|---|---|---|
| `BalanceSummaryCard` | `totalBalance`, `activeWalletsCount`, `cashTotal`/`bankTotal`/`ewalletTotal`, `balanceHidden`, `showRangeStats`, `totalIncome`/`totalExpense`, `rangeLabel` | emit `update:balanceHidden`, `add` |
| `TransactionItem` | `transaction` (object: `type`, `amount`, `note`, `category`, `category_emoji`, `wallet`, `transacted_at_time`) | emit `click` |
| `TransactionDateGroup` | `label`, `transactions` (array) | emit `item-click`, meneruskan dari `TransactionItem` |
| `CardDompet` | `wallet` (object: `display_name`, `type`, `balance`, `bank_color`, `bank_initial`, `logo_url`, `is_saham`), `balanceHidden` | emit `click`; slot `#actions` opsional |
| `EmptyState` | `icon`, `title`, `actionLabel` | emit `action` |
| `ErrorState` | `message`, `retryLabel` | emit `retry` |
| `SkeletonLoader` | `variant`: `'card'\|'list-item'\|'hero'` | dimensi per varian match komponen asli (kontrak CLS<0.1) |

## Relasi endpoint

Pola: `GET /dompet` mengembalikan Inertia props (`Dompet.vue`); mutasi lain redirect + flash
(`back()->with('success'|'error', ...)`), kecuali 2 endpoint JSON existing (`dompet.logs`,
`dompet.export`/CSV pakai `Content-Disposition`, bukan JSON API biasa — lihat kontrak masing-masing
di controller).

| Route name | Method | Dipanggil dari |
|---|---|---|
| `dompet.index` | GET | load awal halaman + semua `router.get(...)` filter/search/range |
| `dompet.store` | POST | `txForm.post(...)` — tambah transaksi |
| `dompet.update` | PUT | `txForm.put(...)` — edit transaksi |
| `dompet.destroy` | DELETE | `router.delete(...)` — hapus transaksi (tombol "🗑️ Hapus Transaksi") |
| `dompet.duplicate` | POST | `router.post(...)` — duplikasi transaksi (tombol "📋 Duplikasi" di modal edit, **Round 4**) |
| `dompet.logs` | GET (JSON) | riwayat edit transaksi (dipakai di luar `Dompet.vue` inti) |
| `wallets.store` / `wallets.update` / `wallets.destroy` | POST/PUT/DELETE | modal Tambah/Edit/Hapus Dompet |
| `wallets.transfer` | POST | modal Transfer Antar Dompet |
| `bills.store` / `bills.update` / `bills.destroy` | POST/PUT/DELETE | modal Tambah Tagihan |
| `bills.pay` | POST | modal Bayar Tagihan |

## Katalog `trackEvent`

`trackEvent()` (`resources/js/lib/analytics.js`) masih stub `console.debug` tanpa consumer backend —
aman ditambah event baru tanpa migrasi. Tabel di bawah kumulatif Round 1-4 (additive, tidak ada
event yang dihapus/di-rename).

| Event | Titik panggil | Payload |
|---|---|---|
| `dompet_filter_apply` | Submit `FilterDrawer` (`applyFilters`) | filter yang diterapkan |
| `dompet_category_chip` | Pilih chip kategori (`onQuickCategorySelect`) | `{ category_id }` |
| `dompet_search` | Debounce input pencarian (400ms) | `{ query }` |
| `dompet_quick_action` | `openAddIncome`/`openAddExpense`/`openTransfer` (toolbar desktop) | `{ action }` |
| `dompet_tx_list_scroll_depth` | Scroll list transaksi >80% | `{ percent: 80 }` |
| `dompet_export_csv` | Klik tombol export (`ExportButton.vue`) | `{ filters }` |
| `dompet_tx_duplicate` | Klik "📋 Duplikasi" di modal edit transaksi | `{ transaction_id }` |
| `wallet_opened` **(Round 4)** | `onMounted()` `Dompet.vue`, sekali per kunjungan halaman | `{}` |
| `transaction_filter_used` **(Round 4)** | Sama dengan `dompet_filter_apply`, `dompet_category_chip`, `dompet_search`, dan `changeRange()` | `{ filters }` (reuse state filter aktif) |
| `quick_add_clicked` **(Round 4)** | `QuickActions.vue` toolbar desktop (bareng `dompet_quick_action`) & `AppLayout.vue` (FAB mobile, tombol sidebar, tiap opsi bottom sheet `goTo()`) | `{ action, surface: 'dompet-toolbar'\|'global-fab' }` |

## Test

Unit/snapshot komponen: `resources/js/**/*.spec.js` (Vitest + `@vue/test-utils` + `happy-dom`),
dijalankan via `npm run test`. E2E sederhana (responsive 3 viewport + ganti tema via `?theme=`):
`tests/e2e/*.spec.js` (Playwright), dijalankan via `npm run test:e2e` (butuh `E2E_TEST_EMAIL` /
`E2E_TEST_PASSWORD` untuk login, dan server Laravel berjalan di `PLAYWRIGHT_BASE_URL`).
