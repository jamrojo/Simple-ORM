<?php

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
