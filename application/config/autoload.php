<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}
/*
| -------------------------------------------------------------------
| AUTO-LOADER
| -------------------------------------------------------------------
| This file specifies which systems should be loaded by default.
|
| In order to keep the framework as light-weight as possible only the
| absolute minimal resources are loaded by default. For example,
| the database is not connected to automatically since no assumption
| is made regarding whether you intend to use it.  This file lets
| you globally define which systems you would like loaded with every
| request.
|
| -------------------------------------------------------------------
| Instructions
| -------------------------------------------------------------------
|
| These are the things you can load automatically:
|
| 1. Packages
| 2. Libraries
| 3. Helper files
| 4. Custom config files
| 5. Language files
| 6. Models
|
*/

/*
| -------------------------------------------------------------------
|  Auto-load Packges
| -------------------------------------------------------------------
| Prototype:
|
|  $autoload['packages'] = array(APPPATH.'third_party', '/usr/local/shared');
|
*/

$autoload['packages'] = [];

/*
| -------------------------------------------------------------------
|  Auto-load Libraries
| -------------------------------------------------------------------
| These are the classes located in the system/libraries folder
| or in your application/libraries folder.
|
| Prototype:
|
|	$autoload['libraries'] = array('database', 'session', 'xmlrpc');
*/

// A área pública (/publico/*) é a única rota anônima e fica exposta à internet
// pelo túnel. Com `session` autoloaded, CADA acesso de visitante — página,
// miniatura, anexo e varredura de bot — insere/atualiza linha em `ci_sessions`
// (driver `database`) e devolve o cookie de sessão do painel a quem nunca fez
// login: tabela crescendo sem limite na rota mais exposta, e cookie de sessão
// administrativa entregue a anônimo. O controller Publico não usa sessão.
//
// Este arquivo é PHP executado antes do bootstrap, então a decisão é tomada
// sobre REQUEST_URI, normalizado do mesmo jeito que o hook do Whoops (a forma
// `//publico/...` faz parse_url ler 'publico' como host).
$mapos_uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$mapos_path = parse_url('http://dummy' . '/' . ltrim($mapos_uri, '/'), PHP_URL_PATH);
$mapos_path = preg_replace('#/{2,}#', '/', (string) $mapos_path);
$mapos_rota = '/' . ltrim((string) preg_replace('#^/index\.php#', '', $mapos_path), '/');
$mapos_ehPublico = strpos($mapos_rota, '/publico/') === 0 || $mapos_rota === '/publico';

$autoload['libraries'] = $mapos_ehPublico
    ? ['database']
    : ['database', 'session', 'permission'];

unset($mapos_uri, $mapos_path, $mapos_rota, $mapos_ehPublico);

/*
| -------------------------------------------------------------------
|  Auto-load Helper Files
| -------------------------------------------------------------------
| Prototype:
|
|	$autoload['helper'] = array('url', 'file');
*/

$autoload['helper'] = ['url', 'audit', 'date', 'dd', 'validation', 'general', 'captcha'];

/*
| -------------------------------------------------------------------
|  Auto-load Config files
| -------------------------------------------------------------------
| Prototype:
|
|	$autoload['config'] = array('config1', 'config2');
|
| NOTE: This item is intended for use ONLY if you have created custom
| config files.  Otherwise, leave it blank.
|
*/

$autoload['config'] = [];

/*
| -------------------------------------------------------------------
|  Auto-load Language files
| -------------------------------------------------------------------
| Prototype:
|
|	$autoload['language'] = array('lang1', 'lang2');
|
| NOTE: Do not include the "_lang" part of your file.  For example
| "codeigniter_lang.php" would be referenced as array('codeigniter');
|
*/

$autoload['language'] = [];

/*
| -------------------------------------------------------------------
|  Auto-load Models
| -------------------------------------------------------------------
| Prototype:
|
|	$autoload['model'] = array('model1', 'model2');
|
*/

$autoload['model'] = [];

/* End of file autoload.php */
/* Location: ./application/config/autoload.php */
