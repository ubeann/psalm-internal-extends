<?php declare(strict_types=1);

/**
 * Example: Global-namespace @internal trait
 *
 * A trait declared in the global namespace as `@internal` should be usable only
 * from the global namespace according to the heuristic.
 *
 * Can't handle to make only `global` because the current implementation does not have
 * indicators for internal is present or not (using array length check), so it treats
 * empty list as public.
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
    final class GlobalDescendantOk {
        use \GlobalHidden; // allowed: descendant namespace
    }
}
