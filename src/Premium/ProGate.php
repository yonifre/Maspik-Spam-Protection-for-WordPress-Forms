<?php

declare(strict_types=1);

namespace Maspik\Premium;

/**
 * Pro feature gate — successor of v2's cfes_is_supporting().
 *
 * Reads the stored license state ('maspik_license_active'), which License sets
 * only after a successful server activation/validation against the wpmaspik.com
 * Digital License Manager (see License::activate()/recheck()). A
 * `maspik/pro_supports` filter can still force Pro for development / the parity
 * harness.
 */
class ProGate
{
    public function isActive(): bool
    {
        return get_option('maspik_license_active', 'no') === 'yes';
    }

    public function supports(string $feature): bool
    {
        if ($this->isActive()) {
            return true;
        }

        return (bool) apply_filters('maspik/pro_supports', false, $feature);
    }
}
