<?php

declare(strict_types=1);

namespace Maspik\Domain;

/**
 * Whether a submission source carries the front-end guard fields (honeypot +
 * advanced key). v2 skips them for integrations whose requests can't include
 * them: Ninja Forms (AJAX field model) and the WooCommerce Store API checkout
 * block. Pure so both CheckFactory and the parity harness share one rule.
 */
final class GuardPolicy
{
    private const WITHOUT_GUARD_FIELDS = ['ninjaforms', 'woocommerce_checkout_block'];

    public static function carriesGuardFields(string $source): bool
    {
        return ! in_array($source, self::WITHOUT_GUARD_FIELDS, true);
    }
}
