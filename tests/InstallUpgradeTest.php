<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

require_once dirname(__FILE__) . '/support/StatsbestsuppliersTestCase.php';

class InstallUpgradeTest extends StatsbestsuppliersTestCase
{
    public function testInstallRegistersDisplayAdminStatsModules()
    {
        Module::$installResult = true;
        Module::$registerHookResult = true;

        $module = $this->newModule();
        $this->assertTrue($module->install());
        $this->assertSame(array('displayAdminStatsModules'), $module->hooksRegistered);
    }

    public function testInstallStopsWhenParentInstallFails()
    {
        Module::$installResult = false;

        $module = $this->newModule();
        $this->assertFalse($module->install());
        $this->assertSame(array(), $module->hooksRegistered);
    }

    public function testInstallFailsWhenHookRegistrationFails()
    {
        Module::$installResult = true;
        Module::$registerHookResult = false;

        $module = $this->newModule();
        $this->assertFalse($module->install());
        $this->assertSame(array('displayAdminStatsModules'), $module->hooksRegistered);
    }

    public function testUpgradeMovesLegacyHookToDisplayHook()
    {
        $module = new Module();
        require_once dirname(__DIR__) . '/upgrade/upgrade-2.0.1.php';

        upgrade_module_2_0_1($module);

        $this->assertSame(array('AdminStatsModules'), $module->hooksUnregistered);
        $this->assertSame(array('displayAdminStatsModules'), $module->hooksRegistered);
    }
}
