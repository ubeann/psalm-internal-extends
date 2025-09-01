<?php declare(strict_types=1);

/**
 * Example: Invalid internal trait usage
 *
 * This file demonstrates a usage that should be flagged by the plugin's
 * InternalTraitUse rule:
 * - The trait `Lib\\Internal\\Hidden` is marked `@internal` and thus intended
 *   to be used only within (or under) its declaring namespace.
 * - `App\\Bad` attempts to `use` the trait from a different namespace.
 *
 * Expected outcome: Psalm reports an InternalTraitUse error for the `use` line.
 */

namespace Lib\Internal {
    /**
     * @internal
     */
    trait Hidden
    {
        /**
         * No-op method; present only to make the trait non-empty for analysis.
         */
        public function t(): void {}
    }
}

namespace App {
    /**
     * Using an `@internal` trait outside its allowed namespace; should be flagged.
     */
    final class Bad
    {
        use \Lib\Internal\Hidden; // expected: InternalTraitUse error (disallowed @internal trait)
    }
}
