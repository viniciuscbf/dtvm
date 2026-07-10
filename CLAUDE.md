# CLAUDE.md — mapa técnico do projeto DTVM

> Orientação para o Claude (auto-carregada no início da sessão). Foco no que **não** é óbvio
> lendo um arquivo só: convenções, invariantes, como rodar/testar e onde cada coisa mora.
> Docs de negócio ficam nos `.md` da raiz e nos pacotes `custodiante/` e `administrador_fiduciario/`.
> **Mantenha este arquivo atualizado quando a arquitetura mudar.**

## O que é
Projeto para ajudar uma instituição pequena (art. 4º, já autorizada BCB) a constituir área de
**custódia + administração fiduciária** (DTVM). Dois entregáveis:
1. **Pacotes regulatórios** (docs) para pedir licença na CVM — `custodiante/` (Res. CVM 32) e `administrador_fiduciario/` (Res. CVM 21/175).
2. **Piloto** (`piloto/`) — réplica funcional (simulação) que comprova estrutura tecnológica/operacional.

Escopo **fora** (não implementar sem pedido): FIDC, FII. Sempre manter as **notas honestas** ("no piloto… é simulado") nas telas — o usuário preza por honestidade sobre o que é real vs. simulado.

## Stack e como rodar/testar (Windows + XAMPP)
- **PHP 8** (`C:\xampp\php\php.exe`) + **MariaDB/MySQL** (`C:\xampp\mysql\bin\mysql.exe`, user `root`, senha vazia).
- Banco: **`administradora`** (config em `piloto/config/db.php`).
- **Lint:** `C:/xampp/php/php.exe -l <arquivo>` (rode em todo arquivo tocado).
- **Servidor de teste:** `C:/xampp/php/php.exe -S 127.0.0.1:8099 -t "C:/Users/vinic/OneDrive/Desktop/codes/dtvm/piloto"` (background).
- **Reset de fábrica** (rode depois de qualquer teste que escreva no banco):
  `mysql -uroot administradora < piloto/sql/schema.sql` e depois `< piloto/sql/seed.sql`.
- **Smoke web:** login via cookie-jar (`curl -c/-b`), depois GET das páginas checando `http=200` e 0 ocorrências de `Fatal error|Parse error|Warning|Undefined`.
- **Armadilha de teste (não é bug do app):** o `curl` do Git Bash no Windows **corrompe argumentos acentuados** em `--data-urlencode` (ex.: "Ações"→"A��es") e o **cliente mysql** mostra acentos quebrados na saída. Para validar caminhos com acento, teste via **PHP CLI** (UTF-8 real) ou use nomes ASCII. Um navegador real envia UTF-8 correto. (`regulamento.php` faz `mb_internal_encoding('UTF-8')` e usa `mb_*` com encoding explícito por causa disso.)

### Credenciais demo (senha `demo123`, exceto simulador)
- admin: `admin@administradora.com.br`
- gestor principal: `gestor@auroracapital.com.br` (fundos 1 e 9) · outros: `gestor@horizonteinvest.com.br`, `gestor@atlascapital.com.br`, `gestor@novafronteira.com.br`
- **funcionário sem fundo** (demo de convite/equipe): `analista@auroracapital.com.br`
- custódia: `custodia@bancoparceiro.com.br`
- cotista (token, fundo 1): `3f2a1c9e-8b47-4d10-9e2f-6a5d4c3b2a19`
- Simulator Master (2º site, `piloto/simulador/`): senha `god123` (god-mode, separada dos portais demo123)

## Estrutura
```
dtvm/
├─ custodiante/ · administrador_fiduciario/   # pacotes regulatórios (docs)
├─ guia_*.md, relatorio_*.md                  # docs de negócio/gaps/auditoria
└─ piloto/
   ├─ config/db.php                           # conexão PDO
   ├─ includes/                               # TODA a lógica (ver abaixo)
   ├─ admin/ (24) gestor/ (24) cotista/ (4) custodia/ (6) simulador/ (3)   # portais
   ├─ api/                                     # endpoints JSON (alertas, carteira_export, cota_historico, relatorio)
   ├─ sql/schema.sql · seed.sql               # reset de fábrica
   └─ assets/ · index.php · logout.php
```

## Convenções e invariantes (LEIA antes de editar)
- **Cadeia de carregamento:** toda página faz `define('BASE_URL','../'); require config/db.php; require includes/auth.php; require includes/layout.php`. `layout.php`→`helpers.php`→`dominio.php`. No **fim** de `dominio.php` há `require` de: `marcacao.php`, `derivativos.php`, `fip.php`, `equipe.php`, `batch.php`, `regulamento.php`, `passivo.php`, `contabilidade.php`, `templates_docs.php`, `contrapartes.php`.
- **`ensure_*` (DDL lazy):** tabelas/colunas são criadas sob demanda por funções `ensure_*`; `ensure_dominio($pdo)` chama todas. **DDL (CREATE/ALTER) tem que ficar FORA de `com_transacao`** — MySQL faz commit implícito e quebra a transação. Sempre `ALTER TABLE … ADD COLUMN IF NOT EXISTS …` dentro de `try/catch`.
- **Toda tabela nova precisa entrar na lista `DROP TABLE` do `schema.sql`** (senão o reset deixa lixo entre versões).
- **Transações:** `com_transacao($pdo, function() use(...) { … })`. **Idempotência:** `nonce_campo()`/`nonce_valido()`. **CSRF:** `csrf_campo()`/`csrf_validar()` (campo `name="csrf"`; nonce `name="nonce"`).
- **Versionamento por data:** `caixa_na_data`, `total_cotas_na_data`, `calcular_cota($pdo,$fundo,$data)` — cota/PL de qualquer data derivam do histórico. `calcular_cota` bifurca para FIP (usa `valor_participacoes_fip`).
- **Estilo:** comentários em pt-BR, Bootstrap 5, `e_html()` sempre no output. Combine com o código ao redor.

## Onde cada coisa mora (`includes/`)
- `auth.php` — sessão, login, `exigir_perfil`, `fundos_do_usuario`/`fundo_do_usuario`, token do cotista (`exigir_token`). `fundos_do_usuario` lê `fundo_membros` (Ativo) com **fallback** para `usuario_fundos` e `usuarios.fundo_id`.
- `seguranca.php` — CSRF, headers, cookie de sessão, timeout.
- `dominio.php` — infra central: `com_transacao`, nonce, dia útil/feriados, provisão de despesas, `caixa_na_data`, `total_cotas_na_data`, e a maioria dos `ensure_*` (catálogo, tickets, KYC, subclasses, tipos de fundo, posição custodiante, preços).
- `helpers.php` — `calcular_cota`, `carteira`, badges, formatação, `fundos_do_usuario` helpers.
- `marcacao.php` — marcação de RF por **indexador** (% CDI, CDI+spread, IPCA+, pré), cota de fundo pelo master, RV; fontes ANBIMA/B3/Comitê.
- `derivativos.php` — DI1/DAP com **ajuste diário** liquidado no caixa.
- `fip.php` — Private Equity: LPs, chamadas de capital, participações, laudo (valor justo nível 3), waterfall, distribuições.
- `passivo.php` — passivo do cotista, come-cotas/IR/IOF (Lei 14.754).
- `contabilidade.php` — partidas dobradas, diário/razão, **balancete (Ativo = Passivo + PL)**.
- `equipe.php` — **modelo de time do gestor**: `fundo_membros` (papel principal/membro, status, `permissoes` JSON), `account_id`, convites (aceitar/recusar), `transferir_principal`, permissões (`perms_no_fundo`/`eh_principal`/`pode`/`exigir_permissao`), **reset de senha simulado** (`criar_reset_senha`/`redefinir_senha`). `membership()` **materializa `ensure_equipe` uma vez por request** (migra vínculos legados → funciona logo após reset).
- `batch.php` — **fechamento resiliente por fundo**: `processar_fundos()` (isolado por fundo, idempotente, trava de validação), catálogo de erros explicados, **consolidação de erros iguais**; grava em `processamento_batch`. Página: `admin/batch.php`.
- `templates_docs.php` — **gerador de minutas/modelos** (docs do FUNDO Res. 175 preenchidos + docs da GESTORA Res. 21/50 como esqueleto). `tpl_gerador_por_nome($nome)` mapeia nome→gerador (fundo: regulamento, política de invest., termo, lâmina, custódia, auditoria, distribuição; gestora: contrato social, formulário de referência Anexo E, políticas de risco/PLD/ética/rateio/investimentos pessoais/continuidade). **Export .docx sem lib**: `docx_from_html` (DOMDocument→WordML) + `docx_zip_store` (ZIP "store" em PHP puro, dispensa ext-zip) + `enviar_documento_download` (fallback .doc). Download em `gestor/documento_ver.php` (minutas por fundo) e `gestor/template_modelo.php` (modelo do formulário, público). Modelos calibrados contra documentos reais (validação por pesquisa). **Realce âmbar** (`tpl_realcar_campos`): notas de orientação (`<em>`) e campos `[entre colchetes]` saem em amarelo/âmbar (docx via `<w:color>`, HTML via CSS), sinalizando o que preencher/remover. Trilha guiada pública **`gestor/constituir_gestora.php`** (passo a passo da autorização CVM com estimativas de prazo + referências oficiais + download dos modelos da gestora), linkada de `index.php` e `cadastro.php`.
- `contrapartes.php` — **cadastro/habilitação de contrapartes** ("conheça sua contraparte"). `ensure_contrapartes` cria a tabela `contrapartes` + colunas `boletas.contraparte_id`/`corretora_executora_id` e **auto-semeia** uma lista de referência quando vazia (como `ensure_catalogo`). Distinção-chave: **bolsa** (`cp_eh_bolsa`: Ação/ETF/Derivativo) NÃO tem contraparte bilateral — a Câmara B3 é a **contraparte central (CCP)** e o que se registra é a **corretora executora**; **balcão** (RF privada/OTC) tem contraparte nominal com KYC/PLD (Res. CVM 50), rating e **limite de crédito** (`cp_exposicao` vs `limite_credito`), aprovada pela administradora. `cp_camara_por_tipo` (SELIC/B3 Balcão/CCP). A boleta (`gestor/boletas.php`) **bifurca**: bolsa→corretora executora + nota CCP; balcão→contraparte aprovada + trava de limite. Cadastro/aprovação em `admin/contrapartes.php`; a custódia continua lendo o snapshot textual `boletas.contraparte`.
- `regulamento.php` — **gerador de regulamento dirigido por schema** (fundo/classe/subclasse). Tipos em `reg_tipos()` (FIF_RF/FIF_ACOES/FIF_MULTI/FIF_CAMBIAL/FIP). `reg_schema($tipo)` = seções/campos (com condicionais `se`); `reg_validar` (obrigatórios/faixas/nome); `reg_nome_tokens`/`reg_sugerir_nome` (sufixos obrigatórios: classe, "Crédito Privado", "Responsabilidade Limitada", qualificações RF, categoria FIP); `reg_render_form`+`reg_form_js` (form condicional); `reg_gerar_html` (documento em 3 camadas: Parte Geral → Anexo da Classe → [Suplemento da subclasse]); `reg_coletar` (lê `$_POST`). Calibrado contra 15 regulamentos reais + Res. 175 Anexo I/IV (cláusulas verbatim: não-solidariedade, insolvência art. 1.368-D, FGC, base 252, multa 0,5%/dia, waterfall FIP). Persiste em `fundos.reg_*`, tabela `classes`, `subclasses.reg_html`. Páginas: `gestor/novo_fundo.php`, `admin/classes.php`, `admin/subclasses.php` (suplemento), viewer `admin/regulamento_ver.php`. **Documentos são minutas — precisam de revisão jurídica** (dito no rodapé gerado).
- `simulador.php` — "passar de dia" do Simulator Master (marca ativos, ajusta derivativos, gera cotas). `layout.php` — sidebar/menu por perfil (menu do gestor é **filtrado por permissão** via `permissao_de_menu()`). `pdf.php` — geração de PDF.

## Modelo de acesso (importante)
- Perfis: `admin`, `gestor`, `custodia`. Cotista **não tem usuário** — entra por token.
- Gestor: conta pode começar **sem fundo**; cria o 1º em `gestor/novo_fundo.php` (vira **principal**; todo fundo tem ≥1 principal). Convida contas por `account_id` em `gestor/equipe.php`; membro entra **sem permissões** até o principal liberar por checkbox (permissões de **visão** e **ação**). Páginas de gestor têm gate `exigir_permissao(...)` e o menu esconde o que não é permitido.
- Tickets: tabela `tickets` com coluna `canal` — `gestor_admin` (gestor↔administradora, em `admin/tickets.php`/`gestor/tickets.php`) e `cotista_gestor` (cotista↔gestor, em `cotista/tickets.php`/`gestor/chamados_cotistas.php`, gated por `ver_/responder_chamados_cotista`). Abrir/fechar/reabrir; tudo em `ticket_mensagens`.

## Rumo de produção (decidido nesta linha de trabalho)
Piloto é **PHP** (é só demonstração — não reescrever). Produção recomendada: **núcleo em C#/.NET**
(decimal nativo, threads para o batch paralelo, tipagem forte), **Python** na camada analítica/integração
(marcação, risco, PLD, ETL), **PostgreSQL particionado** por fundo/data. O motor de `batch.php`
(isolamento por fundo + idempotência + trava de validação + erros consolidados) é o **desenho de referência**.

## Ao terminar uma tarefa
`php -l` nos arquivos tocados → smoke web (login + `http=200`/`err=0`) → **restaurar o seed** → relatar
honestamente o que é real vs. simulado. O usuário commita manualmente (não commitar sem pedir).
