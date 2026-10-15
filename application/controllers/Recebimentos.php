<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Recebimentos extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('form');
        $this->load->model('OsRecebimentos_model', 'recebimentos');
        $this->load->model('os_model');
        $this->data['menuOs'] = 'OS';
    }

    public function index()
    {
        redirect('os');
    }

    public function os($id = null)
    {
        if (!ctype_digit((string) $id) || !$this->os_model->getById((int) $id)) { show_404(); return; }
        if (!$this->permitido('vOs') || !$this->permitido('vLancamento')) { show_error('Sem permissão para consultar recebimentos.', 403); return; }
        $this->data['result'] = $this->os_model->getById((int) $id);
        $this->data['recebimentos'] = $this->recebimentos->resumo((int) $id);
        $this->data['chaveRecebimento'] = bin2hex(random_bytes(24));
        $this->data['podeReceber'] = $this->permitido('eOs') && $this->permitido('aLancamento') && $this->permitido('eLancamento');
        $this->data['podeEstornar'] = $this->permitido('eOs') && $this->permitido('dLancamento') && $this->permitido('eLancamento');
        $this->data['candidatos'] = $this->db->select('idLancamentos, descricao, valor, valor_desconto, baixado, data_vencimento')
            ->where('clientes_id', $this->data['result']->clientes_id)->where('tipo', 'receita')->where('os_id', null)
            ->order_by('idLancamentos', 'desc')->get('lancamentos')->result();
        $this->data['view'] = 'os/recebimentos';
        $this->layout();
    }

    private function permitido($permissao)
    {
        return $this->permission->checkPermission($this->session->userdata('permissao'), $permissao);
    }

    public function salvar($id = null)
    {
        if ($this->input->method() !== 'post') { show_error('Método não permitido.', 405); return; }
        if (!ctype_digit((string) $id) || !$this->os_model->getById((int) $id)) { show_404(); return; }
        $acao = $this->input->post('acao');
        $financeira = $acao === 'estornar' ? 'dLancamento' : 'aLancamento';
        if (!$this->permitido('eOs') || !$this->permitido('vLancamento') || !$this->permitido($financeira) || !$this->permitido('eLancamento')) {
            show_error('Sem permissão para esta operação financeira.', 403); return;
        }
        $usuario = (int) $this->session->userdata('id_admin');
        try {
            switch ($acao) {
                case 'iniciar':
                    $this->recebimentos->iniciar((int) $id, $this->input->post('vencimento'), $usuario);
                    break;
                case 'vincular':
                    if ($this->input->post('confirmar_vinculo') !== '1') { throw new DomainException('Confirme o lançamento que pertence à OS.'); }
                    $this->recebimentos->vincular((int) $id, $this->input->post('lancamento'), $usuario);
                    break;
                case 'receber':
                    $this->recebimentos->receber((int) $id, $this->input->post('valor'), $this->input->post('data_recebimento'),
                        $this->input->post('forma_pgto'), $usuario, $this->input->post('chave'));
                    break;
                case 'estornar':
                    $this->recebimentos->estornar((int) $id, $this->input->post('recebimento'), $this->input->post('motivo'), $usuario);
                    break;
                default:
                    throw new DomainException('Operação inválida.');
            }
            log_info('Recebimentos OS #' . (int) $id . ': ' . $acao);
            $this->session->set_flashdata('success', 'Operação registrada. O saldo foi atualizado.');
        } catch (DomainException $e) {
            $this->session->set_flashdata('error', $e->getMessage());
        } catch (Throwable $e) {
            log_message('error', 'Falha transacional nos recebimentos da OS #' . (int) $id);
            $this->session->set_flashdata('error', 'Não foi possível concluir. Nenhuma alteração financeira foi confirmada.');
        }
        redirect('recebimentos/os/' . (int) $id);
    }
}
