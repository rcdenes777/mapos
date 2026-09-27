<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_create_os_tokens_publicos extends CI_Migration
{
    public function up()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `os_tokens_publicos` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `os_id` INT NOT NULL,
            `token` VARCHAR(64) NOT NULL,
            `data_criacao` DATETIME NOT NULL,
            `data_expiracao` DATETIME NOT NULL,
            `revogado` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_token` (`token`),
            KEY `idx_os_id` (`os_id`)
        ) ENGINE = InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;");
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS `os_tokens_publicos`;');
    }
}
