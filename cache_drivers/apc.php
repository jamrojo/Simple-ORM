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

final class ApcDBCache extends BaseCache 
{
    public function __construct()
    {
        if (!is_callable("apc_add")) {
            throw new Exception("There is not APC module");
        }
    }
    
    public function add($key, &$records, $ttl = 3600)
    {
        return apc_add($key, $records, $ttl);
    }

    public function update($key, &$records, $ttl = 3600)
    {
        return apc_store($key, $records, $ttl);
    }

    public function get($key, &$records)
    {
        $records = apc_fetch($key, $success);
        return $success;
    }

    public function delete($key) 
    {
        return apc_delete($key);
    }
}

DB::setCacheHandler(new ApcDBCache);

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */
