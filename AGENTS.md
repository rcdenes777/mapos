# MapOS — Sistema de Controle de Ordens de Serviço

> Bloco de estado para agentes. Mantenha curto: isto é lido a cada turno.
> Detalhe fica na memória do agente (ai-memory), não aqui.

## Identidade

- Repositório canônico: `/home/hulk/Documentos/Labsoftwares/MapOs`
- Branch: `master` — remoto: `https://github.com/rcdenes777/mapos`
- Memória do agente (ai-memory): workspace `labsoftwares`, project `MapOs`.
  Passe os dois **explícitos** em toda chamada — o cliente MCP é estático e não
  deriva o escopo sozinho. A captura automática segue o `.ai-memory.toml`, que
  declara os dois, e só ocorre com a sessão aberta dentro do projeto; no servidor
  existe um único escopo `labsoftwares/MapOs`.

## Estado atual (27/09/2026)

- **10 commits locais em `master`, NÃO publicados** (base `c46e2ac`). Fase 1 do
  link público de OS por token: pronta, revisada por 3 modelos, verificada em
  runtime. Árvore limpa. Detalhe na memória:
  `decisions/fase1-link-publico-os-implementada.md`.
- nginx tem **duas portas**: 8000 interna (uso diário) e **8010 pública**
  (allowlist, só `/publico/*`, escuta em 127.0.0.1) — é a 8010 que o túnel
  exporá. Nada exposto à internet ainda.
- `docker/.env` é configuração local privada, ignorada pelo Git. Use
  `docker/.env.example` como modelo sem senhas. Não adicione credenciais aos
  commits. O histórico remoto antigo ainda contém a configuração anterior.

## Subir o ambiente

    cd docker && docker compose up -d --build

- App: http://localhost:8000 (nginx + php-fpm) · phpMyAdmin: :8080 · MySQL: :8989
- Porta pública (allowlist): http://127.0.0.1:8010 — só `/publico/*` responde.
- `docker compose` existe **no host**, não dentro do container do agente.
- Credenciais de app e banco: consultar a página `notes/ambiente-docker.md` na
  memória. **Não versionar credenciais aqui** — este repositório é público.

## Verificar antes de dizer que está pronto

- Não há suíte de testes no projeto. O gate é: `php -l` em cada PHP alterado
  + smoke HTTP na rota afetada. Para impressão, conferir a página gerada.
- Mudança que afeta a área pública: exercitar **as duas portas** (8000 e 8010) e
  requisitar a URL que a **página emite**, não a que você digitou — foi assim que
  um 404 pelo túnel passou por duas revisões.
- Composer usa `"vendor-dir": "application/vendor"` — as dependências **não**
  ficam em `vendor/`.

## Armadilhas já pagas

- Campos monetários usam o plugin **jQuery maskMoney**: preencher pela API
  (`jQuery(el).maskMoney('mask', 250.00)`), nunca por `value = ...` nem
  simulando teclas, ou o valor é gravado 100x maior.
- Formulários usam jQuery Validate: `form.submit()` direto é ignorado; o certo
  é clicar no botão real (`#btnContinuar`, `#btn-acessar`, `#btnAdicionarProduto`).
- Campo CEP dispara lookup e **limpa** rua/bairro/cidade/estado quando o
  serviço não responde.
- POST de login responde **403** sem o campo `MAPOS_CSRF_TOKEN`.
- Anexos que não são imagem não têm miniatura no banco — o vídeo usa a própria
  tag `<video preload="metadata">`; o host **não tem ffmpeg**.
- `editarOs.php` e `visualizarOs.php` carregam **sweetalert diferentes**:
  a primeira tem `Swal.fire`, a segunda só `swal()` (`Swal` é `undefined`).
  Copiar JS entre as duas faz o clique falhar **sem erro visível**.
- `assets/css/custom.css` é **compartilhado** entre painel e página pública:
  mudar a moldura `.table-condensed td img` (340×250) altera as duas telas.
- Estáticos vêm com `expires max`: ao conferir mudança de CSS/JS é preciso
  **Ctrl+F5**, senão o navegador serve a versão antiga.
- `.ai-memory.toml` é intencionalmente não rastreado (está em `.git/info/exclude`).

## Pendências abertas

- Dados do emitente não cadastrados: o cabeçalho da OS impressa sai em branco e
  a mensagem de WhatsApp termina em "Atenciosamente, .".
- `cloudflared` não instalado, túnel não criado — exige o Humano (serviço
  permanente + autenticação na conta Cloudflare).
- Sem flag "publicar anexo": **todo** anexo da OS vai ao cliente pelo link,
  inclusive PDF/DOCX interno. Decisão do dono pendente.
- Vídeo na página pública não tem `Range`/seek (Fase 3).

## Autoridade

Git, código, testes e decisão humana atual **vencem** qualquer narrativa de
memória ou handoff. Memória é consulta e pista, nunca fonte de verdade.
