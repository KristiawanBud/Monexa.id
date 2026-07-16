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
            'amount' => ['required', 'numeric', 'min:1', function ($attribute, $value, $fail) {
                $max = config('wallet.max_transfer_amount');

                if ($max && $value > $max) {
                    $fail(__('wallet.validation.amount_exceeds_max', [
                        'max' => number_format((float) $max, 0, ',', '.'),
                    ]));
                }
            }],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:transaction_categories,id'],
            'transferred_at' => ['required', 'date'],
            'request_id' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'fee.numeric' => __('wallet.validation.fee_numeric'),
            'fee.min' => __('wallet.validation.fee_min'),
            'request_id.required' => __('wallet.validation.request_id_required'),
        ];
    }
}
