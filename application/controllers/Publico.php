<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * Controller da área pública sem login (link de OS por token).
 *
 * Estende CI_Controller diretamente — NUNCA MY_Controller (exige sessão de
 * painel) nem reaproveita a sessão de Mine.php (portal do cliente). Este é
 * o único ponto de entrada do MapOS acessível sem autenticação nenhuma, e
 * deve continuar deliberadamente mínimo: sem menu, sem navegação, sem
 * qualquer link para outra tela do sistema.
 *
 * Anexos (fotos/vídeo) são servidos por aqui (método anexo()), não mais
 * como estático pelo nginx — a regra de borda do Cloudflare Tunnel deve
 * liberar SÓ /publico/*, nunca /assets/anexos/*.
 */
class Publico extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('OsTokenPublico_model');
        $this->load->model('mapos_model');
        $this->load->model('os_model');
    }

    public function os($token = null)
    {
        $osId = $this->OsTokenPublico_model->resolverOsId($token);

        if ($osId === null) {
            show_404();

            return;
        }

        $data['result'] = $this->os_model->getById($osId);

        if (! $data['result']) {
            show_404();

            return;
        }

        $data['token'] = $token;
        $data['produtos'] = $this->os_model->getProdutos($osId);
        $data['servicos'] = $this->os_model->getServicos($osId);
        $data['anexos'] = $this->os_model->getAnexos($osId);
        $data['emitente'] = $this->mapos_model->getEmitente();

        $this->CI = &get_instance();
        $this->CI->load->database();

        // A linha pix_key pode não existir (instalação nova, seed não rodado).
        // Sem esta guarda, `->row_object()->valor` dispara warning que o hook
        // do Whoops transforma em página de erro com stack trace e código-fonte
        // — na única rota anônima do sistema. Ver hooks/whoops.php.
        $linhaPix = $this->CI->db->get_where('configuracoes', ['config' => 'pix_key'])->row_object();
        $data['pix_key'] = $linhaPix ? $linhaPix->valor : '';

        $data['qrCode'] = $data['pix_key'] ? $this->os_model->getQrCode($osId, $data['pix_key'], $data['emitente']) : '';
        $data['chaveFormatada'] = $data['pix_key'] ? $this->formatarChave($data['pix_key']) : '';

        // O logo do emitente é gravado em assets/uploads/ e o banco guarda uma
        // URL ABSOLUTA da origem interna (base_url() no momento do upload).
        // Pela porta pública isso falha duas vezes: a origem não existe para o
        // cliente do túnel e /assets/uploads/ é negado de propósito (a mesma
        // pasta recebe os PDFs de OS gerados pelo mpdf, que são dado de
        // cliente e não podem ficar públicos). Servimos o logo por rota
        // própria, que lê só o arquivo apontado pelo emitente.
        $data['logoPublico'] = ! empty($data['emitente']->url_logo)
            ? '/publico/logo/' . rawurlencode($token)
            : '';

        $this->load->view('publico/visualizar_os', $data);
    }

    /**
     * Serve o logo do emitente para a página pública.
     *
     * Exige token válido (mesma barreira da página) e serve exclusivamente o
     * arquivo declarado em `configuracoes.url_logo`, resolvido sob
     * assets/uploads/ — nunca um caminho vindo da URL. Assim a pasta continua
     * negada no nginx e os PDFs de OS gravados ali seguem inacessíveis.
     */
    public function logo($token = null)
    {
        if ($this->OsTokenPublico_model->resolverOsId($token) === null) {
            show_404();

            return;
        }

        $emitente = $this->mapos_model->getEmitente();

        if (empty($emitente->url_logo)) {
            show_404();

            return;
        }

        // basename() sobre o valor do banco: só o nome do arquivo interessa,
        // a URL absoluta gravada é descartada.
        $nome = basename(parse_url((string) $emitente->url_logo, PHP_URL_PATH) ?: '');

        if ($nome === '' || $nome === '.' || $nome === '..') {
            show_404();

            return;
        }

        $base = realpath(FCPATH . 'assets/uploads');
        $caminho = realpath(FCPATH . 'assets/uploads/' . $nome);

        // Contenção: o arquivo resolvido tem de ficar sob assets/uploads/ e ser
        // imagem. Impede que dado corrompido no banco (ou symlink) transforme
        // esta rota em leitura arbitrária — e barra o .pdf gravado na mesma pasta.
        if ($base === false || $caminho === false
            || strpos($caminho, $base . DIRECTORY_SEPARATOR) !== 0
            || ! is_file($caminho)) {
            show_404();

            return;
        }

        $mime = @mime_content_type($caminho);

        if (! is_string($mime) || strpos($mime, 'image/') !== 0) {
            show_404();

            return;
        }

        // Mesmo padrão de anexo()/thumb(): header() direto porque a entrega
        // libera a resposta na hora e os headers do CI Output chegariam tarde.
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($caminho));
        header('Content-Disposition: inline; filename="' . basename($caminho) . '"');
        header('X-Content-Type-Options: nosniff');

        $this->entregarArquivo($caminho);
    }

    /**
     * Serve um anexo (foto/vídeo) da OS dona do token, lendo do disco.
     * Sem suporte a Range/seek nesta fase (Fase 3) — o navegador baixa/
     * reproduz do início ao fim.
     */
    public function anexo($token = null, $anexoId = null)
    {
        $osId = $this->OsTokenPublico_model->resolverOsId($token);

        if ($osId === null) {
            show_404();

            return;
        }

        $anexo = $this->os_model->getAnexoDaOs($osId, (int) $anexoId);

        if (! $anexo) {
            show_404();

            return;
        }

        $caminhoCompleto = $this->resolverCaminhoSeguro($anexo->path, $anexo->anexo);

        if ($caminhoCompleto === null) {
            show_404();

            return;
        }

        // header() direto, e não $this->output->set_content_type(): a entrega
        // abaixo libera a resposta na hora e os headers enfileirados no CI
        // Output chegam tarde demais — sem isso o navegador recebia
        // "Content-Type: text/html" para o vídeo (confirmado com `curl -D -`).
        //
        // nosniff: o Content-Type sai de mime_content_type() (sniff do
        // conteúdo). Sem o header, um arquivo enviado como imagem mas contendo
        // HTML/JS poderia ser reinterpretado pelo navegador na origem pública.
        header('Content-Type: ' . (mime_content_type($caminhoCompleto) ?: 'application/octet-stream'));
        header('Content-Length: ' . filesize($caminhoCompleto));
        header('Content-Disposition: inline; filename="' . basename($caminhoCompleto) . '"');
        header('X-Content-Type-Options: nosniff');

        $this->entregarArquivo($caminhoCompleto);
    }

    /**
     * Serve a miniatura (thumbnail) de um anexo, mesma checagem de token.
     */
    public function thumb($token = null, $anexoId = null)
    {
        $osId = $this->OsTokenPublico_model->resolverOsId($token);

        if ($osId === null) {
            show_404();

            return;
        }

        $anexo = $this->os_model->getAnexoDaOs($osId, (int) $anexoId);

        // Anexo que não pertence à OS do token: resposta IDÊNTICA à de token
        // inválido. Antes este caso caía no mesmo ramo do "anexo sem thumb" e
        // respondia 302 para o ícone genérico, enquanto anexo() respondia 404 —
        // a diferença revelava que o token era válido (oráculo de enumeração)
        // e entregava um recurso para id de outra OS (fail-open).
        if (! $anexo) {
            show_404();

            return;
        }

        // Anexo legítimo, mas sem miniatura (não é imagem, ou vídeo sem thumb):
        // devolve o ícone genérico servido POR AQUI, sem redirect. Um 302 para
        // /assets/img/... apontaria para fora da allowlist de rota pública e
        // continuaria distinguível de um 404.
        if (! $anexo->thumb) {
            $this->entregarIconeGenerico();

            return;
        }

        // A miniatura fica em <path>/thumbs/<thumb> — é onde o upload a grava
        // (Os.php, image_lib com new_image = file_path.'thumbs'.'/thumb_...') e
        // é o caminho que a view autenticada usa ($a->url . '/thumbs/' . $a->thumb).
        // Sem o segmento 'thumbs' a rota devolvia 404 para toda imagem.
        $caminhoCompleto = $this->resolverCaminhoSeguro(
            rtrim($anexo->path, '/\\') . DIRECTORY_SEPARATOR . 'thumbs',
            $anexo->thumb
        );

        if ($caminhoCompleto === null) {
            // Miniatura ausente em disco é falha de dado, não pista de token:
            // cai no ícone genérico, mesma resposta do anexo sem thumb.
            $this->entregarIconeGenerico();

            return;
        }

        // Mesmo motivo do anexo(): header() direto, antes de entregar o corpo.
        header('Content-Type: ' . (mime_content_type($caminhoCompleto) ?: 'image/png'));
        header('Content-Length: ' . filesize($caminhoCompleto));
        header('X-Content-Type-Options: nosniff');

        $this->entregarArquivo($caminhoCompleto);
    }

    /**
     * Resolve <diretório>/<arquivo> garantindo que o resultado final continua
     * dentro de assets/anexos. `path` e o nome vêm do banco (não da URL), mas a
     * contenção é barata e transforma qualquer dado corrompido, link simbólico
     * ou '..' gravado por outra rota em 404 em vez de leitura arbitrária.
     *
     * @return string|null caminho real do arquivo, ou null se inválido
     */
    private function resolverCaminhoSeguro($diretorio, $arquivo)
    {
        if (! is_string($diretorio) || ! is_string($arquivo) || $arquivo === '') {
            return null;
        }

        // Só o nome do arquivo: descarta qualquer tentativa de subir diretório.
        $arquivo = basename($arquivo);

        if ($arquivo === '' || $arquivo === '.' || $arquivo === '..') {
            return null;
        }

        $raiz = realpath(FCPATH . 'assets' . DIRECTORY_SEPARATOR . 'anexos');

        if ($raiz === false) {
            return null;
        }

        $caminho = realpath(rtrim($diretorio, '/\\') . DIRECTORY_SEPARATOR . $arquivo);

        if ($caminho === false || ! is_file($caminho)) {
            return null;
        }

        // Contenção: o arquivo resolvido tem de estar sob assets/anexos.
        if (strpos($caminho, $raiz . DIRECTORY_SEPARATOR) !== 0) {
            return null;
        }

        return $caminho;
    }

    /**
     * Envia o arquivo em blocos, sem carregá-lo inteiro na memória.
     *
     * readfile() de um vídeo de centenas de MB com output buffering ativo
     * acumula o arquivo todo na memória do PHP (memory_limit) antes de enviar.
     * Limpar os buffers e ler em blocos mantém o consumo constante.
     */
    private function entregarArquivo($caminho)
    {
        // Descarta buffers do CI para o corpo não ser acumulado em memória.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $handle = fopen($caminho, 'rb');

        if ($handle === false) {
            return;
        }

        while (! feof($handle)) {
            $bloco = fread($handle, 8192);

            if ($bloco === false) {
                break;
            }

            echo $bloco;
            flush();
        }

        fclose($handle);
    }

    /**
     * Ícone genérico para anexo sem miniatura, servido pela própria rota
     * pública (sem redirect para fora de /publico/*).
     */
    private function entregarIconeGenerico()
    {
        $icone = realpath(FCPATH . 'assets' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'icon-file.png');

        if ($icone === false || ! is_file($icone)) {
            show_404();

            return;
        }

        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($icone));
        header('X-Content-Type-Options: nosniff');

        $this->entregarArquivo($icone);
    }

    // Copiar formatarChave/validarCPF/validarCNPJ de Mine.php tal como
    // estão (mesma lógica, não há motivo para reescrever). Considerar
    // extrair para um helper compartilhado numa iteração futura — fora do
    // escopo desta fase para não tocar em Mine.php sem necessidade.
    private function formatarChave($chave)
    {
        if ($this->validarCPF($chave)) {
            return substr($chave, 0, 3) . '.' . substr($chave, 3, 3) . '.' . substr($chave, 6, 3) . '-' . substr($chave, 9);
        } elseif ($this->validarCNPJ($chave)) {
            return substr($chave, 0, 2) . '.' . substr($chave, 2, 3) . '.' . substr($chave, 5, 3) . '/' . substr($chave, 8, 4) . '-' . substr($chave, 12);
        } elseif (strlen($chave) === 11) {
            return '(' . substr($chave, 0, 2) . ') ' . substr($chave, 2, 5) . '-' . substr($chave, 7);
        }

        return $chave;
    }

    private function validarCPF($cpf)
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1+$/', $cpf)) {
            return false;
        }
        $soma1 = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma1 += $cpf[$i] * (10 - $i);
        }
        $resto1 = $soma1 % 11;
        $dv1 = ($resto1 < 2) ? 0 : 11 - $resto1;
        if ($dv1 != $cpf[9]) {
            return false;
        }
        $soma2 = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma2 += $cpf[$i] * (11 - $i);
        }
        $resto2 = $soma2 % 11;
        $dv2 = ($resto2 < 2) ? 0 : 11 - $resto2;

        return $dv2 == $cpf[10];
    }

    private function validarCNPJ($cnpj)
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1+$/', $cnpj)) {
            return false;
        }
        $soma1 = 0;
        for ($i = 0, $pos = 5; $i < 12; $i++, $pos--) {
            $pos = ($pos < 2) ? 9 : $pos;
            $soma1 += $cnpj[$i] * $pos;
        }
        $dv1 = ($soma1 % 11 < 2) ? 0 : 11 - ($soma1 % 11);
        if ($dv1 != $cnpj[12]) {
            return false;
        }
        $soma2 = 0;
        for ($i = 0, $pos = 6; $i < 13; $i++, $pos--) {
            $pos = ($pos < 2) ? 9 : $pos;
            $soma2 += $cnpj[$i] * $pos;
        }
        $dv2 = ($soma2 % 11 < 2) ? 0 : 11 - ($soma2 % 11);

        return $dv2 == $cnpj[13];
    }
}
