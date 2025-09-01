<?php declare(strict_types=1);

namespace Ubean\Psalm\Internal\TraitEnforcer;

use Psalm\Plugin\PluginEntryPointInterface;
use Psalm\Plugin\RegistrationInterface;
use SimpleXMLElement;
use Ubean\Psalm\Internal\TraitEnforcer\Hook\TraitUseEnforcer;

/**
 * Plugin
 *
 * Psalm entry point for the "Internal Trait Enforcement" plugin.
 *
 * ---
 * #### What this does
 * Registers the hook(s) that enforce internal-visibility rules for traits:
 * - Prevent using traits annotated with `@psalm-internal Namespace\Prefix`
 *   outside the allowed namespace.
 * - Treat `@internal` traits as internal to their declaring namespace by default.
 *
 * ---
 * #### Configuration
 * Psalm supports passing a <pluginClass> node with custom XML. If you later
 * add plugin-specific options, read them from $config in __invoke().
 *
 * ---
 * #### Suppression / Baselines
 * The plugin emits the issue `InternalTraitUse`. Teams can suppress via:
 * - @psalm-suppress InternalTraitUse
 * - baseline entries
 * - issueHandlers in psalm.xml
 */
final class Plugin implements PluginEntryPointInterface
{
    /**
     * Psalm calls the plugin entry point during initialization.
     *
     * @param RegistrationInterface $registration Provides methods to register hooks.
     * @param SimpleXMLElement|null $config       Optional <pluginClass> XML from psalm.xml.
     */
    #[\Override]
    public function __invoke(RegistrationInterface $registration, ?SimpleXMLElement $config = null): void
    {
        // Register all analysis hooks for this plugin.
        // TraitUseEnforcer performs the actual internal-visibility checks at trait "use" sites.
        if (class_exists(TraitUseEnforcer::class)) {
            $registration->registerHooksFromClass(TraitUseEnforcer::class);
        }

        // Parse plugin options from <pluginClass> node and wire to hooks.
        // Supported options:
        // - policy: "namespace" | "package" (default: namespace)
        $policy = 'namespace';
        if ($config !== null && isset($config->policy)) {
            $policy = (string) $config->policy;
        }

        if (method_exists(TraitUseEnforcer::class, 'setPolicy')) {
            TraitUseEnforcer::setPolicy($policy);
        }
    }
}
