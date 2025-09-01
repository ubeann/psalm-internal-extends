<?php
declare(strict_types=1);

/**
 * Example: Valid trait usage
 *
 * This file demonstrates a permitted scenario where a public trait is used
 * from another namespace. Since `Lib\\PublicT` has no `@internal` or
 * `@psalm-internal` annotation, it is considered part of the public API.
 *
 * Expected outcome: No InternalTraitUse error.
 */

namespace Lib {
    trait PublicT {}
}

namespace App {
    final class Good {
        use \Lib\PublicT;
    }
}
