<?php

use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PlainTextHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

class WhoopsHook
{
    public function bootWhoops()
    {
        // A página do Whoops expõe código-fonte e stack trace: só é exibida em
        // ambiente de desenvolvimento e quando habilitada explicitamente no .env
        $whoopsEnabled = defined('ENVIRONMENT')
            && ENVIRONMENT === 'development'
            && filter_var($_ENV['WHOOPS_ERROR_PAGE_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $whoopsEnabled) {
            return;
        }

        $handler = null;
        if (php_sapi_name() === 'cli') {
            $handler = new PlainTextHandler();
        } else {
            // Normalização deliberada, na mesma ordem que o CodeIgniter usa ao
            // rotear. `parse_url('//publico/os/x', PHP_URL_PATH)` devolve
            // '/os/x' — a forma '//' é lida como URL relativa a protocolo, com
            // 'publico' virando HOST. O nginx colapsa as barras e serve a
            // página, o CI roteia para o controller Publico, mas a checagem
            // ingênua não casava e o Whoops era registrado na rota anônima.
            // Prefixar um host fictício e colapsar barras repetidas remove as
            // duas divergências.
            $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
            $path = parse_url('http://dummy' . '/' . ltrim($uri, '/'), PHP_URL_PATH);
            $path = preg_replace('#/{2,}#', '/', (string) $path);

            // A área pública é a única rota anônima do sistema e fica exposta à
            // internet pelo Cloudflare Tunnel. PrettyPageHandler publica stack
            // trace, trecho de código-fonte, caminhos absolutos e variáveis de
            // ambiente — inaceitável sem autenticação. Aqui o Whoops não é
            // registrado: o CodeIgniter cai na própria página de erro genérica.
            //
            // A detecção ignora o prefixo opcional /index.php (o front
            // controller aceita /publico/... e /index.php/publico/...), porque
            // casar apenas o início do caminho deixaria a segunda forma
            // servindo página de debug.
            $rota = preg_replace('#^/index\.php#', '', $path);
            $rota = '/' . ltrim((string) $rota, '/');

            if (strpos($rota, '/publico/') === 0 || $rota === '/publico') {
                return;
            }

            $handler = str_contains($path, '/api')
                ? new JsonResponseHandler()
                : new PrettyPageHandler();
        }

        if ($handler instanceof PrettyPageHandler) {
            $sensitiveVars = $this->extractEnvNames(APPPATH . '.env');

            foreach ($sensitiveVars as $sensitiveVar) {
                $handler->blacklist('_SERVER', $sensitiveVar);
                $handler->blacklist('_ENV', $sensitiveVar);
            }
        }

        $whoops = new Run();
        $whoops->pushHandler($handler);
        $whoops->register();
    }

    private function extractEnvNames(string $filePath)
    {
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! $lines) {
            return [];
        }

        $envNames = [];
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || trim($line) === '') {
                continue;
            }

            $parts = explode('=', $line, 2);

            if (isset($parts[0])) {
                $envNames[] = trim($parts[0]);
            }
        }

        return $envNames;
    }
}
