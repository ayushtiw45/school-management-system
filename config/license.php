<?php

return [
    // Licensing is only enforced in the Windows desktop build (TDS_DESKTOP=true).
    'enabled' => (bool) env('LICENSE_ENABLED', env('TDS_DESKTOP', false)),

    // Free days before activation. 0 = the school must activate (₹1 trial via the website) before use.
    'trial_days' => (int) env('LICENSE_TRIAL_DAYS', 0),

    // Website the software asks for its newest license key (GET {url}/api/license/{installation id}).
    'server' => env('TDS_LICENSE_SERVER', env('TDS_VENDOR_WEBSITE')),

    // Ed25519 public key (base64) from the license admin page. Keys signed with the matching
    // secret key on your website are the only ones this app accepts.
    'public_key' => env('LICENSE_PUBLIC_KEY'),

    // First-run date written to the Windows registry by the launcher (survives a data reset).
    'first_run' => env('TDS_FIRST_RUN'),

    // Shown on the license screen so schools know whom to contact.
    'vendor' => [
        'name' => env('TDS_VENDOR_NAME', 'TDS School Management Software'),
        'phone' => env('TDS_VENDOR_PHONE'),
        'whatsapp' => env('TDS_VENDOR_WHATSAPP'),
        'email' => env('TDS_VENDOR_EMAIL'),
        'website' => env('TDS_VENDOR_WEBSITE'),
    ],

    'version' => env('TDS_VERSION', 'dev'),
];
