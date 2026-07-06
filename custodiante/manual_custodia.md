# Manual Operacional de Custódia de Valores Mobiliários

> **Documento de trabalho — v1.0** · Manual Operacional de Custódia
> **Base legal:** Resolução CVM nº 32/2021 (texto consolidado com a Resolução CVM nº 209/2024), com destaque para os **arts. 12, 13 e 14**.
> **Finalidade:** descrever, de forma operacional e auditável, como o custodiante realiza a conservação, o controle, a movimentação e a conciliação das posições de valores mobiliários de seus contratantes, bem como o tratamento de instruções e eventos. Integra o pacote de comprovação de estrutura que instrui o pedido de autorização à SMI/CVM.

---

## Como ler este manual

| Marcador | Significado |
|---|---|
| `[colchetes]` | Campo a ser preenchido/parametrizado pelo banco antes do protocolo (dado societário, nome de área, contato, número de adesão etc.). |
| 🟩 **Roadmap** | Ponto em que a plataforma-piloto **simula** uma integração real (RSFN/SPB, B3, SELIC). A implementação viva (homologação de mensageria, adesões às centrais) ocorre na fase pós-deferimento, conforme cronograma de adesões. |
| `arquivo/tela` | Evidência na plataforma-piloto (portal `/custodia/`) ou referência cruzada a documento irmão do pacote. |

> **Nota sobre a plataforma-piloto como evidência.** O piloto em `..\piloto\` (portal `/custodia/`, base PHP + MySQL) é a maquete funcional da retaguarda de custódia. Demonstra os fluxos exigidos pela norma — guarda segregada, liquidação DVP, eventos corporativos, mensageria SPB, geração de arquivos e conciliação — operando sobre uma base de dados real. Este manual **cita os módulos como evidência**, sem depender de integrações vivas no momento do pedido.

**Documentos irmãos deste pacote** (referências cruzadas ao longo do texto):

- `matriz_conformidade_cvm32.md` — matriz dispositivo a dispositivo da Res. CVM 32.
- `dossie_estrutura_operacional_tecnologica.md` — arquitetura, dimensionamento e controles de TI.
- `manual_liquidacao_dvp.md` — detalhamento do ciclo de liquidação entrega-contra-pagamento (DVP).
- `manual_conciliacao.md` — rotinas de conciliação com as centrais depositárias.
- `manual_contingencia_continuidade.md` — plano de continuidade de negócios e contingência.
- `politica_confidencialidade_seguranca.md` — política de confidencialidade, segurança da informação e controle de acesso.

---

## 1. Objetivo e abrangência

### 1.1 Objetivo

Este manual estabelece os procedimentos operacionais pelos quais **[Razão Social do Banco]** ("o Custodiante"), na qualidade de prestador de serviço de custódia de valores mobiliários autorizado pela CVM, realiza:

- a **conservação** (guarda) dos valores mobiliários e ativos financeiros de titularidade dos contratantes;
- o **controle e a conciliação** das posições e movimentações mantidas junto aos depositários centrais;
- o **tratamento de instruções** de movimentação e de liquidação; e
- o **tratamento de eventos** incidentes sobre os ativos custodiados.

Estas atribuições materializam o serviço de custódia definido no **art. 2º** da Res. CVM 32, prestado a investidores — no escopo deste pacote, **fundos de investimento** regidos pela **Resolução CVM nº 175**.

### 1.2 Abrangência

| Item | Situação |
|---|---|
| Custódia para investidores (fundos de investimento) | **Incluída** — objeto central deste manual. |
| Contas individualizadas por fundo, segregadas das posições do Custodiante | **Incluída** (art. 12). |
| Liquidação DVP de operações | **Incluída** — detalhe em `manual_liquidacao_dvp.md`. |
| Tratamento de eventos corporativos | **Incluída** (art. 13, III). |
| Guarda física de certificados / cautelas cartulares | **Excluída** — a custódia é exclusivamente escritural (ativos desmaterializados em depositário central). |
| Serviço de custódia a **emissores** (escrituração de ativos) | **Excluída** — não é objeto deste pedido; declara-se expressamente. |

> **Declaração de escopo.** O Custodiante **não** presta, no âmbito desta autorização, guarda física de títulos cartulares nem serviço de custódia a emissores. A prestação restringe-se à custódia escritural de valores mobiliários e ativos financeiros de titularidade de investidores contratantes.

---

## 2. Estrutura de contas de custódia (art. 12)

O **art. 12** exige que as contas de custódia sejam **individualizadas em nome do investidor** e **segregadas** das contas e posições próprias do Custodiante. A plataforma implementa essa exigência na tabela `contas_centrais` (visível no painel `custodia/index.php`).

### 2.1 Modelo de contas

Cada fundo contratante possui **uma conta de custódia individualizada por central depositária** em que mantenha ativos. A tabela `contas_centrais` relaciona:

| Campo | Conteúdo |
|---|---|
| `fundo_id` | Identificador do fundo titular da conta. **`fundo_id NULL` = conta própria do Custodiante** (posição do banco), fisicamente/logicamente segregada das contas dos fundos. |
| `central` | Depositário/sistema: **SELIC**, **B3 Depositária**, **B3 Balcão**, **STR/Reservas**. |
| Identificação da conta na central | Número/código da conta individualizada mantida junto à central. |
| Saldo/posição | Posição de custódia conciliada com a central (ver `manual_conciliacao.md`). |

> **Nota de honestidade terminológica.** A Res. CVM 32 (art. 12) emprega a expressão **"contas de custódia individualizadas em nome do investidor"** — este manual adota rigorosamente essa terminologia e **não** utiliza rótulos genéricos do tipo "conta cliente 1/2". Cada conta corresponde a um fundo identificado (`fundo_id`).

### 2.2 Segregação patrimonial

- As posições dos fundos **nunca** se confundem com a posição própria do Custodiante (`fundo_id NULL`).
- A segregação é lógica (modelo de dados) e reflete a segregação mantida junto a cada central depositária, na qual o banco figura como participante e as contas são abertas em nome dos investidores finais, conforme as regras de cada central.
- Controle de acesso por perfil e trilha de auditoria reforçam a segregação (ver `politica_confidencialidade_seguranca.md`).

### 2.3 Abertura e encerramento de contas

| Etapa | Procedimento |
|---|---|
| **Abertura** | Mediante contrato de prestação de serviço com o fundo/administrador e cadastro concluído (art. 15, seção 8). Registra-se novo `fundo_id` e criam-se as contas em `contas_centrais` por central aplicável. Solicita-se a abertura da conta individualizada correspondente junto ao depositário central. 🟩 A integração viva de abertura junto a B3/SELIC é etapa de adesão pós-deferimento. |
| **Encerramento** | Após transferência integral das posições (para outro custodiante ou por resgate/liquidação do fundo) e zeragem conciliada dos saldos. Mantêm-se os registros pelo prazo de retenção (art. 22, seção 9). |
| **Correspondência com o depositário central** | Cada conta na plataforma tem correspondência unívoca com a conta individualizada mantida na respectiva central; a conciliação diária (seção 8 e `manual_conciliacao.md`) garante o alinhamento de saldos. |

---

## 3. Guarda e movimentação dos ativos (art. 13, III)

O **art. 13, III** impõe ao Custodiante a **guarda e a regular movimentação** dos ativos, além do processamento de eventos com controle eletrônico e documental (seção 6). A guarda é sempre **escritural**, junto à central competente para cada classe de ativo.

### 3.1 Roteamento por central depositária

| Classe de ativo | Central de guarda | Campo `central` |
|---|---|---|
| Títulos públicos federais (LFT, LTN, NTN etc.) | **SELIC** | `SELIC` |
| Ações, ETFs, BDRs | **B3 Depositária** | `B3 Depositária` |
| Debêntures, CDB/CDBs, CRI, CRA, LF e demais ativos de renda fixa privada/registro | **B3 Balcão** | `B3 Balcão` |
| Movimentação de recursos / reservas bancárias | **STR/Reservas** | `STR/Reservas` |

### 3.2 Movimentação

- Toda movimentação decorre de **instrução válida** (seção 5) e é liquidada por **DVP** quando envolve troca de ativo por recursos (seção 5 e `manual_liquidacao_dvp.md`).
- Cada movimentação gera registro em `log_processamento` (trilha) e, quando aplicável, mensagem em `mensagens_spb`.
- A posição resultante é conciliada com a central no ciclo seguinte (`manual_conciliacao.md`).

---

## 4. Recebimento e validação de instruções

Antes de qualquer movimentação, o Custodiante valida a **origem** e a **integridade** da instrução. O aceite ocorre na tela `custodia/instrucoes.php` (aceite de boleta), que também dispara a liquidação DVP e o processamento de eventos.

### 4.1 Controles de validação

| Controle | Verificação |
|---|---|
| **Origem/autorização** | A instrução provém de contratante/administrador habilitado, por canal autorizado e usuário com perfil válido (ver `politica_confidencialidade_seguranca.md`). |
| **Integridade** | Campos obrigatórios presentes e consistentes (ativo, central, quantidade, financeiro, contraparte, data pretendida). |
| **Elegibilidade da conta** | Conta individualizada do fundo existe e está ativa na central indicada (seção 2). |
| **Suficiência** | Saldo/posição compatível com a movimentação pretendida. |
| **Registro** | Aceite registrado com carimbo de tempo e usuário em `log_processamento`. |

### 4.2 Encaminhamento à liquidação

Instrução aceita que implique liquidação segue o ciclo **DVP** — status em `liquidacoes` (Pendente → Liquidada / Falha), com `data_liquidacao` em **D+1/D+2** conforme o ativo. O detalhamento completo do ciclo, janelas, tratamento de falhas e reprocessamento consta de **`manual_liquidacao_dvp.md`**.

---

## 5. Eventos corporativos (art. 13, III)

O processamento de eventos com **controle eletrônico e documental** é exigência do **art. 13, III** e está implementado na tabela `eventos_corporativos` (operada em `custodia/instrucoes.php`).

### 5.1 Tipos de evento suportados

| Tipo | Natureza |
|---|---|
| **Dividendo** | Provento em dinheiro sobre ações. |
| **JCP** (Juros sobre Capital Próprio) | Provento em dinheiro, com retenção fiscal quando aplicável. |
| **Cupom** | Pagamento periódico de juros de renda fixa. |
| **Amortização** | Devolução parcial do principal. |
| **Bonificação** | Distribuição de novas ações. |
| **Desdobramento** (split) | Aumento de quantidade sem alteração do capital. |

### 5.2 Fluxo do evento

O ciclo segue três estados registrados em `eventos_corporativos` (`status`):

```
Anunciado  ──►  Provisionado  ──►  Liquidado
```

| Estado | Ação operacional |
|---|---|
| **Anunciado** | Evento capturado da central/emissor e cadastrado; identificam-se ativo, data-com, data-ex e datas de pagamento. |
| **Provisionado** | Cálculo do direito por conta individualizada (posição na data-com); provisão registrada e conciliada; documentação de suporte arquivada. |
| **Liquidado** | Crédito do provento (dinheiro) ou da quantidade (bonificação/desdobramento) na conta do fundo; baixa da provisão; registro em `log_processamento`. |

- Eventos financeiros liquidam via **STR/Reservas** e podem gerar mensagem em `mensagens_spb`.
- Eventos em ativos liquidam por atualização de posição na central correspondente (seção 3).

---

## 6. Gravames e ônus (art. 13, IV)

O **art. 13, IV** exige o **registro de gravames e direitos** que recaiam sobre os ativos custodiados.

| Item | Procedimento |
|---|---|
| **Constituição** | Registro do gravame/ônus (penhor, alienação fiduciária, bloqueio judicial, garantia) vinculado à conta individualizada e ao ativo, com identificação do beneficiário e do instrumento de origem. |
| **Efeito operacional** | Ativo onerado fica **indisponível** para movimentação livre; instruções incompatíveis são rejeitadas na validação (seção 4). |
| **Baixa** | Liberação mediante comprovação da extinção do gravame; registro da baixa com trilha em `log_processamento`. |
| **Documentação** | Instrumento constitutivo e de baixa arquivados pelo prazo de retenção (art. 22, seção 9). |

> 🟩 A troca automatizada de mensagens de bloqueio/desbloqueio com as centrais é item de adesão pós-deferimento; no piloto, o controle de indisponibilidade é demonstrado no modelo de dados e na validação de instruções.

---

## 7. Cadastro (art. 15) e prestação de informações ao contratante (art. 14)

### 7.1 Cadastro dos investidores (art. 15)

O **art. 15** exige cadastro dos investidores com **rastreabilidade**. Para cada fundo contratante mantém-se:

- identificação completa do fundo e do administrador/gestor;
- documentos de constituição e contrato de prestação de serviço;
- histórico de alterações cadastrais versionado, com autor e carimbo de tempo (rastreabilidade);
- vínculo cadastro → `fundo_id` → contas em `contas_centrais`.

O cadastro completo e atualizado é **condição para abertura de contas** (seção 2.3).

### 7.2 Prestação de informações (art. 14)

O **art. 14** determina o fornecimento ao contratante de **posição consolidada, movimentação e eventos**, com periodicidade e prazos definidos. A plataforma gera os arquivos na tela `custodia/arquivos.php` (CSV de posição e extrato).

| Informação | Conteúdo | Prazo / periodicidade |
|---|---|---|
| **Arquivo diário de posição** | Posição de custódia por conta individualizada e central, conciliada. | Diário (D+0/D+1), via `custodia/arquivos.php`. |
| **Extrato de movimentação** | Movimentações do período (liquidações, eventos, gravames). | Diário / sob demanda. |
| **Relatório mensal** | Posição consolidada, movimentação e eventos do mês. | Até o **10º dia** do mês seguinte (art. 14). |
| **Relatório anual** | Consolidação do exercício. | Até o **fim de fevereiro** do ano seguinte (art. 14). |

- Os arquivos diários alimentam a **controladoria da administradora** (seção 10) para a marcação/valorização das cotas.
- Toda geração de arquivo é registrada em `log_processamento` para comprovação de envio.

---

## 8. Registros, trilha e retenção (art. 13, VIII; art. 22)

### 8.1 Manuais operacionais atualizados (art. 13, VIII)

O **art. 13, VIII** exige **manter manuais operacionais atualizados**. Este manual e os documentos irmãos são versionados; cada revisão registra versão, data, autor e resumo da alteração. A revisão é obrigatória a cada alteração normativa relevante e, no mínimo, anualmente.

### 8.2 Trilha de auditoria

- A tabela `log_processamento` registra a **trilha** de todas as operações relevantes (aceite de instrução, liquidação, evento, gravame, geração de arquivo), com identificação de usuário e carimbo de tempo.
- A mensageria SPB fica registrada em `mensagens_spb` (códigos no padrão **SEL1052 / STR0008 / MOV0001**, com status **Recebida / Processada / Erro**).
- A conciliação é registrada em `conciliacao` (ver `manual_conciliacao.md`).

### 8.3 Retenção (art. 22)

O **art. 22** exige a **guarda de documentos e registros por prazo não inferior a 5 (cinco) anos** (podendo estender-se por determinação da CVM ou enquanto perdurar demanda). Aplica-se a:

- registros de posições, movimentações e conciliações;
- instruções recebidas e respectivas validações;
- documentação de eventos corporativos e de gravames;
- mensagens SPB (`mensagens_spb`) e trilha (`log_processamento`);
- cadastros e suas alterações versionadas.

---

## 9. Papéis e responsabilidades

### 9.1 Mesa de Custódia (interna)

| Papel | Responsabilidade |
|---|---|
| **Diretor responsável pela custódia** | Responde perante a CVM pela atividade (art. 13 e correlatos). Campo: **[Nome do Diretor Responsável]**. |
| **Mesa de Custódia (operação)** | Recebimento e validação de instruções, liquidação, processamento de eventos e gravames, geração de arquivos. |
| **Conciliação** | Execução e tratamento de quebras de conciliação com as centrais (`manual_conciliacao.md`). |
| **Compliance / Controles Internos** | Monitoramento de conformidade, revisão de manuais e trilha de auditoria. |
| **Tecnologia** | Disponibilidade, segurança e continuidade da plataforma (`dossie_estrutura_operacional_tecnologica.md`; `manual_contingencia_continuidade.md`). |

Contato operacional da mesa: **[e-mail/telefone da Mesa de Custódia]**. Horário de operação: **[janela operacional]**.

### 9.2 Interface com a controladoria da administradora

- O Custodiante fornece à **controladoria da administradora** do fundo os arquivos de posição e extrato (seção 7.2) que subsidiam a valorização das cotas.
- A responsabilidade pela **controladoria de ativos e de passivos** e pela precificação é da administradora (Res. CVM 175); o Custodiante responde pela **guarda, movimentação, conciliação e informação** das posições (Res. CVM 32).
- Divergências apuradas na conciliação são tratadas conjuntamente conforme fluxo em `manual_conciliacao.md`.

---

## 10. Referência normativa e ressalvas

**Base normativa:** Resolução CVM nº 32, de 19/05/2021, texto consolidado com as alterações da Resolução CVM nº 209/2024, em especial os **arts. 2º, 5º (II), 12, 13 (III, IV e VIII), 14, 15 e 22**; e Resolução CVM nº 175/2022 (fundos de investimento), quanto às interfaces com a administração e controladoria.

**Módulos de evidência (plataforma-piloto, portal `/custodia/`):** `custodia/index.php` (painel), `custodia/instrucoes.php` (instruções, DVP e eventos), `custodia/mensageria.php`, `custodia/arquivos.php`; tabelas `contas_centrais`, `mensagens_spb`, `liquidacoes`, `eventos_corporativos`, `conciliacao`, `log_processamento`.

> **Ressalva.** Este é um documento de trabalho (v1.0). Os campos entre `[colchetes]` devem ser preenchidos e os pontos marcados 🟩 (integrações RSFN/SPB, B3 e SELIC) formalizados na fase de adesões pós-deferimento. **Revisar com as áreas jurídica e de compliance antes do protocolo junto à SMI/CVM.**

---

*Custodiante: [Razão Social do Banco] · CNPJ [nº] · Diretor Responsável pela Custódia: [Nome] · Versão 1.0 · Data: [dd/mm/aaaa]*
