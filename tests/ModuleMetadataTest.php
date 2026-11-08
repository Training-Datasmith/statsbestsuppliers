<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

require_once dirname(__FILE__) . '/support/StatsbestsuppliersTestCase.php';

class ModuleMetadataTest extends StatsbestsuppliersTestCase
{
    public function testIdentityProperties()
    {
        $module = $this->newModule();

        $this->assertSame('statsbestsuppliers', $module->name);
        $this->assertSame('administration', $module->tab);
        $this->assertSame('2.0.3', $module->version);
        $this->assertSame('PrestaShop', $module->author);
        $this->assertSame(0, $module->need_instance);
    }

    public function testCompatibilityRangeUsesRuntimeVersion()
    {
        $module = $this->newModule();

        $this->assertSame('1.7.1.0', $module->ps_versions_compliancy['min']);
        $this->assertSame(_PS_VERSION_, $module->ps_versions_compliancy['max']);
    }

    public function testConstructorTranslationKeys()
    {
        $module = $this->newModule();

        $expected = array(
            array('id' => 'Empty recordset returned', 'parameters' => array(), 'domain' => 'Admin.Notifications.Info', 'locale' => null),
            array(
                'id' => 'Displaying %1$s of %2$s',
                'parameters' => array('{0} - {1}', '{2}'),
                'domain' => 'Admin.Global',
                'locale' => null,
            ),
            array('id' => 'Name', 'parameters' => array(), 'domain' => 'Admin.Global', 'locale' => null),
            array('id' => 'Quantity sold', 'parameters' => array(), 'domain' => 'Admin.Global', 'locale' => null),
            array('id' => 'Total paid', 'parameters' => array(), 'domain' => 'Modules.Statsbestsuppliers.Admin', 'locale' => null),
            array('id' => 'Best suppliers', 'parameters' => array(), 'domain' => 'Modules.Statsbestsuppliers.Admin', 'locale' => null),
            array(
                'id' => 'Enrich your stats: add a list of the best suppliers to your dashboard.',
                'parameters' => array(),
                'domain' => 'Modules.Statsbestsuppliers.Admin',
                'locale' => null,
            ),
        );

        foreach ($expected as $index => $row) {
            $this->assertArrayHasKey($index, Module::$translationLog);
            $this->assertSame($row, Module::$translationLog[$index]);
        }

        $this->assertSame('Best suppliers', $module->displayName);
        $this->assertSame(
            'Enrich your stats: add a list of the best suppliers to your dashboard.',
            $module->description
        );
    }

    public function testConfigXmlNameAndVersionMatchClass()
    {
        $module = $this->newModule();
        $xml = simplexml_load_file(dirname(__DIR__) . '/config.xml');

        $this->assertSame((string) $xml->name, $module->name);
        $this->assertSame((string) $xml->version, $module->version);
    }

    public function testConstructorCallsParent()
    {
        $module = $this->newModule();

        $this->assertSame(Context::getContext(), $module->context);
    }
}
