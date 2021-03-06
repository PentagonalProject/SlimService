<?php
/**
 * MIT License
 *
 * Copyright (c) 2017, Pentagonal
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NON INFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace PentagonalProject\SlimService;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\View;

/**
 * Class Database
 * @package PentagonalProject\SlimService
 *
 * Magic Method @see Database::__call()
 *      -> @uses Connection
 *
 * @method void beginTransaction()
 *
 * @method void         close()
 * @method void         commit()
 * @method bool         connect()
 * @method mixed        convertToDatabaseValue(mixed $value, $type)
 * @method mixed        convertToPHPValue(mixed $value, $type)
 * @method QueryBuilder createQueryBuilder()
 * @method void         createSavepoint(string $savepoint)
 *
 * @method int delete(string $tableExpression, array $identifier, array $types = [])
 *
 * @method int       errorCode()
 * @method array     errorInfo()
 * @method void      exec(string $statement)
 * @method Statement executeQuery(string $query, array $params = [], array $types = [], QueryCacheProfile $qcp = null)
 * @method ResultStatement executeCacheQuery(string $query, $params, $types, QueryCacheProfile $qcp)
 * @method int       executeUpdate(string $query, array $params = [], array $types = [])
 *
 * @method int  insert(string $tableExpression, array $data, array $types = [])
 * @method bool isAutoCommit()
 * @method bool isConnected()
 * @method bool isRollbackOnly()
 * @method bool isTransactionActive()
 *
 * @method array fetchAssoc(string $statement, array $params = [], array $types = [])
 * @method array fetchArray(string $statement, array $params = [], array $types = [])
 * @method array fetchColumn(string $statement, array $params = [], array $types = [])
 * @method array fetchAll(string $sql, array $params = array(), $types = array())
 *
 * @method Configuration         getConfiguration()
 * @method Driver                getDriver()
 * @method string                getDatabase()
 * @method AbstractPlatform      getDatabasePlatform()
 * @method EventManager          getEventManager()
 * @method ExpressionBuilder     getExpressionBuilder()
 * @method string                getHost()
 * @method array                 getParams()
 * @method string|null           getPassword()
 * @method mixed                 getPort()
 * @method AbstractSchemaManager getSchemaManager()
 * @method int                   getTransactionIsolation()
 * @method int                   getTransactionNestingLevel()
 * @method string|null           getUsername()
 * @method Connection            getWrappedConnection()
 *
 * @method string lastInsertId(string|null $seqName)
 *
 * @method bool      ping()
 * @method Statement prepare(string $statement)
 * @method array     project(string $query, array $params, \Closure $function)
 *
 * @method void      releaseSavepoint(string $savePoint)
 * @method array     resolveParams(array $params, array $types)
 * @method bool|void rollBack()
 * @method void      rollbackSavepoint(string $savePoint)
 *
 * @method void setAutoCommit(bool $autoCommit)
 * @method void setFetchMode(int $fetchMode)
 * @method void setNestTransactionsWithSavePoints(bool $nestTransactionsWithSavePoints)
 * @method void setRollbackOnly()
 * @method int  setTransactionIsolation(int $level)
 *
 * @method void transactional(\Closure $func)
 *
 * @method int update(string $tableExpression, array $data, array $identifier, array $types = [])
 *
 * @method string    quote(mixed $input, int $type = \PDO::PARAM_STR)
 * @method string    quoteIdentifier(string $str)
 *
 * @uses \PDO::ATTR_DEFAULT_FETCH_MODE for (19)
 * @method Statement query(string $sql, int $mode = 19, mixed $additionalArg = null, array $constructorArgs = [])
 */
class Database
{
    /**
     * @see Connection::TRANSACTION_READ_UNCOMMITTED
     */
    const TRANSACTION_READ_UNCOMMITTED = Connection::TRANSACTION_READ_UNCOMMITTED;

    /**
     * @see Connection::TRANSACTION_READ_COMMITTED
     */
    const TRANSACTION_READ_COMMITTED   = Connection::TRANSACTION_READ_COMMITTED;

    /**
     * @see Connection::TRANSACTION_REPEATABLE_READ
     */
    const TRANSACTION_REPEATABLE_READ = Connection::TRANSACTION_REPEATABLE_READ;

    /**
     * @see Connection::TRANSACTION_SERIALIZABLE
     */
    const TRANSACTION_SERIALIZABLE = Connection::TRANSACTION_SERIALIZABLE;

    /**
     * @see Connection::PARAM_INT_ARRAY
     */
    const PARAM_INT_ARRAY = Connection::PARAM_INT_ARRAY;

    /**
     * @see Connection::PARAM_STR_ARRAY
     */
    const PARAM_STR_ARRAY = Connection::PARAM_STR_ARRAY;

    /**
     * @see Connection::ARRAY_PARAM_OFFSET
     */
    const ARRAY_PARAM_OFFSET = Connection::ARRAY_PARAM_OFFSET;

    /**
     * @var Connection
     */
    protected $currentConnection;

    /**
     * @var string
     */
    protected $currentSelectedDriver;

    /**
     * @var array
     */
    protected $currentUserParams = [];

    /**
     * @var array
     */
    protected $defaultParams = [
        self::DB_CHARSET => self::DEFAULT_CHARSET,
    ];

    /**
     * @var string
     */
    protected $currentTablePrefix = '';

    /**
     * Column Quote Identifier
     *
     * @var string
     */
    protected $currentQuoteIdentifier = '"';

    /**
     * Default Driver
     *
     * @var string
     */
    protected $defaultDriver = 'mysql';

    /**
     * Default character Set
     */
    const DEFAULT_CHARSET = 'UTF8';

    /**
     * Setting
     */
    const
        DB_HOST = 'host',
        DB_USER = 'user',
        DB_NAME = 'dbname',
        DB_PASSWORD = 'password',
        DB_DRIVER   = 'driver',
        DB_PATH     = 'path',
        DB_PORT     = 'port',
        DB_PREFIX   = 'prefix',
        DB_PROTOCOL = 'protocol',
        DB_CHARSET  = 'charset',
        DB_COLLATE  = 'collate';

    /**
     * @var array
     */
    protected static $lastParams;

    /**
     * Database constructor.
     * @param array $configs database Configuration
     * @throws DBALException
     */
    public function __construct(array $configs)
    {
        /**
         * Merge User Param
         */
        $this->currentUserParams = $this->normalizeDatabaseParams($configs);

        /**
         * Re-Sanitize Selected Driver
         */
        $this->currentSelectedDriver = is_string($this->currentUserParams[self::DB_DRIVER])
            ? $this->normalizeDatabaseDriver($this->currentUserParams[self::DB_DRIVER])
            : null;
        if (!$this->currentSelectedDriver) {
            throw new DBALException('Selected driver unavailable.', E_USER_ERROR);
        }
        if (empty($this->currentUserParams[self::DB_NAME])) {
            throw new DBALException('Database Name could not be empty.', E_USER_ERROR);
        }

        /**
         * create new parameters
         */
        $this->currentUserParams[self::DB_DRIVER] = $this->currentSelectedDriver;

        /**
         * Create New Connection
         */
        $this->currentConnection = DriverManager::getConnection($this->currentUserParams);

        // set last params
        static::$lastParams = $this->currentUserParams;

        /**
         * Set Quote Identifier
         */
        $this->currentQuoteIdentifier = $this
            ->currentConnection
            ->getDatabasePlatform()
            ->getIdentifierQuoteCharacter();
    }

    /**
     * Instance Database
     *
     * @param array $config
     *
     * @return Database
     */
    public static function create(array $config = null) : Database
    {
        return !is_array($config)
            // fallback to last param
            ? static::createLastParams()
            : new static($config);
    }

    /**
     * Create From last Parameter
     *
     * @return Database
     */
    public static function createLastParams() : Database
    {
        if (empty(static::$lastParams)) {
            throw new \RuntimeException(
                'Database not being init before',
                E_WARNING
            );
        }

        return new static(static::$lastParams);
    }

    /**
     * Normalize Configurations Param
     *
     * @param array $configs
     *
     * @return array
     */
    final public function normalizeDatabaseParams(array $configs) : array
    {
        if (empty($configs)) {
            return $configs;
        }

        /**
         * Merge User Param
         */
        $currentUserParams = array_merge($this->defaultParams, $configs);

        $toSanity = [
            self::DB_HOST => 'dbhost',
            self::DB_USER => 'dbuser',
            self::DB_NAME => 'name',
            self::DB_PASSWORD => 'dbpass',
            self::DB_DRIVER   => 'dbdriver',
            self::DB_PATH     => 'dbpath',
            self::DB_PORT     => 'dbport',
            self::DB_PREFIX   => 'dbprefix',
            self::DB_PROTOCOL => 'dbprotocol',
            self::DB_CHARSET  => 'dbcharset',
            self::DB_COLLATE  => 'dbcollate'
        ];
        foreach ($toSanity as $key => $name) {
            if ($key === $name) {
                continue;
            }
            if (!isset($currentUserParams[$key]) && isset($currentUserParams[$name])) {
                $currentUserParams[$key] = $currentUserParams[$name];
                unset($currentUserParams[$name]);
            }
        }

        // re-sanitize
        if (!isset($currentUserParams[self::DB_PASSWORD]) && isset($currentUserParams['dbpassword'])) {
            $currentUserParams[self::DB_PASSWORD] = $currentUserParams['dbpassword'];
            unset($currentUserParams['dbpassword']);
        }

        /**
         * check if port in 3306 & empty driver
         */
        if (empty($currentUserParams[self::DB_DRIVER])
            && isset($currentUserParams[self::DB_PORT])
            && abs($currentUserParams[self::DB_PORT]) === 3306
        ) {
            $currentUserParams[self::DB_DRIVER] = 'mysql';
        }
        if (empty($currentUserParams[self::DB_DRIVER])) {
            $currentUserParams[self::DB_DRIVER] = $this->defaultDriver;
        }

        if (!empty($currentUserParams[self::DB_DRIVER])) {
            $currentUserParams[self::DB_DRIVER] = $this
                ->normalizeDatabaseDriver($this->currentUserParams[self::DB_DRIVER]);
        }

        if (!empty($currentUserParams[self::DB_DRIVER])
            && $currentUserParams[self::DB_DRIVER] == 'pdo_sqlite'
        ) {
            if (empty($currentUserParams[self::DB_PATH])) {
                if (is_string($currentUserParams[self::DB_NAME])
                    && isset($currentUserParams[self::DB_NAME])
                ) {
                    $currentUserParams[self::DB_PATH] = $currentUserParams[self::DB_NAME];
                }
            } elseif (empty($currentUserParams[self::DB_NAME])) {
                if (is_string($currentUserParams[self::DB_PATH])
                    && isset($currentUserParams[self::DB_PATH])
                ) {
                    $currentUserParams[self::DB_NAME] = $currentUserParams[self::DB_PATH];
                }
            }
        }

        $charset = self::DEFAULT_CHARSET;
        if (is_string($currentUserParams[self::DB_CHARSET])
            && strpos($currentUserParams[self::DB_CHARSET], '-') !== false
        ) {
            $currentUserParams[self::DB_CHARSET] = str_replace(
                '-',
                '',
                trim(strtoupper($currentUserParams[self::DB_CHARSET]))
            );
        }

        if (isset($currentUserParams[self::DB_COLLATE])) {
            $collate = isset($currentUserParams[self::DB_COLLATE])
                ? $currentUserParams[self::DB_COLLATE]
                : null;
            if (!is_string($collate) && $currentUserParams[self::DB_DRIVER] === 'mysql') {
                $collate = 'utf8_unicode_ci';
            }
            $collate = preg_replace('`(\-|\_)+`', '_', $collate);
            $collate = trim(strtolower($collate));
            $collateArray = explode('_', $collate);
            $charset = reset($collateArray)?: $charset;
        }

        if (!is_string($currentUserParams[self::DB_CHARSET])
            || trim($currentUserParams[self::DB_CHARSET]) === ''
        ) {
            $currentUserParams[self::DB_CHARSET] = $charset;
        }

        return $currentUserParams;
    }

    /**
     * Aliases
     *
     * please @uses normalizeDatabaseDriver()
     *
     * @param string $driver
     *
     * @return bool|string
     */
    final public function sanitizeSelectedAvailableDriver(string $driver)
    {
        return $this->normalizeDatabaseDriver($driver);
    }

    /**
     * Check Database driver available for Doctrine
     * and choose the best driver of sqlsrv an oci
     *
     * @param string $driverName
     * @final
     * @return bool|string return lowercase an fix database driver for Connection
     */
    final public function normalizeDatabaseDriver(string $driverName)
    {
        if (is_string($driverName) && trim($driverName)) {
            $driverName = trim(strtolower($driverName));
            /**
             * switch to Doctrine fixed db
             * Aliases
             */
            $driverSchemeAliases = [
                'db2'        => 'ibm_db2',
                'drizzle'    => 'drizzle_pdo_mysql',
                'mssql'      => 'pdo_sqlsrv',
                'mysql'      => 'pdo_mysql',
                'mysql2'     => 'pdo_mysql', // Amazon RDS, for some weird reason
                'postgre'    => 'pdo_pgsql',
                'postgre_sql'=> 'pdo_pgsql',
                'postgres'   => 'pdo_pgsql',
                'postgresql' => 'pdo_pgsql',
                'pgsql'      => 'pdo_pgsql',
                'sqlite'     => 'pdo_sqlite',
                'sqlite3'    => 'pdo_sqlite',
                'oci'        => 'oci8',
                'pdo_oci'    => 'oci8',   # recommendation pdo_oci uses oci8
                'pdo_sqlsrv' => 'sqlsrv', #recommendation pdo_sqlsrv uses sqlsrv
            ];
            if (isset($driverSchemeAliases[$driverName])) {
                $driverName = $driverSchemeAliases[$driverName];
            }

            if (in_array($driverName, DriverManager::getAvailableDrivers())) {
                return $driverName;
            }
        }

        return false;
    }

    /**
     * Getting Doctrine Connection
     *
     * @return Connection
     */
    public function getConnection() : Connection
    {
        return $this->currentConnection;
    }

    /**
     * Get Table Prefix
     *
     * @return string
     */
    public function getTablePrefix() : string
    {
        return $this->currentTablePrefix;
    }

    /**
     * Get identifier
     *
     * @return string
     */
    public function getQuoteIdentifier() : string
    {
        return $this->currentQuoteIdentifier;
    }

    /**
     * Get user params
     *
     * @return array
     */
    public function getUserParams() : array
    {
        return $this->currentUserParams;
    }

    /**
     * Get Connection params
     *
     * @return array
     */
    public function getConnectionParams() : array
    {
        return $this->getParams();
    }

    /**
     * Trimming table for safe usage
     *
     * @param mixed $table
     * @return mixed
     */
    public function trimTableSelector($table)
    {
        if (is_array($table)) {
            foreach ($table as $key => $value) {
                $table[$key] = $this->trimTableSelector($value);
            }
            return $table;
        } elseif (is_object($table)) {
            foreach (get_object_vars($table) as $key => $value) {
                $table->{$key} = $this->trimTableSelector($value);
            }
            return $table;
        }
        if (is_string($table)) {
            $tableArray = explode('.', $table);
            foreach ($tableArray as $key => $value) {
                $tableArray[$key] = trim(
                    trim(
                        trim($value),
                        $this->currentQuoteIdentifier
                    )
                );
            }
            $table = implode('.', $tableArray);
        }

        return $table;
    }

    /**
     * Alternative multi variable type quoted identifier
     *
     * @param mixed $quoteStr
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public function quoteIdentifiers($quoteStr)
    {
        if ($quoteStr instanceof \Closure || is_resource($quoteStr)) {
            throw new \InvalidArgumentException(
                "Invalid value to be quote, quote value could not be instance of `Closure` or as a `Resource`",
                E_USER_ERROR
            );
        }

        $quoteStr = $this->trimTableSelector($quoteStr);
        if (is_array($quoteStr)) {
            foreach ($quoteStr as $key => $value) {
                $quoteStr[$key] = $this->quoteIdentifiers($value);
            }
            return $quoteStr;
        } elseif (is_object($quoteStr)) {
            foreach (get_object_vars($quoteStr) as $key => $value) {
                $quoteStr->{$key} = $this->quoteIdentifiers($value);
            }
            return $quoteStr;
        }

        $return = $this->quoteIdentifier($quoteStr);

        return $return;
    }

    /**
     * Alternative multi variable type quote string
     *      Nested quotable
     *
     * @param mixed $quoteStr
     * @param int   $type
     * @return array|mixed|string
     */
    public function quotes($quoteStr, $type = \PDO::PARAM_STR)
    {
        if ($quoteStr instanceof \Closure || is_resource($quoteStr)) {
            throw new \InvalidArgumentException(
                "Invalid value to be quote, quote value could not be instance of `Closure` or as a `Resource`",
                E_USER_ERROR
            );
        }

        $quoteStr = $this->trimTableSelector($quoteStr);
        if (is_array($quoteStr)) {
            foreach ($quoteStr as $key => $value) {
                $quoteStr[$key] = $this->quotes($value, $type);
            }
            return $quoteStr;
        } elseif (is_object($quoteStr)) {
            foreach (get_object_vars($quoteStr) as $key => $value) {
                $quoteStr->{$key} = $this->quotes($value, $type);
            }
            return $quoteStr;
        }

        return $this->quote($quoteStr);
    }

    /**
     * Prefix CallBack
     *
     * @access private
     * @param  string $table the table
     * @return string
     */
    private function prefixTableCallback(string $table) : string
    {
        $prefix = $this->getTablePrefix();
        if (!empty($prefix) && is_string($prefix) && trim($prefix)) {
            $table = (strpos($table, $prefix) === 0)
                ? $table
                : $prefix.$table;
        }

        return $table;
    }

    /**
     * Prefixing table with predefined table prefix on configuration
     *
     * @param mixed $table
     * @param bool  $use_identifier
     * @return array|null|string
     */
    public function prefixTables($table, bool $use_identifier = false)
    {
        if ($table instanceof \Closure || is_resource($table)) {
            throw new \InvalidArgumentException(
                "Invalid value to be quote, table value could not be instance of `Closure` or as a `Resource`",
                E_USER_ERROR
            );
        }

        $prefix = $this->getTablePrefix();
        if (is_array($table)) {
            foreach ($table as $key => $value) {
                $table[$key] = $this->prefixTables($value, $use_identifier);
            }
            return $table;
        }
        if (is_object($table)) {
            foreach (get_object_vars($table) as $key => $value) {
                $table->{$key} = $this->prefixTables($value, $use_identifier);
            }
            return $table;
        }
        if (!is_string($table)) {
            return null;
        }
        if (strpos($table, $this->currentQuoteIdentifier) !== false) {
            $use_identifier = true;
        }
        if (!empty($prefix) && is_string($prefix) && trim($prefix)) {
            $tableArray = explode('.', $table);
            $tableArray    = $this->trimTableSelector($tableArray);
            if (count($tableArray) > 1) {
                $connectionParams = $this->getConnectionParams();
                if (isset($connectionParams['dbname']) && $tableArray[0] == $connectionParams['dbname']) {
                    $tableArray[1] = $this->prefixTableCallback($tableArray);
                }
                if ($use_identifier) {
                    return $this->currentQuoteIdentifier
                           . implode("{$this->currentQuoteIdentifier}.{$this->currentQuoteIdentifier}", $tableArray)
                           . $this->currentQuoteIdentifier;
                } else {
                    return implode(".", $tableArray);
                }
            } else {
                $table = $this->prefixTableCallback($tableArray[0]);
            }
        }

        return $use_identifier
            ? $this->currentQuoteIdentifier.$table.$this->currentQuoteIdentifier
            : $table;
    }

    /**
     * @uses Database::prefixTables()
     *
     * @param mixed $tables
     * @param bool  $use_identifier
     * @return mixed
     */
    public function prefix($tables, bool $use_identifier = false)
    {
        return $this->prefixTables($tables, $use_identifier);
    }

    /**
     * Compile Bindings
     *     Take From CI 3 Database Query Builder, default string Binding use Question mark ( ? )
     *
     * @param   string $sql   sql statement
     * @param   array  $binds array of bind data
     * @return  mixed
     */
    public function compileBindsQuestionMark(string $sql, $binds = null)
    {
        if (empty($binds) || strpos($sql, '?') === false) {
            return $sql;
        } elseif (! is_array($binds)) {
            $binds = [$binds];
            $bind_count = 1;
        } else {
            // Make sure we're using numeric keys
            $binds = array_values($binds);
            $bind_count = count($binds);
        }
        // Make sure not to replace a chunk inside a string that happens to match the bind marker
        if ($countMatches = preg_match_all("/'[^']*'/i", $sql, $matches)) {
            $countMatches = preg_match_all(
                '/\?/i', # regex
                str_replace(
                    $matches[0],
                    str_replace('?', str_repeat(' ', 1), $matches[0]),
                    $sql,
                    $countMatches
                ),
                $matches, # matches
                PREG_OFFSET_CAPTURE
            );
            // Bind values' count must match the count of markers in the query
            if ($bind_count !== $countMatches) {
                return false;
            }
        } elseif (($countMatches = preg_match_all('/\?/i', $sql, $matches, PREG_OFFSET_CAPTURE)) !== $bind_count) {
            return $sql;
        }

        do {
            $countMatches--;
            $escapedValue = is_int($binds[$countMatches])
                ? $binds[$countMatches]
                : $this->quote($binds[$countMatches]);
            if (is_array($escapedValue)) {
                $escapedValue = '('.implode(',', $escapedValue).')';
            }
            $sql = substr_replace($sql, $escapedValue, $matches[0][$countMatches][1], 1);
        } while ($countMatches !== 0);

        return $sql;
    }

    /**
     * Query using binding optionals statements
     *
     * @uses   compileBindsQuestionMark
     * @param  string $sql
     * @param  mixed  $statement array|string|null
     * @return Statement
     * @throws DBALException
     */
    public function queryBind(string $sql, $statement = null)
    {
        $sql = $this->compileBindsQuestionMark($sql, $statement);
        if ($sql === false) {
            throw new DBALException(
                sprintf(
                    'Invalid statement binding count with sql query : %s',
                    $sql
                ),
                E_USER_WARNING
            );
        }

        return $this->query($sql);
    }

    /**
     * --------------------------------------------------------------
     * SCHEMA
     *
     * Lists common additional Methods just for check & lists only
     * to use more - please @uses Database::getSchemaManager()
     *
     * @see AbstractSchemaManager
     *
     * ------------------------------------------------------------ */

    /**
     * Check Table Maybe Invalid
     *
     * @param string $tableName
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function tableMaybeInvalid($tableName) : string
    {
        if (!is_string($tableName)) {
            throw new \InvalidArgumentException(
                'Invalid table name type. Table name must be as string',
                E_USER_ERROR
            );
        }

        $tableName = trim($tableName);
        if ($tableName == '') {
            throw new \InvalidArgumentException(
                'Invalid parameter table name. Table name could not be empty.',
                E_USER_ERROR
            );
        }

        return $tableName;
    }

    /**
     * Get List Available Databases
     *
     * @return array
     */
    public function listDatabases() : array
    {
        return $this->getSchemaManager()->listDatabases();
    }

    /**
     * Returns a list of all namespaces in the current database.
     *
     * @return array
     */
    public function listNamespaceNames() : array
    {
        return $this->getSchemaManager()->listDatabases();
    }

    /**
     * Lists the available sequences for this connection.
     *
     * @return Sequence[]
     */
    public function listSequences() : array
    {
        return $this->getSchemaManager()->listSequences();
    }

    /**
     * Get Doctrine Column of table
     *
     * @param string $tableName
     * @return Column[]
     */
    public function listTableColumns(string $tableName) : array
    {
        $tableName = $this->tableMaybeInvalid($tableName);
        return $this
            ->getSchemaManager()
            ->listTableColumns($tableName);
    }

    /**
     * Lists the indexes for a given table returning an array of Index instances.
     *
     * Keys of the portable indexes list are all lower-cased.
     *
     * @param string $tableName The name of the table.
     *
     * @return Index[]
     */
    public function listTableIndexes(string $tableName) : array
    {
        $tableName = $this->tableMaybeInvalid($tableName);
        return $this
            ->getSchemaManager()
            ->listTableIndexes($tableName);
    }

    /**
     * Check if table is Exists
     *
     * @param string $tables
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function tablesExist($tables)
    {
        if (! is_string($tables) && !is_array($tables)) {
            throw new \InvalidArgumentException(
                'Invalid table name type. Table name must be as string or array',
                E_USER_ERROR
            );
        }

        $tables = $this->prefixTables($tables);
        ! is_array($tables) && $tables = [$tables];
        return $this
            ->getSchemaManager()
            ->tablesExist($tables);
    }

    /**
     * Returns a list of all tables in the current Database Connection.
     *
     * @return array
     */
    public function listTableNames() : array
    {
        return $this
            ->getSchemaManager()
            ->listTableNames();
    }

    /**
     * Get List Table
     *
     * @return Table[]
     */
    public function listTables() : array
    {
        return $this
            ->getSchemaManager()
            ->listTables();
    }

    /**
     * Get Object Doctrine Table from Table Name
     *
     * @param string $tableName
     *
     * @return Table
     */
    public function listTableDetails(string $tableName) : Table
    {
        $tableName = $this->tableMaybeInvalid($tableName);
        return $this->getSchemaManager()->listTableDetails($tableName);
    }

    /**
     * Lists the views this connection has.
     *
     * @return View[]
     */
    public function listViews() : array
    {
        return $this->getSchemaManager()->listViews();
    }

    /**
     * Lists the foreign keys for the given table.
     *
     * @param string      $tableName    The name of the table.
     *
     * @return ForeignKeyConstraint[]
     */
    public function listTableForeignKeys(string $tableName) : array
    {
        $tableName = $this->tableMaybeInvalid($tableName);
        return $this->getSchemaManager()->listTableForeignKeys($tableName);
    }

    /**
     * Magic Method __call - calling arguments for backward compatibility
     *
     * @uses Connection
     *
     * @param string $method method object :
     *                       @see Connection
     * @param array  $arguments the arguments list
     * @return mixed
     * @throws DBALException
     */
    public function __call(string $method, array $arguments)
    {
        /**
         * check if method exists on connection @see Connection !
         */
        if (method_exists($this->getConnection(), $method)) {
            return call_user_func_array([$this->getConnection(), $method], $arguments);
        }

        throw new \BadMethodCallException(
            sprintf(
                "Call to undefined Method %s",
                $method
            ),
            E_USER_ERROR
        );
    }
}
