<?php declare(strict_types=1);

/**
 * Example: Allowed @psalm-internal usage
 *
 * Trait is annotated with `@psalm-internal Lib\\Secret` and is used from a
 * sub-namespace of `Lib\\Secret`, which is permitted by the rule.
 *
 * Expected outcome: No InternalTraitUse error.
 */

namespace Lib\Secret {
    /**
     * @psalm-internal Lib\Secret
     */
    trait Hidden2 {
        public function go(): void {}
    }
}

namespace Lib\Secret\Consumer {
    final class Ok
    {
        use \Lib\Secret\Hidden2; // allowed: inside permitted namespace
    }
}
