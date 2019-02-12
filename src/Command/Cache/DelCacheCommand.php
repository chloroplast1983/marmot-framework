<?php
namespace Marmot\Framework\Command\Cache;

use Marmot\Framework\Interfaces;
use Marmot\Framework\Observer;
use Marmot\Framework\Classes;
use Marmot\Core;

/**
 * 删除cache缓存命令
 * @author chloroplast1983
 *
 */

class DelCacheCommand implements Interfaces\Command
{
    
    private $key;
    
    public function __construct($key)
    {
        $this->key = $key;
    }

    public function execute() : bool
    {
        return Core::$cacheDriver->delete($this->key);
    }

    /**
     * @codeCoverageIgnore
     */
    public function undo()
    {
        //
    }
}