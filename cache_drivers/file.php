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

final class FileDBCache extends BaseCache 
{
    static $_base = null;

    public static function setDir($dir)
    {
        if (is_dir($dir)) {
            self::$_base = $dir;
        }
    }
    
    public function add($key, &$records, $ttl = 3600)
    {
        $file       = $this->_getFilePath($key);
        $cache      = new FileCache($records);
        $cache->ttl = $ttl;
        file_put_contents($file, serialize($cache));
        return is_file($file);
    }

    public function update($key, &$records, $ttl = 3600)
    {
    }

    public function get($key, &$records)
    {
        $file = $this->_getFilePath($key);
        if (!is_file($file)) {
            return false;
        }
        $cache = unserialize(file_get_contents($file));
        if (!$cache->isValid()) {
            return false;
        }
        $records = $cache->data;
        return true;
    }

    public function delete($key) 
    {
    }

    private function _getFilePath($key)
    {
        if (self::$_base === null) {
            throw new Exception("There is no FileCache Dir, use it file FileDBCache::setDir");
        }
        $file = $key;
        if (strpos($file, "_") !== false) {
            list($file, $extra) = explode("_", $file, 2);
            $file = "{$file[0]}/{$file[1]}/".substr($file, 2)."/".$extra;
        } else {
            $file = "{$file[0]}/{$file[1]}/".substr($file, 2);
        }
        $file = self::$_base."/{$file}.cache";
        $dir  = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $file;
    }
}

class FileCache
{
    public $start_date;
    public $ttl  = 3600;
    public $data = array();

    function __construct($data=array()) 
    {
        $this->start_date = time();
        $this->setData($data);
    }

    function setData(array $data)
    {
        $this->data = $data;
    }

    function isValid()
    {
        return $this->start_date + $this->ttl  > time();
    }
}

DB::setCacheHandler(new FileDBCache);

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */
