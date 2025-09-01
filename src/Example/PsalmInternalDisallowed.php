<?php declare(strict_types=1);

/**
 * Example: Disallowed @psalm-internal usage
 *
 * Trait is annotated with `@psalm-internal Lib\\Only` and is used from `App`,
 * which is outside the allowed namespace boundary.
 *
 * Expected outcome: InternalTraitUse error on the `use` statement.
 */

namespace Lib\Only {
    /**
     * @psalm-internal Lib\Only
     */
    trait Hidden3 {}
}

namespace App {
    final class NotOk
    {
        use \Lib\Only\Hidden3; // expected: error (outside @psalm-internal namespace)
    }
}
