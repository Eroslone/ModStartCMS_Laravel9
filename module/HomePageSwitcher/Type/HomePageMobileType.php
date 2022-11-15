<?php


namespace Module\HomePageSwitcher\Type;


use ModStart\Core\Type\BaseType;
use Module\Vendor\Provider\HomePage\AbstractHomePageProvider;
use Module\Vendor\Provider\HomePage\HomePageProvider;

class HomePageMobileType implements BaseType
{
    public static function getList()
    {
        return array_build(array_filter(HomePageProvider::all(), function ($provider) {
            /** @var $provider AbstractHomePageProvider */
            $type = $provider->type();
            if (!is_array($type)) {
                $type = [$type];
                // 只支持电脑端的情况历史版本兼容
                if (!in_array(AbstractHomePageProvider::TYPE_MOBILE, $type)) {
                    $type[] = AbstractHomePageProvider::TYPE_MOBILE;
                }
            }
            return in_array(AbstractHomePageProvider::TYPE_MOBILE, $type);
        }), function ($k, $provider) {
            /** @var $provider AbstractHomePageProvider */
            return [
                $provider->action(),
                $provider->title() . ' → ' . $provider->action(),
            ];
        });
    }

}
