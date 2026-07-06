# Pacote de Autorização para Prestação de Serviços de Custódia — Res. CVM nº 32/2021

> **Documento de trabalho — v1.0** · Diretório `custodiante/`
> **Objetivo:** reunir, num único conjunto, a **comprovação de estrutura operacional e tecnológica** necessária para instruir o pedido de autorização de custódia de valores mobiliários (Res. CVM 32/2021, consolidada com a Res. CVM 209/2024) por uma **instituição financeira de menor porte que constitui uma nova área de custódia, do zero, com apoio na estruturação**, tendo a plataforma-piloto (`../piloto/`, portal `/custodia/`) como **anexo técnico** que demonstra os fluxos exigidos.

---

## Para que serve este pacote

A CVM **não recebe código-fonte**: ela recebe um **requerimento** (art. 6º) instruído com os documentos do **Anexo A**, que descrevem e comprovam a capacidade organizacional, técnica, operacional e financeira do requerente (art. 5º). A plataforma é a **evidência demonstrável**; estes documentos são o **instrumento regulatório** que a traduz para o que a norma exige.

Princípio que orienta todo o pacote: para o **protocolo**, a Res. 32 exige **capacidade demonstrável + plano** — não exige integrações vivas (RSFN homologada, adesões B3/SELIC) no momento do pedido. Essas habilitações ocorrem **após** o deferimento. Onde a plataforma simula, marcamos 🟩 (roadmap) e remetemos ao cronograma de adesões.

---

## Perfil da instituição-alvo e premissa de elegibilidade

Este pacote é desenhado para **instituições financeiras de menor porte** — bancos comerciais, múltiplos ou de investimento de pequeno porte (inclusive de praças do interior), caixas, CTVMs e DTVMs — que **já são autorizadas pelo BCB mas ainda não têm área de custódia**. A proposta é ajudá-las a **constituir essa área do zero**: sistemas, manuais, governança e o pedido à CVM. Não pressupõe estrutura pronta — ela é construída ao longo do projeto.

> ⚠️ **Pré-requisito de elegibilidade (art. 4º).** A autorização de custódia só pode ser **requerida por quem já é** um dos tipos do art. 4º (banco/caixa/CTVM/DTVM/entidade de mercado autorizado pelo BCB). Se a instituição-alvo ainda **não** for elegível (ex.: instituição de pagamento, fintech sem carta bancária), obter antes essa autorização no BCB é **etapa anterior e independente**, fora do escopo deste pacote. Confirme o enquadramento antes de montar o dossiê — detalhe no [checklist do Anexo A](requerimento_anexoA_checklist.md) §2.

O **arranjo de papéis** entre você (estruturador) e a instituição — **parceria com empresa externa** ou **estruturação interna** — e a fronteira do que pode/não pode ser terceirizado (art. 19) estão em [modelo_parceria_papeis.md](modelo_parceria_papeis.md). Em ambos, **a custodiante é sempre a instituição**.

---

## Conteúdo do diretório

| # | Arquivo | O que é | Atende (Res. 32) |
|---|---|---|---|
| — | **[README.md](README.md)** | Este índice: propósito, como ler, governança documental | — |
| 0 | **[matriz_conformidade_cvm32.md](matriz_conformidade_cvm32.md)** | **Espinha do pacote.** Mapa dispositivo→exigência→como é atendido→evidência→status. Prova de suficiência | Todos os dispositivos |
| 1 | **[dossie_estrutura_operacional_tecnologica.md](dossie_estrutura_operacional_tecnologica.md)** | **Anexo técnico do requerimento.** Objeto, arquitetura, fluxos, governança, controles, contingência, roadmap | Art. 5º; Anexo A III e IV |
| 2 | **[manual_custodia.md](manual_custodia.md)** | Manual operacional de custódia: contas, guarda, eventos, informação | Arts. 12, 13, 14, 15 |
| 3 | **[manual_liquidacao_dvp.md](manual_liquidacao_dvp.md)** | Manual de liquidação por entrega contra pagamento (DVP) e ciclos | Art. 13, II e III |
| 4 | **[manual_conciliacao.md](manual_conciliacao.md)** | Manual de conciliação **diária** custodiante × depositário central | Art. 13, §1º, I; art. 3º |
| 5 | **[manual_contingencia_continuidade.md](manual_contingencia_continuidade.md)** | Plano de continuidade de negócios (BCP/DR): RTO/RPO, backup, incidentes | Art. 13, IX; Anexo A III "f" |
| 6 | **[politica_confidencialidade_seguranca.md](politica_confidencialidade_seguranca.md)** | Política de confidencialidade e segurança da informação; sigilo e acesso | Arts. 5º I, 13 §1º II; Anexo A III "c"/"e" |
| 7 | **[requerimento_anexoA_checklist.md](requerimento_anexoA_checklist.md)** | Minuta de requerimento à SMI + checklist de completude do Anexo A + fluxo processual | Arts. 6º, 7º; Anexo A |
| 8 | **[minuta_contrato_custodia.md](minuta_contrato_custodia.md)** | Modelo de contrato de prestação de serviços de custódia | Art. 10; Anexo A VIII |
| 9 | **[modelo_parceria_papeis.md](modelo_parceria_papeis.md)** | Papéis do estruturador × instituição em 2 versões (parceria externa / estruturação interna) e a fronteira da terceirização | Arts. 4º, 5º, 17, 19 |

**Ordem de leitura sugerida:** README → Matriz (7) para ver o todo → Requerimento/Anexo A (7) para o protocolo → Dossiê (1) e manuais (2–6) como anexos → Contrato (8).

---

## Como a plataforma-piloto entra como evidência

O portal `/custodia/` do piloto demonstra, funcionando, as três funções nucleares que a Res. 32 exige comprovar, mais os processos de suporte:

| Função (Res. 32) | Módulo-piloto | Documento que descreve |
|---|---|---|
| Guarda segregada por fundo (art. 12) | `contas_centrais` · `custodia/index.php` | Manual de Custódia |
| Liquidação DVP (art. 13, III) | `liquidacoes` · `custodia/instrucoes.php` | Manual de Liquidação DVP |
| Eventos corporativos (art. 13, III) | `eventos_corporativos` | Manual de Custódia |
| Mensageria RSFN/SPB (art. 5º, II) 🟩 | `mensagens_spb` · `custodia/mensageria.php` | Dossiê §3.4 |
| Arquivos de posição/extrato (art. 14) | `custodia/arquivos.php` | Manual de Custódia |
| Conciliação diária (art. 13, §1º, I) | `conciliacao` · `admin/conciliacao.php` | Manual de Conciliação |
| Controle de acesso e sigilo (arts. 5º I, 13 §1º II) | `includes/auth.php` (bcrypt, perfis) | Política de Confidencialidade |

---

## O que ainda depende do banco antes do protocolo

Estes itens **não são lacuna tecnológica** — são formalização e coleta:

- [ ] **Designar os dois diretores estatutários** em ata (art. 17: cumprimento da norma + supervisão de controles internos, funções **não cumuláveis**);
- [ ] **Aprovar os manuais e políticas** deste pacote na diretoria do banco (torná-los oficiais e versionados);
- [ ] **Coletar os anexos societários** (atos constitutivos, representantes legais, participações — Anexo A II, V, VII) e **contratos de software** (Anexo A III "g");
- [ ] **Preencher os campos `[colchetes]`** com os dados reais do banco;
- [ ] **Revisão jurídica/compliance** de todo o pacote antes do protocolo.

Detalhamento no [checklist do Anexo A](requerimento_anexoA_checklist.md).

---

## Roadmap pós-deferimento (itens 🟩)

Após a autorização da CVM: adesões B3 (Central Depositária + Balcão) e Termo de Adesão Participante Selic (liquidante); homologação RSFN/mensageria com BCB/B3; adesão ao Código ANBIMA de Serviços Qualificados; certificação B3/SELIC; go-live assistido. Cronograma de referência: **12–18 meses** (ver `../relatorio_gaps_producao_licenca.md` e `dossie...` §7).

---

## Governança documental (art. 16, §1º)

As regras, procedimentos e controles internos devem ser **escritos**, passíveis de verificação e **disponíveis à CVM**, aos depositários centrais e à autorregulação (art. 16, §1º, I–III). Convenções deste diretório:

- **Versionamento:** cada documento traz `v1.0` no cabeçalho; alterações incrementam a versão e devem ser reaprovadas pela diretoria.
- **Retenção:** documentos e evidências guardados por **≥ 5 anos** (art. 22).
- **Status:** ✅ Atendido · 🟡 Parcial (demonstrável, formalizar) · 🟩 Roadmap (pós-deferimento).

---

## Avisos importantes

1. **Precisão normativa:** a Res. CVM 32 exige **auditoria interna** (arts. 18 e 20) — **não** exige relatório de auditor independente/asseguração sobre a custódia. Menções a "relatório de auditor tipo 1" em documentos de planejamento anteriores referem-se a boa prática de mercado/ANBIMA, não a requisito da norma.
2. **Terminologia:** a norma usa "**contas de custódia individualizadas**" (art. 12), não "conta cliente 1/2".
3. **Natureza:** este é um pacote de **trabalho**. Deve ser revisado pelo **jurídico e pelo compliance do banco** antes de qualquer protocolo na CVM.

---

*Base legal: Resolução CVM nº 32/2021, consolidada com a Resolução CVM nº 209/2024. Fonte primária: PDF consolidado oficial da CVM.*
