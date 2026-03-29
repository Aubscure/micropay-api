<?php

// config/fraud.php

return [
    'velocity' => [
        // Flag merchants who process more than 10 transactions in 5 minutes
        'max_per_5_minutes' => 10,
    ],

    'amount' => [
        // Single transaction above PHP 50,000 triggers large amount rule
        'large_single_centavos' => 5000000,

        // Round amounts above PHP 10,000 are suspicious
        'round_amount_threshold' => 1000000,
    ],
];