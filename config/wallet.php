<?php

return [
    // Batas maksimum jumlah per transfer wallet. null = tidak ada batas eksplisit
    // selain saldo dompet sumber (perilaku default).
    'max_transfer_amount' => env('WALLET_MAX_TRANSFER_AMOUNT'),
];
