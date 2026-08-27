<?php

declare(strict_types=1);

namespace Maspik\Integrations;

use Maspik\Application\SpamGate;
use Maspik\Infrastructure\Settings\Settings;
use Maspik\Premium\ProGate;

/**
 * Knows every integration, activates the ones that are installed and enabled.
 */
final class Registry
{
    /** @var FormIntegration[] */
    private $integrations = [];

    /** @var Settings */
    private $settings;

    /** @var SpamGate */
    private $gate;

    /** @var ProGate */
    private $proGate;

    public function __construct(Settings $settings, SpamGate $gate, ProGate $proGate)
    {
        $this->settings = $settings;
        $this->gate = $gate;
        $this->proGate = $proGate;
    }

    public function add(FormIntegration $integration): void
    {
        $this->integrations[$integration->id()] = $integration;
    }

    /** @return FormIntegration[] */
    public function all(): array
    {
        return $this->integrations;
    }

    public function activateEnabled(): void
    {
        foreach ($this->integrations as $integration) {
            if (! $integration->isAvailable() || ! $this->isEnabled($integration)) {
                continue;
            }
            // Pro-only integrations never run without an active license.
            if ($integration->pro() && ! $this->proGate->isActive()) {
                continue;
            }
            $integration->register($this->gate);
        }
    }

    /**
     * Whether an integration is switched on. v2 semantics: an unset toggle
     * ('') counts as enabled; only an explicit 'no' disables it.
     */
    public function isEnabled(FormIntegration $integration): bool
    {
        $value = $this->settings->raw($integration->toggleKey());

        // Opt-in integrations (WooCommerce checkout) must stay off until
        // explicitly enabled — an unset toggle means "off", not "on".
        if ($integration->optIn()) {
            return $value === 'yes' || $value === '1';
        }

        return $value === '' || $value === 'yes' || $value === '1';
    }

    /**
     * Metadata for the Advanced → Form integrations screen. Undetected
     * plugins are still listed (so users know what MASPIK can protect) with
     * available=false.
     *
     * @return array<int, array{id: string, label: string, toggleKey: string, available: bool, enabled: bool, pro: bool, locked: bool}>
     */
    public function describe(): array
    {
        $proActive = $this->proGate->isActive();
        $out = [];
        foreach ($this->integrations as $integration) {
            $pro = $integration->pro();
            $out[] = [
                'id' => $integration->id(),
                'label' => $integration->label(),
                'toggleKey' => $integration->toggleKey(),
                'available' => $integration->isAvailable(),
                'enabled' => $this->isEnabled($integration),
                'pro' => $pro,
                // Detected but inert until upgraded — the UI shows it locked.
                'locked' => $pro && ! $proActive,
            ];
        }

        return $out;
    }

    /** Valid toggle keys, for validating PATCH requests from the admin. */
    public function toggleKeys(): array
    {
        return array_map(
            static function (FormIntegration $integration): string {
                return $integration->toggleKey();
            },
            array_values($this->integrations)
        );
    }
}
