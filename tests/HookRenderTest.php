<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

require_once dirname(__FILE__) . '/support/StatsbestsuppliersTestCase.php';

class HookRenderTest extends StatsbestsuppliersTestCase
{
  /**
     * @return string
     */
    private function expectedExportHref()
    {
        return '/admin/index.php?controller=AdminStats&amp;module=statsbestsuppliers&amp;export=1';
    }

    protected function setUp()
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/admin/index.php?controller=AdminStats&module=statsbestsuppliers';
    }

    public function testHookIncludesEngineOutputAndEscapedExportLink()
    {
        $module = $this->newModule();
        $html = $module->hookDisplayAdminStatsModules(array());

        $this->assertContains('panel-heading', $html);
        $this->assertContains('Best suppliers', $html);
        $this->assertContains(ModuleGrid::GRID_ENGINE_SENTINEL, $html);
        $this->assertContains('export-csv', $html);
        $this->assertContains('CSV Export', $html);
        $this->assertContains('href="' . $this->expectedExportHref(), $html);

        $params = ModuleGrid::$lastEngineParams;
        $this->assertInternalType('array', $params);
        $this->assertSame('sales', $params['defaultSortColumn']);
        $this->assertSame('DESC', $params['defaultSortDirection']);
        $this->assertSame('Best suppliers', $params['title']);
        $this->assertSame('Empty recordset returned', $params['emptyMessage']);
        $this->assertSame('Displaying %1$s of %2$s', $params['pagingMessage']);

        $this->assertCount(3, $params['columns']);
        $headers = array('Name', 'Quantity sold', 'Total paid');
        foreach (array('name', 'quantity', 'sales') as $index => $id) {
            $column = $params['columns'][$index];
            $this->assertSame($id, $column['id']);
            $this->assertSame($headers[$index], $column['header']);
            $this->assertSame($id, $column['dataIndex']);
            $this->assertSame('center', $column['align']);
        }

        $this->assertNull(ModuleGrid::$lastCsvParams);

        $foundCsvExport = false;
        foreach (Module::$translationLog as $entry) {
            if ($entry['id'] === 'CSV Export' && $entry['domain'] === 'Admin.Global') {
                $foundCsvExport = true;
                break;
            }
        }
        $this->assertTrue($foundCsvExport);
    }

    public function testExportOneCallsCsvExportBeforeEngine()
    {
        $_GET['export'] = '1';
        ModuleGrid::$csvShouldThrow = true;

        $module = $this->newModule();

        try {
            $module->hookDisplayAdminStatsModules(array());
            $this->fail('Expected csv-export exception');
        } catch (RuntimeException $exception) {
            $this->assertSame('csv-export', $exception->getMessage());
        }

        $csvParams = ModuleGrid::$lastCsvParams;
        $this->assertInternalType('array', $csvParams);
        $this->assertSame('sales', $csvParams['defaultSortColumn']);
        $this->assertSame('name', $csvParams['columns'][0]['id']);
        $this->assertSame('quantity', $csvParams['columns'][1]['id']);
        $this->assertSame('sales', $csvParams['columns'][2]['id']);
        $this->assertNull(ModuleGrid::$lastEngineParams);
    }

    public function testExportZeroRendersHtml()
    {
        $_GET['export'] = '0';

        $module = $this->newModule();
        $html = $module->hookDisplayAdminStatsModules(array());

        $this->assertContains(ModuleGrid::GRID_ENGINE_SENTINEL, $html);
        $this->assertNull(ModuleGrid::$lastCsvParams);
    }
}
