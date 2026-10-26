# Flow Exam Prep — Laravel

Preparação independente para o exame RBT® (Registered Behavior Technician) do BACB®, construída em Laravel 12 para rodar em nuvem. É a versão de servidor do protótipo **Flow Exam Prep (prototype)**: mesma experiência para o candidato, mesmos 24 itens e as mesmas cinco línguas, agora com as garantias que o protótipo listava como “impossíveis de testar no navegador”.

> RBT® e BACB® são marcas registradas do Behavior Analyst Certification Board, Inc. Este app não é afiliado, endossado nem aprovado pelo BACB. Os itens são originais, não são itens oficiais do exame.

## Do protótipo para o Laravel

| No protótipo (localStorage) | Neste projeto |
|---|---|
| Gabarito e justificativas no código-fonte da página | `Grader` corrige no servidor; `key` é atributo oculto do modelo e só chega à página depois da correção |
| `grade()` separada da renderização | `App\Services\Grader` |
| Ordem das opções embaralhada por item | `OptionOrder`, semeada por sessão + item e gravada em `session_items.option_order` |
| Log local no formato da tabela `responses` | Tabela `responses` append-only: `learner_id`, `item_id`, `item_version`, `selected_option` (rótulo canônico), `is_correct`, `option_order_key`, `response_time_ms` |
| `user_id` pseudônimo no dispositivo | `learners` com UUID em cookie criptografado; sem nome, e-mail ou analytics |
| “Limpar meus dados” | Apaga learner, sessões e respostas (cascade) e o cookie |
| Release gates, versionamento, importação transacional, RLS: “não testáveis” | Fluxo de status com papéis, Gate 0 por variável de ambiente, versão por hash de conteúdo, importação tudo-ou-nada, posse de sessão verificada em cada rota |
| Banner “Team test view” | Staff logado vê rascunhos com o banner; respostas marcadas `is_staff_test` |
| Cronômetro no navegador | `ends_at` no servidor; o contador da página é só exibição |

O visual (paleta teal/dourado, Atkinson Hyperlegible, o “rio” de domínios A–F) foi portado do protótipo para `public/css/app.css`, com tema claro/escuro. Tudo funciona sem JavaScript; `public/js/app.js` só melhora a experiência.

## Estrutura

```
app/
  Enums/ItemStatus.php        draft → bcba_review → fairness_review → approved → released → retired
  Services/Grader.php         correção no servidor
  Services/OptionOrder.php    embaralhamento determinístico
  Services/ItemImporter.php   importação transacional + versionamento
  Services/ItemWorkflow.php   transições de status com papel e Gate 0
  Services/PracticeService.php modos por domínio e cronometrado, resumo por domínio
  Http/Controllers/           candidato (onboarding, home, prática, sobre) e Staff/ (login, revisão, notas)
database/data/items.json      banco de itens (fonte da verdade do conteúdo)
database/data/taxonomy.json   domínios A–F e rótulos internos de tarefas (nunca o texto oficial da BACB)
lang/{en,pt,es,uk,ru-UA}/     textos da interface (uk e ru-UA: rascunho, aguardando revisão nativa)
```

## Rodar localmente

Requer PHP 8.2+ e Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed            # domínios, tarefas e os 24 itens como draft
php artisan staff:create voce@exemplo.com --name="Seu Nome" --role=admin
php artisan serve
```

Abra `http://localhost:8000`. Como candidato você verá “Ainda não há itens de prática”, porque nada foi liberado. Entre em `/staff/login` para usar a visão de teste com todos os rascunhos.

Testes: `php artisan test` (cobrem gabarito oculto, correção, posse de sessão, cronômetro no servidor, limpeza de dados, gates, importação, cabeçalhos de cache e Turnstile).

Com Docker e Postgres: `APP_KEY=$(php artisan key:generate --show) docker compose up --build`.

## Conteúdo e revisão

O conteúdo dos itens vive no repositório (`database/data/items.json`); o status de revisão vive no banco.

```bash
php artisan items:import --dry-run   # valida sem gravar
php artisan items:import             # aplica; itens alterados ganham nova versão e voltam para draft
```

Cada passo de revisão exige um papel (`staff:create --role=...`):

| De → para | Quem |
|---|---|
| draft → bcba_review | qualquer staff |
| bcba_review → fairness_review | `bcba_reviewer` |
| fairness_review → approved | `fairness_reviewer` |
| approved → released | `admin`, e só com `EXAMPREP_RELEASE_GATE_OPEN=true` (Gate 0) |
| qualquer revisão → draft | revisor, com nota obrigatória |

O revisor aprova a versão que leu: se o item mudar enquanto a página está aberta, a aprovação é recusada.

## Deploy

- **Laravel Cloud** (recomendado): ver [`docs/DEPLOY-LARAVEL-CLOUD.md`](docs/DEPLOY-LARAVEL-CLOUD.md).
- **Cloudflare na frente** (DNS, HTTPS, cache de estáticos, WAF, Turnstile): ver [`docs/CLOUDFLARE.md`](docs/CLOUDFLARE.md).
- **Qualquer nuvem de contêiner** (Cloud Run, Fly.io, Render, ECS): `Dockerfile` pronto; o entrypoint roda migrations, taxonomia e importação a cada release (`RUN_MIGRATIONS=false` para desligar).

## Fora do escopo até o Gate 1

Contas de candidato, pagamentos, sugestões adaptativas, recursos de IA e simulado completo de 85 itens. A tela de resultados mostra apenas contagens brutas, sem linguagem de prontidão ou probabilidade de aprovação.

## Pendências conhecidas

- Gere e versione o `composer.lock` no primeiro `composer install`.
- A especificação de privacidade da captura de respostas (`response_time_ms`, `option_order_key`) ainda precisa ser escrita antes de expor candidatos.
- As traduções `uk` e `ru-UA` da interface e dos domínios A–E são rascunhos; o domínio F existe só em inglês.
- Domínios A–E ainda não estão mapeados por tarefa; aparecem como “not mapped” na revisão.
- O nome do produto e o aviso de marca precisam ser conferidos com as diretrizes atuais de marcas do BACB.
