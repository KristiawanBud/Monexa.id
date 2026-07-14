<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class DompetFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // range/type/wallet_id/category_id/tab: sudah ada sebelumnya tanpa Form Request dan
            // punya fallback/whitelist sendiri di controller (mis. resolveRange()) — tetap dibuat
            // longgar di sini supaya tidak menambah mode gagal baru (422) untuk value yang dulunya
            // diterima dan di-fallback secara graceful. start_date/end_date BARU, ini yang wajib
            // divalidasi ketat sesuai kontrak B.1.
            'range' => 'nullable|string',
            'period' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'wallet_id' => 'nullable|string',
            'type' => 'nullable|string',
            'category_id' => 'nullable|string',
            'search' => 'nullable|string|max:255',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'tab' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'show_archived' => 'nullable|boolean',
        ];
    }
}
