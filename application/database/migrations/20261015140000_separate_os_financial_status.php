<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_separate_os_financial_status extends CI_Migration
{
    public function up()
    {
        // Apenas o andamento da OS muda; o faturamento e seus lançamentos são preservados.
        $this->db->where('status', 'Faturado')->update('os', ['status' => 'Finalizado']);
        $config = $this->db->get_where('configuracoes', ['config' => 'os_status_list'])->row();
        if ($config) {
            $status = json_decode($config->valor, true);
            if (is_array($status)) {
                $status = array_values(array_unique(array_map(static function ($item) {
                    return $item === 'Faturado' ? 'Finalizado' : $item;
                }, $status)));
                $this->db->where('config', 'os_status_list')->update('configuracoes', ['valor' => json_encode($status)]);
            }
        }
    }

    public function down()
    {
        throw new RuntimeException('Use o backup para restaurar os status anteriores sem alterar OS finalizadas posteriormente.');
    }
}
