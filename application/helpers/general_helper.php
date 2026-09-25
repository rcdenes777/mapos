<?php

use Piggly\Pix\Parser;

if (! function_exists('convertUrlToUploadsPath')) {
    function convertUrlToUploadsPath($url)
    {
        if (! $url) {
            return;
        }

        return FCPATH . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($url);
    }
}

if (! function_exists('limitarTexto')) {
    function limitarTexto($texto, $limite)
    {
        $contador = strlen($texto);

        if ($contador >= $limite) {
            $texto = substr($texto, 0, strrpos(substr($texto, 0, $limite), ' ')) . '...';

            return $texto;
        } else {
            return $texto;
        }
    }
}

if (! function_exists('getMoneyAsCents')) {
    function getMoneyAsCents($value)
    {
        // make sure we are dealing with a proper number now, no +.4393 or 3...304 or 76.5895,94
        if (! is_numeric($value)) {
            throw new \InvalidArgumentException('A entrada deve ser numérica!');
        }

        return intval(round(floatval($value), 2) * 100);
    }
}

if (! function_exists('getCobrancaTransactionStatus')) {
    function getCobrancaTransactionStatus($paymentGatewaysConfig, $paymentGateway, $status)
    {
        return $paymentGatewaysConfig[$paymentGateway]['transaction_status'][$status];
    }
}

if (! function_exists('getPixKeyType')) {
    function getPixKeyType($value)
    {
        if (Parser::validateDocument($value)) {
            return Parser::KEY_TYPE_DOCUMENT;
        }

        if (Parser::validateEmail($value)) {
            return Parser::KEY_TYPE_EMAIL;
        }

        if (Parser::validatePhone($value)) {
            return Parser::KEY_TYPE_PHONE;
        }

        if (Parser::validateRandom($value)) {
            return Parser::KEY_TYPE_RANDOM;
        }

        return null;
    }
}

if (! function_exists('getAmount')) {
    function getAmount($money)
    {
        $cleanString = preg_replace('/([^0-9\.,])/i', '', $money);
        $onlyNumbersString = preg_replace('/([^0-9])/i', '', $money);

        $separatorsCountToBeErased = strlen($cleanString) - strlen($onlyNumbersString) - 1;

        $stringWithCommaOrDot = preg_replace('/([,\.])/', '', $cleanString, $separatorsCountToBeErased);
        $removedThousandSeparator = preg_replace('/(\.|,)(?=[0-9]{3,}$)/', '', $stringWithCommaOrDot);

        return floatval(str_replace(',', '.', $removedThousandSeparator));
    }
}

if (! function_exists('json_decode_legacy')) {
    function json_decode_legacy(string $raw): mixed
    {
        $decoded = json_decode($raw, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            $decoded = unserialize($raw, ['allowed_classes' => false]);
        }

        return $decoded;
    }
}

if (! function_exists('printSafeHtml')) {
    function printSafeHtml(string $html): string
    {
        static $purifier = null;

        if ($purifier === null) {
            $config = HTMLPurifier_Config::createDefault();

            // Por padrão o HTMLPurifier grava seu cache dentro de vendor/, que
            // não é gravável pelo PHP e faz visualizar/imprimir OS quebrar.
            // application/cache é gravável e sobrevive a um composer install.
            $cacheDir = APPPATH . 'cache' . DIRECTORY_SEPARATOR . 'htmlpurifier';

            if (! is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }

            $config->set('Cache.SerializerPath', $cacheDir);

            $purifier = new HTMLPurifier($config);
        }

        return $purifier->purify($html);
    }
}

if (! function_exists('printCampoOsHtml')) {
    /**
     * Prepara o conteúdo dos campos de texto da OS (descrição, defeito,
     * observações, laudo) para exibição em tela e impressão.
     *
     * O editor de texto grava imagem e texto na ordem em que o usuário colou,
     * então é comum o texto acabar no meio das fotos (img, img, texto, img).
     * Aqui o conteúdo é separado: primeiro todo o texto, depois todas as
     * imagens agrupadas em um contêiner que o CSS exibe em grade de 2 colunas.
     *
     * Não altera printSafeHtml() porque aquele helper também serve ao e-mail e
     * à impressão térmica, onde grade de 2 colunas não se aplica.
     */
    function printCampoOsHtml(?string $html): string
    {
        $html = printSafeHtml((string) $html);

        if (stripos($html, '<img') === false) {
            return $html;
        }

        $doc = new DOMDocument();
        $anterior = libxml_use_internal_errors(true);

        // O prefixo XML declara o charset sem depender de <meta>, evitando que
        // acento vire caractere quebrado na saída.
        $carregou = $doc->loadHTML(
            '<?xml encoding="UTF-8"?><div id="mapos-campo-os">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        // Conteúdo que o parser não entendeu volta como veio: melhor manter o
        // layout antigo do que perder o que o técnico escreveu.
        if ($carregou === false) {
            return $html;
        }

        $raiz = $doc->getElementById('mapos-campo-os');

        if ($raiz === null) {
            return $html;
        }

        $imagens = [];

        foreach (iterator_to_array($raiz->getElementsByTagName('img')) as $img) {
            $imagens[] = $doc->saveHTML($img);
            $img->parentNode->removeChild($img);
        }

        if ($imagens === []) {
            return $html;
        }

        $texto = '';

        foreach ($raiz->childNodes as $filho) {
            $texto .= $doc->saveHTML($filho);
        }

        // Parágrafos que só continham imagens ficam vazios após a remoção.
        $texto = preg_replace('#<p>(?:\s|&nbsp;|<br\s*/?>)*</p>#iu', '', $texto);

        return trim((string) $texto)
            . '<div class="os-galeria">' . implode('', $imagens) . '</div>';
    }
}
