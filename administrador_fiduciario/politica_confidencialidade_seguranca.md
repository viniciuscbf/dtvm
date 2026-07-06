# Política de Confidencialidade e Segurança da Informação — Administração Fiduciária

> **Documento integrante do pedido de autorização — Resolução CVM nº 21/2021** (registro na categoria administrador fiduciário), lido em conjunto com a **Resolução CVM nº 175/2022**.
> **Base legal (Res. CVM 21):** art. 4º, §8º (recursos computacionais protegidos contra adulteração e com registros para auditoria e inspeção); arts. 22–23 (controles internos adequados; regras e procedimentos escritos; identificação e eliminação de conflitos de interesse); art. 27 (segregação da administração de carteiras das demais atividades); art. 28 (manuais escritos de segregação de atividades — inciso I — e de confidencialidade — inciso II); art. 30, caput (custódia e controladoria totalmente segregadas da gestão de recursos); art. 25 (relatório anual de compliance).
> **Requerente:** `[RAZÃO SOCIAL DA INSTITUIÇÃO]` — CNPJ `[__]` — instituição financeira de menor porte, já autorizada a funcionar pelo Banco Central do Brasil, que **constitui uma nova área de administração fiduciária**.
> **Documento de trabalho — v1.0.** Campos entre `[colchetes]` a preencher pela instituição. Marcações **🟩** indicam controles-alvo (roadmap) posteriores à autorização.

---

## 1. Objetivo e princípios

### 1.1 Objetivo
Esta Política estabelece as regras e os procedimentos escritos que a área de administração fiduciária adota para **proteger a confidencialidade, a integridade e a disponibilidade** das informações sob sua responsabilidade — em especial os dados de cotistas e as posições dos fundos administrados —, atendendo ao dever de manter **controles internos adequados** (Res. 21, arts. 22–23), à **segregação de atividades** (arts. 27, 28 e 30) e à exigência de **recursos computacionais protegidos contra adulteração e com registros para auditoria e inspeção** (art. 4º, §8º).

A Política compõe o **manual de confidencialidade** exigido pelo art. 28, II, e se articula com o **manual de segregação de atividades** (art. 28, I), tratado no `dossie_estrutura_operacional_tecnologica.md`.

### 1.2 Princípios
- **Confidencialidade** — a informação só é acessível a quem tem necessidade legítima de conhecê-la (*need-to-know*) e autorização compatível com o perfil.
- **Integridade** — a informação é protegida contra alteração indevida; toda modificação relevante é **registrada de forma rastreável** e o conteúdo é preservado contra adulteração (art. 4º, §8º).
- **Disponibilidade** — a informação e os recursos computacionais estão disponíveis a quem de direito, com continuidade operacional proporcional ao porte da instituição.
- **Menor privilégio** — cada perfil recebe o mínimo de acesso necessário à sua função.
- **Segregação** — administração fiduciária (controladoria), gestão de recursos e custódia operam de forma segregada, com acessos e ambientes distintos.
- **Rastreabilidade** — ações sensíveis geram trilha de auditoria destinada à supervisão da CVM e à inspeção interna.

### 1.3 Abrangência
Aplica-se a todos os administradores, diretores, colaboradores, estagiários e prestadores de serviço que, no exercício de suas funções, acessem informações ou recursos da área de administração fiduciária, incluindo a plataforma de controladoria (no piloto, o portal `/admin/`).

---

## 2. Classificação da informação

A informação é classificada em quatro níveis. O nível determina os controles mínimos de acesso, armazenamento e transmissão.

| Nível | Definição | Exemplos | Controles mínimos |
|---|---|---|---|
| **Pública** | Divulgação livre, sem dano potencial. | Material institucional; regulamentos publicados; informações já divulgadas ao mercado. | Sem restrição de acesso; integridade preservada na origem. |
| **Interna** | Uso interno; divulgação indevida gera impacto baixo. | Procedimentos operacionais; comunicações administrativas internas. | Acesso restrito a colaboradores; trânsito em canais corporativos. |
| **Confidencial** | Divulgação indevida gera impacto relevante ao negócio ou a terceiros. | Parâmetros de precificação; relatórios de conciliação; correspondência com o custodiante e com a CVM. | Acesso por perfil e *need-to-know*; trilha de auditoria; transmissão criptografada em trânsito. |
| **Sigilosa** | Sujeita a sigilo legal/regulatório; inclui dados pessoais. | **Dados cadastrais e de posição dos cotistas**; **posições e carteiras dos fundos**; tokens de acesso de cotista. | Acesso mínimo e nominal; segregação por perfil; criptografia em trânsito (🟩 e em repouso); tratamento conforme **LGPD**; termo de confidencialidade. |

> **Regra de referência.** Os **dados de cotistas** e as **posições dos fundos** são classificados, no mínimo, como **sigilosos**, por envolverem dados pessoais (LGPD) e informação protegida por dever de confidencialidade (Res. 21, art. 28, II). Na dúvida quanto à classificação, adota-se o nível **mais restritivo**.

---

## 3. Controle de acesso e autenticação

### 3.1 Perfis segregados e menor privilégio
O acesso à plataforma é organizado em **perfis segregados**, cada um limitado às funções da respectiva atividade. No piloto (`includes/auth.php`), coexistem os perfis **`admin`** (administração fiduciária/controladoria), **`gestor`** (gestão de recursos) e **`custodia`** (custódia), além do acesso do **cotista** por *token* nominal, sem usuário. Cada perfil é direcionado ao seu próprio portal e não acessa áreas de outro perfil.

### 3.2 Matriz de acesso por perfil

| Recurso / função | `admin` (adm. fiduciário) | `gestor` | `custodia` | Cotista (token) |
|---|:--:|:--:|:--:|:--:|
| Carteira e precificação (MaM) dos fundos | Leitura/edição | Leitura (do próprio fundo) | — | — |
| Lançamentos e ajustes de controladoria (`lancamentos`) | Edição | — | — | — |
| Conciliação com o custodiante (`conciliacao`) | Edição | — | Leitura | — |
| Cálculo/divulgação de cota e PL | Edição | Leitura | — | Leitura (posição própria) |
| Posição consolidada dos fundos | Total | Do próprio fundo | Da guarda | Da posição própria |
| Emissão/revogação de tokens de cotista | Edição | — | — | — |
| Trilha de auditoria (`auditoria`) | Leitura | — | — | — |

> A matriz materializa o **menor privilégio**: nenhum perfil acumula funções que devam permanecer segregadas (§5). O acesso do cotista é restrito à sua própria posição, com **data de corte** conforme o nível do token (`data_corte_token`).

### 3.3 Autenticação
- **Senhas com hash bcrypt.** As senhas são armazenadas com `password_hash`/`password_verify` (bcrypt), nunca em texto claro (`includes/auth.php`).
- **Revalidação de sessão no banco a cada requisição.** A função `exigir_perfil` reconsulta o usuário e o seu perfil no banco a cada página; se o usuário deixou de existir ou teve o perfil alterado, a sessão é encerrada imediatamente. Para o cotista, `exigir_token` revalida o token no banco, de modo que a **revogação tem efeito imediato**.
- **Versionamento de sessão.** A constante `AUTH_V` invalida automaticamente sessões emitidas por versões anteriores da plataforma.
- **Hardening de sessão.** Cookie de sessão com atributos `HttpOnly` e `SameSite=Lax` (e `Secure` sob HTTPS), rotação de identificador no login (`session_regenerate_id`) e **timeout por inatividade** de 30 minutos (`includes/seguranca.php`).
- **MFA — autenticação multifator (🟩).** Fator adicional para os perfis `admin`, `gestor` e `custodia`, como controle-alvo a implantar após a autorização, priorizado para acessos privilegiados.
- **Política de senha `[definir]`.** Comprimento mínimo, complexidade, expiração e bloqueio após tentativas malsucedidas — a formalizar pela instituição; as tentativas de login (bem-sucedidas e malsucedidas) já são registradas na trilha de auditoria.

---

## 4. Segregação de atividades

### 4.1 Segregação funcional (arts. 27 e 30)
A **administração de carteiras** (administração fiduciária/controladoria) é **segregada das demais atividades** exercidas pela instituição (art. 27). A **custódia e a controladoria** permanecem **totalmente segregadas da gestão de recursos** (art. 30, caput). Na plataforma, essa segregação é imposta pelos perfis distintos (`admin`, `gestor`, `custodia`), pela matriz de acesso (§3.2) e pela revalidação de perfil a cada requisição.

### 4.2 Segregação física ante intermediação/distribuição
Quando a instituição também exerce atividades de **intermediação ou distribuição**, a administração de carteiras é segregada inclusive **fisicamente** dessas atividades (art. 27), com separação de instalações, sistemas e equipes. `[Descrever a segregação física adotada: instalações, acessos lógicos e físicos, e equipe dedicada.]`

### 4.3 Manuais escritos (art. 28)
A instituição mantém manuais escritos de:
- **Segregação de atividades** (art. 28, I) — ver `dossie_estrutura_operacional_tecnologica.md`;
- **Confidencialidade** (art. 28, II) — a presente Política.

A correspondência entre exigências e evidências consta da `matriz_conformidade_cvm21_175.md`.

---

## 5. Conflitos de interesse

Em cumprimento ao art. 23 da Res. 21, a área mantém regras e procedimentos escritos para **identificar, administrar e eliminar** conflitos de interesse:
- **Identificação.** Mapeamento das situações em que interesses da instituição, de administradores ou de colaboradores possam se sobrepor ao interesse dos fundos e cotistas (por exemplo, acúmulo de funções segregadas, contratações com partes relacionadas, uso de informação privilegiada).
- **Administração.** Barreiras de acesso à informação entre atividades segregadas (§4), *need-to-know*, aprovação e registro de operações com partes relacionadas, e trilha de auditoria das ações sensíveis.
- **Eliminação.** Quando o conflito não puder ser adequadamente administrado, a situação é **eliminada** — pela vedação da conduta, pela segregação adicional ou pela abstenção do envolvido.

O diretor responsável por compliance e controles internos (art. 4º) supervisiona a aplicação desta seção e reporta desvios à alta administração. `[Indicar diretor responsável.]`

---

## 6. Segurança e integridade dos recursos computacionais (art. 4º, §8º)

Os recursos computacionais da administração fiduciária são mantidos **protegidos contra adulteração** e dotados de **registros que permitem auditoria e inspeção** pela CVM, nos termos do art. 4º, §8º.

### 6.1 Proteção contra adulteração
- **Trilha append-only.** As ações relevantes são registradas em tabela de auditoria de acréscimo (*append-only*), sem rotina de alteração ou exclusão a partir da aplicação (§7).
- **Proteção CSRF.** Todas as operações via `POST` exigem token CSRF válido, comparado em tempo constante (`csrf_validar`, `hash_equals`), impedindo submissões forjadas.
- **Cabeçalhos de segurança.** `Content-Security-Policy` (CSP restritiva), `X-Frame-Options: DENY`/`frame-ancestors 'none'` (anti-clickjacking), `X-Content-Type-Options: nosniff`, `Referrer-Policy` e `Permissions-Policy` (`includes/seguranca.php`).
- **Consultas parametrizadas.** O acesso ao banco usa *prepared statements* (PDO), mitigando injeção de SQL.

### 6.2 Ambientes segregados
Os ambientes de desenvolvimento/homologação e de produção são **segregados**, com dados de produção restritos ao ambiente produtivo e credenciais próprias por ambiente. `[Descrever topologia de ambientes e responsáveis.]`

### 6.3 Criptografia
- **Em trânsito.** O tráfego trafega sob **HTTPS**; o cookie de sessão é marcado `Secure` quando há TLS.
- **Em repouso (🟩).** Criptografia de dados em repouso com **gestão de chaves** (controle-alvo), aplicável ao armazenamento dos dados sigilosos (cotistas e posições).

---

## 7. Registro e trilha de auditoria

### 7.1 Trilha de auditoria (`auditoria`)
A função `registrar_auditoria` (`includes/seguranca.php`) grava, em tabela **`auditoria`** de acréscimo (*append-only*), os eventos sensíveis com: **ator, perfil, ação, entidade, identificador da entidade, fundo, detalhe, IP e user-agent**. São registrados, entre outros, `login_ok`, `login_falha`, `logout`, aceite de boleta, liquidação, crédito de eventos e processamento de mensagens. A gravação é *best-effort* — **jamais interrompe a operação**, preservando a disponibilidade.

### 7.2 Cobertura da trilha
Além de `auditoria`, os módulos de **`lancamentos`** (lançamentos e ajustes de controladoria) e de **`conciliacao`** mantêm registro próprio das operações, apoiando a rastreabilidade do cálculo da cota e da conciliação com o custodiante (ver `manual_conciliacao.md`).

### 7.3 Imutabilidade e retenção
- A trilha é concebida como **append-only** na camada de aplicação.
- **Imutabilidade externa (WORM) — 🟩.** Retenção dos logs em armazenamento **WORM** (*write-once, read-many*) ou equivalente, fora do alcance da aplicação e dos operadores, como controle-alvo para reforçar a não repudiação.
- **Retenção `[definir prazo]`**, observados os prazos regulatórios aplicáveis e o mínimo necessário à auditoria e inspeção da CVM.

---

## 8. Confidencialidade e proteção de dados (LGPD)

### 8.1 Dever de confidencialidade
Os dados de cotistas e as posições dos fundos são **sigilosos**. O acesso é nominal, por perfil e por necessidade, e toda divulgação a terceiros observa base legal ou autorização expressa. Este item integra o manual de confidencialidade do art. 28, II.

### 8.2 LGPD (boa prática)
Como boa prática, o tratamento de dados pessoais de cotistas observa a **Lei nº 13.709/2018 (LGPD)**:
- **Base legal e finalidade** — tratamento limitado ao necessário à administração dos fundos e ao cumprimento de obrigações legais e regulatórias.
- **Encarregado (DPO) `[indicar]`** — ponto de contato para titulares e autoridade.
- **Inventário de dados (RoPA) `[elaborar]`** — mapeamento dos dados pessoais tratados, finalidades, bases legais e retenção.
- **Direitos dos titulares** — procedimento de atendimento a solicitações de acesso, correção e eliminação, quando cabível.
- **Incidentes** — plano de resposta e comunicação a titulares e à ANPD conforme a legislação. `[definir fluxo]`

### 8.3 Termo de confidencialidade
Todo administrador, colaborador e prestador de serviço com acesso a informação confidencial ou sigilosa firma **termo de confidencialidade**, com vigência que se estende após o término do vínculo. `[Anexar modelo do termo.]`

---

## 9. Relatório anual de compliance (art. 25) e governança da política

### 9.1 Relatório anual de compliance
O diretor responsável por controles internos e compliance elabora, **até o último dia útil de abril**, relatório anual relativo ao ano civil anterior, abrangendo as conclusões dos exames, as recomendações e as manifestações do diretor de administração de carteiras a respeito das deficiências identificadas (Res. 21, art. 25). Esta Política é objeto dos exames do relatório.

### 9.2 Governança da política
- **Aprovação e revisão.** Aprovada pela alta administração; revisão **anual** ou sempre que houver alteração regulatória ou material na operação. `[Registrar data de aprovação e versão.]`
- **Responsáveis.** Diretor de administração de carteiras (art. 4º, III) e diretor de compliance/controles internos (art. 4º, IV). `[Indicar nomes.]`
- **Divulgação e treinamento.** Comunicada a todos os destinatários (§1.3), com treinamento periódico e no ingresso.
- **Exceções.** Qualquer exceção é formalizada, justificada, aprovada pelo responsável e registrada.

---

## 10. Documentos relacionados

| Documento | Conteúdo |
|---|---|
| `dossie_estrutura_operacional_tecnologica.md` | Estrutura operacional/tecnológica; manual de segregação de atividades (art. 28, I). |
| `matriz_conformidade_cvm21_175.md` | Correspondência exigência ↔ evidência (Res. 21 e 175). |
| `manual_conciliacao.md` | Procedimentos de conciliação com o custodiante e respectiva trilha. |

---

## 11. Rodapé

*Referências normativas: Resolução CVM nº 21/2021 (arts. 4º §8º, 22–23, 25, 27–28, 30) e Resolução CVM nº 175/2022 (consolidadas); Lei nº 13.709/2018 (LGPD). Evidências técnicas: `../piloto/includes/auth.php` e `../piloto/includes/seguranca.php`; portal `/admin/`. Documento de trabalho — v1.0 — revisar com jurídico/compliance antes do protocolo. Campos entre `[colchetes]` e marcações **🟩** (controles-alvo) a completar pela instituição.*
