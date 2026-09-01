@extends('admin.layout')
@section('title', 'SSO Settings')

@section('content')
<x-breadcrumb :items="[['label' => 'Single Sign-On (SSO)']]" />

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h2 class="text-base sm:text-lg font-bold text-white">SAML2 / OAuth Single Sign-On</h2>
        <p class="text-xs text-slate-400">Enterprise identity provider federation and automated account provisioning</p>
    </div>
</div>

<div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xs mb-6">
    <div class="p-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active SSO Parameters</h3>
        @if(config('sso.enabled'))
            <span class="badge badge-success">Federation Active</span>
        @else
            <span class="badge badge-warning">SSO Disabled</span>
        @endif
    </div>

    <table class="w-full text-left text-sm text-slate-300">
        <tbody class="divide-y divide-slate-800/60 font-mono text-xs">
            <tr class="hover:bg-slate-800/40">
                <td class="px-5 py-3 font-sans font-semibold text-slate-400 w-1/3">Provider Type</td>
                <td class="px-5 py-3 text-white">{{ config('sso.provider', 'saml2') }}</td>
            </tr>
            <tr class="hover:bg-slate-800/40">
                <td class="px-5 py-3 font-sans font-semibold text-slate-400">IdP Entity ID</td>
                <td class="px-5 py-3 text-blue-400">{{ config('sso.idp_entity_id') ?: '(Not configured)' }}</td>
            </tr>
            <tr class="hover:bg-slate-800/40">
                <td class="px-5 py-3 font-sans font-semibold text-slate-400">IdP SSO Gateway URL</td>
                <td class="px-5 py-3 text-slate-300">{{ config('sso.idp_sso_url') ?: '(Not configured)' }}</td>
            </tr>
            <tr class="hover:bg-slate-800/40">
                <td class="px-5 py-3 font-sans font-semibold text-slate-400">Service Provider Entity ID</td>
                <td class="px-5 py-3 text-slate-300">{{ config('sso.sp_entity_id', 'print-hub') }}</td>
            </tr>
            <tr class="hover:bg-slate-800/40">
                <td class="px-5 py-3 font-sans font-semibold text-slate-400">ACS Callback URL</td>
                <td class="px-5 py-3 text-emerald-400">{{ url(config('sso.sp_acs_url', '/auth/sso/callback')) }}</td>
            </tr>
            <tr class="hover:bg-slate-800/40">
                <td class="px-5 py-3 font-sans font-semibold text-slate-400">SP Metadata Descriptor</td>
                <td class="px-5 py-3">
                    <a href="{{ route('sso.metadata') }}" target="_blank" class="text-blue-400 hover:underline">
                        {{ route('sso.metadata') }} ↗
                    </a>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xs">
    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">IdP Environment Setup (.env)</h3>
    <pre class="p-4 rounded-xl bg-slate-950 border border-slate-800 font-mono text-xs text-slate-300 overflow-x-auto">SSO_ENABLED=true
SSO_PROVIDER=saml2
SAML2_IDP_ENTITY_ID=https://idp.example.com/entity-id
SAML2_IDP_SSO_URL=https://idp.example.com/sso-url
SAML2_SP_ENTITY_ID=print-hub
SAML2_SP_ACS_URL=/auth/sso/callback
SSO_AUTO_PROVISION=true</pre>
</div>
@endsection
