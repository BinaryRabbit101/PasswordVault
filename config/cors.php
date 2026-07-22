<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS)
    |--------------------------------------------------------------------------
    |
    | Only the token-authenticated lookup endpoint is exposed cross-origin, so
    | the in-page autofill filler (a bookmarklet or the Shortcuts "Run
    | JavaScript on Web Page" action running in a third-party login page) can
    | fetch a credential for the page it is on.
    |
    | Any origin may *ask*, but the fill token only ever returns the credential
    | for the request's real Origin (see LookupController) and the endpoint is
    | still gated to the tailnet by RequireLocalNetwork. No cookies are used —
    | auth is the token — so a wildcard origin without credentials is safe.
    |
    */

    'paths' => ['api/lookup'],

    'allowed_methods' => ['GET', 'OPTIONS'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 600,

    'supports_credentials' => false,

];
