# Relatório de Lacunas — Tudo que Falta para a Estrutura Completa (Produção + Licença de Custodiante)

> **Documento de trabalho — v1.0 (jul/2026)**
> O piloto demonstra o **fluxo operacional** de ponta a ponta. Este relatório lista, de forma exaustiva,
> o que ainda precisa existir para (a) **operar de verdade** como administrador fiduciário + custodiante e
> (b) **protocolar o pedido de licença** na CVM. Organizado em 12 blocos, do mais bloqueante ao mais evolutivo,
> com um cronograma consolidado no final. Referências: Res. CVM 175 (fundos), Res. CVM 32 (custódia),
> Res. CVM 50 (PLD/FT), Res. CVM 21 (gestores), Lei 14.754/2023 (tributação), Res. CMN 4.893 (cibersegurança).

---

## Bloco 1 — Passivo do cotista (o maior módulo ausente)

O piloto trata o cotista como posição estática. Em produção, o passivo é um motor próprio:

- [ ] **Onboarding do cotista**: cadastro completo (PF/PJ), KYC documental, FATCA/CRS, **suitability** (perfil × público-alvo do fundo), termo de adesão eletrônico com trilha de aceite;
- [ ] **Aplicações**: ordem → cotização em D+0/D+1 (conforme regulamento) → conversão em cotas pela cota da data → liquidação financeira (débito via TED/PIX na conta do fundo);
- [ ] **Resgates**: ordem → fila de resgate → cotização D+N → liquidação D+M; bloqueios (carência, lock-up de fundos fechados); **resgate compulsório** para come-cotas;
- [ ] **Tributação — a parte mais sensível (Lei 14.754/2023)**: o **administrador fiduciário é o responsável tributário**. Motor de: come-cotas semestral (último dia útil de **maio e novembro**; **15%** longo prazo, **20%** curto prazo, com redução de cotas do cotista), IR na fonte em resgates (tabela regressiva 22,5%→15% conforme prazo), **IOF** (< 30 dias), controle do custo médio por cotista, exceções (FII, Fiagro, ETF RF, FIDC 12.431, FIP-IE — se algum dia entrarem no escopo);
- [ ] **Informe de rendimentos anual** por cotista (DIRF/e-Financeira para a Receita);
- [ ] **Extrato do cotista** e posição consolidada; comunicação obrigatória de eventos (assembleias, alterações de regulamento com direito de retirada).

**Impacto se faltar:** sem isso não se opera um único cotista real — é pré-requisito absoluto de produção.

## Bloco 2 — Contabilidade e provisionamento (a cota "de verdade")

No piloto a cota = (ativos + caixa)/cotas. Em produção a cota é **contábil**:

- [ ] **Acrual diário de despesas**: taxa de administração, gestão e custódia provisionadas **pro-rata dia a dia** dentro da cota (não só apuradas no fim do mês); taxa de performance com linha d'água por cotista; provisão de auditoria, taxa de fiscalização CVM, cartório/publicações;
- [ ] **Plano contábil de fundos (COSIF/padrão CVM)**: lançamentos em partidas dobradas, balancete mensal no leiaute exigido (é ele que vai à CVM em 10 dias úteis), razão e diário;
- [ ] **Eventos de renda fixa**: acrual de juros (curva do papel) diário, amortizações, ágio/deságio; **eventos de renda variável**: dividendos/JCP provisionados na data ex (não na data de pagamento — o piloto simplifica);
- [ ] **Fechamento mensal e anual**: demonstrações contábeis, notas explicativas, suporte à **auditoria independente** anual;
- [ ] **Ajustes a valor justo** níveis 1/2/3 com documentação (exigência de auditoria).

## Bloco 3 — Precificação de produção (MaM homologada)

- [ ] **Manual de Precificação formal** (aprovado no credenciamento, publicado): cascata de fontes por classe de ativo (ANBIMA → B3 → modelo → comitê), tratamento de feriados/ativos sem negócio, spreads de crédito;
- [ ] **Captura automática**: curvas e taxas ANBIMA (feed), fechamento B3, PU de debêntures/CRI/CRA;
- [ ] **Comitê de precificação formal**: agenda, atas, alçadas, backtesting periódico das marcações de ilíquidos (a CVM cobra a governança — vide ofício sobre ativos ilíquidos);
- [ ] **Preço em D0 com defasagem D-1 tratada** (timing de fontes) e reprecificação retroativa com trilha.

## Bloco 4 — IA de conformidade documental (o "leitor de regulamento") e demais IAs

O diferencial de automação prometido no plano, agora especificado:

- [ ] **IA de análise de regulamento (pré-registro CVM)**: pipeline que recebe a minuta e:
  1. **Extrai os campos estruturados** — classe/anexo normativo, público-alvo, condomínio, taxas, política de investimento, limites por ativo/emissor/modalidade, prazos de cotização/liquidação, benchmark, tributação-alvo;
  2. **Valida contra a Res. CVM 175** — checklist dos dispositivos obrigatórios do regulamento (conteúdo mínimo), coerência interna (ex.: política declara "até 40% em crédito privado" mas o limite de enquadramento diz 50%), compatibilidade com o anexo normativo da classe, aderência ao público-alvo (ex.: ativo restrito a qualificado num fundo de varejo → apontar);
  3. **Cruza com a operação**: os limites extraídos alimentam automaticamente as **regras de enquadramento** do motor (hoje cadastradas à mão no piloto) e o cadastro de taxas;
  4. **Emite parecer**: "apto para protocolo" ou lista de não-conformidades com o artigo citado — o humano (jurídico/compliance) revisa e assina. Mesmo pipeline para **lâmina** (cenários obrigatórios ANBIMA) e **termo de adesão**;
- [ ] **IA de fraude real** (substituindo o motor de regras do piloto): modelos de anomalia treinados na trilha (movimentações, remarcações, padrões de resgate), com backtesting e comitê de revisão de alertas;
- [ ] **IA de conciliação**: classificação automática de divergências (timing × erro × suspeita) aprendida do histórico de resoluções;
- [ ] **OCR + validação documental** na abertura de fundos (contrato social, atos CVM, certidões — validade e autenticidade);
- [ ] **Monitor regulatório**: leitura automática de ofícios/normas novas da CVM/ANBIMA com resumo e impacto nos fundos da casa.

## Bloco 5 — Risco e enquadramento de produção

- [ ] **Checagem pré-trade**: a boleta do gestor validada **antes** do aceite contra os limites do regulamento (o piloto só mede pós);
- [ ] **Gestão de risco de liquidez** (norma ANBIMA/CVM): ativos líquidos × perfil do passivo, testes de estresse de resgate, ferramentas de liquidez (side pockets, gates — Res. 175);
- [ ] Métricas: VaR/vol/DV01 por fundo, concentração por emissor/grupo econômico, risco de contraparte;
- [ ] **Desenquadramento**: matriz de prazos de reenquadramento por tipo (passivo 15 dias etc.) com workflow de comunicação à CVM quando aplicável.

## Bloco 6 — Derivativos e colateral (se o escopo incluir multimercados de verdade)

- [ ] Ajustes diários de futuros (B3), chamadas de **margem**, gestão de garantias depositadas;
- [ ] Precificação de swaps/opções/NDF e registro em balcão B3;
- [ ] Conta de margem segregada por fundo no clearing.

## Bloco 7 — Integrações reais (substituir tudo que o piloto simula)

| Integração | O que substitui no piloto | Observação |
|---|---|---|
| **RSFN** (mensageria SPB: SEL/STR) | `mensagens_spb` | links duplos, certificação digital, **homologação formal BCB/B3** com roteiro de testes |
| **B3 Central Depositária + Balcão** (arquivos/API de posição e movimentação) | `custodia/arquivos.php` | leiautes oficiais de posição, eventos e movimentação |
| **SELIC** (posição e liquidação de títulos públicos) | idem | via participante liquidante |
| **HUB ANBIMA** (envio de carteiras/estatísticas) | `envios_regulatorios` ANBIMA | API/arquivo padronizado |
| **CVMWeb / Fundos.NET** (informe diário, CDA, balancete, perfil, DFs, fatos relevantes) | `envios_regulatorios` CVM | protocolo real com recibo |
| **Feeds de preço** (ANBIMA, B3 market data) | `preco_referencia` do seed | contratos de dados |
| **Banco liquidante/contas** (TED/PIX das aplicações e resgates) | `movimentacoes` manuais | conta do fundo no próprio banco parceiro |
| **e-Financeira / DIRF** (Receita) | — | obrigações do responsável tributário |

## Bloco 8 — PLD/FT transacional (Res. CVM 50)

- [ ] Política PLD/FT aprovada + diretor responsável; **KYC contínuo** (atualização cadastral periódica, PEP, sanções/OFAC);
- [ ] **Monitoramento transacional**: regras de atipicidade sobre aplicações/resgates (fracionamento, incompatibilidade patrimonial), fila de análise, dossiês;
- [ ] **Comunicação ao COAF** (operações atípicas em 24h; comunicação negativa anual);
- [ ] Avaliação interna de risco (ABR) bienal.

## Bloco 9 — Segurança, infraestrutura e continuidade

- [ ] Política de **segurança cibernética** (Res. CMN 4.893 se dentro do banco): gestão de incidentes, testes de intrusão, criptografia em repouso/trânsito;
- [ ] **LGPD**: base legal, DPO, inventário de dados de cotistas;
- [ ] Ambientes segregados (dev/homolog/prod), **logs imutáveis** e trilha de auditoria completa (quem viu/alterou o quê — o piloto já tem o embrião), MFA e gestão de acessos por papel;
- [ ] **BCP/DR**: RTO/RPO definidos, site alternativo, teste anual documentado (a CVM avalia isso na capacidade operacional da Res. 32);
- [ ] Monitoramento 24×7 do batch e da mensageria com alertas.

## Bloco 10 — Governança, gente e manuais

- [ ] **Diretores estatutários** designados: administração fiduciária, custódia (pode acumular? verificar vedações), PLD, risco/compliance;
- [ ] **Manuais operacionais escritos** (a CVM pede na Res. 32): custódia, controladoria, precificação, liquidação, conciliação, contingência;
- [ ] Equipe mínima: mesa de custódia (2–3), controladoria (2–3), compliance/PLD (1–2), TI (2+) — mesmo com automação, os nomes precisam existir;
- [ ] **Auditoria interna/controles internos** e contratação da **auditoria independente** dos fundos (pulverizada — ver risco de independência no plano);
- [ ] Seguro E&O / responsabilidade civil profissional.

## Bloco 11 — O processo de licenciamento como custodiante (checklist de protocolo)

Consolidando a Res. CVM 32 + adesões (detalhe em `guia_custodia_conexoes.md`):

1. [ ] Banco autorizado BCB (já assumido na parceria);
2. [ ] Dossiê CVM: requerimento, dados institucionais, **descrição da estrutura operacional e tecnológica** (aqui entram os manuais + a plataforma — o piloto evoluído é literalmente o anexo técnico), diretor responsável, organograma, plano de contingência, política de confidencialidade;
3. [ ] Aprovação CVM → adesões: **B3 Central Depositária** (agente de custódia) + **Balcão** + **Termo de Adesão Participante Selic** (liquidante, com conta Reservas);
4. [ ] **Homologação RSFN/mensageria** (testes obrigatórios com BCB/B3);
5. [ ] Adesão ao **Código ANBIMA de Serviços Qualificados** + certificação dos profissionais (CPA/CGA conforme função);
6. [ ] Ambiente de **certificação B3/SELIC**: rodar o ciclo completo de testes;
7. [ ] Go-live assistido com fundos-piloto.

## Bloco 12 — Comercial/distribuição (opcional, mas planejar)

- [ ] Se distribuir cotas diretamente: suitability + material publicitário conforme ANBIMA; alternativa mais leve: **integrar distribuidores parceiros** (assessorias) via API de aplicações/resgates — que é justamente o canal comercial do plano.

---

## Cronograma consolidado (estimativa honesta)

| Fase | Conteúdo | Duração | Dependências |
|---|---|---|---|
| 1 | Blocos 1–2 (passivo + contabilidade/provisionamento) | 4–6 meses | nenhuma — começa já |
| 2 | Blocos 3–5 (MaM produção, IA regulamento, risco pré-trade) | 3–4 meses | paralela à 1 |
| 3 | Bloco 7 (integrações) + Bloco 11 (licença/adesões) | 4–6 meses | banco parceiro assinado |
| 4 | Blocos 8–10 (PLD, segurança, governança, manuais) | 2–3 meses | paralela; manuais antes do protocolo CVM |
| 5 | Certificação + operação assistida | 2–3 meses | tudo acima |

**Total realista: 12–18 meses** do contrato com o banco ao go-live pleno — com o piloto servindo de especificação
funcional viva e de anexo técnico da capacidade operacional no protocolo da Res. 32.

## Priorização em uma frase

**Bloqueia a licença:** Blocos 10–11 (manuais, diretores, dossiê) + prova de capacidade tecnológica (Blocos 2, 7, 9).
**Bloqueia operar cotista real:** Blocos 1–3 (passivo, tributação, contabilidade, MaM).
**Diferencia e escala:** Bloco 4 (IAs — regulamento, fraude, conciliação) e Bloco 5 (pré-trade).

*Fontes: Res. CVM 32, 175, 50 e 21; Lei 14.754/2023 (come-cotas mai/nov 15%/20%, administrador como responsável tributário) e regulamentação RFB/CMN; manuais B3/SELIC; Res. CMN 4.893.*
