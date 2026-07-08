# Deploy em produção — Argus DTVM (`argusdtvm.com.br`)

> **Natureza do sistema:** o piloto é um **ambiente de demonstração com dados simulados**.
> Colocá-lo no domínio é hospedar uma *vitrine funcional* — **não** é operar uma DTVM real
> (isso depende de licença BCB/CVM e da parceria bancária). **Mantenha visíveis** os avisos de
> "demonstração / dados simulados / não constitui oferta" em todas as telas. Ver seção 7.

Este guia assume **hospedagem Apache** (cPanel/Plesk ou VPS) com PHP 8.x e MySQL/MariaDB —
por isso os `.htaccess`. Se o host for **Nginx**, os `.htaccess` são ignorados: peça as regras
equivalentes (posso gerá-las).

---

## 1. Layout de arquivos assumido

O site tem duas partes: a **landing** (marketing) na raiz e o **piloto** (app PHP) em `/piloto`.
As URLs do piloto na landing já apontam para `/piloto/` e `/piloto/simulador/`.

```
public_html/                 ← docroot do domínio  (= conteúdo de landing/)
├─ .htaccess                 ← (vem de landing/.htaccess) HTTPS, headers, cache, bloqueios
├─ index.html                ← landing
├─ assets/                   ← logo, etc.
└─ piloto/                   ← (= conteúdo de piloto/)
   ├─ .htaccess              ← hardening da app
   ├─ index.php  admin/ gestor/ cotista/ custodia/ simulador/ api/ assets/
   ├─ config/    (.htaccess "deny all"  +  env.php  ← você cria, com segredos)
   ├─ includes/  (.htaccess "deny all")
   └─ sql/       (.htaccess "deny all")
```

> **Alternativa:** hospedar o piloto num subdomínio (`app.argusdtvm.com.br`). Nesse caso o docroot
> do subdomínio recebe o conteúdo de `piloto/`, e é preciso trocar `pilotoURL`/`simuladorURL` no
> bloco `CONFIG` da landing (`landing/index.html`) para as URLs absolutas. **Decisão pendente** (seção 8).

---

## 2. Banco de dados

1. No painel do host, crie um **banco** e um **usuário** (com senha forte). **Não use `root`.**
2. Importe o schema e a carga demo:
   ```
   mysql -u USUARIO -p NOME_DO_BANCO < piloto/sql/schema.sql
   mysql -u USUARIO -p NOME_DO_BANCO < piloto/sql/seed.sql
   ```
   (ou importe pelos dois arquivos no phpMyAdmin do host)
3. Crie **`piloto/config/env.php`** a partir de `env.sample.php` e preencha:
   ```php
   <?php
   define('ARGUS_ENV', 'prod');
   define('ARGUS_DB_HOST', 'localhost');
   define('ARGUS_DB_NAME', 'NOME_DO_BANCO');
   define('ARGUS_DB_USER', 'USUARIO');
   define('ARGUS_DB_PASS', 'SENHA_FORTE');
   ```
   > `env.php` **não vai para o Git** (já está no `.gitignore`). `ARGUS_ENV=prod` desliga a
   > exibição de erros e faz o `db.php` mostrar uma página de indisponibilidade genérica em vez do stack trace.

O app cria/ajusta tabelas sob demanda (`ensure_*`), então o usuário do banco precisa de
privilégios de **CREATE/ALTER/INDEX** no próprio banco (o padrão do cPanel já concede "ALL" no banco do usuário).

---

## 3. HTTPS

- Emita um certificado (Let's Encrypt / AutoSSL do cPanel) para `argusdtvm.com.br` **e** `www`.
- O `.htaccess` da raiz já **força HTTPS** e canonicaliza para **sem www**.
- Só depois de confirmar HTTPS estável, **ative o HSTS** (linha comentada no `.htaccess` da raiz).
  HSTS é difícil de reverter — não ligue antes.

## 4. PHP em produção

O `piloto/.htaccess` tenta desligar `display_errors`/`expose_php` via `php_flag`. **Isso só funciona
com mod_php.** Em cPanel moderno (PHP-FPM/CGI) essas linhas são ignoradas — crie um
**`piloto/.user.ini`** (ou use o "MultiPHP INI Editor"):
```ini
display_errors = Off
log_errors = On
expose_php = Off
session.cookie_httponly = 1
session.cookie_samesite = "Lax"
session.use_strict_mode = 1
```
(O `db.php` já força `display_errors=0` quando `ARGUS_ENV=prod`, então há dupla proteção.)

## 5. Checklist de segurança (antes de divulgar a URL)

- [ ] `config/env.php` criado, com `ARGUS_ENV=prod` e **senha de banco forte** (não `root`, não vazia).
- [ ] Testar que `https://argusdtvm.com.br/piloto/config/env.php` responde **403/404** (não baixa o arquivo).
- [ ] Testar que `.../piloto/includes/` e `.../piloto/sql/schema.sql` respondem **403/404**.
- [ ] Confirmar redirecionamento `http → https` e `www → sem-www`.
- [ ] Conferir cabeçalhos com https://securityheaders.com (deve pontuar bem).
- [ ] **Trocar as senhas demo** (`demo123`, `master123`) — ver seção 6.
- [ ] Decidir sobre a exposição pública do login demo e do Simulador Master (seção 6).
- [ ] Backup automático do banco habilitado no host.

## 6. Credenciais demo e o "god-mode" (DECISÃO IMPORTANTE)

Hoje, para facilitar a demonstração, o sistema:
- **imprime as credenciais na tela de login** (ex.: `admin@administradora.com.br — demo123`);
- expõe o **Simulador Master** (senha `master123`) por um link discreto no rodapé — ele "passa o dia",
  marca ativos e gera cotas (poder total sobre o ambiente).

Num site público isso precisa de uma escolha (ver seção 8):
- **(a) Demo aberta:** mantém as senhas visíveis para qualquer visitante testar — mas **troque-as**
  por algo próprio e **remova/proteja o Simulador Master** (ou mova para trás de senha só sua).
- **(b) Demo sob convite:** remove as credenciais das telas e você as fornece na reunião;
  opcionalmente bloqueia `/piloto/` por senha de diretório (`.htpasswd`) e libera só a landing.

## 7. Honestidade (obrigatório manter)

O usuário do projeto preza por isso e é o certo a fazer perante o mercado/regulador:
- Manter em todas as telas os selos **"piloto · dados simulados"** e **"não constitui oferta nem
  parecer jurídico"** (já presentes na landing, no `index.php` do piloto e na sidebar).
- Não apresentar números simulados (PL, cotas) como track record real.
- Deixar claro que a operação regulada depende de **licença** e da **parceria bancária** — a Argus
  ainda está na fase de demonstração/estruturação.

## 8. Decisões pendentes (me diga e eu ajusto)

1. **Piloto em `/piloto` (padrão atual) ou subdomínio `app.argusdtvm.com.br`?**
2. **Login demo:** aberto (a) ou sob convite (b)? E o **Simulador Master**: mantém, protege ou remove?
3. **Nome do banco/e-mails demo** (`@administradora.com.br`) — migrar para `@argusdtvm.com.br`?
   É invasivo (mexe no `seed.sql` e nas credenciais) — deixei **fora** deste passo; faço se quiser.
4. **Nginx?** Se o host não for Apache, gero as regras equivalentes.

## 9. O que já foi feito nesta preparação

- `.htaccess` da raiz (HTTPS, www→raiz, headers, gzip, cache, bloqueio de arquivos sensíveis).
- `.htaccess` do piloto (PHP hardening) + "deny all" em `includes/`, `config/`, `sql/`.
- `config/db.php` agora lê segredos de `config/env.php` (fora do Git) e **não vaza erros** em `prod`;
  mantém o fallback XAMPP para desenvolvimento local. `config/env.sample.php` + `.gitignore` criados.
- Rebrand **Vértice/ADMINISTRADORA → Argus** nos wordmarks (logo, landing, sidebar do piloto,
  página-portal e títulos), preservando o termo de negócio "administradora".
