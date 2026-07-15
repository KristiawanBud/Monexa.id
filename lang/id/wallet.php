<?php

return [
    'insufficient_balance_with_fee' => 'Saldo :wallet tidak cukup. Saldo saat ini: Rp :balance. Transfer ini butuh Rp :amount + biaya Rp :fee = Rp :total.',
    'insufficient_balance_for_reversal' => 'Saldo :wallet tidak cukup untuk membatalkan transfer ini.',

    'validation' => [
        'fee_numeric' => 'Biaya transfer harus berupa angka.',
        'fee_min' => 'Biaya transfer tidak boleh negatif.',
        'request_id_required' => 'ID permintaan wajib disertakan untuk mencegah transfer ganda.',
    ],
];
