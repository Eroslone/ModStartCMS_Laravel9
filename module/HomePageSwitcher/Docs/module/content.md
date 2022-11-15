一键切换您的首页

## 使用说明

如果模块中使用首页提供者注册了首页，则可以在首页切换器中直接切换成为模块提供的首页。

## 如何注册首页

组要在 `ModuleServiceProvider:boot` 中通过如下方式注册了首页

```php
if(class_exists('Module\\Vendor\\Provider\\HomePage\\HomePageProvider')){
    \Module\Vendor\Provider\HomePage\HomePageProvider::register( Provider类 )
}
``` 

其中 `Provider类` 需要继承 `\Module\Vendor\Provider\HomePage\AbstractHomePageProvider`，如：

```php
<?php

namespace Module\TestModule\Provider\HomePage;

use Module\Vendor\Provider\HomePage\AbstractHomePageProvider;

class TestHomePageProvider extends AbstractHomePageProvider
{
    public function title()
    {
        return '测试首页';
    }
    public function action()
    {
        return '\\Module\\TestModule\\Web\\Controller\\IndexController@index';
    }
}
```

## 为何我的系统不生效

需要在首页通过如下方法调用

> 默认系统已经调用该方法

```php
<?php

namespace App\Web\Controller;

use Module\Vendor\Provider\HomePage\HomePageProvider;

class IndexController extends BaseController
{
    public function index()
    {
        return HomePageProvider::call(__METHOD__, '\\App\\Web\\Controller\\IndexController@index');
    }
}
```
