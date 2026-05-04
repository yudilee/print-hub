@extends('admin.layout')

@section('title', 'SSO Settings')

@section('content')
<div class="page-header">
    <h1>Single Sign-On (SSO) Settings</h1>
    <p>Configure SAML2-based authentication for your organization.</p>
</div>

<div class="card">
    <div class="card-header">
        <h2>Current Configuration</h2>
    </div>

    <table>
        <thead>
            <tr>
                <th>Setting</th>
                <th>Value</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Status</td>
                <td>
                    @if(config('sso.enabled'))
                        <span class="badge badge-success">Enabled</span>
                    @else
                        <span class="badge badge-warning">Disabled</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td>Provider</td>
                <td><span class="mono">{{ config('sso.provider', 'saml2') }}</span></td>
            </tr>
            <tr>
                <td>IdP Entity ID</td>
                <td><span class="mono">{{ config('sso.idp_entity_id') ?: '(not set)' }}</span></td>
            </tr>
            <tr>
                <td>IdP SSO URL</td>
                <td><span class="mono">{{ config('sso.idp_sso_url') ?: '(not set)' }}</span></td>
            </tr>
            <tr>
                <td>SP Entity ID</td>
                <td><span class="mono">{{ config('sso.sp_entity_id', 'print-hub') }}</span></td>
            </tr>
            <tr>
                <td>ACS URL</td>
                <td><span class="mono">{{ url(config('sso.sp_acs_url', '/auth/sso/callback')) }}</span></td>
            </tr>
            <tr>
                <td>Auto-Provision</td>
                <td>
                    @if(config('sso.auto_provision'))
                        <span class="badge badge-success">Enabled</span>
                    @else
                        <span class="badge badge-warning">Disabled</span>
                    @endif
                </td>
            </tr>
            <tr>
                <td>Default Role</td>
                <td><span class="mono">{{ config('sso.default_role', 'user') }}</span></td>
            </tr>
            <tr>
                <td>SP Metadata URL</td>
                <td>
                    <a href="{{ route('sso.metadata') }}" target="_blank" class="mono">
                        {{ route('sso.metadata') }}
                    </a>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="card">
    <div class="card-header">
        <h2>How to Configure</h2>
    </div>
    <div style="font-size: 0.875rem; line-height: 1.7; color: var(--text-muted);">
        <p>SSO settings are managed via environment variables in your <code class="mono">.env</code> file.</p>

        <h3 style="margin: 1rem 0 0.5rem; color: var(--text); font-size: 1rem;">Step 1: Set up your Identity Provider</h3>
        <p>Create an application in your IdP (Azure AD, Okta, Keycloak, etc.) with the following:</p>
        <ul style="padding-left: 1.5rem; margin: 0.5rem 0;">
            <li><strong>ACS (Assertion Consumer Service) URL:</strong> <code class="mono">{{ url('/auth/sso/callback') }}</code></li>
            <li><strong>SP Entity ID:</strong> <code class="mono">{{ config('sso.sp_entity_id', 'print-hub') }}</code></li>
        </ul>

        <h3 style="margin: 1rem 0 0.5rem; color: var(--text); font-size: 1rem;">Step 2: Configure .env</h3>
        <pre style="background: var(--bg); padding: 1rem; border-radius: 6px; overflow-x: auto; font-size: 0.8rem; margin: 0.5rem 0;">
SSO_ENABLED=true
SSO_PROVIDER=saml2
SAML2_IDP_ENTITY_ID=https://your-idp.com/entity-id
SAML2_IDP_SSO_URL=https://your-idp.com/sso-url
SAML2_IDP_X509_CERT=-----BEGIN CERTIFICATE-----\n...\n-----END CERTIFICATE-----
SAML2_SP_ENTITY_ID=print-hub
SAML2_SP_ACS_URL=/auth/sso/callback
SSO_AUTO_PROVISION=true
SSO_DEFAULT_ROLE=user</pre>

        <h3 style="margin: 1rem 0 0.5rem; color: var(--text); font-size: 1rem;">Step 3: Install SAML Library (Required)</h3>
        <p>Run the following command to install the SAML2 library:</p>
        <pre style="background: var(--bg); padding: 1rem; border-radius: 6px; overflow-x: auto; font-size: 0.8rem; margin: 0.5rem 0;">composer require onelogin/php-saml</pre>

        <div class="alert alert-warning" style="margin-top: 1rem;">
            <strong>Note:</strong> Without the <code class="mono">onelogin/php-saml</code> library, the SSO flow uses a stub implementation. The SAML response is parsed using basic XML processing, which may not be compatible with all IdP configurations.
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>IdP Configuration</h2>
    </div>
    <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1rem;">
        Provide the following Service Provider (SP) metadata to your Identity Provider:
    </p>

    <div style="background: var(--bg); padding: 1rem; border-radius: 6px; overflow-x: auto;">
        <pre style="font-size: 0.75rem; white-space: pre-wrap; word-break: break-all;">
{{ '<?xml version="1.0"?>' }}
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
                     entityID="{{ config('sso.sp_entity_id', 'print-hub') }}">
    <md:SPSSODescriptor AuthnRequestsSigned="false"
                        WantAssertionsSigned="false"
                        protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
                                     Location="{{ url('/auth/sso/callback') }}"
                                     index="0"/>
    </md:SPSSODescriptor>
</md:EntityDescriptor></pre>
    </div>

    <p style="margin-top: 1rem; color: var(--text-muted); font-size: 0.875rem;">
        Or access the metadata URL directly:
        <a href="{{ route('sso.metadata') }}" target="_blank" class="mono">{{ route('sso.metadata') }}</a>
    </p>
</div>
@endsection
