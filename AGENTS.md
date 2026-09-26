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

## Subir o ambiente

    cd docker && docker compose up -d --build

- App: http://localhost:8000 (nginx + php-fpm) · phpMyAdmin: :8080 · MySQL: :8989
- `docker compose` existe **no host**, não dentro do container do agente.
- Credenciais de app e banco: consultar a página `notes/ambiente-docker.md` na
  memória. **Não versionar credenciais aqui** — este repositório é público.

## Verificar antes de dizer que está pronto

- Não há suíte de testes no projeto. O gate é: `php -l` em cada PHP alterado
  + smoke HTTP na rota afetada. Para impressão, conferir a página gerada.
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
- `.ai-memory.toml` é intencionalmente não rastreado (está em `.git/info/exclude`).

## Pendências abertas

- Dados do emitente não cadastrados: o cabeçalho da OS impressa sai em branco.

## Autoridade

Git, código, testes e decisão humana atual **vencem** qualquer narrativa de
memória ou handoff. Memória é consulta e pista, nunca fonte de verdade.
