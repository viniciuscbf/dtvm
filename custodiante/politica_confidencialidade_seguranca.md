# Política de Confidencialidade e Segurança da Informação

> **Base legal.** Documento elaborado em atendimento à Resolução CVM nº 32, de 19 de maio de 2021 (consolidada com as alterações da Resolução CVM nº 209, de 2024), em especial: **art. 5º, I** (capacidade operacional e tecnológica com garantia da qualidade e da confidencialidade das informações); **art. 13, §1º, II** (dever de manter sigilo quanto às características e às quantidades dos valores mobiliários dos investidores sob custódia); **Anexo A, art. 1º, III, "c"** (normas de segurança sobre instalações, equipamentos e dados) e **"e"** (políticas de segregação de funções, controle de acesso e senhas, e treinamento). Integra o pacote de comprovação para pleito de autorização para o exercício da atividade de custódia de valores mobiliários.

---

## 1. Objetivo, alcance e princípios

Esta Política estabelece as regras, os procedimentos e os controles internos escritos (**art. 16** da Res. CVM 32/2021) que asseguram a confidencialidade, a integridade e a disponibilidade das informações tratadas no exercício da atividade de custódia de valores mobiliários, com destaque para o **sigilo das posições dos investidores** exigido pelo **art. 13, §1º, II**.

O documento aplica-se a todos os colaboradores, administradores, prestadores de serviço e terceiros que, no desempenho de suas funções, tenham acesso a sistemas, dados ou instalações da instituição custodiante.

Princípios norteadores:

- **Confidencialidade** — a informação é acessível apenas a quem possui necessidade legítima de conhecê-la (*need-to-know*).
- **Integridade** — a informação é mantida completa, exata e protegida contra alteração não autorizada; toda alteração relevante é rastreável.
- **Disponibilidade** — a informação e os sistemas estão disponíveis quando necessários, conforme o Manual de Contingência e Continuidade (`manual_contingencia_continuidade.md`).
- **Sigilo regulatório** — características e quantidades dos valores mobiliários dos investidores são tratadas como informação sigilosa, nos termos do art. 13, §1º, II.

Referências cruzadas: `dossie_estrutura_operacional_tecnologica.md` (arquitetura e capacidade tecnológica), `matriz_conformidade_cvm32.md` (rastreabilidade artigo a artigo), `manual_conciliacao.md` (controles de conciliação) e `manual_contingencia_continuidade.md` (resposta a incidentes e continuidade).

> **Estado de implementação na plataforma-piloto.** Já implementados e verificáveis no portal de custódia (`piloto/includes/seguranca.php`): **trilha de auditoria append-only** (`auditoria`, visível em *Custódia → Trilha de auditoria*), **proteção CSRF** nos formulários, **cabeçalhos de segurança** (CSP, X-Frame-Options, X-Content-Type-Options, Referrer/Permissions-Policy) e **hardening de sessão** (cookie `HttpOnly`/`SameSite=Lax`, `Secure` sob HTTPS, timeout por inatividade). Ainda em roadmap de produção (🟩): **MFA**, **criptografia em repouso gerenciada** e **imutabilidade externa dos logs (WORM)**.

---

## 2. Classificação da informação

Toda informação sob responsabilidade da instituição é classificada em quatro níveis. As **posições dos investidores** — quantidades e características dos valores mobiliários custodiados — são sempre classificadas como **sigilosas**, por força do art. 13, §1º, II.

| Nível | Definição | Exemplos | Controles mínimos |
|---|---|---|---|
| **Pública** | Pode ser divulgada livremente. | Material institucional, normativos publicados. | Sem restrição de leitura; controle de integridade na publicação. |
| **Interna** | Uso interno; divulgação externa causaria dano leve. | Procedimentos operacionais, comunicados internos. | Acesso restrito a colaboradores autenticados. |
| **Confidencial** | Divulgação indevida causaria dano relevante. | Dados cadastrais de investidores, contratos, relatórios gerenciais. | Controle de acesso por perfil; registro de acesso; criptografia em trânsito. |
| **Sigilosa** | Protegida por dever legal de sigilo (art. 13, §1º, II). | **Posições, quantidades e características dos VM dos investidores**; senhas e chaves. | *Need-to-know* estrito; segregação de funções; trilha de auditoria; criptografia em trânsito e em repouso 🟩; termo de confidencialidade. |

---

## 3. Controle de acesso e autenticação

*(atende ao Anexo A, art. 1º, III, "e" — controle de acesso e senhas)*

O acesso à plataforma é regido pelos princípios do **menor privilégio** e da **necessidade de conhecer**. A plataforma-piloto (PHP + MySQL, em `..\piloto\`) implementa, como evidência efetiva, os controles descritos abaixo.

### 3.1 Perfis segregados

O sistema opera com perfis funcionalmente segregados — **admin**, **gestor** e **custodia** —, atribuídos a cada usuário e validados a cada requisição. Nenhum perfil acumula competências que anulem a segregação de funções descrita na Seção 5.

### 3.2 Autenticação e política de senhas

- Senhas armazenadas com **hash bcrypt**, por meio das funções nativas `password_hash` e `password_verify` (evidência: `includes/auth.php`); a senha em texto claro nunca é persistida.
- Política de senhas: comprimento mínimo, complexidade, expiração periódica e bloqueio após tentativas sucessivas.
- **Revalidação de perfil no banco a cada requisição** — a função `exigir_perfil(...)` reconsulta o perfil corrente do usuário no banco de dados a cada acesso protegido, de modo que a revogação de permissões tem efeito imediato, sem depender de dados persistidos apenas na sessão.
- **Versionamento de sessão** (`AUTH_V`) — sessões de versões anteriores do sistema são automaticamente invalidadas, impedindo o reaproveitamento de sessões legadas.

### 3.3 Acesso do cotista por token

O acesso do cotista às suas próprias posições ocorre por **token UUID**, com **defasagem temporal configurável** entre a data-base da informação e sua exibição, de forma que o investidor visualiza exclusivamente a sua posição, sem acesso à base geral.

### 3.4 Itens de produção — roadmap

- 🟩 **Autenticação multifator (MFA)** para perfis administrativos e de custódia.
- 🟩 Gestão centralizada de identidades e revisão periódica formal de acessos (recertificação).

### 3.5 Matriz de acesso por perfil

| Recurso / função | admin | gestor | custodia | cotista (token) |
|---|:---:|:---:|:---:|:---:|
| Gestão de usuários e perfis | ✅ | — | — | — |
| Parâmetros do sistema | ✅ | — | — | — |
| Lançamentos de custódia `[lancamentos]` | ✅ | Leitura | ✅ | — |
| Conciliação `[conciliacao]` | ✅ | Leitura | ✅ | — |
| Posições consolidadas dos investidores | ✅ | Leitura | ✅ | — |
| Posição individual própria | — | — | — | ✅ (só a própria) |
| Trilha de auditoria `[log_processamento]` | ✅ | Leitura | — | — |

Legenda: ✅ acesso pleno à função · Leitura restrita · — sem acesso.

---

## 4. Segregação de funções

*(atende ao Anexo A, art. 1º, III, "e")*

A instituição mantém segregação entre as funções de **custódia**, **controladoria** e **compliance**, de modo que quem executa uma operação não é o mesmo que a concilia ou a fiscaliza. A separação reflete-se tanto na estrutura organizacional quanto nos perfis técnicos (admin / gestor / custodia).

A segregação relaciona-se à indicação dos **dois diretores estatutários** exigidos pela Res. CVM 32/2021 (**art. 17**): um responsável pela atividade de custódia e outro responsável pela supervisão do cumprimento de regras, procedimentos e controles internos (compliance). Nenhum dos dois pode acumular funções que comprometam a independência da supervisão. Detalhamento em `dossie_estrutura_operacional_tecnologica.md`.

Controle técnico de apoio à segregação: a conciliação registra **quem resolveu** e **quando** (`[conciliacao].resolvido_por` e `resolvido_em`), permitindo a verificação independente da autoria de cada tratamento de divergência (ver `manual_conciliacao.md`).

---

## 5. Sigilo das posições dos investidores

*(atende ao art. 13, §1º, II)*

A instituição mantém sigilo quanto às **características e às quantidades dos valores mobiliários** de cada investidor sob custódia. Regras aplicáveis:

- **Quem pode ver o quê.** Posições consolidadas são acessíveis apenas aos perfis com necessidade operacional (admin e custodia; gestor em leitura). O cotista acessa exclusivamente a **própria** posição, via token UUID (Seção 3.3).
- **Vedações.** É vedada a divulgação, o compartilhamento ou a extração de posições de investidores a pessoas ou sistemas sem necessidade legítima; é vedado o uso das informações para fim diverso da prestação do serviço de custódia; é vedado o acesso cruzado entre cotistas.
- **Exceções legais.** A quebra de sigilo somente ocorre mediante ordem judicial, requisição de autoridade competente nos termos da lei, ou autorização expressa do titular, sempre registrada na trilha de auditoria.
- **Termo de confidencialidade.** Todo colaborador, administrador e terceiro com acesso a informações sigilosas firma **termo de confidencialidade**, cujo dever subsiste após o término do vínculo.

---

## 6. Segurança de dados

- **Criptografia em trânsito.** Toda comunicação com a plataforma trafega por **TLS** (HTTPS), protegendo credenciais e dados sensíveis contra interceptação.
- **Criptografia em repouso.** 🟩 Em produção, os dados sigilosos serão protegidos por criptografia em repouso com **gestão de chaves** dedicada (rotação, custódia e segregação de chaves), controle ainda não implementado no piloto.
- **Ambientes segregados.** Manutenção de ambientes distintos de **desenvolvimento, homologação e produção**, sem uso de dados reais de investidores fora do ambiente de produção; dados de teste são anonimizados ou fictícios.
- **Minimização.** Coleta e retenção limitadas ao necessário para a prestação do serviço e para o cumprimento das obrigações regulatórias.

---

## 7. Segurança física e de infraestrutura

*(atende ao art. 13, VI e ao Anexo A, art. 1º, III, "c")*

- Equipamentos e instalações que suportam a atividade de custódia possuem **segurança física** — controle de acesso às dependências, proteção de servidores e restrição de acesso físico a pessoal autorizado.
- Normas de **segurança de dados** aplicáveis a instalações e equipamentos, incluindo hardening de servidores, atualização de correções de segurança e proteção de perímetro de rede.
- Cópias de segurança (*backups*) regulares, com testes de restauração, conforme o `manual_contingencia_continuidade.md`.

---

## 8. Registro e trilha de auditoria

*(atende ao art. 13, V — registro de acessos, erros, incidentes e interrupções)*

A plataforma mantém trilhas de auditoria que permitem reconstituir acessos, alterações e ocorrências:

- **`[log_processamento]`** — registro de processamentos, erros e eventos do sistema.
- **`[conciliacao]`** (`resolvido_por` / `resolvido_em`) — autoria e momento da resolução de cada divergência de conciliação.
- **`[lancamentos]`** — histórico dos lançamentos de custódia.

Os registros contemplam acessos, alterações relevantes, incidentes e interrupções, viabilizando investigação e prova.

🟩 **Roadmap:** exportação de **logs imutáveis para repositório externo** (WORM / *append-only*), assegurando não repúdio e proteção contra adulteração da própria trilha. O piloto ainda não implementa imutabilidade externa dos logs.

---

## 9. Retenção de documentos e proteção de dados pessoais (LGPD)

### 9.1 Retenção

*(atende ao art. 22)*

Documentos, registros e logs relacionados à atividade de custódia são guardados pelo prazo mínimo de **5 (cinco) anos**, ou por prazo superior quando exigido por regulação específica ou por determinação de autoridade competente.

### 9.2 LGPD — boa prática complementar

Como boa prática complementar, o tratamento de dados pessoais de investidores e cotistas observa a Lei nº 13.709/2018 (LGPD):

- **Base legal** — o tratamento apoia-se, entre outras, no cumprimento de obrigação legal/regulatória e na execução de contrato.
- **Encarregado (DPO)** — designação de encarregado pelo tratamento de dados pessoais como canal de comunicação com titulares e com a autoridade.
- **Inventário de dados** — manutenção de inventário (*data mapping*) e de registro das operações de tratamento.
- **Direitos dos titulares** — procedimentos para atendimento a solicitações de titulares, observados os deveres de sigilo e retenção regulatória.

---

## 10. Gestão de incidentes de segurança e resposta

A instituição mantém processo de **gestão de incidentes de segurança da informação**, articulado com o `manual_contingencia_continuidade.md`:

1. **Detecção e registro** do incidente na trilha de auditoria (art. 13, V).
2. **Classificação** por severidade e impacto sobre a confidencialidade das posições dos investidores.
3. **Contenção, erradicação e recuperação**, acionando os procedimentos de contingência quando houver impacto na disponibilidade.
4. **Comunicação** às áreas internas, aos diretores estatutários (art. 17) e, quando cabível, à CVM e à autoridade de proteção de dados.
5. **Análise pós-incidente** e registro de lições aprendidas, com atualização dos controles.

---

## 11. Treinamento e conscientização

*(atende ao Anexo A, art. 1º, III, "e" — treinamento)*

- Programa periódico de **treinamento e conscientização** em segurança da informação, sigilo do art. 13, §1º, II e proteção de dados pessoais, obrigatório para todos os colaboradores.
- Treinamento específico de integração (*onboarding*) e assinatura do termo de confidencialidade no início do vínculo.
- Reciclagem periódica e comunicação de atualizações relevantes desta Política.
- Registro da participação para fins de comprovação regulatória (art. 22).

---

## 12. Governança, revisão e vigência

- Esta Política é revisada, no mínimo, anualmente, ou sempre que houver alteração regulatória relevante, incidente significativo ou mudança material na plataforma.
- A supervisão do cumprimento cabe ao diretor estatutário responsável por regras, procedimentos e controles internos (art. 17).
- A rastreabilidade desta Política frente à Res. CVM 32/2021 consta em `matriz_conformidade_cvm32.md`.

---

> **Referência normativa:** Resolução CVM nº 32/2021 (consolidada com a Res. CVM nº 209/2024) — arts. 5º, I; 13, §1º, II; 13, V; 13, VI; 16; 17; 22; e Anexo A, art. 1º, III, "c" e "e". Lei nº 13.709/2018 (LGPD).
>
> **Aviso:** Documento de natureza técnico-regulatória, integrante do pacote de comprovação para pleito de autorização de custódia. **Revisar com as áreas jurídica, de compliance e de segurança da informação** antes da submissão à CVM. Itens marcados com 🟩 constituem roadmap de produção ainda não implementado na plataforma-piloto.
