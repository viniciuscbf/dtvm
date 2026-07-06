# Matriz de Conformidade — Resolução CVM nº 32/2021

> **Documento de trabalho — v1.0** · Pacote de Autorização para Prestação de Serviços de Custódia de Valores Mobiliários
> **Base legal:** Resolução CVM nº 32, de 19/05/2021, texto consolidado com as alterações da Resolução CVM nº 209/2024 (revogou a Instrução CVM 542/2013).
> **Finalidade:** demonstrar, dispositivo por dispositivo, como a estrutura operacional e tecnológica do custodiante atende a cada requisito da norma — indicando a **evidência** (módulo da plataforma, manual ou documento societário) e o **status** (Atendido / Parcial / Roadmap). É a espinha do dossiê e o índice de leitura do requerimento (Anexo A).

---

## Como ler esta matriz

| Coluna | Significado |
|---|---|
| **Dispositivo** | Artigo/parágrafo/inciso da Res. CVM 32 |
| **Exigência** | O que a norma requer (paráfrase fiel) |
| **Como é atendido** | Processo/controle que satisfaz a exigência |
| **Evidência** | Onde se comprova: módulo do piloto (`portal/arquivo`), manual do pacote, ou ato societário do banco |
| **Status** | ✅ Atendido · 🟡 Parcial (demonstrável, formalização pendente) · 🟩 Roadmap (implementação na fase de adesões/homologação) |

> **Nota sobre a plataforma como evidência.** O piloto em `piloto/` (portal `/custodia/`) é a **maquete funcional** da retaguarda de custódia: demonstra os fluxos exigidos (guarda segregada, liquidação DVP, eventos, mensageria, arquivos, conciliação) rodando sobre uma base de dados real. Para o protocolo da Res. 32, a CVM avalia **capacidade demonstrável + plano** — não exige integrações vivas (RSFN homologada, adesões B3/SELIC) no momento do pedido. Onde o piloto simula, este documento marca 🟩 e remete ao cronograma de adesões.

---

## Bloco I — Autorização e requerimento

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 2º** | Custódia só pode ser prestada por PJ autorizada pela CVM | Pedido de autorização protocolado pela instituição requerente (já autorizada a funcionar pelo BCB) | `requerimento_anexoA_checklist.md` | 🟡 |
| **Art. 4º** | Elegibilidade (bancos comerciais/múltiplos/investimento, caixas, CTVM/DTVM, entidades de mercado) | Requerente é instituição de **menor porte, porém elegível** (banco pequeno/caixa/CTVM/DTVM já autorizado pelo BCB), constituindo uma nova área de custódia. **Pré-requisito:** se ainda não for tipo elegível, a autorização do BCB é etapa anterior (fora deste pacote) | Atos societários; autorização de funcionamento BCB | ✅ (se já elegível) / ⚠️ |
| **Art. 6º** | Requerimento à **SMI** instruído com os documentos do **Anexo A** | Requerimento + dossiê + manuais montados neste pacote | `requerimento_anexoA_checklist.md` (índice do Anexo A) | 🟡 |
| **Art. 7º** | Deferimento tácito em 90 dias (interrompível 1× por exigência) | Controle de prazo e de exigências no acompanhamento do protocolo | `requerimento_anexoA_checklist.md` §Fluxo | ✅ (processo) |
| **Art. 8º** | Hipóteses de indeferimento (docs faltantes, informação falsa/inexata, incapacidade técnica) | Checklist de completude do Anexo A antes do protocolo evita indeferimento por falha formal | `matriz` (esta) + checklist | ✅ (mitigação) |
| **Art. 9º** | Hipóteses de cancelamento (a pedido; por decisão da SMI após processo; falência/liquidação/dissolução) | Manutenção contínua das condições autorizativas; monitoramento por compliance e diretoria | `dossie_estrutura_operacional_tecnologica.md` §Compliance/Governança | ✅ (ciência) |

## Bloco II — Capacidade operacional e tecnológica (o coração da prova)

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 5º, I** | Constituir e manter **capacidade operacional e tecnológica**, com **qualidade e confidencialidade** das informações | Plataforma de custódia com controle de acesso por perfil, trilha de auditoria e segregação lógica; política de confidencialidade | `dossie_estrutura_operacional_tecnologica.md`; `politica_confidencialidade_seguranca.md`; `piloto/includes/auth.php` (perfis + revalidação) | 🟡 |
| **Art. 5º, II** | Sistemas seguros que permitam **registro, processamento e controle das posições e contas de custódia** | Motor de custódia: `contas_centrais` (contas segregadas), `liquidacoes`, `eventos_corporativos`, `mensagens_spb`, geração de arquivos de posição/extrato | `piloto/custodia/*`; `dossie` §Arquitetura | 🟡 |
| **Art. 5º, §1º** | Sistemas compatíveis com tamanho, características e volume das operações | Dimensionamento descrito por faixa de fundos/ativos; arquitetura em nuvem escalável | `dossie` §Dimensionamento e escala | 🟡 |
| **Art. 5º, §2º** | Capacidade econômico-financeira compatível | Demonstrada pelas DFs do banco + plano de custos da atividade | DFs do banco; `../planilha_custos.md` (referência) | 🟩 |
| **Art. 5º, §3º** | (Se guarda física) estrutura com acesso restrito e integridade | **Não aplicável** no escopo inicial (só VM escriturais/depósito centralizado) — declarar | `dossie` §Escopo (exclusão de guarda física) | ✅ (N/A declarado) |
| **Anexo A, art. 1º, III, "a"** | Descrição de **processos e sistemas informatizados e controles internos** | Descrição da arquitetura, fluxos e controles | `dossie` §Arquitetura, §Controles internos | 🟡 |
| **Anexo A, art. 1º, III, "b"** | Descrição da **estrutura de contas de custódia** | Modelo de contas individualizadas por fundo em cada central (`contas_centrais`) | `manual_custodia.md` §Estrutura de contas; `piloto` `contas_centrais` | 🟡 |
| **Anexo A, art. 1º, III, "c"** | Normas de segurança de instalações, equipamentos e **dados** | Política de segurança + **cabeçalhos de segurança (CSP, X-Frame-Options, nosniff)** aplicados na aplicação; criptografia, backup, acesso | `politica_confidencialidade_seguranca.md`; `piloto/includes/seguranca.php` | 🟡 |
| **Anexo A, art. 1º, III, "d"** | **Recursos humanos** adequados | Organograma da custódia + equipe mínima (mesa 2–3, TI, compliance) | `dossie` §Organograma e pessoas | 🟡 |
| **Anexo A, art. 1º, III, "e"** | Políticas de **segregação de funções**, controle de acesso, senhas e treinamento | Perfis segregados (admin/custódia); senhas bcrypt; **proteção CSRF + hardening de sessão** (HttpOnly/SameSite/timeout); política de acesso; plano de treinamento | `politica...` §Acesso; `piloto/includes/auth.php`, `seguranca.php`; `dossie` §Segregação | 🟡 |
| **Anexo A, art. 1º, III, "f"** | **Plano de contingência** e recuperação de arquivos/BD | Plano de continuidade com RTO/RPO, backup e site alternativo | `manual_contingencia_continuidade.md` | 🟡 |
| **Anexo A, art. 1º, III, "g"** | Cópias de **contratos de software** | Relação e cópias dos contratos de licenciamento/nuvem (na Versão A, inclui a plataforma licenciada do estruturador) | Anexos da instituição (a coletar); `modelo_parceria_papeis.md` | 🟩 |

## Bloco III — Segregação patrimonial e contas

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 12** | VM dos investidores em **contas individualizadas em nome destes**, segregadas das contas e posições do custodiante | Cada fundo tem conta própria em cada central; conta do banco marcada separadamente (`fundo_id NULL`) | `piloto` `contas_centrais`; `custodia/index.php` (painel de segregação); `manual_custodia.md` §Segregação | ✅ (modelo) 🟩 (contas reais nas centrais) |
| **Art. 2º, §2º, I, "a"** | Conservação, controle e **conciliação** das posições em contas em nome do investidor | Conciliação diária posição × depositário (ver Bloco V) | `manual_conciliacao.md` | 🟡 |
| **Art. 3º, par. único** | Posições nas contas de custódia devem **corresponder** às do depositário central | Batimento "Posição × Custodiante" e arquivos de posição diários | `piloto` `conciliacao` (origem *Posição × Custodiante*); `custodia/arquivos.php` | 🟡 |

## Bloco IV — Deveres do custodiante (Art. 13)

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 13, I** | Boa-fé, diligência e lealdade; vedado privilegiar interesse próprio/vinculado | Código de conduta + segregação de funções + monitoramento de partes relacionadas | `politica_confidencialidade_seguranca.md` §Conduta; `piloto` `partes_relacionadas` | 🟡 |
| **Art. 13, II** | Identificar titularidade e garantir **integridade e origem das instruções** | Instruções nascem de boletas autenticadas (perfil gestor) e mensageria; aceite/rejeição registrado | `piloto/custodia/instrucoes.php` (aceite de boleta); `manual_liquidacao_dvp.md` | 🟡 |
| **Art. 13, III** | **Guarda e regular movimentação**; processar eventos com controle eletrônico e documental | Liquidação DVP (`liquidacoes`) + eventos (`eventos_corporativos`: anunciar→provisionar→creditar) | `piloto/custodia/instrucoes.php`; `manual_custodia.md` §Eventos | 🟡 |
| **Art. 13, IV** | Registro de gravames/direitos sobre os VM | Campo/rotina de ônus e gravames na conta de custódia | `manual_custodia.md` §Gravames | 🟩 |
| **Art. 13, V** | Qualidade permanente dos processos/sistemas com **registro de acessos, erros, incidentes e interrupções** | **Trilha de auditoria append-only** (login/logout, boletas, liquidações, eventos, mensagens) + log de processamento + monitoramento de mensageria | `piloto` `auditoria` · `custodia/auditoria.php` · `includes/seguranca.php`; `log_processamento`, `mensagens_spb` | ✅ (trilha na aplicação) · 🟩 (WORM externo) |
| **Art. 13, VI** | Segurança física de equipamentos/instalações e de dados | Infra em nuvem com controles; política de segurança | `politica_confidencialidade_seguranca.md` | 🟡 |
| **Art. 13, VII** | Recursos humanos suficientes e capazes | Equipe mínima + certificação (CPA/CGA conforme função) | `dossie` §Pessoas; plano de certificação | 🟡 |
| **Art. 13, VIII** | Manter atualizados **manuais operacionais**, descrição de sistemas, fluxograma de rotinas, documentação de programas, controles de qualidade e regulamentos de segurança | Conjunto de manuais deste pacote + descrição de arquitetura | `manual_custodia.md`, `manual_liquidacao_dvp.md`, `manual_conciliacao.md`, `manual_contingencia...`, `politica...`; `dossie` | 🟡 |
| **Art. 13, IX** | Implementar e manter **plano de contingência** de continuidade | Manual de contingência/continuidade com RTO/RPO e testes | `manual_contingencia_continuidade.md` | 🟡 |
| **Art. 13, §1º, I** | (Investidores) **Conciliação diária** entre contas de custódia e posições do depositário central | Batch diário de conciliação posição × custodiante | `manual_conciliacao.md`; `piloto` `conciliacao` | 🟡 |
| **Art. 13, §1º, II** | (Investidores) **Sigilo** sobre características e quantidades dos VM | Confidencialidade por perfil + política de sigilo | `politica_confidencialidade_seguranca.md`; `piloto/includes/auth.php` | 🟡 |
| **Art. 13, §2º** | (Emissores) deveres específicos de guarda física/depósito centralizado | **Fora do escopo inicial** (custódia para investidores/fundos) — declarar | `dossie` §Escopo | ✅ (N/A declarado) |

## Bloco V — Informação, cadastro e conciliação

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 14** | Disponibilizar posição consolidada, movimentação e eventos; mensal até o 10º dia do mês seguinte; anual até fim de fevereiro | Arquivos de posição/extrato diários + relatórios periódicos ao contratante | `piloto/custodia/arquivos.php`; `manual_custodia.md` §Informação | 🟡 |
| **Art. 15** | Manter **cadastro** dos investidores, com rastreabilidade de alterações e atualização junto ao depositário | Cadastro de fundos/contas com trilha; atualização nas centrais | `manual_custodia.md` §Cadastro; `piloto` `fundos`/`contas_centrais` | 🟡 🟩 |
| **Art. 13, §1º, I** | Conciliação **diária** (reforço) | Ver Bloco IV | `manual_conciliacao.md` | 🟡 |

## Bloco VI — Regras, procedimentos e controles internos (Art. 16)

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 16, I** | Regras adequadas e eficazes para cumprir a Resolução | Manuais operacionais + políticas aprovados pela diretoria | Todos os manuais deste pacote | 🟡 |
| **Art. 16, II** | Procedimentos e **controles internos** para verificar aplicação e eficácia das regras | Auditoria interna + indicadores + revisão periódica | `dossie` §Controles internos; `manual_conciliacao.md` §Controles | 🟡 |
| **Art. 16, §1º, I** | Regras/procedimentos/controles **escritos** | Todo o pacote é escrito e versionado | Este diretório `custodiante/` | ✅ |
| **Art. 16, §1º, II–III** | Passíveis de verificação e **disponíveis à CVM**, depositários e autorregulação | Versionamento + guarda + disponibilização sob demanda | `README.md` §Governança documental | ✅ |

## Bloco VII — Governança: diretores e auditoria

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 17, I** | Diretor estatutário responsável pelo **cumprimento da Resolução** | Designado em ata; atribuições no dossiê | Ata de designação (Anexo A, VI); `dossie` §Governança | 🟡 |
| **Art. 17, II** | Diretor estatutário responsável pela **supervisão de controles internos** (art. 16, II) | Designado em ata, função **não cumulável** com o diretor do inc. I | Ata; `dossie` §Governança | 🟡 |
| **Art. 17, §1º** | Comunicar designação/substituição à CVM, depositários e mercados em **7 d.u.** | Procedimento de comunicação no manual de governança | `dossie` §Governança | ✅ (processo) |
| **Art. 17, §2º** | Funções dos dois diretores **não podem ser cumuladas** nem conflitantes | Segregação formal das diretorias | `dossie` §Segregação de funções | 🟡 |
| **Art. 18** | Diretor de supervisão encaminha relatório anual (até último d.u. de **abril**): conclusões da auditoria interna, deficiências, cronograma de saneamento | Rotina de relatório anual de controles internos | `dossie` §Auditoria interna; `manual_conciliacao.md` §Relatório anual | 🟩 |
| **Art. 20** | Manter **estrutura de auditoria interna**; relatórios à disposição da CVM; auditorias extraordinárias sob demanda | Função de auditoria interna do banco cobrindo a custódia | `dossie` §Auditoria interna | 🟡 |

> ⚠️ **Correção relevante frente ao material anterior:** a Res. CVM 32 **não exige relatório de auditor independente/asseguração** específico sobre a atividade de custódia. A exigência é de **auditoria interna** (arts. 18 e 20). Referências a "relatório de auditor tipo 1" nos guias antigos devem ser tratadas como *boa prática de mercado / exigência ANBIMA*, não como requisito da Resolução.

## Bloco VIII — Contrato de prestação de serviço (Art. 10) e terceiros (Art. 19)

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 10, caput e I–IV** | **Contrato específico** dispondo sobre transmissão de ordens, guarda física (se houver), contratação de terceiros e **riscos** | Minuta padrão de contrato de custódia | `minuta_contrato_custodia.md` | ✅ (minuta) |
| **Art. 10, par. único** | Contrato pode abranger controladoria de ativos e correlatos | Cláusula opcional de controladoria na minuta | `minuta_contrato_custodia.md` §Controladoria | ✅ |
| **Anexo A, art. 1º, VIII** | **Modelo de contrato** deve instruir o pedido | A minuta acompanha o requerimento | `minuta_contrato_custodia.md` | ✅ |
| **Art. 11 (§§, red. Res. 209/24)** | Vigência das obrigações; **portabilidade** e transferência (com titularidade, máx. **2 d.u.**) | Cláusula de transferência/portabilidade na minuta | `minuta_contrato_custodia.md` §Transferência | ✅ |
| **Art. 19, §1º–§4º** | Atividade regulada só a custodiante autorizado (§1º); terceirização **não altera responsabilidade** (§2º); **solidariedade** (§3º); controle de conflitos (§4º) | Delimitação software/estruturação × atividade regulada; política de terceiros + solidariedade | `dossie` §5.5; `minuta_contrato_custodia.md`; `modelo_parceria_papeis.md` | 🟡 |

## Bloco IX — Guarda de documentos e infrações

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 22** | Guardar documentos/informações por **≥ 5 anos** (digitalização admitida) | Política de retenção de 5 anos + logs imutáveis | `politica_confidencialidade_seguranca.md` §Retenção | 🟡 |
| **Art. 21** | Ciência das infrações (exercer sem autorização, docs falsos, violar arts. 2º, 3º, 10–17) | Governança e compliance asseguram cumprimento | `dossie` §Compliance | ✅ (ciência) |

---

## Placar de conformidade (resumo)

| Status | Contagem aproximada | Leitura |
|---|---|---|
| ✅ Atendido / processo definido | dispositivos formais e de governança | prontos no protocolo |
| 🟡 Parcial (demonstrável, formalizar) | maioria dos requisitos técnico-operacionais | plataforma prova o fluxo; falta aprovação formal dos manuais pela diretoria do banco |
| 🟩 Roadmap (adesões/homologação) | integrações reais (RSFN, B3, SELIC), contas reais nas centrais | executam **após** o deferimento, na fase de adesões |

**Conclusão de suficiência.** Para o *protocolo do pedido de autorização*, o conjunto (dossiê + manuais + minutas + plataforma como anexo técnico) endereça **todos os dispositivos** da Res. CVM 32 que dependem do requerente. Os itens 🟩 são, por natureza, posteriores ao deferimento (a CVM autoriza; depois vêm as adesões B3/SELIC e a homologação RSFN). O que falta antes do protocolo é **formalização**: aprovação dos manuais pela diretoria, designação dos dois diretores em ata e coleta dos anexos societários do banco — não é lacuna tecnológica.

---

*Referência normativa: Resolução CVM nº 32/2021 (consolidada, com Res. CVM 209/2024). Fonte primária: PDF consolidado oficial da CVM. Este documento é um instrumento de trabalho e deve ser revisado pelo jurídico/compliance do banco antes do protocolo.*
