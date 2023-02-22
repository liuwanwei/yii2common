# 使用说明

## install

首先编辑项目的 `composer.json`，添加到 `repositories` 属性数组的最前面:

```JSON
"repositories": [
    {
        "type": "git",
        "url": "https://github.com/liuwanwei/yii2common"
    },
    {
        "type": "composer",
        "url": "https://asset-packagist.cn"
    }
]
```

执行 composer，添加到项目:

>composer require "bytefly/yii2common" "dev-master"

## 使用

切记：在应用的入口处初始化缓存区域总入口，如果不初始化，它的初始值就是 `mf`。
多个项目共享同一个 memcache 服务时，使用前必须初始化，否则很容易产生冲突。

```PHP
public function beforeAction(){
    \bytefly\yii2common\grandcache\CacheEntry::$zone = 'my-project1';
}
```

然后派生自己的缓存管理对象。

```PHP
class MyObjectCache extends \bytefly\yii2common\grandcache{
    // 必须定义，且在系统内唯一
    public $objectEntryName = "myObject";

    // 定义一种分类（category）名字
    const Objects = 'objects';
}
```

在查询的时候调用:

```PHP
public function queryObjects($objectId){
    MyObjectCache::getData($objectId, MyObjectCache::Objects, function() use ($objectId){
        return $query->where(['objectId' => $objectId])->all();
    });
}
```

如果想临时关闭缓存功能，可以设置 `Yii::$app->params`:

```PHP
Yii::$app->params['useGrandCache'] = false;
```
