<?php
declare(strict_types=1);

namespace Ubean\Psalm\Internal\TraitEnforcer\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class InternalTraitUseTest extends TestCase
{
    public function testPsalmInternalDisallowsUseOutsideNamespace(): void
    {
        $dir = $this->makeTempProject([
            'psalm.xml' => $this->psalmXml(),
            'src/InternalTrait.php' => <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace A\B {
                    /**
                     * @psalm-internal A\B
                     */
                    trait InternalT {
                        public function t(): void {}
                    }
                }
                PHP,
            'src/UseOutside.php' => <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace A\B\C {
                    class UsesTrait {
                        use \A\B\InternalT; // should be flagged
                    }
                }
                PHP,
        ]);

        $report = PsalmRunner::run($dir);
        $issues = $report['issues'] ?? [];

        $this->assertNotEmpty($issues, 'Expected at least one issue');
        $this->assertTrue(
            $this->containsIssueType($issues, 'InternalTraitUse'),
            'Expected InternalTraitUse to be reported for cross-namespace trait use'
        );
    }

    public function testPsalmInternalAllowsUseInsideNamespace(): void
    {
        $dir = $this->makeTempProject([
            'psalm.xml' => $this->psalmXml(),
            'src/InternalTrait.php' => <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace A\B {
                    /**
                     * @psalm-internal A\B
                     */
                    trait InternalT {
                        public function t(): void {}
                    }
                    class OK {
                        use InternalT; // allowed
                    }
                }
                PHP,
        ]);

        $report = PsalmRunner::run($dir);
        $issues = $report['issues'] ?? [];

        $this->assertFalse(
            $this->containsIssueType($issues, 'InternalTraitUse'),
            'Did not expect InternalTraitUse inside allowed namespace'
        );
    }

    public function testInternalHeuristicDisallowsOutsideDeclaringNamespace(): void
    {
        $dir = $this->makeTempProject([
            'psalm.xml' => $this->psalmXml(),
            'src/InternalTrait.php' => <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace Lib\Internal {
                    /**
                     * @internal
                     */
                    trait Hidden {
                        public function t(): void {}
                    }
                }

                namespace App {
                    class Bad {
                        use \Lib\Internal\Hidden; // should be flagged by heuristic
                    }
                }
                PHP,
        ]);

        $report = PsalmRunner::run($dir);
        $issues = $report['issues'] ?? [];

        $this->assertTrue(
            $this->containsIssueType($issues, 'InternalTraitUse'),
            'Expected InternalTraitUse for @internal outside declaring namespace'
        );
    }

    public function testPublicTraitProducesNoIssue(): void
    {
        $dir = $this->makeTempProject([
            'psalm.xml' => $this->psalmXml(),
            'src/PublicTrait.php' => <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace Lib {
                    trait PublicT {}
                }

                namespace App {
                    class Good {
                        use \Lib\PublicT; // allowed
                    }
                }
                PHP,
        ]);

        $report = PsalmRunner::run($dir);
        $issues = $report['issues'] ?? [];

        $this->assertFalse(
            $this->containsIssueType($issues, 'InternalTraitUse'),
            'Public traits should not trigger InternalTraitUse'
        );
    }

    // ----------------- helpers -----------------

    private function psalmXml(): string
    {
        return <<<'XML'
            <?xml version="1.0"?>
            <psalm errorLevel="1" findUnusedCode="false" cacheDirectory=".psalm-cache">
              <projectFiles>
                <directory name="src" />
              </projectFiles>
              <plugins>
                <pluginClass class="Ubean\Psalm\Internal\TraitEnforcer\Plugin" />
              </plugins>
            </psalm>
            XML;
    }

    /**
     * @param array<string,string> $files relativePath => contents
     */
    private function makeTempProject(array $files): string
    {
        $dir = sys_get_temp_dir() . '/psalm-internal-trait-' . bin2hex(random_bytes(6));
        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create temp dir: $dir");
        }

        foreach ($files as $path => $contents) {
            $full = $dir . '/' . $path;
            $parent = dirname($full);
            if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
                throw new \RuntimeException("Failed to create dir: $parent");
            }
            file_put_contents($full, $contents);
        }

        return $dir;
    }

    /**
     * @param array<int,array> $issues
     */
    private function containsIssueType(array $issues, string $type): bool
    {
        foreach ($issues as $issue) {
            if (($issue['type'] ?? '') === $type) {
                return true;
            }
        }
        return false;
    }
}
