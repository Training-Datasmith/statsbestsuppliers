<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

require_once dirname(__FILE__) . '/support/StatsbestsuppliersTestCase.php';

class WebGuardTest extends StatsbestsuppliersTestCase
{
    /**
     * @param string $target
     */
    protected function assertGuardedIncludeExitsCleanly($target)
    {
        $phpBinary = getenv('PHP_BIN');
        if ($phpBinary === false || $phpBinary === '') {
            $phpBinary = 'php';
        }

        $wrapper = dirname(__FILE__) . '/support/include-target.php';
        $command = escapeshellarg($phpBinary)
            . ' -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1 -d log_errors=0 '
            . escapeshellarg($wrapper) . ' '
            . escapeshellarg($target);

        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        $process = proc_open($command, $descriptors, $pipes);
        $this->assertInternalType('resource', $process);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        $combined = $stdout . $stderr;

        $this->assertSame(0, $exitCode, 'Exit code was not zero: ' . $combined);
        $this->assertSame('', $stdout, 'Stdout was not empty');
        $this->assertNotContains('AFTER_INCLUDE', $combined);
        $this->assertNoPhpDiagnostics($combined);
    }

    public function testRootIndexExitsWithEmptyOutput()
    {
        $this->assertGuardedIncludeExitsCleanly(dirname(__DIR__) . '/index.php');
    }

    public function testTestsIndexExitsWithEmptyOutput()
    {
        $this->assertGuardedIncludeExitsCleanly(dirname(__FILE__) . '/index.php');
    }

    public function testPhpstanIndexExitsWithEmptyOutput()
    {
        $this->assertGuardedIncludeExitsCleanly(dirname(__FILE__) . '/phpstan/index.php');
    }

    public function testTranslationsIndexExitsWithEmptyOutput()
    {
        $this->assertGuardedIncludeExitsCleanly(dirname(__DIR__) . '/translations/index.php');
    }

    public function testUpgradeIndexExitsWithEmptyOutput()
    {
        $this->assertGuardedIncludeExitsCleanly(dirname(__DIR__) . '/upgrade/index.php');
    }

    public function testModuleFileExitsWhenPsVersionMissing()
    {
        $this->assertGuardedIncludeExitsCleanly(dirname(__DIR__) . '/statsbestsuppliers.php');
    }
}
