<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | The log channel the APNs and FCM clients write to: every accepted push
    | at debug, every refused one at warning, with the target, status, reason
    | and the service's message ID. Null uses the application's default
    | channel.
    |
    */

    'logging' => [
        'channel' => env('PUSH_LOG_CHANNEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Cache
    |--------------------------------------------------------------------------
    |
    | The APNs provider token (one per auth key, refreshed every 50 minutes)
    | and the FCM access token (one per service account, refreshed hourly)
    | are shared through this cache store, so a fleet of queue workers mints
    | one token per key instead of one per process. Use a shared store
    | (redis, database) behind more than one server; null uses the
    | application's default store. Disable to keep a token per process.
    |
    */

    'token_cache' => [
        'enabled' => (bool) env('PUSH_TOKEN_CACHE', true),
        'store' => env('PUSH_TOKEN_CACHE_STORE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    |
    | Timeouts in seconds, an optional proxy, and how many pushes a batch
    | multiplexes over the connection at once.
    |
    */

    'http' => [
        'timeout' => (int) env('PUSH_HTTP_TIMEOUT', 30),
        'connect_timeout' => (int) env('PUSH_HTTP_CONNECT_TIMEOUT', 10),
        'concurrency' => (int) env('PUSH_HTTP_CONCURRENCY', 50),
        'proxy' => env('PUSH_HTTP_PROXY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | APNs
    |--------------------------------------------------------------------------
    |
    | 'environment' is the gateway apps use unless they set their own:
    | "production" for App Store and TestFlight builds, "sandbox" for Xcode
    | builds. With 'sandbox_fallback' on, a production push answered with
    | BadDeviceToken is sent once more through the sandbox gateway, which is
    | where a token from a development build lives; the answer from the
    | sandbox then replaces the production one.
    |
    */

    'apns' => [
        'environment' => env('PUSH_APNS_ENVIRONMENT', 'production'),
        'port' => (int) env('PUSH_APNS_PORT', 443),
        'sandbox_fallback' => (bool) env('PUSH_APNS_SANDBOX_FALLBACK', false),
        'token_refresh_after' => (int) env('PUSH_APNS_TOKEN_REFRESH_AFTER', 3000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Teams
    |--------------------------------------------------------------------------
    |
    | One APNs auth key per Apple developer team. The key signs pushes for
    | every iOS app in the team, and every app in the team shares one
    | connection. 'private_key' is the .p8 file's path or its PEM contents
    | (line breaks flattened by an env channel are restored).
    |
    */

    'teams' => [
        // 'acme' => [
        //     'team_id' => env('ACME_TEAM_ID'),
        //     'key_id' => env('ACME_APNS_KEY_ID'),
        //     'private_key' => env('ACME_APNS_PRIVATE_KEY'),
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Apps
    |--------------------------------------------------------------------------
    |
    | Each app you push to, by the name your code uses with Push::app().
    | An 'apns' app names its bundle ID and team, and may set its own
    | 'environment'. An 'fcm' app names its Firebase service account: the
    | key file's path or its JSON contents.
    |
    */

    'apps' => [
        // 'acme-ios' => [
        //     'driver' => 'apns',
        //     'bundle_id' => 'com.acme.app',
        //     'team' => 'acme',
        //     'environment' => 'production',
        // ],
        // 'acme-android' => [
        //     'driver' => 'fcm',
        //     'service_account' => env('ACME_FIREBASE_SERVICE_ACCOUNT_JSON'),
        // ],
    ],
];
