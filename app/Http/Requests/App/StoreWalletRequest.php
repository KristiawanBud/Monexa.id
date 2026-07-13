<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class StoreWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id' => ['nullable', 'exists:banks,id'],
            'display_name' => ['required', 'string', 'max:60'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'initial_balance' => ['nullable', 'numeric', 'min:0'],
            'type' => ['required', 'in:cash_flow,saving,both,investment'],
            'is_saham' => ['boolean'],
            'currency' => ['nullable', 'string', 'in:IDR,USD,EUR,SGD,MYR'],
        ];
    }
}
