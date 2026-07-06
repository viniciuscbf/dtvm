# Manual de Contingência e Continuidade de Negócios (BCP/DR) da Atividade de Custódia

> **Base legal:** Resolução CVM nº 32, de 19/05/2021 (texto consolidado com as alterações da Resolução CVM nº 209/2024) — **art. 13, IX** (implementar e manter atualizado plano de contingência que assegure a continuidade dos negócios e a prestação dos serviços) e **Anexo A, art. 1º, III, "f"** (o pedido de autorização deve conter plano de contingência e de recuperação de arquivos e banco de dados).
> **Requerente:** `[RAZÃO SOCIAL DA INSTITUIÇÃO]` — CNPJ `[__]` — instituição financeira de menor porte, já autorizada a funcionar pelo Banco Central do Brasil, que constitui uma nova área de custódia.
> **Documento de trabalho — v1.0.** Integra o pacote de comprovação de estrutura operacional e tecnológica do pedido de autorização para prestação de serviços de custódia de valores mobiliários.
> Os campos entre `[colchetes]` devem ser preenchidos/calibrados pelo banco antes do protocolo.

---

## 1. Objetivo e escopo

### 1.1 Objetivo
Estabelecer o **Plano de Continuidade de Negócios (BCP)** e de **Recuperação de Desastres (DR)** da atividade de custódia, de modo a assegurar a **continuidade dos serviços** e a **recuperação de arquivos e banco de dados** diante de eventos que comprometam a operação normal, em cumprimento ao art. 13, IX, e ao Anexo A, art. 1º, III, "f", da Res. CVM 32.

O plano define papéis de crise, métricas de recuperação (RTO/RPO), estratégias de continuidade, rotinas de backup e restauração, gestão de incidentes, cenários de contingência mapeados e o regime de testes periódicos documentados.

### 1.2 Escopo
Aplica-se aos **processos críticos de custódia** suportados pela plataforma (retaguarda `/custodia/` no piloto) e pela infraestrutura de mercado a ela associada:

- **Liquidação por entrega contra pagamento (DVP)** — ciclos D+1 (títulos públicos/renda fixa) e D+2 (renda variável);
- **Mensageria RSFN/SPB** — captura e processamento das mensagens das centrais (`mensagens_spb`);
- **Geração de arquivos diários** — posição custodiada e extrato de conta (art. 14);
- **Conciliação diária** entre contas de custódia e posições dos depositários centrais (art. 13, §1º, I);
- **Processamento em lote (batch)** por fundo, com reprocessamento e versionamento (`fechamentos.versao`).

Suportam a demonstração de **capacidade operacional e tecnológica** (art. 5º, I) e de **segurança física de equipamentos e instalações** (art. 13, VI). A segurança da informação e a retenção de dados são tratadas em `politica_confidencialidade_seguranca.md`; a arquitetura, em `dossie_estrutura_operacional_tecnologica.md`; a correspondência normativa, em `matriz_conformidade_cvm32.md`; e a conciliação, em `manual_conciliacao.md`.

### 1.3 Premissas de infraestrutura
A plataforma-piloto roda em **PHP + MySQL/MariaDB sobre infraestrutura em nuvem** (`..\piloto\`). Em produção, a operação será provida pela infraestrutura do banco `[nuvem/on-prem — provedor e região a definir]`, com alta disponibilidade, criptografia em trânsito e em repouso, e backup automatizado. Onde o plano depender de infraestrutura específica do banco, os valores aparecem entre `[colchetes]` como alvos sugeridos, a serem calibrados e homologados.

---

## 2. Papéis e governança de crise

### 2.1 Comitê de Crise
A resposta a incidentes graves é conduzida por um **Comitê de Crise** de composição mínima:

| Papel | Responsabilidade na crise | Vínculo funcional |
|---|---|---|
| **Coordenador de Crise** | Aciona o plano, declara o nível de severidade, decide sobre acionamento de site alternativo e comunicação externa | **Diretor responsável pelo cumprimento da Res. CVM 32 (art. 17, I)** ou seu delegado |
| **Líder de TI / Infra** | Executa failover, restauração de backup e recuperação de banco de dados | `[Gerente de Tecnologia]` |
| **Líder de Custódia (Mesa/Retaguarda)** | Avalia impacto operacional, prioriza liquidações e reprocessa lotes | `[Coordenador da Mesa de Custódia]` |
| **Compliance / Comunicação** | Comunica CVM, depositários e contratantes; registra evidências | `[Compliance]` |

O **Diretor responsável pela supervisão dos controles internos (art. 17, II)** não integra a coordenação executiva da resposta — para preservar a segregação do art. 17, §2º —, mas **fiscaliza** o cumprimento do plano e reporta as conclusões no relatório anual de controles internos (art. 18). A governança de diretores está descrita em `dossie_estrutura_operacional_tecnologica.md` §4.1.

### 2.2 Acionamento
O plano é acionado quando um incidente ultrapassa a resposta operacional de rotina (ver §8) — tipicamente severidade **Alta** ou **Crítica**. O acionamento segue:

1. **Detecção** do evento (monitoramento automático, alerta de operador ou aviso de terceiro/central);
2. **Notificação** ao Coordenador de Crise e classificação preliminar de severidade;
3. **Convocação** do Comitê de Crise (canal primário `[__]`, canal de contingência `[__]`);
4. **Decisão** sobre estratégia de resposta (mitigar em produção × acionar DR/site alternativo);
5. **Execução, monitoramento e comunicação** até a normalização;
6. **Encerramento e pós-morte** documentado (§8.5).

### 2.3 Cadeia de comunicação
Comunicações a **CVM, depositários centrais e entidades administradoras de mercado** seguem os canais e prazos regulatórios aplicáveis e são registradas como evidência. Contatos, matriz de escalonamento e árvore de chamada são mantidos em anexo operacional `[__]`, revisados a cada teste anual.

---

## 3. Análise de Impacto no Negócio (BIA)

A BIA classifica os processos de custódia por **criticidade** e define a **janela máxima tolerável de indisponibilidade (MTPD)** antes de dano regulatório, financeiro ou reputacional relevante.

| Processo crítico | Impacto da interrupção | Janela máxima tolerável (MTPD) | Criticidade |
|---|---|---|---|
| **Liquidação DVP (D+1 / D+2)** | Falha de liquidação, inadimplemento na central, penalidades e risco sistêmico | Dentro do próprio ciclo de liquidação — `[< 1 dia útil]` | **Crítica** |
| **Mensageria RSFN/SPB** | Perda/atraso de mensagens de confirmação e de eventos; quebra de conciliação | `[< 4h]` na janela operacional | **Crítica** |
| **Geração de arquivos diários (posição/extrato)** | Contratante/administradora sem base para cotização e conciliação | `[< 8h]` (mesmo dia útil) | **Alta** |
| **Conciliação diária** | Divergências não detectadas; descumprimento do art. 13, §1º, I | `[< 1 dia útil]` | **Alta** |
| **Portal de consulta / arquivos ao contratante** | Indisponibilidade de acesso, sem impacto direto na posição | `[< 24h]` | **Média** |
| **Relatórios periódicos (mensal/anual, art. 14)** | Descumprimento de prazo se a interrupção se estender | `[dias]` | **Média** |

> **Nota.** A **liquidação** é o processo mais sensível: sua janela é o próprio ciclo D+1/D+2 e não admite postergação sem risco de falha na central. Por isso recebe o RTO mais agressivo (§5) e prioridade máxima no acionamento.

---

## 4. Métricas de recuperação — RTO e RPO

- **RTO (Recovery Time Objective):** tempo máximo para restabelecer o processo após a interrupção.
- **RPO (Recovery Point Objective):** perda máxima tolerável de dados, medida em tempo desde o último ponto recuperável.

Valores-alvo sugeridos, a serem **calibrados e homologados** pelo banco:

| Processo crítico | RTO (alvo) | RPO (alvo) | Justificativa |
|---|---|---|---|
| **Liquidação DVP** | `[4h]` | `[15min]` | Recuperação dentro do ciclo de liquidação; perda mínima de instruções (`liquidacoes`) |
| **Mensageria RSFN/SPB** | `[2h]` | `[15min]` | Fila `mensagens_spb` recuperável; reprocessamento de status *Erro* |
| **Batch diário / fechamento por fundo** | `[6h]` | `[1h]` | Reprocessável por versionamento (`fechamentos.versao`) |
| **Geração de arquivos (posição/extrato)** | `[4h]` | `[1h]` | Regeneráveis a partir da base íntegra |
| **Conciliação diária** | `[8h]` | `[1h]` | Reexecutável após restauração; trilha em `conciliacao` |
| **Portal / consulta ao contratante** | `[24h]` | `[24h]` | Camada de apresentação; sem escrita crítica |

> **Observação técnica.** RTO/RPO agressivos exigem **replicação síncrona/assíncrona** do banco e backups frequentes (§7). O RPO efetivo é limitado pela frequência de replicação/backup contratada com o provedor de nuvem `[__]`.

---

## 5. Estratégias de continuidade

### 5.1 Alta disponibilidade (HA)
Arquitetura sem ponto único de falha nas camadas críticas: aplicação em `[N ≥ 2]` instâncias atrás de balanceador; banco de dados em configuração de **alta disponibilidade** com réplica `[primário + réplica em standby / cluster gerenciado]`. Objetivo: absorver falhas de instância sem acionamento do plano.

### 5.2 Backup automatizado
Backups automáticos, cifrados e testados, do banco de dados e dos artefatos de configuração (§7). Constituem a última linha de recuperação para corrupção lógica ou perda de dados.

### 5.3 Site alternativo / região secundária
Provisionamento de capacidade de **failover em região secundária** `[região secundária do provedor]`, com dados replicados, apto a assumir a operação dentro dos RTOs da §4. No piloto, a estratégia é demonstrada em desenho; a ativação plena é item de **roadmap de produção**.

### 5.4 Redundância de conectividade RSFN 🟩
A conexão com a RSFN e as infraestruturas de mercado (SELIC, B3) será provida com **redundância de links** e caminhos alternativos, conforme requisitos das centrais. 🟩 **Pós-adesão:** a redundância efetiva de RSFN é implementada e homologada na fase de adesões/homologação, posterior ao deferimento do pedido (ver `dossie_estrutura_operacional_tecnologica.md` §7 — roadmap). Até lá, a mensageria é representada pela fila `mensagens_spb`.

---

## 6. Backup e recuperação de arquivos e banco de dados (Anexo A, art. 1º, III, "f")

### 6.1 Política de backup

| Item | Alvo sugerido | Observação |
|---|---|---|
| **Backup completo do banco** | Diário `[00h — após batch]` | Cifrado; verificação de integridade |
| **Backup incremental / log de transações** | `[a cada 15min–1h]` | Base do RPO da §4 |
| **Arquivos gerados (posição/extrato)** | A cada geração | Regeneráveis, mas versionados |
| **Configuração e código** | A cada release | Versionamento em repositório |
| **Retenção de backups** | `[≥ 90 dias operacional]`; evidências regulatórias por **≥ 5 anos** (art. 22) | Ver `politica_confidencialidade_seguranca.md` §Retenção |
| **Armazenamento** | Redundante e geograficamente separado (região secundária) | Isolado do ambiente primário |

### 6.2 Recuperação (restore)
Procedimento documentado de restauração cobrindo: (i) restauração completa do banco a um ponto no tempo; (ii) recuperação seletiva de tabelas; (iii) regeneração de arquivos diários a partir da base íntegra; (iv) reprocessamento de lotes por versionamento (`fechamentos.versao`) e reprocessamento de mensagens em status *Erro* (`mensagens_spb`).

### 6.3 Teste de restauração
A eficácia do backup é comprovada por **teste de restauração periódico** — mínimo **anual** (§10) — em ambiente segregado, com medição do tempo de recuperação contra o RTO e verificação de integridade dos dados restaurados. Backup sem teste de restauração não é considerado evidência de recuperabilidade.

### 6.4 Imutabilidade de logs
Os registros de trilha e de processamento — `log_processamento` (com `nivel` INFO/WARN/ERRO), `mensagens_spb` (status e `processada_em`/`processada_por`) e `conciliacao` — são preservados de forma **imutável e append-only**, sem edição/exclusão pelos operadores, atendendo ao dever de registro de acessos, erros, incidentes e interrupções (art. 13, V) e à guarda por ≥ 5 anos (art. 22).

---

## 7. Gestão de incidentes (art. 13, V)

### 7.1 Registro obrigatório
A Res. CVM 32, art. 13, V, impõe **registro de acessos, erros, incidentes e interrupções**. A plataforma provê a trilha de incidentes por meio de:

- **`log_processamento`** — eventos das rotinas e do batch, com nível (INFO/WARN/ERRO), autor e horário;
- **`mensagens_spb`** — mensageria com estados *Recebida → Processada → Erro* e reprocessamento (`processada_em`, `processada_por`);
- **`processamento`** — status do processamento diário (OK/Rodando/Pendente/Erro);
- **`conciliacao`** — divergências classificadas (Timing/Erro/Suspeita) com trilha de resolução.

### 7.2 Ciclo de gestão de incidentes
1. **Detecção** — monitoramento do batch e da mensageria; alertas de status *Erro*/nível ERRO; consulta a `processamento`;
2. **Registro** — abertura formal do incidente, referenciando os registros técnicos correlatos;
3. **Classificação de severidade** (§7.3);
4. **Contenção e resposta** — mitigação, failover ou restauração conforme o caso;
5. **Comunicação** — interna (Comitê) e externa (CVM/depositários/contratantes) conforme severidade;
6. **Encerramento e pós-morte** (§7.5).

### 7.3 Classificação de severidade

| Severidade | Critério | Resposta |
|---|---|---|
| **Crítica** | Indisponibilidade de liquidação/mensageria ou risco de falha em central; corrupção de dados; incidente de segurança confirmado | Comitê de Crise imediato; comunicação a CVM/depositários; possível DR |
| **Alta** | Falha em processo crítico com contorno possível no dia; batch travado; conciliação bloqueada | Acionamento parcial; escalonamento ao Coordenador |
| **Média** | Degradação sem perda de dados; erro reprocessável | Tratamento operacional; registro |
| **Baixa** | Falha pontual sem impacto em posição ou prazo | Registro e correção de rotina |

### 7.4 Monitoramento contínuo
Monitoramento do **batch diário** (conclusão, tempo, status em `processamento`), da **mensageria** (acúmulo de status *Erro* em `mensagens_spb`) e da **infraestrutura** (disponibilidade, latência, uso de recursos). Limiares de alerta `[a definir]` disparam notificação automática.

### 7.5 Pós-morte (post-mortem)
Todo incidente de severidade Alta/Crítica gera **relatório de causa-raiz** documentado: linha do tempo, impacto, causa, ações corretivas e preventivas, e prazos. Serve como evidência à CVM e alimenta a revisão do plano.

---

## 8. Cenários de contingência e resposta

| Cenário | Efeito | Resposta primária | Recuperação |
|---|---|---|---|
| **Queda de sistema/aplicação** | Indisponibilidade da retaguarda | Failover para instância/nó redundante (HA, §5.1) | Reinício de serviço; validação de integridade do último batch |
| **Perda de conexão com central/RSFN** | Mensageria interrompida; liquidação bloqueada | Acionar link/caminho redundante 🟩; represar instruções; comunicar central | Reprocessar `mensagens_spb` em *Erro* ao restabelecer; reconciliar |
| **Corrupção de dados** | Base inconsistente; risco de posição incorreta | Isolar; interromper batch; acionar restauração a ponto no tempo | Restore (§6.2) + reprocessamento por `fechamentos.versao`; conciliação de fechamento |
| **Indisponibilidade de pessoal** | Operação sem operador-chave | Acionar suplente/backup de função (segregação, `dossie` §4.2) | Redistribuição de tarefas; procedimentos documentados garantem continuidade |
| **Ciberataque / ransomware** | Comprometimento/cifragem de dados ou acesso indevido | Isolar ambiente; acionar Comitê e resposta a incidentes de segurança | Restaurar de backup imutável não comprometido; comunicação regulatória; ver `politica_confidencialidade_seguranca.md` |
| **Falha do provedor de nuvem (região)** | Indisponibilidade de infraestrutura primária | Acionar **site alternativo / região secundária** (§5.3) | Failover regional; validação de RTO/RPO |

---

## 9. Testes periódicos e documentação

### 9.1 Regime de testes
O plano é exercitado e comprovado por **teste anual, no mínimo, documentado**, abrangendo:

- **Teste de restauração de backup** (§6.3) — com medição de tempo × RTO;
- **Simulação de failover** — HA e/ou região secundária;
- **Exercício de mesa (tabletop)** de acionamento do Comitê de Crise sobre ao menos um cenário da §8;
- **Reprocessamento** de batch (`fechamentos.versao`) e de mensageria (`mensagens_spb` em *Erro*).

### 9.2 Evidência para a CVM
Cada teste gera **relatório documentado**: escopo, data, participantes, resultados, RTO/RPO medidos, falhas observadas e plano de ação. Os relatórios ficam à disposição da CVM e da auditoria interna, comprovando o cumprimento contínuo do art. 13, IX. Deficiências alimentam a **revisão do plano** (mínimo anual ou a cada mudança relevante de infraestrutura/processos).

---

## 10. Retenção de evidências (art. 22)

Toda documentação de continuidade — versões do plano, relatórios de teste, registros de incidentes, pós-mortes e comunicações regulatórias — é retida por, **no mínimo, 5 anos** (art. 22), admitida a digitalização. Logs e trilhas (`log_processamento`, `mensagens_spb`, `conciliacao`) são preservados de forma imutável (§6.4). A política de retenção detalhada consta de `politica_confidencialidade_seguranca.md` §Retenção.

| Evidência | Retenção mínima |
|---|---|
| Plano de continuidade (versões) | 5 anos após substituição |
| Relatórios de teste anual | 5 anos |
| Registros de incidentes e pós-mortes | 5 anos |
| Logs e trilhas de processamento | 5 anos |
| Comunicações a CVM/depositários | 5 anos |

---

## 11. Referências cruzadas

| Documento | Relação |
|---|---|
| `dossie_estrutura_operacional_tecnologica.md` | Arquitetura, ambientes, governança de diretores, roadmap |
| `politica_confidencialidade_seguranca.md` | Segurança da informação, criptografia, acessos, retenção |
| `matriz_conformidade_cvm32.md` | Correspondência dispositivo a dispositivo da Res. CVM 32 |
| `manual_conciliacao.md` | Conciliação diária e trilha de divergências |

---

*Referência normativa: Resolução CVM nº 32/2021 (consolidada, com as alterações da Resolução CVM nº 209/2024) — art. 5º, I; art. 13, V, VI e IX; art. 22; e Anexo A, art. 1º, III, "f". Documento de trabalho — os valores entre `[colchetes]`, os RTO/RPO e os itens de roadmap devem ser **revisados com jurídico, compliance e TI do banco** antes do protocolo.*
