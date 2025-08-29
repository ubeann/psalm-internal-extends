<?php
declare(strict_types=1);

namespace Ubean\Psalm\Internal\TraitEnforcer\Hook;

use PhpParser\Node;
use PhpParser\Node\Stmt\TraitUse;
use Psalm\Plugin\Hook\AfterStatementAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterStatementAnalysisEvent;
use Psalm\IssueBuffer;
use Ubean\Psalm\Internal\TraitEnforcer\Issues\InternalTraitUse;

/**
 * TraitUseEnforcer
 *
 * Enforces internal-visibility rules for traits:
 * - `@psalm-internal Namespace\Prefix` → trait may only be used inside the given namespace (or its children).
 * - `@internal` → treated as "internal to the declaring namespace" (heuristic explained below).
 *
 * ---
 * #### Why this exists
 * Psalm already enforces @internal on classes/methods, but trait *use* sites
 * don't currently receive the same protection. This plugin closes that gap by
 * inspecting `use Some\Trait;` statements and raising a dedicated issue when
 * they violate internal boundaries.
 *
 * ---
 * #### Policy (in order of precedence)
 * 1) If `@psalm-internal` is present: allow only when the *current* namespace
 *    starts with one of the allowed namespace prefixes.
 * 2) Else if `@internal` is present: allow only when the current namespace
 *    starts with the trait's *declaring* namespace (namespace-local heuristic).
 * 3) Otherwise: allow (trait is public).
 *
 * ---
 * #### Notes
 * - The "namespace-local" interpretation for `@internal` is a pragmatic default.
 *   If your project treats `@internal` as package-wide (i.e. only usable by the
 *   same Composer package), prefer using `@psalm-internal Vendor\Package` for
 *   precise control, or adjust the policy here.
 * - The error message is actionable and explains the boundary that was crossed.
 *
 * ---
 * #### Suppression
 * - You can suppress the issue via Psalm's standard mechanisms (e.g. baseline
 *   or `@psalm-suppress InternalTraitUse`) if you have a justified exception.
 */
final class TraitUseEnforcer implements AfterStatementAnalysisInterface
{
    /**
     * Runs after Psalm analyzes each statement.
     * We only act on `TraitUse` nodes, checking each referenced trait.
     *
     * @return bool|null Returning null keeps default flow; returning true/false
     *                   would indicate we modified analysis, which we do not.
     */
    public static function afterStatementAnalysis(AfterStatementAnalysisEvent $event): ?bool
    {
        // Ensure we only process TraitUse statements.
        $stmt = $event->getStmt();
        if (!$stmt instanceof TraitUse) {
            return null;
        }

        // Gather context for the analysis.
        $codebase = $event->getCodebase();
        $source   = $event->getStatementsSource();
        $location = $event->getCodeLocation();

        // Current namespace of the file being analyzed ('' when global).
        $currentNamespace = $source->getNamespace() ?? '';

        // A single `use` can import multiple traits: `use A, B, C;`
        foreach ($stmt->traits as $name) {
            // Ensure we have a valid trait name.
            if (!$name instanceof Node\Name) {
                continue;
            }

            // Resolve the fully-qualified class-like name of the trait as seen from this file.
            $fqcn = $source->getAliases()->getFQCLN($name, $source->getNamespace());

            // Only proceed if Psalm knows about this trait.
            if (!$codebase->classlikes->hasFullyQualifiedTraitName($fqcn)) {
                continue;
            }

            // Fetch the trait's storage to inspect its annotations.
            $storage = $codebase->classlike_storage_provider->get($fqcn);

            // Flags extracted from Psalm's storage for the trait:
            // 1) Generic @internal flag (bool)
            $isInternal = (bool)($storage->internal ?? false);

            // 2) Explicit @psalm-internal scope(s). Psalm stores these as a map of "FQN => true".
            /** @var array<string,true> $psalmInternal */
            $psalmInternal = $storage->psalm_internal ?? [];

            // By default, assume not allowed; we'll prove allowance below.
            $allowed = false;

            if (!empty($psalmInternal)) {
                // Allow if current namespace starts with any allowed namespace prefix.
                foreach (array_keys($psalmInternal) as $allowedNs) {
                    $allowedNs = rtrim($allowedNs, '\\');
                    if ($allowedNs !== '' && self::nsStartsWith($currentNamespace, $allowedNs)) {
                        $allowed = true;
                        break;
                    }
                }
            } elseif ($isInternal) {
                // Heuristic for @internal: allow only inside the trait's declaring namespace.
                // E.g. trait declared in "Lib\Internal" is allowed in "Lib\Internal\*".
                $declaringNs = (string)($storage->namespace_name ?? '');
                if ($declaringNs !== '' && self::nsStartsWith($currentNamespace, $declaringNs)) {
                    $allowed = true;
                }
            } else {
                // No internal markers → public trait.
                $allowed = true;
            }


            // Emit a precise, developer-friendly error. Users can baseline or suppress if needed.
            if (!$allowed) {
                IssueBuffer::accepts(
                    new InternalTraitUse(
                        sprintf(
                            'Trait %s is internal to %s and cannot be used from %s. ' .
                            'Consider exposing a public API or using a non-internal alternative.',
                            $fqcn,
                            ($storage->namespace_name ?? '(global)'),
                            ($currentNamespace !== '' ? $currentNamespace : '(global)')
                        ),
                        $location
                    ),
                    $source->getSuppressedIssues()
                );
            }
        }

        // If we reach this point, the trait use is allowed.
        return null;
    }

    /**
     * Returns true if $subjectNs is equal to $prefixNs or starts with "$prefixNs\".
     *
     * Examples:
     *  - nsStartsWith('A\B\C', 'A\B')   → true
     *  - nsStartsWith('A\B',   'A\B')   → true
     *  - nsStartsWith('A\BC',  'A\B')   → false
     *  - nsStartsWith('',      'A\B')   → false
     *
     * @param string $subjectNs Current/using namespace (may be '').
     * @param string $prefixNs  Allowed/declaring namespace (non-empty).
     */
    private static function nsStartsWith(string $subjectNs, string $prefixNs): bool
    {
        // Normalize both namespaces by trimming trailing backslashes
        $subject = $subjectNs === '' ? '' : rtrim($subjectNs, '\\');
        $prefix  = rtrim($prefixNs, '\\');

        // When the prefix is empty, we can't match anything
        if ($prefix === '') return false;

        // If the subject is exactly the same as the prefix, we have a match
        if ($subject === $prefix) return true;

        // Require a boundary `\` to avoid false positives like A\BC vs A\B
        return str_starts_with($subject . '\\', $prefix . '\\');
    }
}
