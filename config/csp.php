<?php

declare(strict_types=1);

use App\Csp\FrontendPolicy;

/**
 * Content-Security-Policy (Issue #21, checklist §G).
 *
 * Frontend-only policy built by App\Csp\FrontendPolicy and emitted by
 * App\Http\Middleware\ContentSecurityPolicy, which excludes /cp. spatie's
 * CspServiceProvider is auto-discovered and wires the per-request nonce plus the
 *
 * @cspNonce Blade directive; only the middleware registration is on us.
 */
return [
    /*
     * Presets determine which CSP directives are set. A preset is any class that
     * implements Spatie\Csp\Preset.
     */
    'presets' => [
        FrontendPolicy::class,
    ],

    /*
     * Additional global CSP directives, appended to whatever the presets set.
     */
    'directives' => [
        //
    ],

    /*
     * Presets emitted as a report-only policy — useful for trialling changes
     * without enforcing them. Empty: the frontend policy is enforced directly.
     */
    'report_only_presets' => [
        //
    ],

    'report_only_directives' => [
        //
    ],

    /*
     * All violations against the enforced policy are reported to this url when set.
     */
    'report_uri' => env('CSP_REPORT_URI', ''),

    /*
     * Optional separate report url for the report-only policy; falls back to
     * report_uri when empty.
     */
    'report_only_uri' => env('CSP_REPORT_ONLY_URI', ''),

    /*
     * Name of the reporting endpoint (defined in reporting_endpoints) that
     * violations are sent to via the Report-To mechanism.
     */
    'report_to' => env('CSP_REPORT_TO', ''),

    'report_only_to' => env('CSP_REPORT_ONLY_TO', ''),

    /*
     * Reporting endpoints sent in the Reporting-Endpoints header. Keys are the
     * names referenced by report_to above.
     */
    'reporting_endpoints' => [
        //
    ],

    /*
     * Headers are only added when this is true.
     */
    'enabled' => env('CSP_ENABLED', true),

    /*
     * Whether to keep emitting CSP while Vite is hot reloading. Left off so local
     * `npm run dev` (cross-origin dev server + eval) is never blocked; the policy
     * is verified against a production build instead.
     */
    'enabled_while_hot_reloading' => env('CSP_ENABLED_WHILE_HOT_RELOADING', false),

    /*
     * The class responsible for generating the nonces used in inline tags and headers.
     */
    'nonce_generator' => Spatie\Csp\Nonce\RandomString::class,

    /*
     * Automatic nonce generation and handling. Kept on so inline script/style tags
     * can opt in via @cspNonce without ever needing 'unsafe-inline'.
     */
    'nonce_enabled' => env('CSP_NONCE_ENABLED', true),
];
