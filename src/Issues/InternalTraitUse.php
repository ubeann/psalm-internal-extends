<?php
declare(strict_types=1);

namespace Ubean\Psalm\Internal\TraitEnforcer\Issues;

use Psalm\Issue\CodeIssue;

/**
 * Issue: InternalTraitUse
 *
 * This custom Psalm issue is triggered when a class attempts to `use` a trait
 * that has been marked as `@internal` or `@psalm-internal`.
 *
 * ---
 * #### Why this matters
 * Traits are often used as implementation details within a library or framework.
 * When marked as internal, they are **not part of the public API** and may change
 * without notice. Using them outside of their intended namespace can lead to
 * brittle code and unexpected breakages after updates.
 *
 * ---
 * #### Suggested fix
 * - If you are *inside* the intended namespace, no action is needed.
 * - If you are *outside* the namespace, avoid relying on this trait.
 *   Instead, look for a public API (class, interface, or helper) that
 *   provides the functionality you need.
 * - If this trait was intended to be used publicly, remove the `@internal`
 *   annotation in its source code.
 *
 * ---
 * #### Error level
 * This issue is reported as `ERROR` by default (`ERROR_LEVEL = 1`),
 * so it will block CI pipelines and be highly visible. You can
 * downgrade or suppress it in your `psalm.xml` if necessary,
 * but it is strongly recommended to respect internal boundaries.
 */
final class InternalTraitUse extends CodeIssue
{
    /**
     * Psalm issue level.
     *
     * You can configure this in your psalm.xml if you prefer warnings instead.
     *
     * @var int
     */
    public const ERROR_LEVEL = 1;
}
