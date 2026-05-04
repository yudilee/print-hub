<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Online Threshold
    |--------------------------------------------------------------------------
    |
    | This value determines how many minutes can pass since an agent's last
    | heartbeat before it is considered offline. Agents with longer sync
    | intervals may need a higher threshold.
    |
    */
    'agent_online_threshold' => env('AGENT_ONLINE_THRESHOLD', 2),

    /*
    |--------------------------------------------------------------------------
    | Agent Auto-Update Configuration
    |--------------------------------------------------------------------------
    |
    | These values control the auto-update mechanism for TrayPrint agents.
    | Set AGENT_LATEST_VERSION to the newest available version, and provide
    | a download URL where the agent can fetch the installer.
    |
    */

    'agent_latest_version' => env('AGENT_LATEST_VERSION', '1.0.0'),
    'agent_download_url'   => env('AGENT_DOWNLOAD_URL', ''),
    'agent_release_notes'  => env('AGENT_RELEASE_NOTES', ''),
    'agent_sha256'         => env('AGENT_SHA256', ''),
    'agent_mandatory'      => env('AGENT_MANDATORY', false),

    /*
    |--------------------------------------------------------------------------
    | IP Whitelist for API Access
    |--------------------------------------------------------------------------
    |
    | Comma-separated list of IP addresses or CIDR ranges allowed to access
    | the API when IP whitelisting is enforced. Leave empty to allow all IPs.
    | Example: "192.168.1.0/24,10.0.0.1"
    |
    */

    'api_ip_whitelist' => env('API_IP_WHITELIST', ''),

];
