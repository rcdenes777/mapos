<?php
$filtrosOs = ['a_receber' => 'Todas com saldo a receber', 'parcial' => 'Parcialmente recebidas',
    'sem_recebimento' => 'Ainda sem recebimento', 'quitado' => 'Quitadas', 'todas' => 'Todas'];
$queryOs = [];
foreach (['vencimento_de', 'vencimento_ate', 'cliente', 'tipo', 'status', 'periodo', 'per_page'] as $campoOs) {
    $valorOs = $this->input->get($campoOs);
    if (is_string($valorOs)) { $queryOs[$campoOs] = $valorOs; }
}
$moedaOs = static function ($valor) { return 'R$ ' . number_format((float) $valor, 2, ',', '.'); };
?>
<section class="widget-box" id="saldos-os" style="clear:both;margin:16px 0">
    <div class="widget-title"><h5>Recebimentos de OS</h5></div>
    <div class="widget-content">
        <p><strong>Quanto cada cliente já pagou e quanto falta cobrar.</strong> Saldos atuais de todos os períodos; os filtros de vencimento abaixo se aplicam somente às movimentações.</p>
        <form method="get" action="<?= site_url('financeiro/lancamentos') ?>#saldos-os">
            <?php foreach ($queryOs as $campoOs => $valorOs): ?>
                <input type="hidden" name="<?= html_escape($campoOs) ?>" value="<?= html_escape($valorOs) ?>">
            <?php endforeach; ?>
            <label for="os-situacao">Situação dos recebimentos da OS</label>
            <select id="os-situacao" name="os_situacao" style="max-width:100%">
                <?php foreach ($filtrosOs as $valorOs => $rotuloOs): ?>
                <option value="<?= $valorOs ?>" <?= $saldosOs['situacao'] === $valorOs ? 'selected' : '' ?>><?= html_escape($rotuloOs) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Mostrar OS</button>
        </form>
        <div style="display:flex;flex-wrap:wrap;gap:16px;margin:12px 0">
            <div>Total das OS selecionadas<br><strong><?= $moedaOs($saldosOs['totais']->total) ?></strong></div>
            <div>Já recebido<br><strong style="color:#276b37"><?= $moedaOs($saldosOs['totais']->recebido) ?></strong></div>
            <div>Falta receber<br><strong style="color:#9c4b00"><?= $moedaOs($saldosOs['totais']->saldo) ?></strong></div>
        </div>
        <div style="max-width:100%;overflow-x:auto">
            <table class="table table-bordered" id="tabela-saldos-os">
                <thead><tr><th>OS</th><th>Cliente</th><th>Total da OS</th><th>Já recebido</th><th>Falta receber</th><th>Vencimento do saldo</th><th>Situação</th><th>Ação</th></tr></thead>
                <tbody>
                <?php if (!$saldosOs['linhas']): ?><tr><td colspan="8">Nenhuma OS nesta situação.</td></tr><?php endif; ?>
                <?php foreach ($saldosOs['linhas'] as $linhaOs):
                    $temSaldoOs = (float) $linhaOs->saldo > 0;
                    $situacaoOs = !$temSaldoOs ? 'Quitado' : ((float) $linhaOs->recebido > 0 ? 'Parcialmente recebido' : 'A receber'); ?>
                    <tr>
                        <td><a href="<?= site_url('os/visualizar/' . (int) $linhaOs->os_id) ?>">#<?= (int) $linhaOs->os_id ?></a></td>
                        <td><?= html_escape($linhaOs->nomeCliente) ?></td>
                        <td style="white-space:nowrap"><?= $moedaOs($linhaOs->total) ?></td>
                        <td style="white-space:nowrap"><?= $moedaOs($linhaOs->recebido) ?></td>
                        <td style="white-space:nowrap;color:<?= $temSaldoOs ? '#9c4b00' : '#276b37' ?>"><strong><?= $moedaOs($linhaOs->saldo) ?></strong></td>
                        <td><?= $temSaldoOs ? date('d/m/Y', strtotime($linhaOs->vencimento)) : '—' ?></td>
                        <td><?= $situacaoOs ?></td>
                        <td><a class="btn btn-small" href="<?= site_url('recebimentos/os/' . (int) $linhaOs->os_id) ?>"><?= $temSaldoOs ? 'Registrar recebimento' : 'Ver histórico' ?></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($saldosOs['paginas'] > 1): ?>
            <p>Página <?= $saldosOs['pagina'] ?> de <?= $saldosOs['paginas'] ?>
            <?php foreach ([-1 => 'Anterior', 1 => 'Próxima'] as $passoOs => $rotuloOs):
                $paginaOs = $saldosOs['pagina'] + $passoOs;
                if ($paginaOs < 1 || $paginaOs > $saldosOs['paginas']) { continue; }
                $urlOs = site_url('financeiro/lancamentos') . '?' . http_build_query(array_merge($queryOs,
                    ['os_situacao' => $saldosOs['situacao'], 'os_pagina' => $paginaOs])) . '#saldos-os'; ?>
                <a class="btn btn-small" href="<?= html_escape($urlOs) ?>"><?= $rotuloOs ?></a>
            <?php endforeach; ?></p>
        <?php endif; ?>
        <p style="font-size:90%">OS antigas ainda sem vínculo financeiro precisam ser conferidas em <a href="<?= site_url('os') ?>">Ordens de Serviço → Recebimentos</a>.</p>
    </div>
</section>
