<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SsoController extends Controller
{
    /**
     * Redirect the user to the Identity Provider's SSO URL.
     *
     * This is a stub implementation. To enable full SAML2 SSO:
     * 1. Run: composer require onelogin/php-saml
     * 2. Configure SSO settings in your .env file
     * 3. Replace the placeholder logic below with actual SAML2 authn request
     *
     * Example with onelogin/php-saml:
     *   $settings = new \OneLogin\Saml2\Auth(config('sso'));
     *   $settings->login();
     */
    public function login()
    {
        if (! config('sso.enabled')) {
            abort(404, 'SSO is not enabled.');
        }

        // ── Placeholder: Replace with actual SAML2 redirect ──
        // When onelogin/php-saml is installed:
        //   $auth = new \OneLogin\Saml2\Auth($this->samlSettings());
        //   return redirect($auth->getSSOurl());
        //
        // For now, we redirect to the IdP SSO URL directly (testing purposes).
        $idpSsoUrl = config('sso.idp_sso_url');
        if (! $idpSsoUrl) {
            return redirect()->route('login')->withErrors([
                'sso' => 'SSO is not fully configured. Please set SAML2_IDP_SSO_URL in your .env.',
            ]);
        }

        Log::info('SSO login initiated, redirecting to IdP: ' . $idpSsoUrl);

        // In production, replace with:
        // $auth = new \OneLogin\Saml2\Auth($this->samlSettings());
        // return $auth->login(route('admin.dashboard'));
        return redirect($idpSsoUrl);
    }

    /**
     * Handle the SSO callback (ACS - Assertion Consumer Service).
     *
     * This endpoint receives the SAML response from the IdP.
     *
     * Stub implementation — replace with actual SAML response processing
     * when onelogin/php-saml is installed.
     */
    public function callback(Request $request)
    {
        if (! config('sso.enabled')) {
            abort(404, 'SSO is not enabled.');
        }

        // ── Placeholder: Replace with actual SAML2 response processing ──
        // When onelogin/php-saml is installed:
        //
        //   $auth = new \OneLogin\Saml2\Auth($this->samlSettings());
        //   $auth->processResponse();
        //
        //   if (! $auth->isAuthenticated()) {
        //       $errors = $auth->getErrors();
        //       Log::error('SSO authentication failed', ['errors' => $errors]);
        //       return redirect()->route('login')->withErrors(['sso' => 'SSO authentication failed.']);
        //   }
        //
        //   $attributes = $auth->getAttributes();
        //   $nameId     = $auth->getNameId();
        //   $email      = $attributes['email'][0] ?? $nameId;
        //   $name       = $attributes['name'][0] ?? $attributes['displayName'][0] ?? $nameId;
        //
        //   // Find or create user
        //   $user = User::where('email', $email)->first();
        //
        //   if (! $user && config('sso.auto_provision')) {
        //       $user = User::create([
        //           'name'     => $name,
        //           'email'    => $email,
        //           'password' => bcrypt(Str::random(32)),
        //           'role'     => config('sso.default_role', 'user'),
        //       ]);
        //   }
        //
        //   if (! $user) {
        //       return redirect()->route('login')->withErrors(['sso' => 'No matching user found.']);
        //   }
        //
        //   Auth::login($user);
        //   $request->session()->regenerate();
        //
        //   return redirect()->intended(route('admin.dashboard'));

        // ── Placeholder implementation for stub ──
        $samlResponse = $request->input('SAMLResponse');

        if (! $samlResponse) {
            Log::warning('SSO callback received without SAMLResponse', $request->all());
            return redirect()->route('login')->withErrors([
                'sso' => 'Invalid SSO response. No SAML response received.',
            ]);
        }

        // Attempt to decode and process the SAML response
        try {
            $decoded = base64_decode($samlResponse, true);
            if ($decoded === false) {
                throw new \RuntimeException('Invalid base64 SAML response');
            }

            // Parse the SAML XML to extract attributes
            $xml = simplexml_load_string($decoded);
            if ($xml === false) {
                throw new \RuntimeException('Invalid SAML XML');
            }

            // Register SAML namespaces
            $xml->registerXPathNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
            $xml->registerXPathNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');

            // Extract NameID (email)
            $nameIdNodes = $xml->xpath('//saml:NameID');
            $email = $nameIdNodes ? (string) $nameIdNodes[0] : null;

            // Extract attributes if present
            $attributeNodes = $xml->xpath('//saml:Attribute');
            $attributes = [];
            foreach ($attributeNodes as $attr) {
                $name = (string) $attr['Name'];
                $values = [];
                foreach ($attr->children('urn:oasis:names:tc:SAML:2.0:assertion') as $child) {
                    $values[] = (string) $child;
                }
                $attributes[$name] = $values;
            }

            $name = $attributes['displayName'][0] ?? $attributes['cn'][0] ?? $attributes['name'][0] ?? $email;
            $email = $attributes['email'][0] ?? $attributes['mail'][0] ?? $email;

            if (! $email) {
                throw new \RuntimeException('No email found in SAML response');
            }

            // Find or create user
            $user = User::where('email', $email)->first();

            if (! $user && config('sso.auto_provision')) {
                $user = User::create([
                    'name'     => $name ?? $email,
                    'email'    => $email,
                    'password' => bcrypt(\Illuminate\Support\Str::random(32)),
                    'role'     => config('sso.default_role', 'user'),
                ]);
                Log::info('SSO auto-provisioned user', ['email' => $email, 'name' => $name]);
            }

            if (! $user) {
                return redirect()->route('login')->withErrors([
                    'sso' => 'No matching user found and auto-provisioning is disabled.',
                ]);
            }

            Auth::login($user);
            $request->session()->regenerate();

            Log::info('SSO login successful', ['user' => $user->email]);

            return redirect()->intended(route('admin.dashboard'));
        } catch (\Exception $e) {
            Log::error('SSO callback processing failed: ' . $e->getMessage());
            return redirect()->route('login')->withErrors([
                'sso' => 'SSO authentication failed: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Return the SP metadata XML for this service provider.
     *
     * This XML is used to configure the IdP to trust this application.
     *
     * Stub implementation — replace with actual metadata generation
     * when onelogin/php-saml is installed.
     */
    public function metadata()
    {
        if (! config('sso.enabled')) {
            abort(404, 'SSO is not enabled.');
        }

        // ── Placeholder: Replace with onelogin/php-saml metadata generation ──
        // When onelogin/php-saml is installed:
        //   $auth = new \OneLogin\Saml2\Auth($this->samlSettings());
        //   $metadata = $auth->getSettings()->getSPMetadata();
        //   $errors = $auth->getSettings()->validateMetadata($metadata);
        //   if (!empty($errors)) {
        //       abort(500, 'Invalid SP metadata: ' . implode(', ', $errors));
        //   }
        //   return response($metadata, 200, ['Content-Type' => 'application/xml']);

        $spEntityId = config('sso.sp_entity_id', 'print-hub');
        $acsUrl     = url(config('sso.sp_acs_url', '/auth/sso/callback'));

        $metadata = <<<XML
<?xml version="1.0"?>
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
                     entityID="{$spEntityId}">
    <md:SPSSODescriptor AuthnRequestsSigned="false"
                        WantAssertionsSigned="false"
                        protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
                                     Location="{$acsUrl}"
                                     index="0"/>
    </md:SPSSODescriptor>
</md:EntityDescriptor>
XML;

        return response($metadata, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    /**
     * Get SAML settings array for onelogin/php-saml.
     *
     * This method is a stub showing how the configuration would be structured.
     * Requires composer require onelogin/php-saml to use.
     *
     * @return array
     */
    private function samlSettings(): array
    {
        // ── Placeholder: Replace with actual onelogin/php-saml settings ──
        return [
            'sp' => [
                'entityId' => config('sso.sp_entity_id', 'print-hub'),
                'assertionConsumerService' => [
                    'url' => url(config('sso.sp_acs_url', '/auth/sso/callback')),
                ],
            ],
            'idp' => [
                'entityId' => config('sso.idp_entity_id'),
                'singleSignOnService' => [
                    'url' => config('sso.idp_sso_url'),
                ],
                'x509cert' => config('sso.idp_x509_cert'),
            ],
        ];
    }
}
