<?php
declare(strict_types=1);

namespace Ubean\Psalm\Internal\TraitEnforcer\Hook;

use Psalm\IssueBuffer;
use Psalm\Plugin\EventHandler\AfterClassLikeAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterClassLikeAnalysisEvent;
use Ubean\Psalm\Internal\TraitEnforcer\Issues\InternalTraitUse;

/**
 * Hook: TraitUseEnforcer
 *
 * Enforces internal-boundary rules for trait usage during Psalm analysis.
 *
 * This hook inspects every class-like after Psalm's analysis phase and verifies
 * that any trait it `use`s is not marked as `@internal`/`@psalm-internal` for a
 * different namespace. If a violation is found, an {@see InternalTraitUse}
 * issue is emitted at the location of the class-like declaration.
 *
 * ---
 * How it decides whether usage is allowed
 * - If a trait declares `@psalm-internal Some\\Ns`, usage is allowed only when
 *   the current namespace is equal to or nested under `Some\\Ns`.
 * - Else if a trait is `@internal`, usage is allowed only when the current
 *   namespace is equal to or nested under the trait's declaring namespace.
 * - Otherwise, the trait is considered public and usage is allowed.
 *
 * See also: {@see InternalTraitUse} for why this matters and suggested fixes.
 */
final class TraitUseEnforcer implements AfterClassLikeAnalysisInterface
{
    /**
     * Psalm event callback executed after class-like analysis.
     *
     * - Iterates the analyzed class-like's `used_traits` and resolves each trait's storage.
     * - Determines whether the current namespace is permitted by `@psalm-internal` or
     *   `@internal` boundaries.
     * - Emits an {@see InternalTraitUse} issue if usage is not permitted.
     *
     * @param AfterClassLikeAnalysisEvent $event The event context provided by Psalm.
     * @return bool|null Whether the analysis was successful or not.
     */
    #[\Override]
    public static function afterStatementAnalysis(AfterClassLikeAnalysisEvent $event): ?bool
    {
        // Get the codebase and source information
        $codebase = $event->getCodebase();
        $source   = $event->getStatementsSource();

        // Get the class-like storage and location information
        $classlikeStorage = $event->getClasslikeStorage();
        $location = $classlikeStorage->location;

        // The namespace where this class-like resides ('' means global namespace)
        $currentNamespace = $source->getNamespace() ?? '';

        // Iterate through all keys of used_traits (keys are FQCNs of the traits)
        foreach (array_keys($classlikeStorage->used_traits) as $fqTrait) {
            // Skip traits that don't exist in the codebase (e.g., missing/undefined traits)
            if (!$codebase->classlikes->hasFullyQualifiedTraitName($fqTrait)) {
                continue;
            }

           // Get the storage (metadata) of the trait being used
            $storage = $codebase->classlike_storage_provider->get($fqTrait);

            // Flag for @internal annotation
            $isInternal = (bool)($storage->internal ?? false);

            // List of namespaces allowed via @psalm-internal
            /** @var array<string,true> $psalmInternal */
            $psalmInternal = $storage->psalm_internal ?? [];

            // Assume use is not allowed until proven otherwise
            $allowed = false;

            if ($psalmInternal) {
                // Trait has @psalm-internal declarations.
                // Allow only if the current namespace starts with any of the allowed ones.
                foreach (array_keys($psalmInternal) as $allowedNs) {
                    $allowedNs = rtrim($allowedNs, '\\');
                    if ($allowedNs !== '' && self::nsStartsWith($currentNamespace, $allowedNs)) {
                        $allowed = true;
                        break;
                    }
                }
            } elseif ($isInternal) {
                // Trait is marked as plain @internal (without @psalm-internal).
                // Allow only if current namespace matches the declaring namespace.
                $declaringNs = (string)($storage->namespace_name ?? '');
                if ($declaringNs !== '' && self::nsStartsWith($currentNamespace, $declaringNs)) {
                    $allowed = true;
                }
            } else {
                // Public trait: always allowed
                $allowed = true;
            }

            // If the trait use is not allowed, raise a custom issue
            if (!$allowed) {
                IssueBuffer::accepts(
                     new InternalTraitUse(
                        sprintf(
                            'Trait %s is internal to %s and cannot be used from %s. ' .
                            'Consider exposing a public API or using a non-internal alternative.',
                            $fqTrait,
                            ($storage->namespace_name ?? '(global)'),
                            ($currentNamespace !== '' ? $currentNamespace : '(global)')
                        ),
                        $location
                    ),
                    $source->getSuppressedIssues()
                );
            }
        }

        // Return null to defer to Psalm's default flow
        return null;
    }

    /**
     * Boundary-aware namespace prefix check.
     *
     * Returns true when `$subjectNs` equals `$prefixNs` or is nested under it,
     * respecting namespace segment boundaries. For example:
     * - subject `A\B\C`, prefix `A\B`   => true
     * - subject `A\BC`,  prefix `A\B`   => false (no segment boundary)
     * - subject `A\B`,   prefix `A\B`   => true (exact match)
     * - subject `` (global), any non-empty prefix => false
     *
     * @param string $subjectNs Fully qualified namespace of the class/trait being checked.
     * @param string $prefixNs  Fully qualified namespace prefix to test against.
     * @return bool True if `$subjectNs` is the same as `$prefixNs` or a sub-namespace of it;
     *              false otherwise.
     */
    private static function nsStartsWith(string $subjectNs, string $prefixNs): bool
    {
        // Normalize both namespaces by trimming trailing backslashes
        // so "App\Service\" and "App\Service" are treated the same.
        $subject = $subjectNs === '' ? '' : rtrim($subjectNs, '\\');
        $prefix  = rtrim($prefixNs, '\\');

        // An empty prefix is not considered valid — nothing can "start with" it.
        if ($prefix === '') {
            return false;
        }

        // Exact match: the subject is exactly the same namespace as the prefix.
        if ($subject === $prefix) {
            return true;
        }

        // Prefix check: add a trailing backslash to both so we only match
        // complete namespace boundaries. Without this, "A\BC" would incorrectly
        // be considered a sub-namespace of "A\B".
        return str_starts_with($subject . '\\', $prefix . '\\');
    }
}
