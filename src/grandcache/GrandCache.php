<?php
namespace bytefly\yii2common\grandcache;

use bytefly\yii2common\CommonHelper;
use ErrorException;
use Yii;

/**
 * 这里对使用 MemCache 的数据进行统一管理，主要是能自动做到清除旧缓存
 * 
 * 设计目标：
 * 
 * 假如球员一场比赛数据被更改，清除该场比赛的缓存，同时清除球员历史数据缓存
 * 
 * 数据变更的起点：
 * - 添加、修改、删除球员比赛数据 GameStat
 * - 添加、删除、修改球队比赛数据 GameStat
 * 
 * 修改球员数据引起的缓存更新：
 * - 查询球员历史数据接口缓存的数据需要更新 fe:/player/match-history
 * - 查询比赛详情接口缓存的数据需要更新 fe:/kmatch/result
 */
class GrandCache extends \yii\base\Component{    

    const EntryGame = 'game';
    const EntryTour = 'tour';
    const EntryPlayer = 'player';
    const EntryTeam = 'team';

    /** @var string 子类必须定义，否则抛出异常；一般有 'tour', 'team', 'player', 'game' */
    public $objectEntryName = null;

    /** @var CacheEntry */
    protected $cacheEntry = null;

    /** @var array 保存每个缓存子对象的实例 */
    private static $_Defaults = [];

    public static function default() : GrandCache
    {
        $calledClass = get_called_class();        
        $instance = static::$_Defaults[$calledClass] ?? null;
        if ($instance == null){
            $instance = new $calledClass;
            static::$_Defaults[$calledClass] = $instance;
        }

        return $instance;
    }

    public function __construct()
    {
    }

    /**
     * 获取对应的缓存管理入口对象
     * 
     * @param string|null $entry 用户缓存设置的入口名字，未设置时，使用 [[$objectEntryName]] 属性中的名字（一般行为）
     * 
     * @return CacheEntry
     */
    public function getEntry($entry = null){
        if ($this->cacheEntry){
            return $this->cacheEntry;
        }

        // 创建 [[$cacheEntry]]
        $identifier = $entry ?: $this->objectEntryName;
        if (! $identifier){
            throw new \ErrorException('派生类需要给 GrandCache::$objectEntryName 变量赋值');
        }
        
        $this->cacheEntry = new CacheEntry($identifier);
        return $this->cacheEntry;
    }

    /**
     * 从缓存获取数据，如果数据不存在，就根据用户传入的 $callable 生成数据，并写入缓存
     * 
     * 如果启用了 Yii::$app->cache，那么在调用 $callable 生成数据并缓存后，会将缓存位置的键值保存起来，如果 $objectId 对应数据变更，会自动删除缓存的内容
     *
     * @param int $objectId
     * @param string $category
     * @param callable $callable
     * 
     * @return mixed 由 $callable 定义
     */
    protected function getCachedData($objectId, $category, callable $callable){
        if (Yii::$app->params['useGrandCache'] !== true){
            // 通过配置参数控制缓存是否生效，测试时使用
            return call_user_func($callable);
        }        

        $cache = CommonHelper::memcache();
        if($cache){
            $key = $this->getEntry()->makeCacheKey($objectId, $category);
            $data = Yii::$app->cache->getOrSet($key, $callable);
            
        }else{
            $data = call_user_func($callable);
        }

        return $data;
    }    

    /** 主动更新或者清除某个缓存 */
    /*
    public function setObjectData($objectId, string $category, $value){
        $key = $this->getEntry($objectId)->makeCacheKey($objectId, $category);
        return CommonHelper::memcache()?->set($key, $value);
    }
    */

    /** 静态封装，子类各自重载 */
    public static function getData($objectId, string $category, callable $callable){
        return static::default()->getCachedData($objectId, $category, $callable);
    }

    public static function setData(string $category, $value){
        throw new ErrorException('派生类必须重载: ' . __FUNCTION__);
    }

    public static function clearData($objectId, string $category){
        throw new ErrorException('派生类必须重载: ' . __FUNCTION__);
    }

    /** 上面的代码都是在创建和维护缓存，下面的函数接口，是当当外部事件发生后调用，自动清除对应的缓存数据 */

    /**
     * 清除 memcache 缓存的入口（之一，随需扩展）
     *
     * @param array $param 当相关对象被修改时，指定相关需要更新的目标缓存，目前支持 tourId, teamId, playerId, gameId 属性，支持逗号分隔的多个 ID
     * 
     * @return void
     */
    public static function onUpdate(array $param){
        if(Yii::$app->cache == null){
            return;
        }

        // 删除跟赛事关联的数据
        if(! empty($param['tourId'])){
            $default = TourCache::default();
            $array = self::getArray($param['tourId']);
            foreach($array as $tourId){
                $default->getEntry('tour')->deleteDependingdKeys($tourId);
            }            
        }

        // 删除跟比赛关联的缓存
        if (! empty($param['gameId'])) {
            $default = GameCache::default();
            $array = self::getArray($param['gameId']);
            foreach ($array as $gameId) {
                $default->getEntry('game')->deleteDependingdKeys($gameId);
            }            
        }

        // 删除跟球队关联的缓存
        if (! empty($param['teamId'])){
            $default = TeamCache::default();
            $array = self::getArray($param['teamId']);
            foreach ($array as $teamId) {
                $default->getEntry('team')->deleteDependingdKeys($teamId);
            }
        }

        // 删除跟球员关联的缓存
        if (! empty($param['playerId'])) {
            $default = PlayerCache::default();
            $array = self::getArray($param['playerId']);
            foreach ($array as $playerId) {
                $default->getEntry('player')->deleteDependingdKeys($playerId);
            }
        }
    }

    protected static function getArray($value){
        if (! is_array($value)){
            return explode(',', $value);
        }else{
            return $value;
        }
    }
}

?>
