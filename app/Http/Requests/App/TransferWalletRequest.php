<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class TransferWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_wallet_id' => ['required', 'exists:user_wallets,id', 'different:to_wallet_id'],
            'to_wallet_id' => ['required', 'exists:user_wallets,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
            'transferred_at' => ['required', 'date'],
        ];
    }
}
