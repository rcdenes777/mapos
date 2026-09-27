<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

$route['publico/os/(:any)'] = 'publico/os/$1';
$route['publico/logo/(:any)'] = 'publico/logo/$1';
// O nome do arquivo do editor tem barra (mes-ano/arquivo.png), por isso a rota
// aceita o resto do caminho; o controller valida cada segmento.
$route['publico/imagem/(:any)'] = 'publico/imagem/$1';
$route['publico/anexo/(:any)/(:num)'] = 'publico/anexo/$1/$2';
$route['publico/thumb/(:any)/(:num)'] = 'publico/thumb/$1/$2';
