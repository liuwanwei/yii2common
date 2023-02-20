<?php
namespace bytefly\yii2common\grandcache;

use Yii;

class CacheEntry{

    /** 缓存入口键值的区域码，每个项目必须指定不同的区域码，才能尽可能保证隔离 */
    public static $zone = 'mf';

    public $identifier;

    public function __construct($identifier)
    {
        $this->identifier = $identifier;
    }

    private function _getIdentifierEntry(){
        return "cache-entry-{$this->identifier}";
    }

    // 通过 [[_getIdentifierEntry()]] 来保证不同类型对象 $objectId 的唯一性
    private function _getObjectEntry($objectId){
        return  $this->_getIdentifierEntry() . "-{$objectId}";
    }

    /** 
     * 生成一个 memcache 入口键值，
     * 1. 必须具备排他性 
     * 2. 不能包含 : | 等字符，否则使用起来，用做 ./yii gc/vv 参数会被断开解析
     */
    private function _makeNewDependingKey($objectId, $category){
        return static::$zone . "-identifier-{$this->identifier}-object-{$objectId}-category-{$category}";
    }

    /** 获取对象结点的所有依赖项 */
    public function getDependingKeys($objectId){
        // memcache 入口
        $entryKey = $this->_getObjectEntry($objectId);

        // 已经存在的缓存依赖项数组
        $dependingKeys = Yii::$app->cache->get($entryKey);

        return $dependingKeys;
    }

    /** 
     * 生成并向对象结点添加一个缓存依赖项
     * 
     * @param int $objectId 被依赖的对象 ID，对象必须属于 [[identifier]] 对应的对象
     * @param string $category 缓存入口标识，代表依赖项的独特身份，在相同的 [[identifier]] 下必须保持唯一
     * 
     * @return string 新添加的缓存入口字符串，通过 [[_makeNewDependingKey()]] 生成，并且已经被保存到了缓存依赖项里
     */
    public function makeCacheKey($objectId, $category) : string {
        $cache = Yii::$app->cache;

        // memcache 入口
        $entryKey = $this->_getObjectEntry($objectId);

        // 依赖于 $objectId 的所有缓存入口的键值
        $dependingKeys = $cache->get($entryKey);
        
        // 保存数据的入口键值，也是依赖于 $objectId 的缓存键值；当 $objectId 更新时，会清除它下面的所有 $dependingKeys 记录的缓存.
        $key = $this->_makeNewDependingKey($objectId, $category);

        // 不存在时添加到依赖项数组
        if($dependingKeys == false){
            $dependingKeys = [$key];
            $cache->set($entryKey, $dependingKeys);

        }else{
            if (! in_array($key, $dependingKeys)){
                $dependingKeys[] = $key;
                $cache->set($entryKey, $dependingKeys);
            }
        }

        return $key;
    }

    /** 删除一个对象结点的所有缓存依赖项 */
    public function deleteDependingdKeys($objectId = null){
        if($objectId == null){
            // Yii
            return;
        }

        // 获取依赖于对象的所有缓存依赖项数组
        $dependingKeys = $this->getDependingKeys($objectId);

        if ($dependingKeys){
            $cache = Yii::$app->cache;
            foreach ($dependingKeys as $key) {
                $cache->delete($key);
            }

            $cache->delete($this->_getObjectEntry($objectId));
        }

        return $dependingKeys;
    }
}

?>
