<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_create_os_recebimentos extends CI_Migration
{
    public function up()
    {
        if (!$this->db->field_exists('os_id', 'lancamentos')) {
            $this->db->query('ALTER TABLE lancamentos ADD os_id INT NULL, ADD INDEX idx_lancamento_os (os_id)');
        }
        $this->db->query("CREATE TABLE IF NOT EXISTS os_financeiro (
            os_id INT NOT NULL PRIMARY KEY,
            total DECIMAL(12,2) NOT NULL,
            vencimento DATE NOT NULL,
            pendente_id INT NULL,
            criado_em DATETIME NOT NULL,
            CONSTRAINT fk_os_financeiro_os FOREIGN KEY (os_id) REFERENCES os(idOs)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->db->query("CREATE TABLE IF NOT EXISTS os_recebimentos (
            id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            os_id INT NOT NULL,
            valor DECIMAL(12,2) NOT NULL,
            data_recebimento DATE NOT NULL,
            forma_pgto VARCHAR(45) NOT NULL,
            lancamento_id INT NOT NULL,
            usuario_id INT NOT NULL,
            criado_em DATETIME NOT NULL,
            chave VARCHAR(64) NOT NULL,
            estornado_em DATETIME NULL,
            estornado_por INT NULL,
            motivo_estorno VARCHAR(500) NULL,
            UNIQUE KEY uq_recebimento_chave (chave),
            KEY idx_recebimento_os (os_id),
            CONSTRAINT fk_recebimentos_os FOREIGN KEY (os_id) REFERENCES os(idOs)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down()
    {
        // Recebimentos são histórico financeiro: rollback automático não pode apagá-los.
        throw new RuntimeException('Reversão exige restauração planejada do backup financeiro.');
    }
}
