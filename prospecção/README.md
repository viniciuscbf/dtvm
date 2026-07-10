# Prospecção de Parceiros — Argus

Pipeline de dados para achar a instituição que vai **hospedar** a administradora fiduciária de
fundos pequenos (ela assina/tem a licença; nós operamos).

## Regras do alvo (suas)

- **Filtro inegociável:** **não** pode ser **administrador fiduciário**.
- **Excluir DTVM** (distribuidora). **CTVM** (corretora) **pode**. **Custodiante pode** (vira coluna).
- **Ampliar para não-bancárias com capital** (SCD/fintech, IP, financeira, câmbio, hipotecária).
- **Tudo o mais (fintech, estrangeiro, captivo, estatal…) é COLUNA, não filtro** — você filtra no Excel.

## Arquivos

| Arquivo | O que é |
|---|---|
| `build_lista.py` | Script: BCB (universo + Ativo Total) × CVM (licenças) → Excel. |
| `bancos_alvo.xlsx` | **A entrega** — Master + Alvo A + Alvo B + Metodologia. |
| `bancos_alvo.csv` | Backup (Alvo A + B). |
| `concorrentes.py` | Raio-X dos administradores fiduciários/DTVMs (nº fundos, PL, foco). |
| `concorrentes.xlsx` | Cenário competitivo (gerado). |
| `fontes_apis.md` | APIs usadas + outras de CNPJ. |
| `dados/` | Cache. Apague para forçar dados novos. |

## O que o Excel traz (com autofiltro em todas as abas)

Colunas: **Instituição · Segmento BCB · Perfil · Elegibilidade · Grupo tem DTVM/adm.fid.? ·
CNPJ · UF · Município · Ativo Total (R$) · Início atividade · Gestor CVM? · Nº fundos que gere ·
Adm.fiduciário? · Custodiante? · Nº classes que custodia · PL custodiado (R$) · Telefone · E-mail · Site**.

> **Coluna `Grupo tem DTVM/adm.fid.?`** — pega os casos como **Vórtx SCD**, cujo CNPJ é de outra
> empresa do grupo, mas o **grupo já tem uma DTVM/administrador fiduciário** (não é folha em branco).
> Heurística: compara a **marca** (1º nome não-genérico, sem acento/minúsculo, ignorando "banco",
> "agência", "sociedade", "de"…) de cada instituição com a marca de todas as DTVMs e administradores
> fiduciários. Se bater, marca "sim — <marca>" e **exclui dos Alvos A/B** (fica no Master p/ revisão;
> se achar um falso-positivo, me avise que eu incluo a marca na whitelist).

> Enriquecido: **Início de atividade** (idade), **Nº de fundos que já gere** e **Nº de classes /
> PL que já custodia** (cruzando o registro de fundos da CVM) — mostram o quão próxima do mundo de
> fundos a instituição já está (ex.: Banco Paulista custodia 19 classes / R$ 2,6 bi; Deutsche gere 4).

- **Master (tudo)** — as **864** instituições autorizadas, classificadas por tudo.
- **Alvo A — banco e CTVM (190)** — elegíveis **direto** (bancos não-câmbio + CTVMs) que **não** são
  administradores fiduciários. Ordenados por **Ativo Total (menor primeiro)**.
- **Alvo B — não-bancária (487)** — SCD/IP/financeira/câmbio/hipotecária: têm estrutura, mas
  **teriam que virar DTVM/custodiante** antes.
- **Metodologia**.

**Coluna `Perfil`** (para você filtrar): `Independente nacional`, `Grande/varejo`, `Estrangeiro`,
`Estatal/público`, `Captivo (montadora/equip.)`, `Câmbio`, `Fintech (SCD)`, `Fintech (pagamentos)`,
`Financeira`, `Hipotecária`. → No **Alvo A**, filtrar `Independente nacional` dá **88** alvos limpos.

**Coluna `Elegibilidade`:** `Elegível direto (banco/CTVM)` · `Precisa virar DTVM/custodiante` ·
`DTVM (excluir)` · `Já adm. fiduciário (excluir)`.

## Porte = Ativo Total (não capital social)

O tamanho vem do **Ativo Total** do **BCB IF.data** (`IfDataValores`, TipoInstituicao=2, base ~mar/2025),
em R$ — muito mais fiel que capital social. Disponível para ~**548** das 864; em branco quando a
instituição não reporta nesse nível (vão para o fim da ordenação).

## Método

1. **BCB** `Instituicoes_em_funcionamento` (`SedesBancoComMultCE` + `SedesSociedades`) → universo com
   segmento limpo e **contato**.
2. **BCB IFDATA** `IfDataValores` → **Ativo Total** por raiz de CNPJ.
3. **CVM** `cad_adm_cart.zip` (adm fiduciário / gestor) + `tabecus.asp` (custodiantes) → flags.
4. Classifica (Perfil + Elegibilidade), ordena por Ativo Total. **Não** filtra por perfil — isso é seu.

## "Tenho todos os casos das não-bancárias, ou tem outros na internet?"

Instituições **já autorizadas** pelo BCB (banco, CTVM, DTVM, financeira, SCD, IP, câmbio, hipotecária):
**estão TODAS aqui** — registro oficial. **Empresas com capital que ainda não são instituição financeira**
(uma holding/family office que se autorizaria do zero): **não estão em registro nenhum** — só rede/internet.
E virar IF do zero é a própria barreira de capital que você quer evitar; por isso Alvo A/B é o atalho.

## Concorrentes (`concorrentes.py` → `concorrentes.xlsx`)

Raio-X de **quem administra fundos hoje** (129 administradores ativos, 50 DTVMs), a partir do
registro de fundos da CVM (Res.175). Por administrador: **nº de fundos, PL sob administração,
quebra FIF/FIDC/FIP/FII, foco, tipo, Ativo Total, contato**. Abas: `Concorrentes`, `Só DTVMs`,
`Metodologia`. Ordenado pelos maiores. Serve para entender como as DTVMs operam e onde está o
espaço: os grandes concentram; **24 das 50 DTVMs focam FIDC** (crédito estruturado) — o nicho de
**fundos genéricos pequenos (FIF)** segue mal atendido, que é a tese da Argus.

## Próximos passos

1. `bancos_alvo.xlsx` → **Alvo A**, filtrar `Perfil = Independente nacional`, ordenar por Ativo Total.
2. Olhar **Alvo B** para fintechs (SCD/IP com estrutura) e financeiras.
3. Achar o decisor (telefone/e-mail/site já estão na planilha).
4. Contato exploratório; deck (`../apresentacao/`) e roteiro (`../falas_apresentacao_banco.md`) depois.
