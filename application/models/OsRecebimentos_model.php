<?php

defined('BASEPATH') or exit('No direct script access allowed');

class OsRecebimentos_model extends CI_Model
{
    public const FORMAS = ['Dinheiro', 'Pix', 'Cartão de Crédito', 'Débito', 'Boleto', 'Depósito', 'Cheque'];

    public static function centavos($valor)
    {
        $valor = str_replace(',', '.', trim((string) $valor));
        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $valor)) {
            throw new DomainException('Informe um valor válido, com até duas casas decimais.');
        }
        $partes = explode('.', $valor);
        return (int) $partes[0] * 100 + (int) str_pad($partes[1] ?? '', 2, '0');
    }

    private static function dinheiro($centavos)
    {
        return sprintf('%d.%02d', intdiv($centavos, 100), $centavos % 100);
    }

    public static function dataValida($data)
    {
        $obj = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $data);
        if (!$obj || $obj->format('Y-m-d') !== $data) {
            throw new DomainException('Data inválida.');
        }
        return $data;
    }

    public function resumo($osId)
    {
        $controle = $this->db->get_where('os_financeiro', ['os_id' => (int) $osId])->row();
        if (!$controle) {
            return null;
        }
        $historico = $this->db->order_by('data_recebimento')->order_by('id')->get_where('os_recebimentos', ['os_id' => (int) $osId])->result();
        $pago = 0;
        foreach ($historico as $item) {
            if (!$item->estornado_em) {
                $pago += self::centavos($item->valor);
            }
        }
        $total = self::centavos($controle->total);
        return ['total' => $total, 'recebido' => $pago, 'saldo' => $total - $pago,
            'historico' => $historico, 'controle' => $controle];
    }

    public function listarSaldos($situacao = 'a_receber', $pagina = 1, $porPagina = 10)
    {
        $filtros = [
            'a_receber' => 'saldo > 0',
            'parcial' => 'saldo > 0 AND recebido > 0',
            'sem_recebimento' => 'saldo > 0 AND recebido = 0',
            'quitado' => 'saldo = 0',
            'todas' => '1 = 1',
        ];
        if (!isset($filtros[$situacao])) { $situacao = 'a_receber'; }
        $base = 'SELECT f.os_id, c.nomeCliente, f.total, f.vencimento,
            COALESCE(r.recebido, 0) recebido, f.total - COALESCE(r.recebido, 0) saldo
            FROM os_financeiro f
            JOIN os o ON o.idOs = f.os_id
            JOIN clientes c ON c.idClientes = o.clientes_id
            LEFT JOIN (SELECT os_id, SUM(valor) recebido FROM os_recebimentos
                WHERE estornado_em IS NULL GROUP BY os_id) r ON r.os_id = f.os_id';
        // Soma por OS antes da paginação, sem multiplicar parcelas por produtos/serviços.
        $origem = '(' . $base . ') resumo WHERE ' . $filtros[$situacao];
        $totais = $this->db->query('SELECT COUNT(*) quantidade, COALESCE(SUM(total), 0) total,
            COALESCE(SUM(recebido), 0) recebido, COALESCE(SUM(saldo), 0) saldo FROM ' . $origem)->row();
        $porPagina = max(1, min(50, (int) $porPagina));
        $paginas = max(1, (int) ceil($totais->quantidade / $porPagina));
        $pagina = max(1, min($paginas, (int) $pagina));
        $linhas = $this->db->query('SELECT * FROM ' . $origem . ' ORDER BY saldo DESC, vencimento, os_id DESC LIMIT ? OFFSET ?',
            [$porPagina, ($pagina - 1) * $porPagina])->result();
        return ['linhas' => $linhas, 'totais' => $totais, 'situacao' => $situacao, 'pagina' => $pagina, 'paginas' => $paginas];
    }

    private function transacao($osId, callable $operacao)
    {
        $this->load->model('os_model');
        if (!$this->os_model->bloquearOs($osId)) {
            throw new DomainException('Esta OS está sendo alterada em outra operação. Tente novamente.');
        }
        $this->db->trans_begin();
        try {
            $os = $this->db->query('SELECT * FROM os WHERE idOs = ? FOR UPDATE', [(int) $osId])->row();
            if (!$os || strtolower($os->status) === 'cancelado') {
                throw new DomainException('OS inexistente ou cancelada.');
            }
            $resultado = $operacao($os);
            if (!$this->db->trans_status()) {
                throw new RuntimeException('Não foi possível salvar a operação financeira.');
            }
            $this->db->trans_commit();
            return $resultado;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
    }

    private function totalOs($os)
    {
        $this->load->model('os_model');
        $totais = $this->os_model->valorTotalOS($os->idOs);
        $bruto = (int) round(($totais['totalServico'] + $totais['totalProdutos']) * 100);
        if ($os->tipo_desconto && (float) $os->desconto > 0) {
            $total = (int) round((float) $os->valor_desconto * 100);
        } else {
            $total = $bruto;
        }
        if ($total <= 0 || $total > 9999999999) {
            throw new DomainException('A OS precisa ter um total positivo e válido.');
        }
        return [$bruto, $total];
    }

    private function novoLancamento($os, $valor, $data, $pago, $forma, $usuario)
    {
        $cliente = $this->db->get_where('clientes', ['idClientes' => $os->clientes_id])->row();
        $this->db->insert('lancamentos', [
            'os_id' => $os->idOs, 'descricao' => 'Fatura de OS - #' . $os->idOs . ($pago ? ' - Recebimento' : ' - Saldo pendente'),
            'valor' => self::dinheiro($valor), 'desconto' => 0, 'tipo_desconto' => 'real',
            'valor_desconto' => self::dinheiro($valor), 'clientes_id' => $os->clientes_id,
            'cliente_fornecedor' => $cliente->nomeCliente, 'tipo' => 'receita',
            'data_vencimento' => $data, 'data_pagamento' => $pago ? $data : null,
            'baixado' => $pago ? 1 : 0, 'forma_pgto' => $forma, 'usuarios_id' => $usuario,
        ]);
        return (int) $this->db->insert_id();
    }

    public function iniciar($osId, $vencimento, $usuario, $pago = false, $data = null, $forma = 'Dinheiro')
    {
        self::dataValida($vencimento);
        if ($pago) {
            self::dataValida($data);
            if ($data > date('Y-m-d')) { throw new DomainException('Recebimento não pode ter data futura.'); }
            if (!in_array($forma, self::FORMAS, true)) { throw new DomainException('Forma de pagamento inválida.'); }
        }
        return $this->transacao($osId, function ($os) use ($vencimento, $usuario, $pago, $data, $forma) {
            if ($this->resumo($os->idOs)) { return; }
            if ((int) $os->faturado) {
                throw new DomainException('Esta OS já foi faturada. Vincule o lançamento anterior para evitar duplicidade.');
            }
            if ($this->db->get_where('cobrancas', ['os_id' => $os->idOs])->num_rows()) {
                throw new DomainException('Existe uma cobrança integrada nesta OS. Resolva-a antes de iniciar recebimentos manuais.');
            }
            [$bruto, $total] = $this->totalOs($os);
            $id = $this->novoLancamento($os, $total, $pago ? $data : $vencimento, $pago, $forma, $usuario);
            $this->db->insert('os_financeiro', ['os_id' => $os->idOs, 'total' => self::dinheiro($total),
                'vencimento' => $vencimento, 'pendente_id' => $pago ? null : $id, 'criado_em' => date('Y-m-d H:i:s')]);
            if ($pago) { $this->registrarHistorico($os->idOs, $total, $data, $forma, $id, $usuario, bin2hex(random_bytes(24))); }
            $this->db->where('idOs', $os->idOs)->update('os', ['faturado' => 1,
                'valorTotal' => self::dinheiro($bruto), 'valor_desconto' => self::dinheiro($total),
                'lancamento' => $pago ? null : $id]);
        });
    }

    public function vincular($osId, $lancamentoId, $usuario)
    {
        return $this->transacao($osId, function ($os) use ($lancamentoId, $usuario) {
            if ($this->resumo($os->idOs)) { throw new DomainException('A OS já possui controle de recebimentos.'); }
            if (!(int) $os->faturado) { throw new DomainException('Use iniciar recebimentos para uma OS ainda não faturada.'); }
            $l = $this->db->query('SELECT * FROM lancamentos WHERE idLancamentos = ? FOR UPDATE', [(int) $lancamentoId])->row();
            if (($os->lancamento && (int) $os->lancamento !== (int) $lancamentoId)
                || $this->db->where('lancamento', (int) $lancamentoId)->where('idOs !=', $os->idOs)->count_all_results('os')) {
                throw new DomainException('Esta receita já pertence a outra OS ou difere do lançamento original desta OS.');
            }
            $total = self::centavos((float) $os->valor_desconto > 0 ? $os->valor_desconto : $os->valorTotal);
            if ($total <= 0) { throw new DomainException('Total anterior da OS inválido. Confira o faturamento.'); }
            if (!$l || $l->os_id || $l->tipo !== 'receita' || (int) $l->clientes_id !== (int) $os->clientes_id
                || $this->db->get_where('vendas', ['lancamentos_id' => (int) $lancamentoId])->num_rows()
                || self::centavos((float) $l->valor_desconto > 0 ? $l->valor_desconto : $l->valor) !== $total) {
                throw new DomainException('Escolha uma receita não vinculada, do mesmo cliente e com o total exato da OS.');
            }
            $this->db->where('idLancamentos', $l->idLancamentos)->update('lancamentos', ['os_id' => $os->idOs]);
            $this->db->insert('os_financeiro', ['os_id' => $os->idOs, 'total' => self::dinheiro($total),
                'vencimento' => $l->data_vencimento, 'pendente_id' => $l->baixado ? null : $l->idLancamentos, 'criado_em' => date('Y-m-d H:i:s')]);
            $this->db->where('idOs', $os->idOs)->update('os', ['lancamento' => $l->baixado ? null : $l->idLancamentos]);
            if ($l->baixado) { $this->registrarHistorico($os->idOs, $total, $l->data_pagamento ?: $l->data_vencimento,
                $l->forma_pgto ?: 'Dinheiro', $l->idLancamentos, $usuario, bin2hex(random_bytes(24))); }
        });
    }

    private function registrarHistorico($osId, $valor, $data, $forma, $lancamento, $usuario, $chave)
    {
        $this->db->insert('os_recebimentos', ['os_id' => $osId, 'valor' => self::dinheiro($valor),
            'data_recebimento' => $data, 'forma_pgto' => $forma, 'lancamento_id' => $lancamento,
            'usuario_id' => $usuario, 'chave' => $chave, 'criado_em' => date('Y-m-d H:i:s')]);
        return (int) $this->db->insert_id();
    }

    private function conferirSaldo($resumo)
    {
        $id = $resumo['controle']->pendente_id;
        if ($resumo['saldo'] > 0) {
            $l = $this->db->query('SELECT * FROM lancamentos WHERE idLancamentos = ? FOR UPDATE', [$id])->row();
            if (!$l || $l->baixado || (int) $l->os_id !== (int) $resumo['controle']->os_id
                || self::centavos((float) $l->valor_desconto > 0 ? $l->valor_desconto : $l->valor) !== $resumo['saldo']) {
                throw new DomainException('O financeiro diverge do saldo da OS. A operação foi interrompida.');
            }
        } elseif ($id || $resumo['saldo'] < 0) {
            throw new DomainException('Saldo financeiro inconsistente.');
        }
    }

    public function receber($osId, $valor, $data, $forma, $usuario, $chave)
    {
        $valor = self::centavos($valor);
        self::dataValida($data);
        if ($data > date('Y-m-d')) { throw new DomainException('Recebimentos efetivos não podem ter data futura.'); }
        if (!in_array($forma, self::FORMAS, true) || !preg_match('/^[a-f0-9]{48}$/D', (string) $chave)) {
            throw new DomainException('Forma de pagamento ou identificação inválida.');
        }
        return $this->transacao($osId, function ($os) use ($valor, $data, $forma, $usuario, $chave) {
            $anterior = $this->db->get_where('os_recebimentos', ['chave' => $chave])->row();
            if ($anterior) {
                if ((int) $anterior->os_id === (int) $os->idOs && self::centavos($anterior->valor) === $valor
                    && $anterior->data_recebimento === $data && $anterior->forma_pgto === $forma) { return $anterior->id; }
                throw new DomainException('Identificação de recebimento já utilizada. Reabra a tela.');
            }
            $r = $this->resumo($os->idOs);
            if (!$r) { throw new DomainException('Inicie o controle de recebimentos primeiro.'); }
            $this->conferirSaldo($r);
            if ($valor <= 0 || $valor > $r['saldo']) { throw new DomainException('O recebimento deve ser positivo e não pode ultrapassar o saldo.'); }
            $id = $this->novoLancamento($os, $valor, $data, true, $forma, $usuario);
            $restante = $r['saldo'] - $valor;
            $pendente = $r['controle']->pendente_id;
            if ($restante) {
                $this->db->where('idLancamentos', $pendente)->update('lancamentos', ['valor' => self::dinheiro($restante),
                    'desconto' => 0, 'valor_desconto' => self::dinheiro($restante), 'tipo_desconto' => 'real']);
            } else {
                $this->db->where('idOs', $os->idOs)->update('os', ['lancamento' => null]);
                $this->db->where('os_id', $os->idOs)->update('os_financeiro', ['pendente_id' => null]);
                $this->db->where('idLancamentos', $pendente)->delete('lancamentos');
            }
            return $this->registrarHistorico($os->idOs, $valor, $data, $forma, $id, $usuario, $chave);
        });
    }

    public function estornar($osId, $recebimentoId, $motivo, $usuario)
    {
        $motivo = trim((string) $motivo);
        if (mb_strlen($motivo) < 5 || mb_strlen($motivo) > 500) { throw new DomainException('Informe o motivo do estorno (5 a 500 caracteres).'); }
        return $this->transacao($osId, function ($os) use ($recebimentoId, $motivo, $usuario) {
            $r = $this->resumo($os->idOs);
            if (!$r) { throw new DomainException('Controle de recebimentos não encontrado.'); }
            $this->conferirSaldo($r);
            $item = $this->db->get_where('os_recebimentos', ['id' => (int) $recebimentoId, 'os_id' => $os->idOs])->row();
            if (!$item) { throw new DomainException('Recebimento não encontrado nesta OS.'); }
            if ($item->estornado_em) { return; }
            $l = $this->db->query('SELECT * FROM lancamentos WHERE idLancamentos = ? FOR UPDATE', [$item->lancamento_id])->row();
            if (!$l || !$l->baixado || (int) $l->os_id !== (int) $os->idOs
                || self::centavos((float) $l->valor_desconto > 0 ? $l->valor_desconto : $l->valor) !== self::centavos($item->valor)) {
                throw new DomainException('Lançamento divergente. Estorno interrompido.');
            }
            $saldo = $r['saldo'] + self::centavos($item->valor);
            $pendente = $r['controle']->pendente_id;
            if ($pendente) {
                $this->db->where('idLancamentos', $pendente)->update('lancamentos', ['valor' => self::dinheiro($saldo), 'desconto' => 0,
                    'valor_desconto' => self::dinheiro($saldo), 'tipo_desconto' => 'real']);
            } else {
                $pendente = $this->novoLancamento($os, $saldo, $r['controle']->vencimento, false, 'Dinheiro', $usuario);
                $this->db->where('os_id', $os->idOs)->update('os_financeiro', ['pendente_id' => $pendente]);
                $this->db->where('idOs', $os->idOs)->update('os', ['lancamento' => $pendente]);
            }
            $this->db->where('id', $item->id)->update('os_recebimentos', ['estornado_em' => date('Y-m-d H:i:s'),
                'estornado_por' => $usuario, 'motivo_estorno' => $motivo]);
            $this->db->where('idLancamentos', $item->lancamento_id)->delete('lancamentos');
        });
    }
}
