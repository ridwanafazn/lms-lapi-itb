<?php

return [
    // No gateway token, mandate, or automatic debit is used for renewal invoices.
    'manual_transfer' => [
        'bank_name' => env('BILLING_BANK_NAME', ''),
        'account_number' => env('BILLING_BANK_ACCOUNT_NUMBER', ''),
        'account_name' => env('BILLING_BANK_ACCOUNT_NAME', ''),
        'instructions' => env('BILLING_BANK_INSTRUCTIONS', ''),
    ],
];
