<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Network access
    |--------------------------------------------------------------------------
    |
    | Requests from IPs outside these CIDRs are rejected with a 403 by the
    | RequireLocalNetwork middleware. 100.64.0.0/10 is the Tailscale CGNAT
    | range — tailnet traffic arrives with a source IP in that block.
    |
    */

    'network' => [
        'allowed_cidrs' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ALLOWED_CIDRS', '127.0.0.1/32,::1/128'))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Clipboard
    |--------------------------------------------------------------------------
    |
    | Seconds before the frontend attempts to clear a copied secret from the
    | clipboard (best effort — iOS Safari blocks background writes).
    |
    */

    'clipboard_clear_seconds' => (int) env('VAULT_CLIPBOARD_CLEAR_SECONDS', 30),

    /*
    |--------------------------------------------------------------------------
    | API password lookup
    |--------------------------------------------------------------------------
    |
    | Whether the token-authenticated /api/lookup endpoint may include the
    | decrypted password in its response (used by the iOS Shortcut flow).
    |
    */

    'api_returns_passwords' => (bool) env('VAULT_API_RETURNS_PASSWORDS', true),

    /*
    |--------------------------------------------------------------------------
    | Autofill staging
    |--------------------------------------------------------------------------
    |
    | Seconds the "Autofill" button's staged item is honoured by the in-page
    | filler before it expires. Kept short — it only needs to survive the hop
    | from tapping Autofill to running the filler on the opened page.
    |
    */

    'fill_stage_seconds' => (int) env('VAULT_FILL_STAGE_SECONDS', 60),

];
