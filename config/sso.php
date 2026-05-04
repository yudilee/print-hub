<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SSO / SAML Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control Single Sign-On integration. Currently supports
    | SAML2 via the onelogin/php-saml library (requires composer install).
    |
    | When enabled, a "Login with SSO" button appears on the login page.
    | Authentication is handled by the external Identity Provider (IdP).
    |
    */

    'enabled' => env('SSO_ENABLED', false),

    'provider' => env('SSO_PROVIDER', 'saml2'),

    /*
    |--------------------------------------------------------------------------
    | SAML2 Identity Provider (IdP) Settings
    |--------------------------------------------------------------------------
    |
    | These values are provided by your SAML2-compatible IdP (e.g., Azure AD,
    | Okta, Keycloak). The SP (Service Provider) entity ID and ACS URL
    | identify this application to the IdP.
    |
    */

    'idp_entity_id' => env('SAML2_IDP_ENTITY_ID', ''),

    'idp_sso_url' => env('SAML2_IDP_SSO_URL', ''),

    'idp_x509_cert' => env('SAML2_IDP_X509_CERT', ''),

    'sp_entity_id' => env('SAML2_SP_ENTITY_ID', 'print-hub'),

    'sp_acs_url' => env('SAML2_SP_ACS_URL', '/auth/sso/callback'),

    /*
    |--------------------------------------------------------------------------
    | Auto-Provisioning
    |--------------------------------------------------------------------------
    |
    | When enabled, users who authenticate via SSO for the first time will
    | automatically have a user account created in the local database.
    |
    */

    'auto_provision' => env('SSO_AUTO_PROVISION', true),

    'default_role' => env('SSO_DEFAULT_ROLE', 'user'),

];
