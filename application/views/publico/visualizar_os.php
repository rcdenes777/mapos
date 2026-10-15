<?php $totalServico = 0;
$totalProdutos = 0; ?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Ordem de Serviço - <?php echo $this->config->item('app_name') ?></title>
    <link rel="shortcut icon" href="/assets/img/favicon.png">
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="/assets/css/bootstrap-responsive.min.css" />
    <link rel="stylesheet" href="/assets/css/matrix-style.css" />
    <link rel="stylesheet" href="/assets/css/matrix-media.css" />
    <!-- custom.css traz a moldura das imagens de conteúdo (.table-condensed td img,
         340x250 com object-fit: contain). Sem ele, uma foto de celular renderiza
         em tamanho natural (medido: 1103px) e a página fica gigante — a interna
         mostra 340px. Mesma moldura da impressão. -->
    <link rel="stylesheet" href="/assets/css/custom.css" />
    <style>
        /* Anexos em grade de 2 colunas (pedido do dono: "2 x 2").
           box-sizing evita que padding estoure a metade da largura; a moldura
           fixa mantém foto em pé e foto deitada na mesma altura, igual à
           impressão — sem isso uma foto de celular em retrato fica muito mais
           alta que a vizinha e a linha desalinha. */
        .anexo-grade {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }

        .anexo-grade .anexo-celula {
            box-sizing: border-box;
            width: calc(50% - 7px);
        }

        .anexo-grade .anexo-celula img,
        .anexo-grade .anexo-celula video {
            display: block;
            width: 100%;
            height: 250px;
            object-fit: contain;
            object-position: center;
            background: #f7f7f7;
        }

        /* --- Ampliar imagem ao clicar (lightbox próprio, sem biblioteca) ---
           A moldura de 340x250 corta a leitura de detalhe: em foto de defeito o
           cliente precisa enxergar o original. Sem dependência externa de
           propósito — a página é servida pela porta pública, cuja allowlist não
           libera JS de terceiros, e um CDN quebraria pelo túnel. */
        .zoom-fundo {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, .88);
            z-index: 9999;
            overflow: auto;
            text-align: center;
            cursor: zoom-out;
        }

        .zoom-fundo.aberto {
            display: block;
        }

        .zoom-fundo img {
            /* Estado inicial: cabe inteira na tela. */
            max-width: 96%;
            max-height: 92vh;
            width: auto !important;
            height: auto !important;
            margin: 2vh auto;
            object-fit: initial !important;
            cursor: zoom-in;
            background: #fff;
        }

        /* Segundo clique: tamanho original (1:1), com rolagem se passar da tela. */
        .zoom-fundo.real img {
            max-width: none;
            max-height: none;
            cursor: zoom-out;
        }

        .zoom-fechar {
            position: fixed;
            top: 10px;
            right: 18px;
            color: #fff;
            font-size: 34px;
            line-height: 1;
            text-decoration: none;
            z-index: 10000;
            cursor: pointer;
        }

        .zoom-dica {
            position: fixed;
            bottom: 12px;
            left: 0;
            width: 100%;
            color: #ddd;
            font-size: 13px;
            z-index: 10000;
            pointer-events: none;
        }

        /* Sinaliza que a imagem é clicável. */
        .table-condensed td img,
        .anexo-celula a img {
            cursor: zoom-in;
        }

        /* Celular: uma por linha — duas colunas de 250px de altura não cabem. */
        @media (max-width: 640px) {
            .anexo-grade .anexo-celula {
                width: 100%;
            }
        }
    </style>
    <link href="/assets/font-awesome/css/font-awesome.css" rel="stylesheet" />
    <link href='https://unpkg.com/boxicons@2.1.1/css/boxicons.min.css' rel='stylesheet'>
</head>

<body>
    <div class="container-fluid" style="margin-left: 0; padding: 20px 2rem;">
        <div class="row-fluid" style="margin-top: 0">
            <div class="span12">
                <div class="widget-box">
                    <div class="widget-title" style="margin: -20px 0 0">
                        <span class="icon">
                            <i class="fas fa-diagnoses"></i>
                        </span>
                        <h5>Ordem de Serviço</h5>
                    </div>
                    <div class="widget-content" id="printOs">
                        <div class="invoice-content">
                            <div class="invoice-head" style="margin-bottom: 0">

                                <table class="table table-condensed">
                                    <tbody>
                                        <?php if ($emitente == null) { ?>
                                            <tr>
                                                <td colspan="3" class="alert">Os dados do emitente não foram configurados.</td>
                                            </tr>
                                        <?php } else { ?>
                                            <tr>
                                                <td style="width: 25%"><?php if (! empty($logoPublico)) { ?><img src="<?php echo htmlspecialchars($logoPublico, ENT_QUOTES, 'UTF-8'); ?>" style="max-height: 100px" alt=""><?php } ?></td>
                                                <td>
                                                    <span style="font-size: 20px;"><?php echo htmlspecialchars((string) $emitente->nome, ENT_QUOTES, 'UTF-8'); ?></span></br>
                                                    <?php if ($emitente->cnpj != "00.000.000/0000-00") { ?><span class="icon"><i class="fas fa-fingerprint" style="margin:5px 1px"></i> <?php echo htmlspecialchars((string) $emitente->cnpj, ENT_QUOTES, 'UTF-8'); ?></span></br><?php } ?>
                                                    <span class="icon"><i class="fas fa-map-marker-alt" style="margin:4px 3px"></i> <?php echo $emitente->rua . ', ' . $emitente->numero . ', ' . $emitente->bairro . ' - ' . $emitente->cidade . ' - ' . $emitente->uf; ?></span></br>
                                                    <span class="icon"><i class="fas fa-comments" style="margin:5px 1px"></i> E-mail: <?php echo htmlspecialchars((string) $emitente->email, ENT_QUOTES, 'UTF-8') . ' - Fone: ' . htmlspecialchars((string) $emitente->telefone, ENT_QUOTES, 'UTF-8'); ?></span></br>
                                                    <span class="icon"><i class="fas fa-user-check"></i> Responsável: <?php echo $result->nome ?>
                                                </td>
                                                <td style="width: 18%; text-align: center">
                                                    <span><b>N° OS: </b><?php echo $result->idOs ?></span></br></br>
                                                    <span>Emissão: <?php echo date('d/m/Y') ?></span>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- CLIENTE e RESPONSÁVEL: existiam na tela interna e faltavam aqui.
                                 O cliente precisa conferir se a OS é dele e saber com quem falar.
                                 Todos os campos escapados: esta página não tem login. -->
                            <div style="margin-top: 0; padding-top: 0">
                                <table class="table table-condensed">
                                    <tbody>
                                        <tr>
                                            <td style="width: 60%; padding-left: 0">
                                                <h5><b>CLIENTE</b></h5>
                                                <span><i class="bx bxs-business"></i> <b><?php echo htmlspecialchars((string) $result->nomeCliente, ENT_QUOTES, 'UTF-8'); ?></b></span><br />
                                                <?php
                                                    $fones = [];
                                                    if (! empty($result->contato_cliente)) {
                                                        $fones[] = $result->contato_cliente;
                                                    }
                                                    if (! empty($result->celular_cliente) && $result->celular_cliente === $result->telefone_cliente) {
                                                        $fones[] = $result->celular_cliente;
                                                    } else {
                                                        if (! empty($result->telefone_cliente)) {
                                                            $fones[] = $result->telefone_cliente;
                                                        }
                                                        if (! empty($result->celular_cliente)) {
                                                            $fones[] = $result->celular_cliente;
                                                        }
                                                    }
                                                    if ($fones) { ?>
                                                        <span><i class="bx bxs-phone"></i> <?php echo htmlspecialchars(implode(' / ', $fones), ENT_QUOTES, 'UTF-8'); ?></span><br />
                                                <?php } ?>
                                                <?php
                                                    $endereco = implode(', ', array_filter([$result->rua, $result->numero, $result->complemento, $result->bairro]));
                                                    $cidadeUf = trim(implode(', ', array_filter([$result->cep, trim(implode('/', array_filter([$result->cidade, $result->estado])), '/')])));
                                                    if ($endereco !== '' || $cidadeUf !== '') { ?>
                                                        <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars(trim($endereco . ($endereco !== '' && $cidadeUf !== '' ? ' - ' : '') . $cidadeUf), ENT_QUOTES, 'UTF-8'); ?></span><br />
                                                <?php } ?>
                                                <?php if (! empty($result->email)) { ?>
                                                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars((string) $result->email, ENT_QUOTES, 'UTF-8'); ?></span><br />
                                                <?php } ?>
                                            </td>
                                            <td style="width: 40%; padding-left: 0">
                                                <h5><b>RESPONSÁVEL</b></h5>
                                                <span><b><i class="fas fa-user"></i> <?php echo htmlspecialchars((string) $result->nome, ENT_QUOTES, 'UTF-8'); ?></b></span><br />
                                                <?php if (! empty($result->telefone_usuario)) { ?>
                                                    <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars((string) $result->telefone_usuario, ENT_QUOTES, 'UTF-8'); ?></span><br />
                                                <?php } ?>
                                                <?php if (! empty($result->email_usuario)) { ?>
                                                    <span><i class="fas fa-envelope"></i> <?php echo htmlspecialchars((string) $result->email_usuario, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div style="margin-top: 0; padding-top: 0">
                                <table class="table table-condensed">
                                    <tbody>
                                            <?php if ($result->dataInicial != null) { ?>
                                                <tr>
                                                    <td>
                                                        <b>STATUS OS: </b><?php echo $result->status ?>
                                                    </td>

                                                    <td>
                                                        <b>DATA INICIAL: </b><?php echo date('d/m/Y', strtotime($result->dataInicial)); ?>
                                                    </td>

                                                    <td>
                                                        <b>DATA FINAL: </b><?php echo $result->dataFinal ? date('d/m/Y', strtotime($result->dataFinal)) : ''; ?>
                                                    </td>

                                                    <td>
                                                        <?php if (!empty($result->garantia)) { ?>
                                                            <b>GARANTIA: </b><?php echo htmlspecialchars((string) $result->garantia, ENT_QUOTES, 'UTF-8') . ' dia(s)'; ?>
                                                        <?php } ?>
                                                    </td>

                                                    <td>
                                                        <?php if (in_array($result->status, ['Finalizado', 'Faturado', 'Orçamento', 'Aberto'], true) && ! empty($result->garantia) && ! empty($result->dataFinal)) { ?><b> VENC. DA GARANTIA: </b><?php echo dateInterval($result->dataFinal, $result->garantia); ?><?php } ?>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                    </tbody>
                                </table>

                                <?php $this->load->view('os/resumoRecebimentos'); ?>

                                <table class="table table-condensed">
                                    <?php if ($result->descricaoProduto != null || $result->defeito != null || $result->laudoTecnico != null || $result->observacoes) { ?>
                                            <?php if ($result->descricaoProduto != null) { ?>
                                                <tr>
                                                    <td>
                                                        <strong>DESCRIÇÃO: </strong>
                                                        <div class="os-fotos-campo"><?php echo printCampoOsHtml($result->descricaoProduto) ?></div>
                                                    </td>
                                                </tr>

                                            <?php } ?>

                                            <?php if ($result->defeito != null) { ?>
                                                <tr>
                                                    <td>
                                                        <strong>DEFEITO APRESENTADO: </strong>
                                                        <div class="os-fotos-campo"><?php echo printCampoOsHtml($result->defeito) ?></div>
                                                    </td>
                                                </tr>
                                            <?php } ?>

                                            <?php if ($result->observacoes != null) { ?>
                                                <tr>
                                                    <td>
                                                        <strong>OBSERVAÇÕES: </strong>
                                                        <div class="os-fotos-campo"><?php echo printCampoOsHtml($result->observacoes) ?></div>
                                                    </td>
                                                </tr>
                                            <?php } ?>

                                            <?php if ($result->laudoTecnico != null) { ?>
                                                <tr>
                                                    <td>
                                                        <strong>LAUDO TÉCNICO: </strong>
                                                        <div class="os-fotos-campo"><?php echo printCampoOsHtml($result->laudoTecnico) ?></div>
                                                    </td>
                                                </tr>
                                            <?php } ?>

                                            <?php if (! empty($result->textoGarantia)) { ?>
                                                <tr>
                                                    <td>
                                                        <strong>TERMO DE GARANTIA </strong><br>
                                                        <?php echo printSafeHtml($result->textoGarantia) ?>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                    <?php } ?>
                                </table>

                                <?php if ($anexos != null) { ?>
                                    <table class="table table-bordered table-condensed">
                                        <thead>
                                            <tr>
                                                <th>Anexo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                            <td>
                                            <!-- Grade 2 por linha: cada célula ocupa metade da largura.
                                                 Antes era .span3 (4 por linha), que deixava as fotos
                                                 pequenas e desalinhadas quando havia vídeo na mesma fila. -->
                                            <div class="anexo-grade">
                                            <?php foreach ($anexos as $a) {
                                                // Anexos são servidos pela rota pública do controller
                                                // Publico (que valida o token), nunca pelo caminho
                                                // estático assets/anexos/... — ver Task 3 do plano.
                                                //
                                                // Caminho ROOT-RELATIVE de propósito: site_url() e
                                                // base_url() embutem a origem interna
                                                // (http://localhost:8000) e o index_page
                                                // ('index.php'), e nenhum dos dois existe para o
                                                // cliente que chega pelo túnel — a allowlist da
                                                // porta pública só conhece /publico/*. Relativo à
                                                // raiz, a URL resolve na origem que o cliente já
                                                // está usando, seja o túnel ou a rede local.
                                                $thumb = '/publico/thumb/' . rawurlencode($token) . '/' . (int) $a->idAnexos;
                                                $link = '/publico/anexo/' . rawurlencode($token) . '/' . (int) $a->idAnexos;
                                                $ehVideo = (bool) preg_match('/\.(mp4|mov|webm|ogg|m4v)$/i', $a->anexo);

                                                if ($ehVideo) {
                                                    echo '<div class="anexo-celula"><video src="' . $link . '#t=0.1" controls playsinline preload="auto"></video><p><a href="' . $link . '?download=1" download>Baixar vídeo</a></p></div>';
                                                } else {
                                                    echo '<div class="anexo-celula"><a href="' . $link . '" target="_blank"><img src="' . $thumb . '" alt=""></a></div>';
                                                }
                                            } ?>
                                            </div>
                                            </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                <?php } ?>

                                <?php $totalServico = 0;
$totalProdutos = 0; ?>
                                <?php if ($produtos != null) { ?>
                                    <br />
                                    <table class="table table-bordered table-condensed" id="tblProdutos">
                                        <thead>
                                            <tr>
                                                <th>PRODUTO</th>
                                                <th>QTD</th>
                                                <th>UNT</th>
                                                <th>SUBTOTAL</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($produtos as $p) {
                                                $totalProdutos = $totalProdutos + $p->subTotal;
                                                echo '<tr>';
                                                echo '<td>' . htmlspecialchars($p->descricao) . '</td>';
                                                echo '<td>' . htmlspecialchars($p->quantidade) . '</td>';
                                                echo '<td>R$ ' . htmlspecialchars($p->preco ?: $p->precoVenda) . '</td>';
                                                echo '<td>R$ ' . number_format($p->subTotal, 2, ',', '.') . '</td>';
                                                echo '</tr>';
                                            } ?>
                                            <tr>
                                                <td></td>
                                                <td colspan="2" style="text-align: right"><strong>TOTAL:</strong></td>
                                                <td><strong>R$ <?php echo number_format($totalProdutos, 2, ',', '.'); ?></strong>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                <?php } ?>

                                <?php if ($servicos != null) { ?>
                                    <table class="table table-bordered table-condensed">
                                        <thead>
                                            <tr>
                                                <th>SERVIÇO</th>
                                                <th>QTD</th>
                                                <th>UNT</th>
                                                <th>SUBTOTAL</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php setlocale(LC_MONETARY, 'en_US');
                                    foreach ($servicos as $s) {
                                        $totalServico = $totalServico + $s->subTotal;
                                        $preco = $s->preco ?: $s->precoVenda;
                                        $subtotal = $preco * ($s->quantidade ?: 1);
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($s->nome) . '</td>';
                                        echo '<td>' . htmlspecialchars($s->quantidade ?: 1) . '</td>';
                                        echo '<td>R$ ' . htmlspecialchars($preco) . '</td>';
                                        echo '<td>R$ ' . number_format($subtotal, 2, ',', '.') . '</td>';
                                        echo '</tr>';
                                    } ?>
                                            <tr>
                                                <td colspan="3" style="text-align: right"><strong>TOTAL:</strong></td>
                                                <td><strong>R$ <?php echo number_format($totalServico, 2, ',', '.'); ?></strong>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                <?php } ?>

                                <table class="table table-condensed">
                                    <thead>
                                        <td>
                                            <?php if ($totalProdutos != 0 || $totalServico != 0) {
                                                if ($result->valor_desconto != 0) {
                                                    echo "<h4 style='text-align: right'>SUBTOTAL: R$ " . number_format($totalProdutos + $totalServico, 2, ',', '.') . "</h4>";
                                                    echo $result->valor_desconto != 0 ? "<h4 style='text-align: right'>DESCONTO: R$ " . number_format($result->valor_desconto != 0 ? $result->valor_desconto - ($totalProdutos + $totalServico) : 0.00, 2, ',', '.') . "</h4>" : "";
                                                    echo "<h4 style='text-align: right'>TOTAL: R$ " . number_format($result->valor_desconto, 2, ',', '.') . "</h4>";
                                                } else {
                                                    echo "<h4 style='text-align: right'>TOTAL: R$ " . number_format($totalProdutos + $totalServico, 2, ',', '.') . "</h4>";
                                                }
                                            }?>
                                        </td>

                                        <?php if ($result->status == 'Finalizado' || $result->status == 'Aprovado') { ?>
                                            <?php if ($qrCode) : ?>
                                                <td style="width: 15%; padding-left: 0; text-align:center;">
                                                    <img style="margin:0px" src="/assets/img/logo_pix.png" width="48px" alt="QR Code de Pagamento" /></br>
                                                    <img style="margin:6px 0px 0px 0px" width="94px" src="<?= $qrCode ?>" alt="QR Code de Pagamento" /></br>
                                                    <?php echo '<span style="margin:0px;font-size: 80%;text-align:center;">Chave PIX: ' . $chaveFormatada . '</span>';?>
                                                </td>
                                            <?php endif ?>
                                        <?php } ?>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Camada de ampliação. Fica vazia até o primeiro clique. -->
    <div class="zoom-fundo" id="zoomFundo" role="dialog" aria-label="Imagem ampliada">
        <span class="zoom-fechar" id="zoomFechar" title="Fechar" aria-label="Fechar">&times;</span>
        <img id="zoomImg" alt="">
        <div class="zoom-dica" id="zoomDica"></div>
    </div>

    <script>
        (function () {
            'use strict';

            var fundo = document.getElementById('zoomFundo');
            var img = document.getElementById('zoomImg');
            var dica = document.getElementById('zoomDica');
            var fechar = document.getElementById('zoomFechar');
            if (!fundo || !img) { return; }

            function abrir(src) {
                // setAttribute com a URL da própria página: nunca innerHTML,
                // para não reintroduzir injeção pela descrição da OS.
                img.setAttribute('src', src);
                fundo.classList.remove('real');
                fundo.classList.add('aberto');
                document.body.style.overflow = 'hidden';
            }

            function encerrar() {
                fundo.classList.remove('aberto', 'real');
                img.removeAttribute('src');
                document.body.style.overflow = '';
            }

            function atualizarDica() {
                if (!img.naturalWidth) { dica.textContent = ''; return; }
                var real = fundo.classList.contains('real');
                var cabe = img.naturalWidth <= window.innerWidth * 0.96 &&
                           img.naturalHeight <= window.innerHeight * 0.92;
                var tam = img.naturalWidth + ' x ' + img.naturalHeight + ' px';
                if (cabe) {
                    dica.textContent = tam + ' — tamanho original. Clique fora para fechar.';
                } else if (real) {
                    dica.textContent = tam + ' — tamanho original. Clique na imagem para ajustar à tela.';
                } else {
                    dica.textContent = tam + ' — clique na imagem para ver em tamanho original.';
                }
            }

            img.addEventListener('load', atualizarDica);

            // Clique em imagem da descrição/laudo: o src já é o arquivo cheio,
            // só a exibição estava limitada pela moldura.
            document.addEventListener('click', function (ev) {
                var alvo = ev.target;
                if (!alvo || alvo.tagName !== 'IMG') { return; }
                if (alvo.closest('#zoomFundo')) { return; }

                // Anexo: a miniatura vive dentro de um <a> que aponta para o
                // arquivo original; usa o href, não a miniatura.
                var link = alvo.closest('a');
                if (link && link.getAttribute('href')) {
                    ev.preventDefault();
                    abrir(link.getAttribute('href'));
                    return;
                }

                if (alvo.closest('.table-condensed')) {
                    ev.preventDefault();
                    abrir(alvo.getAttribute('src'));
                }
            });

            // Alterna ajustada / tamanho original.
            img.addEventListener('click', function (ev) {
                ev.stopPropagation();
                fundo.classList.toggle('real');
                atualizarDica();
            });

            fundo.addEventListener('click', encerrar);
            fechar.addEventListener('click', function (ev) { ev.stopPropagation(); encerrar(); });
            document.addEventListener('keydown', function (ev) {
                if (ev.key === 'Escape' || ev.keyCode === 27) { encerrar(); }
            });
        })();
    </script>

<?php $this->load->view('os/galeriaFotos'); ?>
</body>

</html>
