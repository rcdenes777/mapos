<?php
if (!isset($recebimentos)) {
    $ciRecebimentos = &get_instance();
    $ciRecebimentos->load->model('OsRecebimentos_model');
    $recebimentos = $ciRecebimentos->OsRecebimentos_model->resumo((int) $result->idOs);
}
if ($recebimentos): ?>
<section class="os-resumo-recebimentos" style="border:1px solid #ddd;padding:12px;margin:12px 0;break-inside:avoid">
    <h4>Recebimentos — <?= $recebimentos['saldo'] === 0 ? 'Quitada' : ($recebimentos['recebido'] > 0 ? 'Parcialmente recebida' : 'Pendente') ?></h4>
    <p>Total: <strong>R$ <?= number_format($recebimentos['total'] / 100, 2, ',', '.') ?></strong> ·
       Recebido: <strong>R$ <?= number_format($recebimentos['recebido'] / 100, 2, ',', '.') ?></strong> ·
       Saldo: <strong>R$ <?= number_format($recebimentos['saldo'] / 100, 2, ',', '.') ?></strong></p>
    <p style="font-size:90%">Valores líquidos, já considerando o desconto da OS.</p>
    <?php foreach ($recebimentos['historico'] as $item): if ($item->estornado_em) { continue; } ?>
        <p><?= date('d/m/Y', strtotime($item->data_recebimento)) ?> — R$ <?= number_format($item->valor, 2, ',', '.') ?> — <?= html_escape($item->forma_pgto) ?></p>
    <?php endforeach; ?>
</section>
<?php endif; ?>
