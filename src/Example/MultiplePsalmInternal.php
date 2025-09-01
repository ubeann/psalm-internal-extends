<?php declare(strict_types=1);

/**
 * Example: Multiple @psalm-internal namespaces
 *
 * Demonstrates a trait annotated to allow use from multiple namespaces.
 */

namespace Lib\Multi {
    /**
     * @psalm-internal Lib\Alpha
     * @psalm-internal Lib\Beta
     */
    trait Shared {}
}

namespace Lib\Alpha\Consumer {
    final class AllowedFromAlpha {
        use \Lib\Multi\Shared; // allowed: under Lib\Alpha
    }
}

namespace Lib\Beta {
    final class AllowedFromBeta {
        use \Lib\Multi\Shared; // allowed: under Lib\Beta
    }
}

namespace Lib\Gamma {
    final class NotAllowedFromGamma {
        use \Lib\Multi\Shared; // expected: error (not under allowed namespaces)
    }
}
