<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

require_once dirname(__FILE__) . '/support/StatsbestsuppliersTestCase.php';
require_once dirname(__FILE__) . '/support/StatsbestsuppliersProbe.php';

class SqlAggregationTest extends StatsbestsuppliersTestCase
{
    /**
     * @param int    $idSupplier
     * @param string $name
     * @param int    $idShop
     */
    protected function insertSupplier($idSupplier, $name, $idShop = 1)
    {
        Db::exec(
            'INSERT INTO ps_supplier (id_supplier, name, id_shop) VALUES ('
            . (int) $idSupplier . ', '
            . Db::getPdo()->quote($name) . ', '
            . (int) $idShop . ')'
        );
    }

    /**
     * @param int      $idProduct
     * @param int|null $idSupplier
     * @param int      $idShop
     */
    protected function insertProduct($idProduct, $idSupplier, $idShop = 1)
    {
        $supplierSql = ($idSupplier === null) ? 'NULL' : (int) $idSupplier;
        Db::exec(
            'INSERT INTO ps_product (id_product, id_supplier, id_shop) VALUES ('
            . (int) $idProduct . ', '
            . $supplierSql . ', '
            . (int) $idShop . ')'
        );
    }

    /**
     * @param int    $idOrder
     * @param string $invoiceDate
     * @param int    $valid
     * @param float  $conversionRate
     * @param int    $idShop
     */
    protected function insertOrder($idOrder, $invoiceDate, $valid, $conversionRate, $idShop = 1)
    {
        Db::exec(
            'INSERT INTO ps_orders (id_order, invoice_date, valid, conversion_rate, id_shop) VALUES ('
            . (int) $idOrder . ', '
            . Db::getPdo()->quote($invoiceDate) . ', '
            . (int) $valid . ', '
            . (float) $conversionRate . ', '
            . (int) $idShop . ')'
        );
    }

    /**
     * @param int   $idDetail
     * @param int   $idOrder
     * @param int   $idProduct
     * @param int   $quantity
     * @param float $unitPriceTaxExcl
     * @param float $productPrice
     */
    protected function insertOrderDetail(
        $idDetail,
        $idOrder,
        $idProduct,
        $quantity,
        $unitPriceTaxExcl,
        $productPrice
    ) {
        Db::exec(
            'INSERT INTO ps_order_detail (id_order_detail, id_order, product_id, product_quantity, unit_price_tax_excl, product_price) VALUES ('
            . (int) $idDetail . ', '
            . (int) $idOrder . ', '
            . (int) $idProduct . ', '
            . (int) $quantity . ', '
            . (float) $unitPriceTaxExcl . ', '
            . (float) $productPrice . ')'
        );
    }

    /**
     * @return StatsbestsuppliersProbe
     */
    protected function newProbe()
    {
        return new StatsbestsuppliersProbe();
    }

    protected function assertSalesString($value, $expected)
    {
        $this->assertInternalType('string', $value);
        $this->assertRegExp('/^[0-9]+\.[0-9]{2}$/', $value);
        $this->assertSame($expected, $value);
    }

    protected function assertQuantityString($value, $expectedInt)
    {
        $this->assertInternalType('string', $value);
        $this->assertRegExp('/^[0-9]+$/', (string) $value);
        $this->assertSame((string) $expectedInt, (string) $value);
    }

    /**
     * @param array  $rows
     * @param string $field
     *
     * @return array
     */
    protected function columnFromRows($rows, $field)
    {
        $values = array();
        foreach ($rows as $row) {
            $values[] = $row[$field];
        }

        return $values;
    }

    protected function seedThreeSupplierFixture()
    {
        $this->insertSupplier(1, 'Alpha');
        $this->insertSupplier(2, 'Beta');
        $this->insertSupplier(3, 'Gamma');
        $this->insertProduct(101, 1);
        $this->insertProduct(102, 2);
        $this->insertProduct(103, 3);
        $this->insertOrder(1, '2019-06-15 12:00:00', 1, 1);
        $this->insertOrder(2, '2019-06-16 12:00:00', 1, 1);
        $this->insertOrder(3, '2019-06-17 12:00:00', 1, 1);
        $this->insertOrderDetail(1, 1, 101, 5, 2.00, 100.00);
        $this->insertOrderDetail(2, 2, 102, 1, 50.00, 100.00);
        $this->insertOrderDetail(3, 3, 103, 9, 2.00, 100.00);
    }

    public function testEmptyCatalogReturnsIntegerZeroAndEmptyRows()
    {
        $probe = $this->newProbe();
        $probe->setSort('name');
        $probe->setDirection('ASC');

        $this->assertSame(0, $probe->getTotalCount());
        $probe->getData();
        $this->assertSame(0, $probe->getGridTotalCount());
        $this->assertSame(array(), $probe->getValues());
    }

    public function testInvalidOrdersAreExcluded()
    {
        $this->insertSupplier(1, 'A');
        $this->insertSupplier(2, 'B');
        $this->insertProduct(1, 1);
        $this->insertProduct(2, 2);
        $this->insertOrder(1, '2019-06-10 12:00:00', 1, 1);
        $this->insertOrder(2, '2019-06-11 12:00:00', 0, 1);
        $this->insertOrderDetail(1, 1, 1, 1, 10.00, 100.00);
        $this->insertOrderDetail(2, 2, 2, 1, 10.00, 100.00);

        $probe = $this->newProbe();
        $this->assertSame(1, $probe->getTotalCount());
        $probe->setSort('name');
        $probe->setDirection('ASC');
        $probe->getData();

        $names = $this->columnFromRows($probe->getValues(), 'name');
        $this->assertSame(array('A'), $names);
    }

    public function testInvoiceWindowIncludesEndpointsOnly()
    {
        $letters = array('May', 'JunStart', 'JunEnd', 'Jul');
        foreach (array(1, 2, 3, 4) as $id) {
            $this->insertSupplier($id, $letters[$id - 1]);
            $this->insertProduct($id, $id);
        }
        $dates = array(
            1 => '2019-05-31 23:59:59',
            2 => '2019-06-01 00:00:00',
            3 => '2019-06-30 23:59:59',
            4 => '2019-07-01 00:00:00',
        );
        foreach ($dates as $orderId => $date) {
            $this->insertOrder($orderId, $date, 1, 1);
            $this->insertOrderDetail($orderId, $orderId, $orderId, 1, 1.00, 1.00);
        }

        $probe = $this->newProbe();
        $this->assertSame(2, $probe->getTotalCount());
        $probe->setSort('name');
        $probe->setDirection('ASC');
        $probe->getData();

        $this->assertSame(array('JunEnd', 'JunStart'), $this->columnFromRows($probe->getValues(), 'name'));
    }

    public function testLinesWithoutSupplierAreExcluded()
    {
        $this->insertSupplier(1, 'Kept');
        $this->insertProduct(1, 1);
        $this->insertProduct(2, null);
        $this->insertProduct(3, 999);
        $this->insertOrder(1, '2019-06-05 12:00:00', 1, 1);
        $this->insertOrder(2, '2019-06-06 12:00:00', 1, 1);
        $this->insertOrder(3, '2019-06-07 12:00:00', 1, 1);
        $this->insertOrderDetail(1, 1, 1, 1, 1.00, 1.00);
        $this->insertOrderDetail(2, 2, 2, 1, 1.00, 1.00);
        $this->insertOrderDetail(3, 3, 3, 1, 1.00, 1.00);

        $probe = $this->newProbe();
        $this->assertSame(1, $probe->getTotalCount());
        $probe->setSort('name');
        $probe->setDirection('ASC');
        $probe->getData();
        $this->assertSame(array('Kept'), $this->columnFromRows($probe->getValues(), 'name'));
    }

    public function testOtherShopIsExcluded()
    {
        $this->insertSupplier(1, 'InShop', 1);
        $this->insertSupplier(2, 'OtherShop', 2);
        $this->insertProduct(1, 1, 1);
        $this->insertProduct(2, 2, 2);
        $this->insertOrder(1, '2019-06-05 12:00:00', 1, 1, 1);
        $this->insertOrder(2, '2019-06-06 12:00:00', 1, 1, 2);
        $this->insertOrderDetail(1, 1, 1, 1, 10.00, 99.00);
        $this->insertOrderDetail(2, 2, 2, 1, 99.00, 99.00);

        $probe = $this->newProbe();
        $this->assertSame(1, $probe->getTotalCount());
        $probe->setSort('sales');
        $probe->setDirection('DESC');
        $probe->getData();

        $values = $probe->getValues();
        $this->assertCount(1, $values);
        $this->assertSame('InShop', $values[0]['name']);
        $this->assertSalesString($values[0]['sales'], '10.00');
    }

    public function testQuantitySumsAndSalesUseTaxExcludedUnitPrice()
    {
        $this->insertSupplier(1, 'Solo');
        $this->insertProduct(1, 1);
        $this->insertOrder(1, '2019-06-05 12:00:00', 1, 1);
        $this->insertOrderDetail(1, 1, 1, 2, 10.00, 100.00);
        $this->insertOrderDetail(2, 1, 1, 3, 4.00, 80.00);

        $probe = $this->newProbe();
        $probe->setSort('name');
        $probe->setDirection('ASC');
        $probe->getData();
        $row = $probe->getValues()[0];
        $this->assertQuantityString($row['quantity'], 5);
        $this->assertSalesString($row['sales'], '32.00');
    }

    public function testUniformConversionRateIsApplied()
    {
        $this->insertSupplier(1, 'Solo');
        $this->insertProduct(1, 1);
        $this->insertOrder(1, '2019-06-05 12:00:00', 1, 2);
        $this->insertOrderDetail(1, 1, 1, 4, 10.00, 99.00);

        $probe = $this->newProbe();
        $probe->setSort('name');
        $probe->setDirection('ASC');
        $probe->getData();
        $this->assertSalesString($probe->getValues()[0]['sales'], '20.00');
    }

    public function testMixedConversionRatesAreConvertedPerLine()
    {
        $this->insertSupplier(1, 'Solo');
        $this->insertProduct(1, 1);
        $this->insertOrder(1, '2019-06-05 12:00:00', 1, 1);
        $this->insertOrder(2, '2019-06-06 12:00:00', 1, 2);
        $this->insertOrderDetail(1, 1, 1, 1, 10.00, 99.00);
        $this->insertOrderDetail(2, 2, 1, 1, 10.00, 99.00);

        $probe = $this->newProbe();
        $probe->setSort('name');
        $probe->setDirection('ASC');
        $probe->getData();
        $row = $probe->getValues()[0];
        $this->assertQuantityString($row['quantity'], 2);
        $this->assertSalesString($row['sales'], '15.00');
    }

    public function testTotalCountIsDistinctSuppliersNotLines()
    {
        $this->insertSupplier(1, 'A');
        $this->insertSupplier(2, 'B');
        $this->insertProduct(1, 1);
        $this->insertProduct(2, 2);
        $this->insertOrder(1, '2019-06-05 12:00:00', 1, 1);
        $this->insertOrderDetail(1, 1, 1, 2, 1.00, 1.00);
        $this->insertOrderDetail(2, 1, 1, 3, 1.00, 1.00);
        $this->insertOrderDetail(3, 1, 2, 4, 1.00, 1.00);

        $probe = $this->newProbe();
        $probe->setSort('name');
        $probe->setDirection('ASC');
        $probe->getData();

        $this->assertSame(2, $probe->getGridTotalCount());
        $this->assertCount(2, $probe->getValues());
        $quantities = $this->columnFromRows($probe->getValues(), 'quantity');
        $this->assertSame(array('5', '4'), $quantities);
    }

    public function testSortOrdersBySalesQuantityAndNameDiffer()
    {
        $this->seedThreeSupplierFixture();

        $probe = $this->newProbe();

        $probe->setSort('sales');
        $probe->setDirection('DESC');
        $probe->getData();
        $this->assertSame(array('Beta', 'Gamma', 'Alpha'), $this->columnFromRows($probe->getValues(), 'name'));

        $probe->setSort('quantity');
        $probe->setDirection('DESC');
        $probe->getData();
        $this->assertSame(array('Gamma', 'Alpha', 'Beta'), $this->columnFromRows($probe->getValues(), 'name'));

        $probe->setSort('name');
        $probe->setDirection('ASC');
        $probe->getData();
        $this->assertSame(array('Alpha', 'Beta', 'Gamma'), $this->columnFromRows($probe->getValues(), 'name'));
    }

    public function testLowercaseDirectionIsNotApplied()
    {
        $this->seedThreeSupplierFixture();

        $probe = $this->newProbe();
        $probe->setSort('sales');
        $probe->setDirection('desc');
        $probe->getData();

        $this->assertSame(array('Alpha', 'Gamma', 'Beta'), $this->columnFromRows($probe->getValues(), 'name'));
    }

    public function testSortThatFailsIsNameIsNotConcatenated()
    {
        $this->seedThreeSupplierFixture();

        $probe = $this->newProbe();
        $probe->setSort('sales;drop');
        $probe->setDirection('DESC');
        $probe->getData();

        $names = $this->columnFromRows($probe->getValues(), 'name');
        $this->assertCount(3, $names);
        sort($names);
        $this->assertSame(array('Alpha', 'Beta', 'Gamma'), $names);
    }

    public function testStringZeroStartLimitsToFirstRow()
    {
        $this->seedThreeSupplierFixture();

        $probe = $this->newProbe();
        $probe->setSort('name');
        $probe->setDirection('ASC');
        $probe->setStart('0');
        $probe->setLimit(1);
        $probe->getData();

        $this->assertSame(array('Alpha'), $this->columnFromRows($probe->getValues(), 'name'));
        $this->assertSame(3, $probe->getGridTotalCount());
    }

    public function testOffsetReturnsSecondRowAndKeepsFullTotal()
    {
        $this->seedThreeSupplierFixture();

        $probe = $this->newProbe();
        $probe->setSort('name');
        $probe->setDirection('ASC');
        $probe->setStart(1);
        $probe->setLimit(1);
        $probe->getData();

        $this->assertSame(array('Beta'), $this->columnFromRows($probe->getValues(), 'name'));
        $this->assertSame(3, $probe->getGridTotalCount());
    }

    public function testInvalidStartOmitsLimit()
    {
        $this->seedThreeSupplierFixture();

        $probe = $this->newProbe();
        $probe->setSort('name');
        $probe->setDirection('ASC');
        $probe->setStart('1abc');
        $probe->setLimit(1);
        $probe->getData();

        $this->assertCount(3, $probe->getValues());
    }

    public function testQueriesRequestTheSlaveConnection()
    {
        $probe = $this->newProbe();
        $probe->setSort('name');
        $probe->setDirection('ASC');
        $probe->getData();

        $this->assertGreaterThanOrEqual(2, count(Db::$getInstanceCalls));
        foreach (Db::$getInstanceCalls as $arguments) {
            $this->assertSame(array(true), $arguments);
        }
    }
}
