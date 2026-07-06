# Matriz de Conformidade — Administrador Fiduciário (Res. CVM 21/2021 + Res. CVM 175/2022)

> **Documento de trabalho — v1.0** · Pacote de Autorização e Estruturação da Atividade de **Administração Fiduciária** de fundos de investimento.
> **Base legal:** **Resolução CVM nº 21/2021** (autorização para o exercício da administração de carteiras — categoria *administrador fiduciário*), consolidada (Res. 162/22, 167/22, 179/23, 209/24); e **Resolução CVM nº 175/2022** (marco dos fundos de investimento — deveres na operação), consolidada (Res. 181/23 a 214/24).
> **Finalidade:** demonstrar, dispositivo por dispositivo, como a estrutura da instituição atende a cada requisito — indicando **evidência** (módulo do piloto `/admin/`, manual ou ato societário) e **status**.

---

## Como ler / convenções

| Símbolo | Significado |
|---|---|
| ✅ | Atendido / processo definido |
| 🟡 | Parcial — demonstrável na plataforma, falta formalização (aprovação de diretoria, dados do banco) |
| 🟩 | Roadmap — construção de produção (o piloto ainda **não** implementa) |

> **Duas normas, dois eixos.** A **Res. CVM 21** rege *quem pode ser* administrador fiduciário e *como se autoriza* (registro na **SIN**). A **Res. CVM 175** rege *o que o administrador faz* na operação do fundo. Esta matriz cruza os dois.
>
> **Âncora tecnológica:** o portal `/admin/` do piloto (`../piloto/`) é a maquete da **controladoria/administração fiduciária** — cálculo e prévia de cota D-1, aprovação do gestor, lançamentos, conciliação, regulatório (envios/ofícios/assembleias), aberturas de fundos e repasses.
>
> **Aviso de honestidade (importante):** o administrador fiduciário é o **responsável tributário** dos cotistas (Lei 14.754/2023) e o **passivo do cotista** (aplicações/resgates, come-cotas, IR) é o **maior módulo ausente** do piloto. Por isso esta matriz tem **mais itens 🟩** do que a do custodiante — e isso está declarado, não escondido.

---

## Bloco I — Autorização como administrador fiduciário (Res. CVM 21)

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 1º, §1º** | Registro em uma ou ambas as categorias: **administrador fiduciário** e/ou **gestor** | Pedido na categoria **administrador fiduciário** | `requerimento_anexoC_checklist.md` | 🟡 |
| **Art. 1º, §2º, I** | São elegíveis **instituições autorizadas a funcionar pelo BCB** | Instituição-alvo já autorizada pelo BCB (não usa a via patrimonial do inc. II) | Atos societários; autorização BCB | ✅ (se já autorizada) / ⚠️ |
| **Art. 2º, §2º** | Categoria *administrador fiduciário* autoriza todas as atividades do art. 1º **exceto gestão de recursos** | Escopo declarado: administração fiduciária, sem gestão | `dossie...` §Escopo | ✅ |
| **Art. 4º, caput** | Requisitos da PJ: sede no Brasil; objeto social; diretor(es) responsável(is); diretor de controles internos; RH e recursos computacionais adequados; Anexo E | Estrutura societária + organograma + plataforma | `dossie...` §Governança/§Arquitetura; `requerimento_anexoC_checklist.md` | 🟡 |
| **Art. 4º, §8º** | Recursos computacionais **protegidos contra adulteração** e com **registros para auditoria/inspeção** | Trilha de auditoria append-only + controle de acesso | `politica_confidencialidade_seguranca.md`; `piloto/includes/seguranca.php`, `auth.php` | 🟡 |
| **Art. 6º, II** | Pedido encaminhado à **SIN**, instruído com o **Anexo C** (PJ) | Requerimento + dossiê + manuais | `requerimento_anexoC_checklist.md` | 🟡 |
| **Art. 7º** | SIN analisa em **60 dias**; exigências suspendem uma vez (20+10 dias); **deferimento tácito** se a SIN não se manifestar (§10) | Controle de prazos/exigências | `requerimento_anexoC_checklist.md` §Fluxo | ✅ (processo) |

## Bloco II — Governança: diretores e segregação (Res. CVM 21)

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 4º, III** | Diretor(es) estatutário(s) responsável(is) pela administração de carteiras, autorizado(s) pela CVM | Designação em ata + registro na CVM | Ata; `dossie...` §Governança | 🟡 |
| **Art. 30, par. único** | Registrado **apenas** como administrador fiduciário **dispensa** diretor exclusivo de administração de carteiras — vedada a acumulação com a **gestão dos recursos da própria instituição** | Estrutura sem gestão própria; diretor de adm. fiduciária designado | `dossie...` §Governança | ✅ |
| **Art. 4º, IV** | Diretor estatutário responsável por **regras e controles internos** (com independência) | Diretor de compliance/controles internos designado | Ata; `dossie...` §Controles internos | 🟡 |
| **Art. 5º** | Impedimento de diretor > 30 dias → substituto assume e comunica à CVM em **7 d.u.** | Procedimento de substituição | `dossie...` §Governança | ✅ (processo) |
| **Art. 27** | Administração de carteiras **segregada** das demais atividades (inclusive segregação física ante intermediação/distribuição) | Segregação funcional e de perfis (admin × gestor × custódia) | `dossie...` §Segregação; `piloto/includes/auth.php` | 🟡 |
| **Art. 30, caput** | **Custódia e controladoria de ativos e passivos totalmente segregadas da gestão de recursos** | Perfis e áreas segregados; controladoria é a administradora, gestão é do gestor | `dossie...` §Segregação; `piloto` (perfis `admin`/`gestor`/`custodia`) | ✅ (modelo) 🟡 |
| **Art. 28** | Manuais escritos de **segregação** (I) e **confidencialidade** (II) | Manuais do pacote | `dossie...`; `politica_confidencialidade_seguranca.md` | 🟡 |

## Bloco III — Controles internos, deveres gerais e vedações (Res. CVM 21)

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 22–23** | Controles internos adequados; regras/procedimentos **escritos**; identificar e eliminar **conflitos de interesse** | Manuais + política; monitoramento de partes relacionadas | Manuais do pacote; `piloto` `partes_relacionadas` | 🟡 |
| **Art. 25** | Relatório anual de compliance ao órgão de administração até o último d.u. de **abril** | Rotina de relatório anual de controles internos | `dossie...` §Controles internos | 🟩 |
| **Art. 18** | Deveres: boa-fé, diligência, lealdade; cumprir regulamento; **contratar/verificar a custódia** dos ativos; informar violações à CVM em 10 d.u. | Processos operacionais + governança | `dossie...`; `manual_regulatorio_envios.md` | 🟡 |
| **Art. 20** | Vedações (contraparte, propaganda de rentabilidade, promessas de retorno, etc.) | Política de conduta + compliance | `politica_confidencialidade_seguranca.md` §Conduta | ✅ (ciência) |
| **Art. 32** | **Fiscalizar os prestadores** contratados em nome do fundo: limites, estrutura, política de risco do gestor e **sistemas do custodiante** (inc. V) | Conciliação com o custodiante + acompanhamento de prestadores | `manual_conciliacao.md`; `piloto` `conciliacao`, `admin/conciliacao.php` | 🟡 |

## Bloco IV — Deveres do administrador na operação do fundo (Res. CVM 175)

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 3º, XXX** | Administrador e gestor são os **prestadores de serviços essenciais** | Papéis definidos na operação | `dossie...` §Papéis | ✅ |
| **Art. 82–83** | Administrador pratica os atos de administração e **contrata, em nome do fundo**: controladoria/processamento de ativos (I), escrituração de cotas (II), **auditoria independente** (III) | Contratação e supervisão dos prestadores | `manual_regulatorio_envios.md`; `dossie...` | 🟡 |
| **Art. 83, §1º** | Instituição autorizada pelo BCB **não precisa contratar** o serviço de controladoria se **ela própria** o executar | A instituição executa a controladoria internamente | `dossie...` §Escopo | ✅ |
| **Art. 104, I** | Manter atualizados e em ordem: registro de cotistas; atas; pareceres do auditor; **registros contábeis** do fundo | Escrituração e registros da controladoria | `manual_controladoria_precificacao.md` | 🟡 🟩 |
| **Art. 104, IV** | **Elaborar e divulgar as informações periódicas e eventuais** da classe | Motor de envios regulatórios | `manual_regulatorio_envios.md`; `piloto` `envios_regulatorios`, `admin/regulatorio.php` | 🟡 |
| **Art. 104, V–VI** | Manter lista de prestadores atualizada na CVM; **serviço de atendimento ao cotista** | Cadastro de prestadores + canal de atendimento | `piloto` `chamados`; `dossie...` | 🟡 |
| **Art. 104, VII** | Nas classes abertas, **receber e processar pedidos de resgate** | Motor de passivo **simulado** (aplicações/resgates com IR/IOF) | `piloto/admin/passivo.php`; `manual_passivo_tributacao.md` | 🟡 |
| **Art. 104, X** | Cumprir as deliberações da assembleia | Módulo de assembleias | `piloto` `assembleias`, `admin/regulatorio.php` | 🟡 |

## Bloco V — Controladoria: cota, PL, contabilidade e precificação

| Tema | Base | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Cálculo e divulgação de cota/PL** | Res. 175, art. 104, IV + Anexos Normativos¹ | Cálculo diário, prévia D-1, aprovação do gestor, publicação | `piloto` `fechamentos`, `admin/processamento.php`, `calcular_cota()` | 🟡 |
| **Escrituração contábil própria e segregada** | Res. 175, **art. 66** | Registros contábeis do fundo segregados | `manual_controladoria_precificacao.md` | 🟡 🟩 (COSIF/acrual completo) |
| **Marcação a mercado (MaM)** | Anexos Normativos¹ + boa prática | Preços por classe de ativo; fonte de preço registrada | `piloto` `ativos_carteira` (`preco_mam`, `fonte_preco`) | 🟡 🟩 (manual formal + captura automática) |
| **Provisionamento (acrual) de despesas e taxas** | boa prática contábil | — (piloto simplifica cota = ativos+caixa/cotas) | — | 🟩 |
| **Lançamentos e reprocessamento com trilha** | controle interno | Ajustes versionados, cascata retroativa | `piloto` `lancamentos`, `fechamentos.versao` | ✅ |

¹ *Os deveres operacionais de cota/PL e os leiautes/prazos de envio residem nos **Anexos Normativos por categoria** da Res. 175 (ex.: Anexo Normativo I). Números de artigo desses anexos **não** foram citados aqui por não terem sido confirmados na Parte Geral — a verificar na redação do anexo aplicável à categoria do fundo.*

## Bloco VI — Conciliação e fiscalização de prestadores

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Res. 21, art. 32, V** | Fiscalizar os **sistemas do custodiante** | Conciliação diária posição × custodiante | `manual_conciliacao.md`; `piloto` `conciliacao`, `admin/conciliacao.php` | 🟡 |
| **Res. 175, art. 104, I "e"** | Manter registros contábeis conciliados | Batimento posição/caixa e trilha de resolução | `piloto` `conciliacao` (origem *Posição × Custodiante*, *Caixa × Extrato*) | 🟡 |

## Bloco VII — Risco de liquidez

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Res. 21, art. 26, §4º** | Administrador fiduciário deve **supervisionar** a gestão de risco do gestor e **gerir, em conjunto**, o **risco de liquidez** | Rotina de gestão de liquidez conjunta + troca de informações | `manual_risco_liquidez.md`; `piloto` `previsao_caixa` | 🟡 🟩 |
| **Res. 175, art. 92** | Gestão de liquidez nas **classes abertas** (dever dos prestadores essenciais) | Casamento ativo × passivo, testes de estresse | `manual_risco_liquidez.md` | 🟩 |

## Bloco VIII — Registro de fundos, classes e subclasses (Res. CVM 175)

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 7º** | Fundo constituído por **deliberação conjunta** dos prestadores essenciais (aprovam o regulamento) | Fluxo de constituição/onboarding | `piloto` `documentos_abertura`, `onboarding_etapas`, `admin/aberturas.php` | 🟡 |
| **Art. 8º/10** | **Registro automático** na CVM com envio eletrônico pelo administrador | Checklist documental e emissão | `piloto` `admin/aberturas.php` | 🟡 |
| **Art. 5º** | Classes com **patrimônio segregado**; subclasses por público-alvo/prazos/taxas | Modelo de classes/subclasses | `../guia_estruturas_classes_subclasses.md` (referência) | 🟩 |

## Bloco IX — Informação e envios periódicos (Res. CVM 175)

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 31** | Comunicar a **primeira integralização** por sistema em **5 d.u.** | Rotina de comunicação | `manual_regulatorio_envios.md` | 🟡 |
| **Art. 61–62** | Divulgar **informações periódicas e eventuais**; a Superintendência pode exigir por sistema eletrônico | Fila de envios + protocolo | `piloto` `envios_regulatorios`, `admin/regulatorio.php` | 🟡 |
| **Informe diário / CDA / perfil mensal / balancete** | **Anexos Normativos por categoria**¹ (não numerados na Parte Geral) | Fila de envios com prazos configuráveis; informe diário bloqueado até cota aprovada | `piloto` `envios_regulatorios` | 🟡 🟩 (integração real CVMWeb/Fundos.NET) |
| **Ofícios do regulador** | dever de resposta tempestiva | Registro de ofícios com prazo e resposta protocolada | `piloto` `oficios_cvm`, `admin/regulatorio.php` | ✅ (fluxo) |

## Bloco X — Auditoria independente (Res. CVM 175)

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 66** | Escrituração contábil própria, **segregada** da dos prestadores | Contabilidade do fundo separada | `manual_controladoria_precificacao.md` | 🟡 🟩 |
| **Art. 69** | Demonstrações **auditadas anualmente** por auditor independente registrado na CVM (dispensa < 90 dias) | Contratação e suporte à auditoria anual | `manual_regulatorio_envios.md`; `dossie...` | 🟡 |

> ⚠️ **Diferente do custodiante:** aqui a **auditoria independente é exigida** (Res. 175, art. 69) — das **demonstrações do fundo**. Não confundir com a auditoria interna da custódia (Res. 32).

## Bloco XI — Assembleias de cotistas (Res. CVM 175)

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Art. 70** | Competência privativa (demonstrações; substituição de prestador essencial; alterações de regulamento; etc.) | Módulo de assembleias com pauta e resultado | `piloto` `assembleias`, `admin/regulatorio.php` | 🟡 |
| **Art. 72** | Convocação a cada cotista, **10 dias** de antecedência mínima | Fluxo de convocação | `manual_regulatorio_envios.md` | 🟡 |
| **Art. 75** | Assembleia **exclusivamente eletrônica** admitida; administrador garante autenticidade/segurança dos votos | Assembleia eletrônica registrada | `piloto` `assembleias` (`modo` Eletrônica) | 🟡 |
| **Art. 79** | Resumo das deliberações em até **30 dias** | Registro e disponibilização | `manual_regulatorio_envios.md` | 🟡 |

## Bloco XII — Passivo do cotista e tributação (Lei 14.754/2023) — o maior 🟩

| Tema | Base | Estado | Evidência | Status |
|---|---|---|---|---|
| **Onboarding do cotista** (KYC, suitability, termo de adesão) | Res. 175 + Res. 50 (PLD) | Cadastro básico ao aplicar; KYC/suitability ausentes | `piloto/admin/passivo.php` | 🟩 |
| **Aplicações e resgates** (cotização/liquidação D+N) | Res. 175, art. 104, VII | **Simulado**: cotização pela cota vigente, emite/resgata cotas, movimenta caixa | `piloto/admin/passivo.php`, `includes/passivo.php` | 🟡 |
| **Responsável tributário** (come-cotas mai/nov 15%/20%; IR regressivo; IOF) | **Lei 14.754/2023** | **Simulado**: IR regressivo 22,5%→15% e IOF no resgate; come-cotas mai/nov com step-up de base (ações isento) | `piloto/admin/passivo.php`, `includes/passivo.php` | 🟡 · 🟩 (provisão diária na cota + DARF) |
| **Informe de rendimentos** (DIRF/e-Financeira) | RFB | Informe simulado exportável (CSV); DIRF/e-Financeira reais ausentes | `piloto/admin/passivo.php` | 🟡 · 🟩 |

## Bloco XIII — Regime de responsabilidade

| Dispositivo | Exigência | Como é atendido | Evidência | Status |
|---|---|---|---|---|
| **Res. 175, art. 81** | Cada prestador responde **na sua esfera**, por seus atos — **sem solidariedade automática**; sem prejuízo do dever de fiscalizar | Segregação de papéis + trilha de diligência (conciliação, fiscalização) | `dossie...` §Responsabilidade; `manual_conciliacao.md` | ✅ (ciência) |

---

## Placar e conclusão de suficiência

| Status | Leitura |
|---|---|
| ✅ / 🟡 | **A autorização (Res. 21) é atingível com o que existe + formalização.** A instituição autorizada pelo BCB é elegível; a controladoria, a conciliação, o regulatório e as assembleias já são demonstráveis no portal `/admin/`. |
| 🟩 | **Operar cotista real (Res. 175 na prática) exige construir o passivo + tributação + contabilidade de acrual + MaM homologada.** Isso é maior do que no custodiante e está honestamente marcado. |

**Conclusão.** Para o **pedido de autorização como administrador fiduciário (Res. 21)** — que é o objetivo deste pacote — o conjunto (dossiê + manuais + checklist do Anexo C + plataforma) endereça os requisitos que dependem do requerente. Para **operar fundos com cotistas reais (Res. 175)**, os itens 🟩 (sobretudo passivo/tributação) são pré-requisitos de produção, posteriores à autorização — e maiores do que no custodiante. Ambos estão declarados.

---

*Referências: Resolução CVM nº 21/2021 e Resolução CVM nº 175/2022 (Parte Geral), textos consolidados oficiais da CVM; Lei 14.754/2023. Documento de trabalho — revisar com jurídico/compliance antes do protocolo.*
