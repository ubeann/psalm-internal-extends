<?php declare(strict_types=1);

/**
 * Example: Global-namespace @internal trait
 *
 * A trait declared in the global namespace as `@internal` should be usable only
 * from the global namespace according to the heuristic.
 */

namespace {
    /**
     * @internal
     */
    trait GlobalHidden {}
}

namespace {
    final class GlobalOk {
        use \GlobalHidden; // allowed: same (global) namespace
    }
}

namespace OtherNs {
    final class GlobalNotOk {
        use \GlobalHidden; // expected: error (not in global namespace)
    }
}
