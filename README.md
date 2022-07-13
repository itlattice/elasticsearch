# PHP ELASTICSEARCH ORM

## 安装/Install

```
composer require iboxs/elasticsearch
```

## 支持ElasticSearch版本
>more than 7.0


## 使用方法

### PHP原生
```php
    //require elasticsearch config
    $config = require "elasticsearch.php";
    //instance
    $builder = Factory::builder($config);
```

### 基于Laravel框架
请将以下配置写入 `config/app.php`
```php
    'providers' => [
        Iboxs\ElasticSearch\Laravel\ElasticsearchOrm\OrmProvider::class,
    ] 
```
使用以下代码初始化
```php
    $builder = app(\Iboxs\ElasticSearch\Builder::class);
```
### 其他框架

* 作者因没时间再维护，目前就只支持laravel框架使用，若需其他框架，可根据laravel框架写法自行改写，若有疑问，可联系作者QQ320587491

## 快速开始

### Create

```php
    $builder->index('index')->create(['key' => 'value']);
    //return collection
    $builder->index('index')->createCollection(['key' => 'value']);
```

### Update

```php
    $builder->index('index')->update(['key' => 'value']) : bool
```

### Delete

```php
    $builder->index('index')->delete($id) : bool
```

### Select

```php
    //select one
    $builder->index('index')->first();
    //select all
    $builder->index('index')->get();
    //select with paginate
    $builder->index('index')->paginate($page, $size) : Collection
    //select by id
    $builder->byId($id) : stdClass
    //select by id if failed throw error
    $builder->byIdOrFail($id) : stdClass
    //select chunk
    $builder->chunk(callback $callback, $limit = 2000, $scroll = '10m')
```

### Count

```php
    $builder->count() : int
```

### Condition

whereTerm
```php
    $builder->whereTerm('key', 'value');
```

whereLike（wildcard）
```php
    //value without add wildcard '*'
    $builder->whereLike('key', 'value');
```

match

```php
    $builder->whereMatch('key', 'value');
```

range

```php
    $builder->whereBetween('key', ['value1', 'value2']);
```

where in

```php
    $builder->whereIn('key', ['value1', 'value2', ...]);
```

nested

```php
    $builder->where(function(Builder $query){
        $query->whereTerm('key', 'value');
    });
```

### 查询布尔运算标识

> ['='  => 'eq','>'  => 'gt','>=' => 'gte','<'  => 'lt','<=' => 'lte','!=' => 'ne',]

```php
    $builder->where('key', '=', 'value');
```

### 更多

请自行查阅源代码（Client文件为入口文件）

