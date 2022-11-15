<?php

namespace Module\HomePageSwitcher\Admin\Controller;

use Illuminate\Routing\Controller;
use ModStart\Admin\Layout\AdminConfigBuilder;
use ModStart\Form\Form;
use Module\HomePageSwitcher\Type\HomePageMobileType;
use Module\HomePageSwitcher\Type\HomePageType;

class ConfigController extends Controller
{
    public function setting(AdminConfigBuilder $builder)
    {
        $builder->pageTitle('首页切换器');
        $builder
            ->switch('HomePage_Enable', '开启首页切换器')
            ->when('=', true, function (Form $form) {
                $form->type('HomePage_Home', '电脑端首页')->type(HomePageType::class);
                $form->type('HomePage_HomeMobile', '手机端首页')->type(HomePageMobileType::class);
            });
        $builder->formClass('wide');
        return $builder->perform();
    }

}
