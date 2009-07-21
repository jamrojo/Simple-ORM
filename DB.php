<?php
/*
   +----------------------------------------------------------------------+
   | PHP Version 5                                                        |
   +----------------------------------------------------------------------+
   | Copyright (c) 1997-2008 The PHP Group                                |
   +----------------------------------------------------------------------+
   | This source file is subject to version 3.01 of the PHP license,      |
   | that is bundled with this package in the file LICENSE, and is        |
   | available through the world-wide-web at the following url:           |
   | http://www.php.net/license/3_01.txt                                  |
   | If you did not receive a copy of the PHP license and are unable to   |
   | obtain it through the world-wide-web, please send a note to          |
   | license@php.net so we can mail you a copy immediately.               |
   +----------------------------------------------------------------------+
   | Author:  Cesar Rodas <saddor@gmail.com>                              |
   +----------------------------------------------------------------------+
*/

require dirname(__FILE__)."/base_cache.php";

// getProperties() {{{
/**
 *  Return public 
 *
 *  @param DB &$obj DB sub-class
 *
 *  @return array
 */
function getProperties(DB &$obj)
{
    return array_keys((array)get_object_vars($obj));
}
// }}}

/**  
 *  DB -- SimpleORM
 *
 *  This base class provides a simple, extensible and efficient
 *  that maps a DB table into an object.
 *
 *  @category DB
 *  @package  SimpleORM
 *  @author   César Rodas <crodas@member.fsf.org>
 *  @license  PHP License <http://www.php.net/license/3_01.txt>
 *  @link     http://cesar.la/projects/SimpleORM
 */
abstract class DB implements Iterator, ArrayAccess
{
    private static $_dbh     = false;
    private static $_db      = '';
    private static $_host    = 'localhost';
    private static $_user    = '';
    private static $_pass    = '';
    private static $_driver  = 'mysql';
    private static $_cache   = null;
    private static $_tescape = array('`','`');
    private $__updatable    = true;
    private $__i;
    private $__upadate = false;
    private $__vars;
    private $__resultset  = array();

    // __construct() {{{
    /**
     *  Class constructor
     *
     *  @return void
     */
    final public function __construct()
    {
        $this->__vars = getProperties($this);
        if (isset($this->ID)) {
            unset($this->ID);
        }
    }
    // }}}

    // setters {{{
    /**
     *  Set the DB username
     *
     *  @param string $user Username
     *
     *  @return void
     */ 
    final public static function setUser($user)
    {
        self::$_user = $user;
    }


    /**
     *  Set the DB password, by default empty
     *
     *  @param string $pass Password
     *
     *  @return void
     */ 
    final public static function setPassword($pass)
    {
        self::$_pass = $pass;
    }

    /**
     *  Set the DB name
     *
     *  @param string $db Database name
     *
     *  @return void
     */ 
    final public static function setDb($db)
    {
        self::$_db = $db;
    }

    /**
     *  Set the DB host, by default localhost
     *
     *  @param string $host Hostname
     *
     *  @return void
     */ 
    final public static function setHost($host)
    {
        self::$_host = $host;
    }

    /**
     *  Set the DB PDO driver, by default mysq1
     *
     *  @param string $driver Drivername
     *
     *  @return void
     */ 
    final public static function setDriver($driver)
    {
        switch (strtolower($driver)) {
        case 'mysql':
            self::$_tescape = array('`','`');
            break;
        case 'mssql':
            self::$_tescape = array('[',']');
            break;
        default:
            self::$_tescape = array('"','"');
            break;

        }
        self::$_driver = $driver;
    }
    // }}}

    // _connect() {{{
    /**
     *  performs the connection to the db, if it fails it throws an exception.
     *
     *  @return void
     */
    private function _connect()
    {
        $connstr    = self::$_driver.':host='.self::$_host.';dbname='.self::$_db;
        self::$_dbh = new pdo($connstr,
                self::$_user,
                self::$_pass, 
                array(
                    pdo::ATTR_PERSISTENT => false,
                    pdo::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                    pdo::ATTR_EMULATE_PREPARES => true
                    )
                );
        self::$_dbh->setattribute(pdo::ATTR_ERRMODE, pdo::ERRMODE_EXCEPTION);
    }
    // }}}

    // relations() {{{
    /**
     *  Return an Array of that has the DB to object mapping, 
     *  where the key is the DB rows and the value is the object's
     *  properties name. This function could be overrided by a sub-class
     *
     *  @return array
     */
    protected function relations()
    {
        $keys = $this->__vars;
        foreach ($keys as $id => $key) {
            if (substr($key, 0, 2) == "__" || $key == "ID") { 
                unset($keys[$id]);
            }
        }
        return array_combine($keys, $keys);
    }
    // }}}

    // save() {{{
    /**
     *  Save
     *
     *  This function saves information into the DB, performing
     *  an INSERT or UPDATE.
     *
     *  @return void
     */
    final function save()
    {
        if (self::$_dbh === false) {
            self::_connect();
        }
        $params = array();
        $this->_loadVars($params);
        if ($this->valid()) {
            if (!$this->__updatable) {
                throw new Exception("Modifications not allowed");
            }
            $changes = $this->_getChanges();
            if (is_array($changes)) {
                foreach (array_keys($params) as $val) {
                    if (!isset($changes[$val])) {
                        unset($params[$val]);
                    }
                }
                if (count($params) == 0) {
                    return;
                }
            }
            $this->ID = $this->__resultset[ $this->__i ]['id'];
            $this->Update($this->getTableName(), $params, array("id"=>$this->ID));
        } else {
            $this->Insert($this->getTableName(), $params);
        }
    }
    // }}}

    // _loadVars() {{{
    /**
     *  Load all variables from the Objects to the DB
     *
     *  @param array &$params Target variable
     *
     *  @return void
     */
    final private function _loadVars(&$params)
    {
        foreach ($this->relations() as $key => $value) {
            $value = $this->$value;
            if (substr($key, 0, 2) == "__" || $key == "ID" || is_null($value)) { 
                continue;
            }
            if (is_callable(array(&$this, "${value}_filter"))) {
                call_user_func_array(array(&$this, "${value}_filter"), array(&$value));
            }
            $params[ $key ] = $value;
        }
    }
    // }}}

    // setDataSource() {{{
    /**
     *  set Data Source
     *
     *  Passed an SQL that will be executed and its result will used as the
     *  the data source.
     *
     *  @param string   $sql       SQL code to execute, it could have variables
     *  @param array    $params    Variables referenced on the SQL
     *  @param bool     $updatable True if this SQL could be saved on the DB
     *  @param callback $ufnc      Replace the default save() function 
     *
     *  @return void
     */
    final protected function setDataSource($sql, $params=array(), $updatable=false, $ufnc=false)
    {
        $cacheable = $this->isCacheable($sql, $params, $ttl);
        if ($updatable) {
            if ($ufnc !== false && is_callable($ufnc)) {
                $this->__update = $ufnc;
            }
        }
        $this->__updatable = $updatable;
        if (!$cacheable || !$this->getFromCache($sql, $params, $this->__resultset)) 
        {
            if (self::$_dbh === false) {
                self::_connect();
            }
            $stmt              = self::$_dbh->prepare($sql);
            $stmt->execute($params);
            $this->__resultset = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if ($cacheable) {
                $this->saveCache($sql, $params);
            }
        } 
        $this->rewind();
    }
    // }}}

    // simpleQuery() {{{
    /**
     *  simpleQuery
     *  
     *  Similar function to setDataSource but instead of buffer the DB
     *  result, it is returned.
     *
     *  @param string $sql    SQL to executed
     *  @param array  $params Variables that might be refered on the SQL
     *  
     *  @return array
     */
    final protected function simpleQuery($sql, $params=array()) 
    {
        if (self::$_dbh === false) {
            self::_connect();
        }
        $stmt = self::$_dbh->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // }}}

    // load() {{{
    /**
     *  Simple function to filter and load data from the DB.  It takes
     *  the variables from the object's properties. 
     *  
     *  @param int $cachettl Cache TTL (default -1, no cache)
     *
     *  @return object A reference to the class it self.
     */
    final public function & load()
    {
        $params = array();
        $table  = $this->getTableName();
        $filter = "";
        $this->_loadVars($params);
        if (isset($this->ID)) {
            $params['id'] = $this->ID;
        }

        foreach (array_keys($params) as $col) {
            if (is_array($params[$col])) {
                $filter .= " $col in (";
                foreach ($params[$col] as $key=>$value) {
                    $nkey          = "{$col}{$key}";
                    $filter       .= ":{$nkey},";
                    $params[$nkey] = $value;
                }
                unset($params[$col]);
                $filter  = substr($filter, 0, strlen($filter)-1);
                $filter .= ") AND"; 
            } else {
                $filter .= " $col = :$col AND";
            }
        }
        list($es, $ee) = self::$_tescape;
        $sql = "SELECT * FROM {$es}{$table}{$ee}";
        if (!empty($filter)) {
            $sql .= " WHERE $filter";
            $sql  = substr($sql, 0, strlen($sql) - 3);
        }
        $this->setDataSource($sql, $params, true);
        return $this;
    }
    // }}}

    // delete() {{{
    /**
     *  Delete the current record on a recordset
     *
     *  @return void
     */
    function delete()
    {
        if (!$this->valid()) {
            throw new Exception("No valid record");
        }
        $this->ID = $this->__recordset[$this->__i]['ID'];
        list($es, $ee) = self::$_tescape;
        $sql = "DELETE FROM {$es}{$table}{$ee} WHERE id = :id";
        $this->simpleQuery($sql, array("id" => $this->ID));
    }
    // }}}

    // Insert() {{{
    /**
     *  Insert a new row in the table
     *
     *  @param string $table Table name
     *  @param array  $rows  Columns names and params
     *
     *  @return void
     */
    final protected function insert($table, $rows) 
    {
        if (isset($this->ID)) {
            $rows['id'] = $this->ID;
        }
        list($es, $ee) = self::$_tescape;
        $cols = array_keys($rows);
        $sql  = "INSERT INTO {$es}{$table}{$ee}(".implode(",", $cols).") ";
        $sql .= "VALUES(:".implode(",:", $cols).")";
        $stmt = self::$_dbh->prepare($sql);
        $stmt->execute($rows);
        $this->ID = self::$_dbh->lastInsertId();
    }
    // }}}

    // Update() {{{
    /**
     *  Modified a row in the table.
     *
     *  @param string $table   Table name
     *  @param array  $rows    Columns names and params
     *  @param array  $filters Columns that are used as filter in the selection
     *
     *  @return void
     */
    final protected function update($table, $rows, $filters=array()) 
    {
        if (!is_array($filters) || count($filters) == 0) {
            return false;
        }
        $changes = "";
        $filter  = "";
        foreach (array_keys($rows) as $col) {
            $changes .= "$col = :$col,";
        }

        foreach ($filters as $key => $value) {
            $k        = 'where_'.$key;
            $rows[$k] = $value;
            $filter  .= "$key = :$k,";
        }

        $changes[strlen($changes)-1] = ' ';
        $filter [strlen($filter)-1]  = ' ';

        list($es, $ee) = self::$_tescape;
        $sql  = "UPDATE {$es}{$table}{$ee} SET $changes WHERE $filter";
        $stmt = self::$_dbh->prepare($sql);
        $stmt->execute($rows);
    }
    // }}}

    // getTableName() {{{
    /**
     *  Returns the Table name, by default it is the lowerdcase
     *  class name, but it could be overriden
     *
     *  @return string
     */
    protected function getTableName()
    {
        return strtolower(get_class($this));
    }
    // }}}

    // _getChanges() {{{
    /**
     *  Get Changes
     *
     *  Returns all the columns that has change in order to perform
     *  an update.
     *
     *  @return bool|array True if there is no result, or the array of columns
     */
    final private function _getChanges()
    {
        $pzRecord = & $this->__resultset[ $this->__i ];
        if (is_null($pzRecord)) {
            return true;
        }
        $changes = array();
        foreach ($this->relations() as $key => $value) {
            if ($this->$value != $pzRecord[$key]) {
                $changes[$key] = true;
            }
        }
        return $changes;
    }
    // }}}

    // getOriginalValue() {{{
    /**
     *  Get Original value of a given column
     *
     *  @param string $key Column name
     *
     *  @return string|bool Column value or false
     */
    final protected function getOriginalValue($key)
    {
        if (!$this->valid()) {
            return false;
        }
        $pzRecord = & $this->__resultset[ $this->__i ];
        return isset($pzRecord[$key]) ? $pzRecord[$key] : false;
    }
    // }}}

    /* Iterator {{{ */
    /**
     *  In a recordset iteration, it moves to the first result. This function
     *  is used by the PHP Iterator
     *
     *  @return void
     */
    final function rewind() 
    {
        $this->__i = 0;
        $this->current();
    }

    /**
     *  In a recordset iteration, it moves to the next result. This function
     *  is used by the PHP Iterator
     *
     *  @return void
     */
    final function next()
    {
        $this->__i++;
    }

    /**
     *  In a recordset iteration, it return the actual result. This function
     *  is used by the PHP Iterator
     *
     *  @return void
     */
    final function & current()
    {
        $pzRecord = & $this->__resultset[ $this->__i ];
        foreach ((array)$pzRecord as $key => $value) {
            $this->$key = $value;
        }
        $this->ID = $pzRecord['id'];
        return $this;
    }

    /**
     *  In a recordset iteration, it return the result position. This function
     *  is used by the PHP Iterator
     *
     *  @return void
     */
    final function key()
    {
        return $this->__i;
    }

    /**
     *  In a recordset iteration, it return the is the result is valid. This function
     *  is used by the PHP Iterator
     *
     *  @return void
     */
    final function valid()
    {
        $count = count($this->__resultset);
        if ($count==0 || $this->__resultset[0] == null) {
            return false;
        }
        return $this->__i < $count;
    }

    /* }}} */

    // ArrayAccess {{{
    /**
     *  In a resultset it return true if the the key exists as column name
     *
     *  @param string $key Column name
     *
     *  @return bool
     */
    final function offsetExists($key)
    {
        return isset($this->$key);
    }

    /**
     *  In a resultset it return value
     *
     *  @param string $key Column name
     *
     *  @return string
     */
    final function offsetGet($key)
    {
        return $this->$key;
    }

    /**
     *  In a resultset, it set override the value of the key
     *
     *  @param string $key   Column name
     *  @param string $value Column value
     *
     *  @return string
     */
    final function offsetSet($key, $value)
    {
        $this->$key = $value;
    }

    /**
     *  In a resultset it reset the value
     *
     *  @param string $key Column name
     *
     *  @return string
     */
    final function offsetUnset($key)
    {
        $this->$key = null;
    }
    // }}}

    // setCacheHandler() {{{
    /**
     *  Set a Default DB Cache manager
     *
     *  @param BaseCache &$cache  DB Cache *object
     *  @param bool      $replace True if the a previous defined Cache manager could be changed
     *
     *  @return bool True if success
     */
    final public static function setCacheHandler(BaseCache &$cache, $replace = false)
    {
        $dcache = & self::$_cache;
        if (!$replace && $dcache InstanceOf BaseCache) {
            return false;
        }
        $dcache = $cache;
        return true;
    }
    // }}}

    // getFromCache() {{{
    /**
     *  get the SQL result from Cache
     *
     *  @param string $sql      SQL code to execute, it could have variables
     *  @param array  $params   Variables referenced on the SQL
     *  @param array  &$results Resultset destination
     *  
     *  @return bool
     */
    final protected function getFromCache($sql, $params=array(), &$results)
    {
        $dcache = & self::$_cache;
        if (!$dcache InstanceOf BaseCache) {
            return false;
        }
        $id = $this->getCacheID($sql, $params);
        return $dcache->get($id, $results);
    }
    // }}}

    // saveCache() {{{
    /** 
     *  Save the actual resultset in the cache.
     *
     *  @param string $sql    SQL code to execute, it could have variables
     *  @param array  $params Variables referenced on the SQL
     *  @param int    $ttl    Cache Time to live
     *
     *  @return bool
     */
    final protected function saveCache($sql, $params=array(), $ttl=3600)
    {
        $dcache = & self::$_cache;
        if (!$dcache InstanceOf BaseCache) {
            return false;
        }
        $id = $this->getCacheID($sql, $params);
        return $dcache->add($id, $this->__resultset, $ttl);
    }
    // }}}

    // getCacheID() {{{
    /**
     *  Generate and return the Cache ID based on the
     *  SQL and all the params on the queries
     *
     *  @param string $sql    SQL code to execute, it could have variables
     *  @param array  $params Variables referenced on the SQL
     *
     *  @return string|bool Cache ID (string) or false
     */
    protected function getCacheID($sql, array $params=array())
    {
        $key = strtolower($sql);
        $key = str_replace(array(" ", "\t","\r", "\n"), array("", "", "", ""), $key);
        $key = md5($key);
        if (count($params) > 0) {
            $tparams  = md5(implode(" ", array_keys($params)));
            $tparams .= md5(implode(" ", $params));
            $key     .= "_".md5($tparams);
        }
        return $key;
    }
    // }}}

    // isCacheable() {{{
    /**
     *
     *
     */
    protected function isCacheable($sql, $params=array(), &$ttl)
    {
        return false;
    }
    // }}}

}



/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */
