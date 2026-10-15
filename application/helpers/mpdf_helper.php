<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

require_once __DIR__ . '/../vendor/autoload.php';

function pdf_create($html, $filename, $stream = true, $landscape = false)
{
    if ($landscape) {
        $mpdf = new \Mpdf\Mpdf(['c', 'A4-L', 'tempDir' => FCPATH . 'assets/uploads/temp/']);
    } else {
        $mpdf = new \Mpdf\Mpdf(['c', 'A4', 'tempDir' => FCPATH . 'assets/uploads/temp/']);
    }

    $mpdf->showImageErrors = true;
    // Resolve only this application's existing assets on disk. In Docker,
    // localhost in a stored image URL points to PHP, not the web server.
    $base = parse_url(base_url());
    $assets = realpath(FCPATH . 'assets');
    $html = preg_replace_callback('~(<img\\b[^>]*\\bsrc\\s*=\\s*)(["\'])(.*?)\\2~is', function ($match) use ($base, $assets) {
        $url = parse_url(html_entity_decode($match[3], ENT_QUOTES, 'UTF-8'));
        if ($url === false || ! $assets) {
            return $match[0];
        }
        if (isset($url['host']) && (
            strcasecmp($url['host'], $base['host'] ?? '') !== 0
            || ($url['port'] ?? null) !== ($base['port'] ?? null)
            || ($url['scheme'] ?? $base['scheme'] ?? '') !== ($base['scheme'] ?? '')
        )) {
            return $match[0];
        }
        $path = rawurldecode($url['path'] ?? '');
        $basePath = rtrim($base['path'] ?? '/', '/') . '/';
        if (strpos($path, $basePath . 'assets/') !== 0) {
            return $match[0];
        }
        $file = realpath(FCPATH . substr($path, strlen($basePath)));
        if (! $file || strpos($file, $assets . DIRECTORY_SEPARATOR) !== 0 || ! is_file($file)) {
            return $match[0];
        }
        return $match[1] . $match[2] . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . $match[2];
    }, $html);
    $mpdf->WriteHTML($html);

    if ($stream) {
        $mpdf->Output($filename . '.pdf', 'I');
    } else {
        $mpdf->Output(FCPATH . 'assets/uploads/temp/' . $filename . '.pdf', 'F');

        return FCPATH . 'assets/uploads/temp/' . $filename . '.pdf';
    }
}
