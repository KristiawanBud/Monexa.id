<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class DompetFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Kompatibilitas: format lama kirim type/category_id sebagai string tunggal
        // (bookmark/localStorage `monexa_dompet_filters` user existing) — normalisasi jadi
        // array 1 elemen sebelum divalidasi sebagai array multi-select, supaya tidak rusak.
        foreach (['type', 'category_id'] as $field) {
            if ($this->has($field) && ! is_array($this->input($field))) {
                $value = $this->input($field);
                $this->merge([$field => $value === null || $value === '' ? [] : [$value]]);
            }
        }
    }

    public function rules(): array
    {
        return [
            // range/wallet_id/tab: sudah ada sebelumnya tanpa Form Request dan
            // punya fallback/whitelist sendiri di controller (mis. resolveRange()) — tetap dibuat
            // longgar di sini supaya tidak menambah mode gagal baru (422) untuk value yang dulunya
            // diterima dan di-fallback secara graceful. start_date/end_date BARU, ini yang wajib
            // divalidasi ketat sesuai kontrak B.1.
            'range' => 'nullable|string',
            'period' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'wallet_id' => 'nullable|string',
            'type' => 'nullable|array',
            'type.*' => 'in:income,expense,transfer',
            'category_id' => 'nullable|array',
            'category_id.*' => 'integer|exists:transaction_categories,id',
            'balance_group' => 'nullable|in:cash,bank,ewallet',
            'search' => 'nullable|string|max:255',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'tab' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
        ];
    }
}
