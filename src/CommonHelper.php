<?php
namespace bytefly\yii2common;

use Yii;
use yii\base\Model;
use yii\redis\Connection;

class CommonHelper {

    public static function makeErrorSummary(Model $model){
        $errors = $model->getFirstErrors();
        return reset($errors);
    }

    public static function makeError(Model $model){
        return static::makeErrorSummary($model);
    }

    public static function memcache() {
        try{
            return Yii::$app->cache;
        }catch(\Exception $e){
            return null;
        }
    }

    public static function redis() : ?Connection{
        try{
            return Yii::$app->redis;
        }catch(\Exception $e){
            return null;
        }
    }

    public static function redisGet($key){
        $redis = static::redis();
        if ($redis == null){
            return null;
        }else{
            try{
                return $redis->get($key);
            }catch(\Exception $e){
                return null;
            }
        }
    }

    public static function redisSet($key, $value){
        $redis = static::redis();
        if ($redis == null){
            return null;
        }else{
            try{
                return $redis->set($key, $value);
            }catch(\Exception $e){
                return null;
            }
        }
    }
}

?>