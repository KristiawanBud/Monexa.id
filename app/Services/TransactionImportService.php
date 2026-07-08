<?php

namespace App\Services;

use App\Models\ImportSession;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\UserWallet;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TransactionImportService
{
    public function __construct(
        private GeminiService $gemini,
        private WalletService $walletService
    ) {}

    // ── Step 1: Parse file → preview data ────────
    public function parseFile(ImportSession $session): array
    {
        $session->update(['status' => 'parsing']);

        try {
            $path = storage_path("app/public/{$session->file_path}");

            // Baca file
            $rawRows = $this->readFile($path, $session->filename);

            if (empty($rawRows)) {
                throw new \Exception('File kosong atau tidak bisa dibaca.');
            }

            // AI detect format & mapping kolom
            $mapping = $this->detectFormatWithAI($rawRows, $session);

            // Preview 20 baris pertama
            $preview = $this->buildPreview($rawRows, $mapping);

            $session->update([
                'status'         => 'preview',
                'preview_data'   => $preview,
                'column_mapping' => $mapping,
                'total_rows'     => count($rawRows) - 1, // minus header
            ]);

            return [
                'success' => true,
                'preview' => $preview,
                'mapping' => $mapping,
                'total'   => $session->total_rows,
            ];

        } catch (\Exception $e) {
            $session->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            Log::error("ImportService parseFile error: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Step 2: Import confirmed rows ke DB ───────
    public function importConfirmed(ImportSession $session, string $walletId, array $overrides = []): array
    {
        $session->update(['status' => 'importing']);

        $path    = storage_path("app/public/{$session->file_path}");
        $rawRows = $this->readFile($path, $session->filename);
        $mapping = $session->column_mapping;
        $user    = $session->user;
        $wallet  = UserWallet::findOrFail($walletId);

        // Ambil semua kategori untuk matching
        $categories = TransactionCategory::forUser($user->id)
            ->keyBy(fn($c) => strtolower($c->name));

        $imported = 0;
        $skipped  = 0;
        $errors   = [];

        // Skip header row
        $dataRows = array_slice($rawRows, 1);

        foreach ($dataRows as $i => $row) {
            try {
                $parsed = $this->parseRow($row, $mapping, $overrides);

                if (!$parsed || !$parsed['amount'] || !$parsed['date']) {
                    $skipped++;
                    continue;
                }

                // Match kategori
                $categoryId = $this->matchCategory($parsed['note'] ?? '', $categories, $user->id);

                DB::transaction(function () use ($user, $wallet, $parsed, $categoryId, $session) {
                    $tx = $user->transactions()->create([
                        'wallet_id'     => $wallet->id,
                        'category_id'   => $categoryId,
                        'type'          => $parsed['type'],
                        'amount'        => abs($parsed['amount']),
                        'note'          => $parsed['note'],
                        'transacted_at' => $parsed['date'],
                        'source'        => 'manual', // import dianggap manual
                        'created_by'    => $user->id,
                    ]);

                    $this->walletService->applyTransaction($tx);
                });

                $imported++;

            } catch (\Exception $e) {
                $errors[] = "Baris " . ($i + 2) . ": {$e->getMessage()}";
            }
        }

        $session->update([
            'status'        => 'done',
            'imported_rows' => $imported,
            'skipped_rows'  => $skipped,
            'error_rows'    => count($errors),
            'errors'        => $errors,
        ]);

        return compact('imported', 'skipped', 'errors');
    }

    // ── Read file (Excel/CSV) ─────────────────────
    private function readFile(string $path, string $filename): array
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            return $this->readCsv($path);
        }

        // Excel via PhpSpreadsheet
        $spreadsheet = IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cells    = [];
            $iterator = $row->getCellIterator();
            $iterator->setIterateOnlyExistingCells(false);
            foreach ($iterator as $cell) {
                $cells[] = $cell->getValue();
            }
            // Skip completely empty rows
            if (array_filter($cells, fn($c) => $c !== null && $c !== '')) {
                $rows[] = $cells;
            }
            if (count($rows) > 2001) break; // max 2000 baris
        }

        return $rows;
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                if (array_filter($row)) $rows[] = $row;
                if (count($rows) > 2001) break;
            }
            fclose($handle);
        }
        return $rows;
    }

    // ── AI Detect Format & Mapping Kolom ─────────
    private function detectFormatWithAI(array $rows, ImportSession $session): array
    {
        // Ambil 5 baris pertama untuk AI analisa
        $sample = array_slice($rows, 0, 5);
        $sampleStr = implode("\n", array_map(fn($r) => implode(' | ', array_map('strval', $r)), $sample));

        $prompt = "Kamu adalah parser format file transaksi keuangan Indonesia.\n\n"
            . "Analisa sampel data ini dan tentukan mapping kolom:\n\n"
            . "```\n{$sampleStr}\n```\n\n"
            . "Kembalikan HANYA JSON ini (tanpa teks lain):\n"
            . "{\n"
            . '  "source_app": "bca"|"mandiri"|"bni"|"bri"|"jenius"|"gopay"|"ovo"|"dana"|"shopeepay"|"generic_csv"|"generic_excel",'."\n"
            . '  "has_header": true|false,'."\n"
            . '  "date_col": 0,'."\n"
            . '  "date_format": "d/m/Y"|"Y-m-d"|"d-m-Y"|"d M Y"|"other",'."\n"
            . '  "amount_col": 1,'."\n"
            . '  "debit_col": null,'."\n"
            . '  "credit_col": null,'."\n"
            . '  "note_col": 2,'."\n"
            . '  "type_col": null,'."\n"
            . '  "balance_col": null,'."\n"
            . '  "skip_rows": 0'."\n"
            . "}\n\n"
            . "Catatan: amount_col untuk kolom yang berisi nominal gabungan (positif=masuk, negatif=keluar).\n"
            . "Jika ada kolom debit & kredit terpisah, isi debit_col dan credit_col, biarkan amount_col null.";

        $result = $this->gemini->generate($prompt);

        if (!$result['success']) {
            // Fallback: mapping default
            return $this->defaultMapping();
        }

        try {
            $cleaned = preg_replace('/```json|```/i', '', $result['text']);
            $mapping = json_decode(trim($cleaned), true);

            if ($mapping) {
                $session->update(['source_app' => $mapping['source_app'] ?? 'ai_detect']);
                return $mapping;
            }
        } catch (\Exception $e) {}

        return $this->defaultMapping();
    }

    // ── Build Preview ─────────────────────────────
    private function buildPreview(array $rows, array $mapping): array
    {
        $skipRows  = $mapping['skip_rows'] ?? 0;
        $hasHeader = $mapping['has_header'] ?? true;
        $startIdx  = $hasHeader ? 1 + $skipRows : $skipRows;
        $dataRows  = array_slice($rows, $startIdx, 20);

        $preview = [];
        foreach ($dataRows as $row) {
            $parsed = $this->parseRow($row, $mapping);
            if ($parsed) $preview[] = $parsed;
        }

        return $preview;
    }

    // ── Parse Single Row ─────────────────────────
    private function parseRow(array $row, array $mapping, array $overrides = []): ?array
    {
        try {
            $dateCol   = $mapping['date_col'] ?? 0;
            $noteCol   = $mapping['note_col'] ?? null;
            $amountCol = $mapping['amount_col'] ?? null;
            $debitCol  = $mapping['debit_col'] ?? null;
            $creditCol = $mapping['credit_col'] ?? null;

            // Date
            $rawDate = $row[$dateCol] ?? null;
            $date    = $this->parseDate($rawDate, $mapping['date_format'] ?? 'auto');
            if (!$date) return null;

            // Note
            $note = $noteCol !== null ? ($row[$noteCol] ?? '') : '';
            $note = trim(str_replace(['"', "'"], '', $note));

            // Amount & type
            $amount = 0;
            $type   = 'expense';

            if ($amountCol !== null) {
                $raw    = $this->cleanNumber($row[$amountCol] ?? '0');
                $amount = abs($raw);
                $type   = $raw >= 0 ? 'income' : 'expense';
            } elseif ($debitCol !== null || $creditCol !== null) {
                $debit  = $this->cleanNumber($row[$debitCol ?? -1] ?? '0');
                $credit = $this->cleanNumber($row[$creditCol ?? -1] ?? '0');
                if ($credit > 0) {
                    $amount = $credit;
                    $type   = 'income';
                } else {
                    $amount = $debit;
                    $type   = 'expense';
                }
            }

            if ($amount <= 0) return null;

            return [
                'date'   => $date,
                'amount' => $amount,
                'type'   => $type,
                'note'   => $note ?: 'Import',
            ];

        } catch (\Exception $e) {
            return null;
        }
    }

    // ── Match Kategori ─────────────────────────────
    private function matchCategory(string $note, $categories, string $userId): ?int
    {
        $note = strtolower($note);

        // Rule-based matching dulu (cepat, tidak pakai AI)
        $rules = [
            'makan|minum|food|resto|warung|cafe|kopi|lunch|dinner|breakfast|nasi|ayam|bakso' => 'makan & minum',
            'gojek|grab|ojek|bensin|bbm|toll|parkir|bus|kereta|commuter|transjakarta' => 'transport',
            'listrik|pln|air|pdam|internet|wifi|telpon|pulsa|token' => 'tagihan',
            'indomaret|alfamart|supermarket|hypermart|giant|belanja|grocery' => 'belanja harian',
            'gaji|salary|upah|thr' => 'gaji',
            'transfer|topup|top up' => null, // skip
        ];

        foreach ($rules as $pattern => $catName) {
            if (preg_match("/{$pattern}/i", $note)) {
                if ($catName === null) return null;
                $cat = $categories->get($catName);
                if ($cat) return $cat->id;
            }
        }

        return null; // biarkan null, user bisa set sendiri
    }

    // ── Helpers ───────────────────────────────────
    private function parseDate(?string $raw, string $format = 'auto'): ?string
    {
        if (!$raw) return null;
        $raw = trim($raw);

        // Coba berbagai format tanggal Indonesia
        $formats = [
            'd/m/Y', 'Y-m-d', 'd-m-Y', 'd M Y', 'Y/m/d',
            'd/m/y', 'j/n/Y', 'j/n/y', 'd-M-Y', 'Y-m-d H:i:s',
            'd/m/Y H:i', 'd M Y H:i:s',
        ];

        // Ganti nama bulan Indo ke angka
        $raw = str_replace(
            ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'],
            ['January','February','March','April','May','June','July','August','September','October','November','December'],
            $raw
        );

        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $raw);
            if ($dt) return $dt->format('Y-m-d');
        }

        // Coba strtotime sebagai fallback
        $ts = strtotime($raw);
        return $ts ? date('Y-m-d', $ts) : null;
    }

    private function cleanNumber(mixed $raw): float
    {
        if (is_numeric($raw)) return (float) $raw;
        $str = (string) $raw;
        // Hapus Rp, spasi, titik ribuan
        $str = preg_replace('/[Rp\s]/', '', $str);
        // Handle format 1.000.000 (titik sebagai separator ribuan)
        if (substr_count($str, '.') > 1 || (strpos($str, '.') !== false && strpos($str, ',') === false)) {
            $str = str_replace('.', '', $str);
        }
        $str = str_replace(',', '.', $str);
        return (float) $str;
    }

    private function defaultMapping(): array
    {
        return [
            'source_app'  => 'generic_excel',
            'has_header'  => true,
            'date_col'    => 0,
            'date_format' => 'auto',
            'amount_col'  => 1,
            'debit_col'   => null,
            'credit_col'  => null,
            'note_col'    => 2,
            'type_col'    => null,
            'balance_col' => null,
            'skip_rows'   => 0,
        ];
    }
}
