<?php
declare(strict_types=1);

namespace Ubean\Psalm\Internal\TraitEnforcer\Tests\Integration;

final class PsalmRunner
{
    /**
     * Run Psalm in $cwd and return decoded JSON report as array.
     *
     * @param string $cwd Directory containing psalm.xml and source files.
     * @return array{totals?:array, issues?:array<int, array>|null}
     */
    public static function run(string $cwd): array
    {
        $psalmBin = self::findPsalmBinary();

        // Always point Psalm explicitly at the config we wrote into $cwd
        $cmd = escapeshellarg($psalmBin)
            . ' --no-cache --output-format=json --show-info=false'
            . ' -c ' . escapeshellarg($cwd . '/psalm.xml');

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Run with CWD = the temp project folder
        $proc = proc_open($cmd, $descriptorSpec, $pipes, $cwd);

        if (!\is_resource($proc)) {
            throw new \RuntimeException('Failed to start Psalm process');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        if ($stdout === '') {
            // If Psalm crashed before producing JSON, surface stderr
            throw new \RuntimeException("Psalm failed:\n$stderr");
        }

        $json = json_decode($stdout, true);
        if (!\is_array($json)) {
            throw new \RuntimeException("Invalid Psalm JSON:\n$stdout\nstderr:\n$stderr");
        }

        return $json;
    }

    /**
     * Locate a runnable Psalm binary.
     * Priority:
     *  1) project-root/vendor/bin/psalm (preferred, executable wrapper)
     *  2) project-root/vendor/vimeo/psalm/psalm (PHP entry point)
     */
    private static function findPsalmBinary(): string
    {
        $root = self::projectRoot();

        $candidates = [
            $root . '/vendor/bin/psalm',
            $root . '/vendor/vimeo/psalm/psalm',
        ];

        foreach ($candidates as $bin) {
            if (is_file($bin) && (is_executable($bin) || self::isPhpFile($bin))) {
                return $bin;
            }
        }

        throw new \RuntimeException("Could not find Psalm binary in {$root}/vendor. "
            . "Did you run `composer install`?");
    }

    private static function isPhpFile(string $path): bool
    {
        // naive check: .php file or file begins with a PHP shebang/open tag
        if (str_ends_with($path, '.php')) {
            return true;
        }
        $fh = @fopen($path, 'r');
        if (!$fh) {
            return false;
        }
        $head = fread($fh, 12) ?: '';
        fclose($fh);
        return str_starts_with($head, '#!') || str_starts_with($head, '<?php');
    }

    /**
     * Resolve the plugin project root from tests/Integration/...
     * Adjust depth if you move files, but usually 2 levels up is correct.
     */
    private static function projectRoot(): string
    {
        // tests/Integration -> tests -> (root)
        return \dirname(__DIR__, 2);
    }
}
