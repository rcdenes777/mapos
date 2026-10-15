<div class="widget-box">
<div class="widget-title"><h5>Recebimentos da OS nº <?= (int) $result->idOs ?></h5></div>
<div class="widget-content">
<p><?= html_escape($result->nomeCliente) ?> · <a href="<?= site_url('os/visualizar/' . (int) $result->idOs) ?>">Voltar à OS</a></p>
<?php $this->load->view('os/resumoRecebimentos'); ?>
<?php if (!$recebimentos && $podeReceber): ?>
    <?php if (!$result->faturado): ?>
        <p>Inicie o controle para registrar entradas e pagamentos posteriores. O total será fixado e a OS será faturada; produtos, serviços e descontos ficarão protegidos.</p>
        <?= form_open('recebimentos/salvar/' . (int) $result->idOs) ?>
        <input type="hidden" name="acao" value="iniciar">
        <label for="vencimento">Vencimento do saldo</label><input id="vencimento" type="date" name="vencimento" value="<?= date('Y-m-d') ?>" required>
        <button class="btn btn-primary" type="submit">Iniciar recebimentos</button></form>
    <?php else: ?>
        <div class="alert alert-info">Esta OS foi faturada antes deste controle. Confirme qual receita já existente pertence a ela. Nenhuma receita nova será criada no vínculo.</div>
        <?= form_open('recebimentos/salvar/' . (int) $result->idOs) ?>
        <input type="hidden" name="acao" value="vincular">
        <label for="lancamento">Lançamento existente</label><select id="lancamento" name="lancamento" required style="max-width:100%">
        <option value="">Selecione e confira no financeiro</option>
        <?php foreach ($candidatos as $c): ?>
        <option value="<?= (int) $c->idLancamentos ?>">#<?= (int) $c->idLancamentos ?> — <?= html_escape($c->descricao) ?> — R$ <?= number_format($c->valor_desconto ?: $c->valor, 2, ',', '.') ?> — <?= $c->baixado ? 'Recebido' : 'Pendente' ?></option>
        <?php endforeach; ?></select>
        <label><input type="checkbox" name="confirmar_vinculo" value="1" required> Confirmo que esta receita pertence à OS e representa seu valor total.</label>
        <button type="submit" class="btn btn-primary">Vincular lançamento</button></form>
    <?php endif; ?>
<?php elseif ($recebimentos): ?>
    <?php if ($podeReceber && $recebimentos['saldo'] > 0): ?>
    <h4>Registrar recebimento</h4>
    <?= form_open('recebimentos/salvar/' . (int) $result->idOs, ['id' => 'form-recebimento']) ?>
    <input type="hidden" name="acao" value="receber"><input type="hidden" name="chave" value="<?= html_escape($chaveRecebimento) ?>">
    <label for="valor-recebimento">Valor recebido (R$)</label>
    <input id="valor-recebimento" name="valor" type="number" inputmode="decimal" min="0.01" max="<?= number_format($recebimentos['saldo']/100, 2, '.', '') ?>" step="0.01" required>
    <label for="data-recebimento">Data do recebimento</label><input id="data-recebimento" name="data_recebimento" type="date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
    <label for="forma-recebimento">Forma de pagamento</label><select id="forma-recebimento" name="forma_pgto">
    <?php foreach (OsRecebimentos_model::FORMAS as $forma): ?><option><?= html_escape($forma) ?></option><?php endforeach; ?>
    </select><p><button type="submit" class="btn btn-success">Registrar recebimento</button></p></form>
    <?php endif; ?>
    <h4>Histórico completo</h4>
    <p>Estornar corrige o registro no sistema; não realiza devolução bancária. O histórico é preservado.</p>
    <?php foreach ($recebimentos['historico'] as $item): ?>
    <div style="border-top:1px solid #ddd;padding:10px 0">
        <strong>#<?= (int) $item->id ?> · R$ <?= number_format($item->valor, 2, ',', '.') ?></strong> — <?= date('d/m/Y', strtotime($item->data_recebimento)) ?> — <?= html_escape($item->forma_pgto) ?>
        <?php if ($item->estornado_em): ?>
            <p>Estornado em <?= date('d/m/Y H:i', strtotime($item->estornado_em)) ?>: <?= html_escape($item->motivo_estorno) ?></p>
        <?php elseif ($podeEstornar): ?>
            <details><summary>Estornar este recebimento</summary>
            <?= form_open('recebimentos/salvar/' . (int) $result->idOs) ?>
            <input type="hidden" name="acao" value="estornar"><input type="hidden" name="recebimento" value="<?= (int) $item->id ?>">
            <label>Motivo do estorno <input name="motivo" minlength="5" maxlength="500" required></label>
            <button type="submit" class="btn btn-warning">Confirmar estorno</button></form></details>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div></div>
