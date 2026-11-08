<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

class Context
{
    /** @var Context */
    private static $instance;

    /** @var int */
    public $language;

    public function __construct()
    {
        $this->language = (object) array('id' => 1);
    }

  /**
     * @return Context
     */
    public static function getContext()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function reset()
    {
        self::$instance = null;
    }
}

class Module
{
    /** @var string */
    public $name;

    /** @var string */
    public $tab;

    /** @var string */
    public $version;

    /** @var string */
    public $author;

    /** @var int */
    public $need_instance;

    /** @var string */
    public $displayName;

    /** @var string */
    public $description;

    /** @var array */
    public $ps_versions_compliancy;

    /** @var Context */
    public $context;

    /** @var bool */
    public static $installResult = true;

    /** @var bool */
    public static $registerHookResult = true;

    /** @var bool */
    public static $unregisterHookResult = true;

    /** @var array */
    public static $translationLog = array();

    /** @var array */
    public $hooksRegistered = array();

    /** @var array */
    public $hooksUnregistered = array();

    public function __construct()
    {
        $this->context = Context::getContext();
    }

    /**
     * @return bool
     */
    public function install()
    {
        return self::$installResult;
    }

    /**
     * @param string $hookName
     *
     * @return bool
     */
    public function registerHook($hookName)
    {
        $this->hooksRegistered[] = $hookName;

        return self::$registerHookResult;
    }

    /**
     * @param string $hookName
     *
     * @return bool
     */
    public function unregisterHook($hookName)
    {
        $this->hooksUnregistered[] = $hookName;

        return self::$unregisterHookResult;
    }

    /**
     * @param string      $id
     * @param array       $parameters
     * @param string|null $domain
     * @param string|null $locale
     *
     * @return string
     */
    public function trans($id, $parameters = array(), $domain = null, $locale = null)
    {
        self::$translationLog[] = array(
            'id' => $id,
            'parameters' => $parameters,
            'domain' => $domain,
            'locale' => $locale,
        );

        return $id;
    }

    public static function resetStatics()
    {
        self::$installResult = true;
        self::$registerHookResult = true;
        self::$unregisterHookResult = true;
        self::$translationLog = array();
    }
}

abstract class ModuleGrid extends Module
{
    const GRID_ENGINE_SENTINEL = 'GRID_ENGINE_SENTINEL';

    /** @var array */
    protected $_values = array();

    /** @var int */
    protected $_totalCount = 0;

    /** @var mixed */
    protected $_sort = null;

    /** @var mixed */
    protected $_direction = null;

    /** @var mixed */
    protected $_start = null;

    /** @var mixed */
    protected $_limit = null;

    /** @var array */
    public static $lastEngineParams = null;

    /** @var array */
    public static $lastCsvParams = null;

    /** @var bool */
    public static $csvShouldThrow = false;

    abstract protected function getData();

    /**
     * @return string
     */
    public function getDate()
    {
        return " '2019-06-01 00:00:00' AND '2019-06-30 23:59:59' ";
    }

    /**
     * @param array $params
     *
     * @return string
     */
    public function engine($params)
    {
        self::$lastEngineParams = $params;

        return self::GRID_ENGINE_SENTINEL;
    }

    /**
     * @param array $datas
     */
    protected function csvExport($datas)
    {
        self::$lastCsvParams = $datas;

        if (self::$csvShouldThrow) {
            throw new RuntimeException('csv-export');
        }
    }

    public static function resetStatics()
    {
        self::$lastEngineParams = null;
        self::$lastCsvParams = null;
        self::$csvShouldThrow = false;
    }
}

class Shop
{
    const SHARE_ORDER = 'share_order';

    const CONTEXT_SHOP = 1;

    /**
     * @param mixed  $share
     * @param string $alias
     *
     * @return string
     */
    public static function addSqlRestriction($share, $alias = null)
    {
        if ($alias) {
            $alias .= '.';
        }

        return ' AND ' . $alias . 'id_shop IN (1) ';
    }
}

class Validate
{
    /**
     * @param string $name
     *
     * @return bool
     */
    public static function isName($name)
    {
        return (bool) preg_match('/^[^0-9!<>,;?=+()@#"°{}_$%:]*$/u', stripslashes($name));
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public static function isUnsignedInt($value)
    {
        return ((string) (int) $value === (string) $value && $value < 4294967296 && $value >= 0);
    }

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public static function isSortDirection($value)
    {
        return ($value !== null && ($value === 'ASC' || $value === 'DESC'));
    }
}

class Tools
{
    /** @var array */
    public static $fallbackParameters = array();

    /**
     * @param string $key
     * @param mixed  $default_value
     *
     * @return mixed
     */
    public static function getValue($key, $default_value = false)
    {
        if (empty($key) || !is_string($key)) {
            return false;
        }

        if (isset($_POST[$key]) || isset($_GET[$key])) {
            $value = isset($_POST[$key]) ? $_POST[$key] : $_GET[$key];
        } elseif (isset(self::$fallbackParameters[$key])) {
            $value = self::$fallbackParameters[$key];
        }

        if (!isset($value)) {
            $value = $default_value;
        }

        if (is_string($value)) {
            return urldecode(preg_replace('/((\%5C0+)|(\%00+))/i', '', urlencode($value)));
        }

        return $value;
    }

    /**
     * @param string $string
     * @param bool   $html
     *
     * @return string
     */
    public static function safeOutput($string, $html = false)
    {
        if (!$html) {
            $string = strip_tags($string);
        }

        return htmlentities((string) $string, ENT_QUOTES, 'utf-8');
    }

    public static function resetStatics()
    {
        self::$fallbackParameters = array();
    }
}

class Db
{
    /** @var Db */
    private static $instance;

    /** @var PDO */
    private static $pdo;

    /** @var array */
    public static $getInstanceCalls = array();

    /**
     * @param bool $slave
     *
     * @return Db
     */
    public static function getInstance($slave = false)
    {
        self::$getInstanceCalls[] = func_get_args();

        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return PDO
     */
    public static function getPdo()
    {
        if (self::$pdo === null) {
            $host = getenv('STATS_TEST_DB_HOST');
            if ($host === false || $host === '') {
                $host = 'mysql';
            }
            $dbname = getenv('STATS_TEST_DB_NAME');
            if ($dbname === false || $dbname === '') {
                $dbname = 'statsbestsuppliers';
            }
            $user = getenv('STATS_TEST_DB_USER');
            if ($user === false || $user === '') {
                $user = 'root';
            }
            $password = getenv('STATS_TEST_DB_PASSWORD');
            if ($password === false) {
                $password = 'statsbestsuppliers';
            }

            self::$pdo = new PDO(
                'mysql:host=' . $host . ';dbname=' . $dbname . ';charset=utf8mb4',
                $user,
                $password,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
            self::$pdo->exec(
                "SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'"
            );
        }

        return self::$pdo;
    }

    /**
     * @param string $sql
     *
     * @return mixed
     */
    public function getValue($sql)
    {
        $statement = self::getPdo()->query($sql);
        $row = $statement->fetch(PDO::FETCH_NUM);

        if ($row === false) {
            return null;
        }

        return $row[0];
    }

    /**
     * @param string $sql
     *
     * @return array
     */
    public function executeS($sql)
    {
        $statement = self::getPdo()->query($sql);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        if ($rows === false) {
            return array();
        }

        return $rows;
    }

    /**
     * @param string $sql
     */
    public static function exec($sql)
    {
        self::getPdo()->exec($sql);
    }

    public static function resetStatics()
    {
        self::$getInstanceCalls = array();
        self::$instance = null;
        self::$pdo = null;
    }
}
