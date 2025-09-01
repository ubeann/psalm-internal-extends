<?php declare(strict_types=1);

/**
 * Example: Namespace boundary behavior
 *
 * Demonstrates that `A\\B` allows sub-namespaces like `A\\B\\C`, but should
 * not allow `A\\BC` (no segment boundary).
 */

namespace A\B {
    /**
     * @internal
     */
    trait OnlyForB {}
}

namespace A\B\C {
    final class Allowed {
        use \A\B\OnlyForB; // allowed: A\B\C is under A\B
    }
}

namespace A\BC {
    final class Disallowed {
        use \A\B\OnlyForB; // expected: error (A\BC is NOT under A\B)
    }
}
