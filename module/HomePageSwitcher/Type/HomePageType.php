<?php


namespace Module\HomePageSwitcher\Type;


use ModStart\Core\Type\BaseType;
use Module\Vendor\Provider\HomePage\AbstractHomePageProvider;
use Module\Vendor\Provider\HomePage\HomePageProvider;

class HomePageType implements BaseType
{
    public static function getList()
    {
        return array_build(array_filter(HomePageProvider::all(), function ($provider) {
            /** @var $provider AbstractHomePageProvider */
            $type = $provider->type();
            if (!is_array($type)) {
                $type = [$type];
            }
            return in_array(AbstractHomePageProvider::TYPE_PC, $type);
        }), function ($k, $provider) {
            /** @var $provider AbstractHomePageProvider */
            return [
                $provider->action(),
                $provider->title() . ' → ' . $provider->action(),
            ];
        });
    }

}
