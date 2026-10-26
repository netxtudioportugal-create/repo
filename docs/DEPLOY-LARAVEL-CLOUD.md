# Deploy no Laravel Cloud

Laravel Cloud constrói direto do repositório Git; não precisa do `Dockerfile`. Os nomes de menus abaixo podem ter mudado: confira a documentação atual em cloud.laravel.com/docs.

## 1. Repositório

1. Rode `composer install` localmente e faça commit do `composer.lock`.
2. Envie o projeto para GitHub, GitLab ou Bitbucket.

## 2. Aplicação e ambientes

1. No Laravel Cloud, crie uma aplicação a partir do repositório.
2. Crie dois ambientes: `staging` (branch `develop`, para a equipe) e `production` (branch `main`).
3. Region: escolha a mais próxima dos candidatos (por exemplo, US East).

## 3. Recursos

- **Banco**: anexe um Postgres ao ambiente. As variáveis `DB_*` são injetadas automaticamente.
- **Cache/KV** (opcional): se anexar, troque `CACHE_STORE` para o driver indicado pelo Cloud. Sem ele, `database` funciona.
- Sessões ficam em `database` (tabela `sessions`), então várias réplicas funcionam sem configuração extra.

## 4. Variáveis de ambiente

```
APP_ENV=production
APP_DEBUG=false
APP_LOCALE=en
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
EXAMPREP_RELEASE_GATE_OPEN=false        # staging pode usar true para testar a liberação
EXAMPREP_PUBLISHER="AS Behavioral and Mental Health Services"
EXAMPREP_AUTHOR_NAME="Americo Silva, MS, BCBA, IBA"
EXAMPREP_AUTHOR_CREDENTIAL="BACB Certification No. 1-21-53901"
```

`APP_KEY` e `APP_URL` são gerados pelo Cloud; confirme que estão definidos.

## 5. Comandos

**Build commands**

```
composer install --no-dev --optimize-autoloader --no-interaction
```

**Deploy commands** (rodam uma vez por release, antes de o tráfego virar)

```
php artisan migrate --force
php artisan db:seed --class=TaxonomySeeder --force
php artisan items:import --no-interaction
php artisan optimize
```

`items:import` é idempotente: itens sem mudança ficam como estão, itens alterados ganham nova versão e voltam para draft. Se um item for inválido, nada é gravado e o deploy falha, o que é o comportamento desejado.

## 6. Primeiro acesso da equipe

Abra o terminal/comandos do ambiente no Cloud e rode:

```
php artisan staff:create admin@seudominio.com --name="Admin" --role=admin
php artisan staff:create bcba@seudominio.com --name="Revisor BCBA" --role=bcba_reviewer
php artisan staff:create fairness@seudominio.com --name="Revisor de equidade" --role=fairness_reviewer
```

## 7. Domínio e checagens

- Conecte o domínio próprio e confirme HTTPS. Se o domínio estiver no Cloudflare, siga também o Caminho A de [`CLOUDFLARE.md`](CLOUDFLARE.md).
- Health check: `GET /up`.
- `public/robots.txt` bloqueia indexação durante o piloto; remova quando for público.
- Agende backups do Postgres antes de abrir para candidatos.

## Liberação (Gate 0)

1. Itens passam por BCBA e equidade até `approved` na área `/staff/items`.
2. Quando o Gate 0 autorizar, mude `EXAMPREP_RELEASE_GATE_OPEN=true` no ambiente de produção e faça redeploy.
3. Um `admin` libera cada item aprovado. Candidatos passam a vê-los imediatamente.
