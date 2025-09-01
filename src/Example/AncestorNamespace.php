<?php declare(strict_types=1);

/**
 * Example: Ancestor & Descendant access
 *
 * Rule: Access is allowed when the consumer namespace is equal to, an ancestor of,
 * or a descendant of the trait’s restricted namespace. Access is denied only for
 * unrelated (different-branch) namespaces.
 */

namespace Tree\Branch\Leaf {
    /**
     * Restricted to the Tree\Branch\Leaf "family" (equal/ancestor/descendant).
     * For clarity, you might represent this with a custom rule in your plugin.
     * If you were mapping from annotations, interpret them as a symmetric boundary.
     *
     * @psalm-internal Tree\Branch\Leaf
     */
    trait LeafOnly {}
}

namespace Tree\Branch\Leaf\Consumer {
    // Allowed: descendant of Tree\Branch\Leaf
    final class DescendantOk {
        use \Tree\Branch\Leaf\LeafOnly;
    }
}

namespace Tree\Branch {
    // Allowed: ancestor of Tree\Branch\Leaf
    final class AncestorOk {
        use \Tree\Branch\Leaf\LeafOnly;
    }
}

namespace Tree\Branch\Leaf {
    // Allowed: same namespace
    final class SameNamespaceOk {
        use \Tree\Branch\Leaf\LeafOnly;
    }
}

namespace Tree\Other {
    // Not allowed: unrelated namespace (different branch)
    final class UnrelatedNotAllowed {
        use \Tree\Branch\Leaf\LeafOnly;
    }
}
