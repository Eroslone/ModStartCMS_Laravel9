<?php

namespace Module\HomePageSwitcher\Core;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use ModStart\Admin\Config\AdminMenu;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(Dispatcher $events)
    {
        AdminMenu::register([
            [
                'title' => L('Site Manage'),
                'icon' => 'cog',
                'sort' => 400,
                'children' => [
                    [
                        'title' => '首页切换器',
                        'url' => '\Module\HomePageSwitcher\Admin\Controller\ConfigController@setting',
                    ]
                ]
            ]
        ]);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

    }
}
