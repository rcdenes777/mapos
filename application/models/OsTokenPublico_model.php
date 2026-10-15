<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class OsTokenPublico_model extends CI_Model
{
    private const VALIDADE_DIAS = 190;
    private const TOKEN_BYTES = 32; // 32 bytes -> 64 caracteres hex
    private const TOKEN_TAMANHO = self::TOKEN_BYTES * 2;

    /**
     * Devolve o link público da OS, reaproveitando o token vigente quando já
     * existir um válido.
     *
     * Por que reaproveitar em vez de inserir sempre: cada inserção espalharia
     * um link permanente de 190 dias, e clicar o botão por dúvida ("será que
     * gerou?") deixaria vários links vivos para a mesma OS, sem nenhuma tela
     * para auditar ou revogar. Reaproveitando, o botão é idempotente: o
     * técnico pode clicar quantas vezes quiser e continua existindo UM link
     * por OS. Para trocar o link de propósito, revogue o atual
     * (`UPDATE os_tokens_publicos SET revogado = 1 WHERE token = '...'`) e
     * clique de novo — a próxima chamada cria um token novo.
     */
    public function gerar($osId)
    {
        $osId = (int) $osId;

        // Lock nomeado por OS: sem ele, dois cliques simultâneos passam os dois
        // pela checagem antes de qualquer INSERT e criam dois tokens vigentes —
        // exatamente o cenário de links espalhados que o reaproveitamento
        // existe para evitar. GET_LOCK é do MySQL e não depende de transação
        // (o driver do CI roda em autocommit).
        $chave = 'mapos_os_token_' . $osId;
        $this->db->query('SELECT GET_LOCK(?, 5)', [$chave]);

        try {
            $vigente = $this->tokenVigente($osId);

            if ($vigente !== null) {
                return $vigente;
            }

            $data = [
                'os_id' => $osId,
                'token' => bin2hex(random_bytes(self::TOKEN_BYTES)),
                'data_criacao' => date('Y-m-d H:i:s'),
                'data_expiracao' => date('Y-m-d H:i:s', strtotime('+' . self::VALIDADE_DIAS . ' days')),
                'revogado' => 0,
            ];

            $this->db->insert('os_tokens_publicos', $data);

            return $this->db->affected_rows() === 1 ? $data['token'] : null;
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(?)', [$chave]);
        }
    }

    /**
     * Token ainda válido (não expirado, não revogado) da OS, ou null.
     * Pega o mais recente para o caso de já existirem vários — histórico de
     * antes do reaproveitamento, ou revogação parcial.
     */
    public function tokenVigente($osId)
    {
        $this->db->where('os_id', (int) $osId);
        $this->db->where('data_expiracao >=', date('Y-m-d H:i:s'));
        $this->db->where('revogado', 0);
        $this->db->order_by('id', 'DESC');
        $this->db->limit(1);
        $row = $this->db->get('os_tokens_publicos')->row();

        return $row ? $row->token : null;
    }

    /**
     * Resolve um token para a OS correspondente, ou null se o token não
     * existir, estiver expirado ou tiver sido revogado. Não diferencia os
     * casos na resposta — mesma resposta genérica usada em
     * Mine::check_token(), para não revelar quais tokens existem.
     */
    public function resolverOsId($token)
    {
        // Comprimento exato + alfabeto hex: `ctype_xdigit` sozinho aceitaria
        // qualquer tamanho, deixando passar tentativa curta até o banco.
        if (! is_string($token)
            || strlen($token) !== self::TOKEN_TAMANHO
            || ! ctype_xdigit($token)) {
            return null;
        }

        // A coluna é utf8mb4_general_ci (case-insensitive), então 'AB…' casaria
        // com 'ab…'. Normalizar para minúsculo — a forma como bin2hex() grava —
        // mantém uma única representação aceita por token.
        $token = strtolower($token);

        $this->db->where('token', $token);
        $this->db->where('data_expiracao >=', date('Y-m-d H:i:s'));
        $this->db->where('revogado', 0);
        $this->db->limit(1);
        $row = $this->db->get('os_tokens_publicos')->row();

        return $row ? (int) $row->os_id : null;
    }
}
