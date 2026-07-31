<?php

declare(strict_types=1);

namespace App\Csp;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;
use Spatie\Csp\Presets\Basic;

/**
 * Frontend Content-Security-Policy (Issue #21, checklist §G).
 *
 * Builds on spatie's Basic preset — default-src / script-src / style-src / … set to
 * 'self', object-src 'none', plus a per-request nonce on script and style — and adds
 * frame-ancestors 'self' to lock down framing. No 'unsafe-inline' / 'unsafe-eval':
 * the Antlers templates carry no inline scripts or style attributes and Vite emits
 * same-origin assets, so 'self' + nonce cover the whole frontend.
 *
 * Applied to the public frontend only, never /cp — the Control Panel is an
 * Inertia/Vue 3 app and Live Preview renders drafts in iframes/modules, both of
 * which this policy would break. The exclusion lives in
 * App\Http\Middleware\ContentSecurityPolicy.
 */
final class FrontendPolicy implements Preset
{
    public function configure(Policy $policy): void
    {
        (new Basic)->configure($policy);

        $policy->add(Directive::FRAME_ANCESTORS, Keyword::SELF);
    }
}
