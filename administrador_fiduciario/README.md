# Pacote de Autorização e Estruturação — Administrador Fiduciário (Res. CVM 21 + 175)

> **Documento de trabalho — v1.0** · Diretório `administrador_fiduciario/`
> **Objetivo:** reunir a **comprovação de estrutura** para uma instituição financeira de menor porte (já autorizada pelo BCB) **constituir uma área de administração fiduciária** e obter o **registro na categoria administrador fiduciário** (Res. CVM 21/2021, pedido à **SIN**), tendo a plataforma-piloto (`../piloto/`, portal `/admin/`) como **anexo técnico**, e preparando a operação sob a **Res. CVM 175/2022**.

---

## Duas normas, um pacote

| Norma | Papel no pacote |
|---|---|
| **Res. CVM 21/2021** | *Quem pode ser* administrador fiduciário e *como se autoriza* (registro na **SIN**, categoria administrador fiduciário, Anexo C + Anexo E). |
| **Res. CVM 175/2022** | *O que o administrador faz* na operação do fundo (deveres, controladoria, envios, assembleias, auditoria, responsabilidade). |

**Princípio (igual ao custodiante):** para o *registro*, a norma exige **capacidade demonstrável + estrutura**, não a operação plena de cotistas rodando. Onde a plataforma simula ou ainda não implementa, marcamos 🟩.

---

## Perfil da instituição-alvo e premissa de elegibilidade

Mesma tese do custodiante: **instituição financeira de menor porte** (banco de interior, caixa, CTVM/DTVM pequena) **já autorizada pelo BCB**, que constitui a área de administração fiduciária **do zero**. Como é autorizada pelo BCB, é elegível pelo **art. 1º, §2º, I, da Res. 21** — **sem** depender da via patrimonial do inciso II (0,20% dos recursos sob administração ou > R$ 550 mil).

> ⚠️ **Pré-requisito:** se a instituição-alvo ainda não for autorizada pelo BCB, essa autorização é **etapa anterior e independente**, fora deste pacote.

O **arranjo de papéis** entre você (estruturador) e a instituição — parceria externa ou estruturação interna — está em [`../custodiante/modelo_parceria_papeis.md`](../custodiante/modelo_parceria_papeis.md) (as mesmas Versões A/B valem aqui; a fronteira de terceirização, porém, segue a Res. 21/175, não a Res. 32).

---

## Conteúdo do diretório

| # | Arquivo | O que é | Base |
|---|---|---|---|
| — | **[README.md](README.md)** | Este índice | — |
| 0 | **[matriz_conformidade_cvm21_175.md](matriz_conformidade_cvm21_175.md)** | **Espinha** — dispositivo → evidência → status, cruzando Res. 21 e 175 | ambas |
| 1 | **[dossie_estrutura_operacional_tecnologica.md](dossie_estrutura_operacional_tecnologica.md)** | **Anexo técnico** do pedido à SIN | Res. 21 art. 4º; Anexo C/E |
| 2 | **[manual_controladoria_precificacao.md](manual_controladoria_precificacao.md)** | Cota/PL, MaM, contabilidade, registros | Res. 175 arts. 66, 104 |
| 3 | **[manual_conciliacao.md](manual_conciliacao.md)** | Conciliação e fiscalização do custodiante | Res. 21 art. 32; Res. 175 art. 81 |
| 4 | **[manual_regulatorio_envios.md](manual_regulatorio_envios.md)** | Envios CVM, demonstrações/auditoria, ofícios, assembleias | Res. 175 arts. 61–79 |
| 5 | **[manual_risco_liquidez.md](manual_risco_liquidez.md)** | Gestão conjunta de risco de liquidez | Res. 21 art. 26 §4º; Res. 175 art. 92 |
| 6 | **[manual_passivo_tributacao.md](manual_passivo_tributacao.md)** | Passivo do cotista + tributação (responsável tributário) | Res. 175 art. 104; Lei 14.754 |
| 7 | **[politica_confidencialidade_seguranca.md](politica_confidencialidade_seguranca.md)** | Segurança, segregação e confidencialidade | Res. 21 arts. 4º §8º, 27–28, 30 |
| 8 | **[requerimento_anexoC_checklist.md](requerimento_anexoC_checklist.md)** | Minuta de requerimento à SIN + checklist do Anexo C/E | Res. 21 arts. 6º–7º |

**Ordem de leitura:** README → Matriz (0) → Requerimento/Anexo C (8) → Dossiê (1) e manuais (2–7).

---

## Diferenças importantes em relação ao pacote de custódia

1. **Órgão e via:** aqui é a **SIN** com **Anexo C** (Res. 21) — na custódia era a **SMI** com Anexo A (Res. 32). Não confundir.
2. **Auditoria independente É exigida** (Res. 175, art. 69) — das demonstrações do fundo. Na custódia, a Res. 32 exigia só auditoria **interna**.
3. **Responsabilidade segregada, sem solidariedade automática** (Res. 175, art. 81).
4. **Enquadramento é dever do gestor** (Res. 175, art. 89) — o administrador **fiscaliza**, não enquadra.
5. **Mais itens 🟩:** o administrador é **responsável tributário** (Lei 14.754) e o **passivo do cotista** (aplicações/resgates, come-cotas, IR) é o **maior módulo ausente** do piloto — declarado honestamente na matriz e no `manual_passivo_tributacao.md`.

---

## O que depende da instituição antes do protocolo

- [ ] **Designar os diretores** (art. 4º, III e IV) em ata — administração fiduciária + controles internos (independente);
- [ ] **Aprovar os manuais e políticas** na diretoria;
- [ ] **Preencher o Anexo E** (formulário de referência; campos "FA" são facultativos ao administrador fiduciário) e coletar **atos societários** com objeto social de administração de carteiras;
- [ ] Preencher os `[colchetes]`;
- [ ] **Revisão jurídica/compliance** antes do protocolo.

Detalhe no [checklist do Anexo C](requerimento_anexoC_checklist.md).

---

## Depois da autorização: operar sob a Res. 175 (roadmap 🟩)

Obtido o registro (Res. 21), para operar fundos com cotistas reais faltam os módulos de produção: **passivo do cotista + tributação** (o maior), **contabilidade de acrual/COSIF**, **MaM homologada**, **integrações reais** de envio e **risco de liquidez** de produção. Priorização em [`../relatorio_gaps_producao_licenca.md`](../relatorio_gaps_producao_licenca.md).

---

## Avisos

1. **Precisão normativa:** os prazos/leiautes de **informe diário, CDA e perfil mensal** estão nos **Anexos Normativos por categoria** da Res. 175 — este pacote **não cita número de artigo** para eles (a confirmar no anexo da categoria do fundo).
2. **Natureza:** pacote de **trabalho** — revisar com **jurídico e compliance** antes de qualquer protocolo na CVM.

---

*Base legal: Resolução CVM nº 21/2021 e Resolução CVM nº 175/2022 (textos consolidados oficiais da CVM); Lei 14.754/2023.*
