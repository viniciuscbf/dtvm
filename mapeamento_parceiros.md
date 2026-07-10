# Mapeamento de Parceiros — Bancos / DTVMs candidatos à parceria

> **Documento de trabalho (jul/2026).** Lista viva de prospecção do parceiro-licença para a administradora
> de fundos pequenos. Objetivo: achar **uma** instituição elegível, pequena e com a licença **subutilizada**,
> disposta a ser o administrador fiduciário formal enquanto a nossa equipe opera a tecnologia (split 75/25).
> Base do perfil: `requisitos_instituicao_alvo.md`. Nada aqui é abordagem — é pipeline interno.

---

## 1. Perfil-alvo (critérios eliminatórios)

Um candidato só entra no funil se cumpre os quatro:
1. **Tipo elegível** (Res. CVM 21/32): banco comercial / múltiplo / de investimento, caixa econômica, **CTVM** ou **DTVM**. *Fora: cooperativa fora do rol, instituição de pagamento, fintech sem carta bancária.*
2. **Regular** perante BCB e CVM (sem impedimento / processo que atrapalhe).
3. Disposto a **nomear diretores estatutários responsáveis** (pessoas reais da casa) — administração + controles; custódia exige 2.
4. **Apetite e orçamento** para constituir a área nos próximos 12–18 meses.

**Sinais de bom alvo (priorização):** porte pequeno; já tem registro de administrador mas **pouco/nenhum PL** (licença ociosa); base de clientes própria; não é grande administrador fiduciário.

> **A DTVM pequena é o alvo mais doce:** existe para distribuir valores mobiliários, é elegível, costuma ser
> subescala buscando receita nova e raramente tem operação de administração fiduciária própria. Bancos
> pequenos/regionais e corretoras pequenas vêm em seguida.

---

## 2. A questão Febraban

- A Febraban tem **114 associados** (108 instituições financeiras + 6 associações), que representam **~99% dos ativos** e **~97% do PL** do sistema — **porque concentra os grandes**.
- Mas são ~108 instituições de um universo de **centenas**. A **maioria numérica** (bancos pequenos, DTVMs, CTVMs) **não é associada** — DTVM nem orbita a Febraban.
- **Conclusão:** "não associado à Febraban" é um **filtro de exclusão útil** (tira os grandes incumbentes), mas **grosseiro e insuficiente** sozinho — há banco médio associado que não serve e não-associado que também não. Use combinado com **tipo + porte (ativos) + baixa presença em fundos**.
- Lista pública: https://portal.febraban.org.br/pagina/3164/12/pt-br/associados · espelho https://www.buscabanco.org.br/

---

## 3. Método para fechar a lista (funil)

1. **Baixar a Relação de Instituições do BCB** (CSV) e filtrar os **tipos elegíveis**.
2. **Cruzar com o IF.data** por **ativo total** → manter só o **porte pequeno**.
3. **Cruzar com o cadastro de administradores/custodiantes da CVM** → priorizar quem **tem registro mas não aparece** (ou aparece com pouco PL) no **ranking ANBIMA** = licença subutilizada.
4. Aplicar **"não Febraban"** como desempate/exclusão de incumbentes.

**Fontes (URLs):**
- BCB — Relação de Instituições em Funcionamento: https://dadosabertos.bcb.gov.br/dataset/relacao-de-instituicoes-em-funcionamento-no-pais · API Olinda: https://olinda.bcb.gov.br/olinda/servico/Instituicoes_em_funcionamento/versao/v1/documentacao
- BCB — IF.data (porte/ativos): https://www3.bcb.gov.br/ifdata/
- CVM — Administradores de Carteira: https://www.gov.br/cvm/pt-br/assuntos/regulados/consultas-por-participante/administradores-de-carteira · dados abertos: https://dados.cvm.gov.br/group/administradores
- CVM — Custodiantes registrados: https://sistemas.cvm.gov.br/asp/cvmwww/invnres/tabecus.asp
- ANBIMA — Ranking de Administração Fiduciária (quem EXCLUIR): https://www.anbima.com.br/pt_br/informar/ranking/fundos-de-investimento/administradores.htm

---

## 4. Short-list inicial (a validar)

> **[S]** tipo/porte bem estabelecidos · **[P]** pista de mercado, validar tipo/porte na base BCB/CVM.
> ⚠️ = pode já ser prestador de adm. fiduciária ou ser grande demais — **checar exclusão** antes de abordar.
> **Nenhum é grande administrador fiduciário consolidado (esses ficam de fora: BB, Itaú, Bradesco, Santander,
> Caixa, BTG, Vórtx, BRL Trust, Singulare/QI Tech, Daycoval, Genial).**

### Bancos pequenos/médios (múltiplo/comercial/investimento)
| # | Nome | Tipo | Observação |
|---|---|---|---|
| 1 | Banco ABC Brasil | múltiplo | atacado/middle market · **[S]** |
| 2 | Banco Sofisa | múltiplo | médio, crédito/investimentos · **[S]** |
| 3 | Banco Pine | múltiplo | atacado/agro · **[S]** |
| 4 | Banco BMG | múltiplo | consignado · **[S]** |
| 5 | Paraná Banco | múltiplo | consignado/seguros, regional PR · **[S]** |
| 6 | Banco Fibra | múltiplo | crédito corporativo · **[S]** |
| 7 | Banrisul | múltiplo estadual | regional RS · **[S]** |
| 8 | Banco da Amazônia (BASA) | regional | Norte · **[S]** |
| 9 | Banco BS2 | múltiplo | digital/PJ, pequeno-médio · **[P]** |
| 10 | Banco Rendimento | múltiplo | câmbio/serviços · **[P]** |
| 11 | Banco Semear | pequeno | MG · **[P]** |
| 12 | Banco Topázio | pequeno | serviços/câmbio · **[P]** |
| 13 | Banco Pottencial | pequeno | MG · **[P]** |
| 14 | Banco Ribeirão Preto | pequeno | regional SP · **[P]** |
| 15 | Banco Paulista | pequeno | câmbio/serviços · **[P]** |
| — | Banco do Nordeste (BNB) | regional | ⚠️ porte grande — validar |

### DTVMs / CTVMs independentes de menor porte
| # | Nome | Tipo | Observação |
|---|---|---|---|
| 16 | Terra Investimentos DTVM | DTVM | pequena · **[P]** |
| 17 | Renascença DTVM | DTVM | nicho câmbio/TVM · **[P]** |
| 18 | Lastro RDV DTVM | DTVM | nicho · **[P]** |
| 19 | Codepe CVM | CTVM | pequena/tradicional · **[P]** |
| 20 | Coinvalores CCVM | CTVM | pequena/tradicional · **[P]** |
| 21 | Nova Futura CTVM | CTVM | pequena · **[P]** |
| 22 | Necton Investimentos | CTVM | pequena · **[P]** |
| 23 | Solidus CCVM | CTVM | pequena tradicional · **[P]** |
| 24 | Magliano CCVM | CTVM | pequena tradicional · **[P]** |
| 25 | Socopa (Corretora Paulista) | CTVM | ligada ao Banco Paulista; faz serviços qualificados · **[P]** |
| — | Planner / Trustee DTVM | DTVM | ⚠️ histórico de adm. fiduciária — pode ser incumbente, validar |

---

## 5. Próximos passos
- [ ] Baixar a Relação BCB (CSV/Olinda) e o IF.data → **lista verificada por tipo + ativos**.
- [ ] Cruzar com o cadastro CVM de administradores → marcar **licença ociosa**.
- [ ] Reduzir a **5–10 alvos prioritários** (DTVMs pequenas primeiro).
- [ ] Para cada prioritário: achar o **decisor** (diretoria) e o canal de contato.
- [ ] Enviar o deck (`apresentacao/`) só depois da primeira conversa exploratória — a regra é sair da reunião com a **reunião técnica** marcada.

*Documento de trabalho. Os nomes [P] são pistas de mercado, não extração verificada — validar na base BCB/CVM antes de abordar.*
