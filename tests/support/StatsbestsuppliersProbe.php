<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

class StatsbestsuppliersProbe extends statsbestsuppliers
{
    /**
     * @param mixed $sort
     */
    public function setSort($sort)
    {
        $this->_sort = $sort;
    }

    /**
     * @param mixed $direction
     */
    public function setDirection($direction)
    {
        $this->_direction = $direction;
    }

    /**
     * @param mixed $start
     */
    public function setStart($start)
    {
        $this->_start = $start;
    }

    /**
     * @param mixed $limit
     */
    public function setLimit($limit)
    {
        $this->_limit = $limit;
    }

    /**
     * @return array
     */
    public function getValues()
    {
        return $this->_values;
    }

    /**
     * @return int
     */
    public function getGridTotalCount()
    {
        return $this->_totalCount;
    }
}
