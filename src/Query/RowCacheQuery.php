<?php
namespace Marmot\Framework\Query;

use Marmot\Framework\Classes;
use Marmot\Framework\Interfaces\DbLayer;
use Marmot\Interfaces\CacheLayer;

/**
 * RowCacheQuery文件,abstract抽象类.所有针对数据库行处理且需要缓存的类需要继承该类.
 *
 * Query层的行缓存处理,一张需要行缓存的数据表对应一个RowCacheQuer层,主要实现2个方法:
 * 1. getOne($id) 获取一条记录,需要先判断缓存中是否存在,不存在则从数据获取,存入缓存
 * 2. getList(array $ids)获取多条记录,需要先判断缓存中是否存在.
 *    如果有不存在的,则把不存在id放入数据中查询
 *
 * @author chloroplast
 * @version 1.0.0: 20160224
 */

abstract class RowCacheQuery
{
    use RowQueryFindable;

    protected $primaryKey;//查询键值在数据库中的命名,行缓存和数据库的交互使用键值

    protected $cacheLayer;//缓存层

    protected $dbLayer;//数据层

    public function __construct(string $primaryKey, CacheLayer $cacheLayer, DbLayer $dbLayer)
    {
        $this->primaryKey = $primaryKey;
        $this->cacheLayer = $cacheLayer;
        $this->dbLayer = $dbLayer;
    }

    public function __destruct()
    {
        unset($this->primaryKey);
        unset($this->cacheLayer);
        unset($this->dbLayer);
    }

    public function getPrimaryKey() : string
    {
        return $this->primaryKey;
    }

    protected function getDbLayer() : DbLayer
    {
        return $this->dbLayer;
    }

    protected function getCacheLayer() : CacheLayer
    {
        return $this->cacheLayer;
    }
    
    /**
     * @param array $data 添加数据
     */
    public function add(array $data, $lasetInsertId = true)
    {
        $result = $this->getDbLayer()->insert($data, $lasetInsertId);
        if (!$result) {
            return false;
        }
        
        return $result;
    }

    /**
     * 兼容 add/edit
     */
    public function edit(array $data, array $condition)
    {
        return $this->update($data, $condition);
    }

    public function insert(array $data, $lasetInsertId = true)
    {
        return $this->add($data, $lasetInsertId);
    }

    /**
     * @param array $data 更新数据
     * @param array $condition 更新条件 | 默认为主键
     */
    public function update(array $data, array $condition)
    {
        $cacheKey = $condition[$this->getPrimaryKey()];
        
        $row = $this->getDbLayer()->update($data, $condition);
        if (!$row) {
            return false;
        }
        //更新缓存
        $this->getCacheLayer()->del($cacheKey);
        return true;
    }
    
    /**
     * @param array $condition 删除条件 | 默认为主键
     */
    public function delete(array $condition)
    {
        $row = $this->getDbLayer()->delete($condition);
        if (!$row) {
            return false;
        }

        //更新缓存
        $cacheKey = $condition[$this->getPrimaryKey()];
        $this->getCacheLayer()->del($cacheKey);
        return true;
    }

    /**
     * @param int $id,主键id
     */
    public function getOne($id)
    {
        $cacheLayer = $this->getCacheLayer();
        //查询缓存中是否有数据,根据id
        $cacheData = $cacheLayer->get($id);
        //如果有数据,返回
        if ($cacheData) {
            return $cacheData;
        }

        //如果没有数据,去数据库查询根据primaryKey 和 id
        $mysqlData = $this->getDbLayer()->select($this->getPrimaryKey().'='.$id, '*');
        //如果数据为空,返回false
        if (empty($mysqlData) || !isset($mysqlData[0])) {
            return false;
        }

        $mysqlData = $mysqlData[0];
        //数据存入缓存
        $cacheLayer->save($id, $mysqlData);
        //返回数据
        return $mysqlData;
    }

    public function fetchOne($id)
    {
        return $this->getOne($id);
    }

    /**
     * 批量获取缓存
     */
    public function getList($ids)
    {
        if (empty($ids) || !is_array($ids)) {
            return false;
        }

        list($hits, $miss) = $this->getCacheLayer()->getList($ids);

        if ($miss) {
                //未缓存数据从数据库读取
            $missRows = $this->getDbLayer()->select($this->getPrimaryKey().' in (' . implode(',', $miss) . ')', '*');
            if ($missRows) {
                foreach ($missRows as $val) {
                    //添加memcache缓存数据
                    $this->getCacheLayer()->save($val[$this->getPrimaryKey()], $val);
                }
                $hits = array_merge($hits, $missRows);
            }
        }

        $resArray = array();
        if ($hits) {
            //按该页要显示的id排序
            $result = array();
            foreach ($hits as $val) {
                $result[$val[$this->getPrimaryKey()]] = $val;
            }
            //按照传入id列表初始顺序排序
            foreach ($ids as $val) {
                if (isset($result[$val])) {
                    $resArray[] = $result[$val];
                }
            }
            unset($result);
        }
        return $resArray;
    }

    public function fetchList($ids)
    {
        return $this->getList($ids);
    }
}
