<?php declare(strict_types=1);

/**
 * Example: Public trait (no @internal) used from same and different namespaces.
 *
 * Expected outcome: Both uses are allowed (no InternalTraitUse error).
 */

namespace Lib\Plain {
	trait PublicTrait {
		public function ping(): string { return 'pong'; }
	}

	// Same-namespace usage — allowed
	final class SameNsUser {
		use PublicTrait;
	}
}

namespace App\Consumers {
	// Different-namespace usage — also allowed for public traits
	final class CrossNsUser {
		use \Lib\Plain\PublicTrait;
	}
}
