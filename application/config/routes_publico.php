<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

$route['publico/os/(:any)'] = 'publico/os/$1';
$route['publico/logo/(:any)'] = 'publico/logo/$1';
$route['publico/anexo/(:any)/(:num)'] = 'publico/anexo/$1/$2';
$route['publico/thumb/(:any)/(:num)'] = 'publico/thumb/$1/$2';
