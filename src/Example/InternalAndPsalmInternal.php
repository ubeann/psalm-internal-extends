<?php declare(strict_types=1);

/**
 * Example: Both @internal and @psalm-internal annotations
 *
 * When both are present, the plugin checks @psalm-internal first to determine
 * allowed namespaces; otherwise falls back to @internal's declaring namespace.
 */

namespace Lib\Both {
    /**
     * @internal
     * @psalm-internal Lib\Allowed
     */
    trait MixedHidden {}
}

namespace Lib\Allowed\X {
    final class AllowedHere {
        use \Lib\Both\MixedHidden; // allowed: within @psalm-internal Lib\Allowed
    }
}

namespace Lib\Both {
    final class AlsoAllowed {
        use \Lib\Both\MixedHidden; // allowed: declaring namespace matches @internal boundary
    }
}

namespace Other {
    final class NotAllowed {
        use \Lib\Both\MixedHidden; // expected: error (outside allowed namespaces)
    }
}
