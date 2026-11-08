<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

if ($argc < 2) {
    fwrite(STDERR, "Missing target path\n");
    exit(1);
}

$target = $argv[1];
if (!is_file($target)) {
    fwrite(STDERR, "Target not found\n");
    exit(1);
}

require $target;
echo "AFTER_INCLUDE\n";
