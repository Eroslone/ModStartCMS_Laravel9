<?php

/* @var \Illuminate\Routing\Router $router */

$router->match(['get', 'post'], 'home_page_switcher/config/setting', 'ConfigController@setting');
