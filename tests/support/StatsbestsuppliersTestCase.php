<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

require_once dirname(__DIR__) . '/bootstrap.php';

class StatsbestsuppliersTestCase extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        Db::resetStatics();
        Module::resetStatics();
        ModuleGrid::resetStatics();
        Tools::resetStatics();
        Context::reset();

        $_GET = array();
        $_POST = array();
        unset($_SERVER['REQUEST_URI']);

        $this->truncateModuleTables();
    }

    protected function truncateModuleTables()
    {
        Db::exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (array('ps_order_detail', 'ps_orders', 'ps_product', 'ps_supplier') as $table) {
            Db::exec('TRUNCATE TABLE ' . $table);
        }
        Db::exec('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * @return statsbestsuppliers
     */
    protected function newModule()
    {
        return new statsbestsuppliers();
    }

    protected function assertNoPhpDiagnostics($output)
    {
        $patterns = array(
            'Notice:',
            'Warning:',
            'Deprecated:',
            'Strict Standards:',
            'Parse error:',
            'Fatal error:',
            'Catchable fatal error:',
            'Recoverable fatal error:',
        );

        foreach ($patterns as $pattern) {
            $this->assertNotContains($pattern, $output, 'PHP diagnostic found in output');
        }
    }
}
