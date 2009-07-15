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
    private static $_dbh    = false;
    private static $_db     = '';
    private static $_host   ='localhost';
    private static $_user   ='';
    private static $_pass   ='';
    private static $_driver ='mysql';
    private $__updatable    = true;
    private $__i;
    private $__upadate = false;
    private $__vars;
    private $__resultset = array();

    // __construct() {{{
    /**
     *
     *
     */
    final public function __construct()
    {
        $this->__vars = getProperties($this);
    }
    // }}}

    // setters {{{
    final public static function setUser($user)
    {
        self::$_user = $user;
    }


    final public static function setPassword($pass)
    {
        self::$_pass = $pass;
    }

    final public static function setDb($db)
    {
        self::$_db = $db;
    }
    // }}}

    // _connect() {{{
    /**
     *  Performs the connection to the DB, if it fails it throws an exception.
     *
     *  @return void
     */
    private function _connect()
    {
        $connstr    = self::$_driver.':host='.self::$_host.';dbname='.self::$_db;
        self::$_dbh = new PDO($connstr,
                self::$_user,
                self::$_pass, 
                array(
                    PDO::ATTR_PERSISTENT => false,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                    PDO::ATTR_EMULATE_PREPARES => true
                    )
                );
        self::$_dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    // }}}

    // relations() {{{
    /**
     *  Return an Array of that has the DB to object mapping, 
     *  where the key is the DB rows and the values the object's
     *  properties. This function could be overrided by a sub-class
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
        $values = array();
        $this->_loadVars($values);
        if (isset($this->ID)) {
            if (!$this->__updatable) {
                throw new Exception("Modifications not allowed");
            }
            $changes = $this->getChanges();
            if (is_array($changes)) {
                foreach (array_keys($values) as $val) {
                    if (!isset($changes[$val])) {
                        unset($values[$val]);
                    }
                }
                if (count($values) == 0) {
                    return;
                }
            }
            $this->Update($this->getTableName(), $values, array("id"=>$this->ID));
        } else {
            $this->Insert($this->getTableName(), $values);
        }
    }
    // }}}

    // _loadVars() {{{
    /**
     *  Load all variables from the Objects to the DB
     *
     *  @param array &$values Target variable
     *
     *  @return void
     */
    final private function _loadVars(&$values)
    {
        foreach ($this->relations() as $key => $value) {
            $value = $this->$value;
            if (substr($key, 0, 2) == "__" || $key == "ID" || is_null($value)) { 
                continue;
            }
            if (is_callable(array(&$this, "${key}_filter"))) {
                call_user_func_array(array(&$this, "${key}_filter"), array(&$value));
            }
            $values[ $key ] = $value;
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
     *  @param array    $values    Variables referenced on the SQL
     *  @param bool     $updatable True if this SQL could be saved on the DB
     *  @param callback $ufnc      Replace the default save() function 
     *
     *  @return void
     */
    final protected function setDataSource($sql, $values=array(), $updatable=false, $ufnc=false)
    {
        if (self::$_dbh === false) {
            self::_connect();
        }
        if ($updatable) {
            $this->__update = false;
            if ($ufnc !== false && is_callable($ufnc)) {
                $this->__update = $ufnc;
            }
        }
        $this->__updatable = $updatable;
        $stmt              = self::$_dbh->prepare($sql);
        $stmt->execute($values);
        $this->__resultset = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
     *  @param array  $values Variables that might be refered on the SQL
     *  
     *  @return array
     */
    final protected function simpleQuery($sql, $values=array()) 
    {
        if (self::$_dbh === false) {
            self::_connect();
        }
        $stmt = self::$_dbh->prepare($sql);
        $stmt->execute($values);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // }}}

    // load() {{{
    /**
     *  Simple function to filter and load data from the DB.  It takes
     *  the variables from the object's properties. 
     *
     *  @return object A reference to the class it self.
     */
    final public function & load()
    {
        $values = array();
        $table  = $this->getTableName();
        $filter = "";
        $this->_loadVars($values);
        if (isset($this->ID)) {
            $values['id'] = $this->ID;
        }

        foreach (array_keys($values) as $col) {
            $filter .= " $col = :$col AND";
        }
        $sql = "SELECT * FROM `{$table}`";
        if (!empty($filter)) {
            $sql .= " WHERE $filter";
            $sql  = substr($sql, 0, strlen($sql) - 3);
        }
        $this->setDataSource($sql, $values, true);
        return $this;
    }
    // }}}

    // Insert() {{{
    /**
     *  Insert a new row in the table
     *
     *  @param string $table Table name
     *  @param array  $rows  Columns names and values
     *
     *  @return void
     */
    final protected function insert($table, $rows) 
    {
        $cols = array_keys($rows);
        $sql  = "INSERT INTO `{$table}`(".implode(",", $cols).") ";
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
     *  @param array  $rows    Columns names and values
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

        $sql  = "UPDATE `{$table}` SET $changes WHERE $filter";
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

    /* Iterator {{{ */
    final function rewind() 
    {
        $this->__i = 0;
        $this->current();
    }

    final function next()
    {
        $this->__i++;
    }

    final function & current()
    {
        $pzRecord = & $this->__resultset[ $this->__i ];
        foreach ((array)$pzRecord as $key => $value) {
            $this->$key = $value;
        }
        $this->ID = $pzRecord['id'];
        return $this;
    }

    final protected function getChanges()
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

    final function key()
    {
        return $this->__i;
    }

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
    final function offsetExists($key)
    {
        return isset($this->$key);
    }

    final function offsetGet($key)
    {
        return $this->$key;
    }

    final function offsetSet($key, $value)
    {
        $this->$key = $value;
    }

    final function offsetUnset($key)
    {
        $this->$key = null;
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

