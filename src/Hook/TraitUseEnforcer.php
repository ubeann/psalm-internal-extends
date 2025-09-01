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
 * - If a trait declares `@psalm-internal Some\\Ns` (can appear multiple times),
 *   usage is allowed only when the current namespace is equal to or nested under
 *   one of the declared namespaces.
 * - Else if a trait is `@internal`, usage is allowed only when the current
 *   namespace is equal to or nested under the trait's declaring namespace.
 *   For a trait declared in the global namespace, only global-namespace usage
 *   is allowed.
 * - Otherwise, the trait is considered public and usage is allowed.
 *
 * See also: {@see InternalTraitUse} for why this matters and suggested fixes.
 */
final class TraitUseEnforcer implements AfterClassLikeAnalysisInterface
{
    /**
     * Enforcement policy.
     * - namespace: allow when namespaces are same or one is ancestor/descendant of the other
     * - package:   allow when the top-level namespace segment matches (e.g., Vendor\*)
     *
     * @var 'namespace'|'package'
     */
    private static string $policy = 'namespace';

    /**
     * Configure the enforcement policy.
     *
     * @param 'namespace'|'package' $policy
     */
    public static function setPolicy(string $policy): void
    {
        $policy = strtolower($policy);
        self::$policy = in_array($policy, ['namespace', 'package'], true) ? $policy : 'namespace';
    }

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
            $isInternal   = (bool)($storage->internal ?? false);
            $internalList = $storage->internal ?? [$storage->aliases->namespace];

            // Assume use is not allowed until proven otherwise
            $allowed = false;

            // Loop through the internal list
            if ($isInternal) {
                foreach ($internalList as $allowedNs) {
                    $allowedNs = rtrim($allowedNs, '\\');
                    if (self::nsAllowed($currentNamespace, $allowedNs)) {
                        $allowed = true;
                        break;
                    }
                }
            } else {
                $allowed = true;
            }

            // If the trait use is not allowed, raise a custom issue
            if (!$allowed) {
                // Build "Allowed from:" hint (only when @internal and we have a list)
                $fmtNs = static fn(string $ns) => $ns === '' ? '(global)' : trim($ns, '\\');

                // Build a list of allowed namespaces
                $allowedNs = ($isInternal && !empty($internalList))
                    ? array_values(array_unique(array_map($fmtNs, $internalList)))
                    : [];

                // Build a hint listing allowed namespaces, if any
                $hint = $allowedNs ? ' Allowed from: ' . implode(', ', $allowedNs) . '.' : '';

                // Emit the issue
                IssueBuffer::accepts(
                    new InternalTraitUse(
                        sprintf(
                            "Trait %s is internal and not accessible from %s.%s\n" .
                            "Use the package's public API or request exposure.",
                            $storage->name,
                            ($currentNamespace !== '' ? $currentNamespace : '(global)'),
                            $hint
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
     * Namespace relationship check (symmetric).
     *
     * Returns true when `$subjectNs` and `$ruleNs` are the same, or one is a parent/ancestor
     * of the other, using segment boundaries. Global ('') matches anything.
     *
     * Examples:
     *  subject 'A\B\C', rule 'A\B'   => true  (descendant)
     *  subject 'A\B',   rule 'A\B\C' => true  (ancestor)
     *  subject 'A',     rule 'A\B'   => true  (ancestor)
     *  subject '',      rule 'A\B'   => true  (global subject matches any rule)
     *  subject 'A\BC',  rule 'A\B'   => false (no boundary)
     *  subject 'A\B',   rule 'A\B'   => true  (equal)
     *  subject 'A\B',   rule ''      => false (no boundary)
     *
     * @param string $subjectNs Fully qualified namespace of the class/trait being checked.
     * @param string $ruleNs    Fully qualified namespace to test against.
     * @return bool True if `$subjectNs` is the same as `$ruleNs` or a sub-namespace of it;
     *              false otherwise.
     */
    private static function nsSameOrRelated(string $consumer, string $rule): bool
    {
        // Normalize both namespaces
        $a = $consumer === '' ? '' : rtrim($consumer, '\\');
        $b = $rule === '' ? '' : rtrim($rule, '\\');

        // Special handling for global
        if ($a === '' && $b !== '') return true;
        if ($b === '' && $a !== '') return false;

        // Exact match
        if ($a === $b) return true;

        // Boundary-safe ancestor/descendant checks (symmetric)
        $aSep = $a === '' ? '' : $a . '\\';
        $bSep = $b === '' ? '' : $b . '\\';

        // Return true if either namespace is a descendant of the other
        return ($a !== '' && str_starts_with($aSep, $bSep))  // consumer is descendant of rule
            || ($b !== '' && str_starts_with($bSep, $aSep)); // consumer is ancestor of rule
    }

    /**
     * Policy-aware namespace allowance check.
     *
     * @param string $consumer Fully qualified namespace of the class/trait using the internal trait.
     * @param string $rule     Fully qualified namespace of the internal trait.
     * @return bool True if the consumer namespace is allowed to use the internal trait; false otherwise.
     */
    private static function nsAllowed(string $consumer, string $rule): bool
    {
        // In package mode, allow if the first segment (vendor/package root) matches.
        // Global namespace never matches a non-empty rule.
        if (self::$policy === 'package') {
            // Normalize both namespaces
            $rule = trim($rule, '\\');
            $consumer = trim($consumer, '\\');

            // Global-to-Global
            if ($rule === '' && $consumer === '') return true;

            // Global doesn't mix with named packages
            if ($rule === '' || $consumer === '') return false;

            // Extract the first segment (vendor/package root)
            $first = static fn(string $ns): string => ($pos = strpos($ns, '\\')) === false ? $ns : substr($ns, 0, $pos);

            // Compare the first segments
            return $first($consumer) === $first($rule);
        }

        // default 'namespace'
        return self::nsSameOrRelated($consumer, $rule);
    }
}
