<?php

namespace App\Http\Requests\App;

use Illuminate\Foundation\Http\FormRequest;

class BalanceTrendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'range' => ['required', 'in:7d,30d'],
        ];
    }
}
