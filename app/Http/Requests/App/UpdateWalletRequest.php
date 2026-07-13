<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:60'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'in:cash_flow,saving,both,investment'],
            'is_active' => ['boolean'],
            'currency' => ['nullable', 'string', 'in:IDR,USD,EUR,SGD,MYR'],
        ];
    }
}
