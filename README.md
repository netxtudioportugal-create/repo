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
  Enums/LanguageStatus.php    draft → in_language_review → approved; stale só é definido pelo sistema
  Services/ItemImporter.php   importação transacional: masters en-US, registos de língua, versões e stale
  Services/LanguageReview.php decisão do revisor de língua (nunca sobrescrita por importação)
  Services/PilotEvidence.php  números a registar como evidência depois de cada passo
  Services/ItemWorkflow.php   transições de status com papel e Gate 0
  Services/PracticeService.php modos por domínio e cronometrado, resumo por domínio
  Http/Controllers/           candidato (onboarding, home, prática, sobre) e Staff/ (login, revisão, notas)
database/data/items.json      banco de itens dos domínios A–E (masters + registos de língua)
database/data/ethics_pilot/   Ethics Pilot (domínio F), todas as línguas: um ficheiro por passo (3 a 6)
database/data/terminology/    dicionário de terminologia ABA v1.0, um ficheiro por pacote (en-US, pt-BR, es-419, uk-UA + ru-UA)
database/data/taxonomy.json   domínios A–F e as 43 tarefas com rótulos internos (official_wording fica NULL, IP hold)
lang/{en-US,pt-BR,es-419,uk-UA,ru-UA}/  textos da interface (uk-UA e ru-UA: rascunho, aguardando revisão nativa)
```

## Rodar localmente

Requer PHP 8.2+ e Composer.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed            # taxonomia, terminologia, banco A–E e os passos 3 a 6 do Ethics Pilot
php artisan staff:create voce@exemplo.com --name="Seu Nome" --role=admin
php artisan serve
```

Abra `http://localhost:8000`. Como candidato você verá “Ainda não há itens de prática”, porque nada foi liberado. Entre em `/staff/login` para usar a visão de teste com todos os rascunhos.

Testes: `php artisan test` (cobrem gabarito oculto, correção, posse de sessão, cronômetro no servidor, limpeza de dados, gates, importação, passos do Ethics Pilot, terminologia, cabeçalhos de cache e Turnstile).

Com Docker e Postgres: `APP_KEY=$(php artisan key:generate --show) docker compose up --build`.

## Conteúdo e revisão

O conteúdo dos itens vive no repositório (`database/data/items.json` e `database/data/ethics_pilot/`); o status de revisão vive no banco.

```bash
php artisan items:import --dry-run   # valida sem gravar
php artisan items:import             # banco A–E; masters alterados ganham nova revisão e voltam para draft
php artisan pilot:import --step=3    # um passo do Ethics Pilot (3, 4, 5 ou 6), seguido da evidência
php artisan pilot:evidence           # só leitura: locales, contagens, master_version, status de língua
php artisan terms:import --dry-run   # valida o dicionário de terminologia sem gravar
php artisan terms:import             # importa; mudar um termo aprovado exige nova dictionary_version
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

### Locales

O conjunto de locales é exatamente `en-US`, `pt-BR`, `es-419`, `uk-UA`, `ru-UA` (congelado no Gate 0), em todo o sistema: conteúdo, candidatos, terminologia e pastas `lang/`. Os rótulos do menu (EN, PT, ES, УКР, РУС) são só de exibição. A migration `migrate_locale_codes` renomeia os códigos antigos no lugar (`en`, `pt`, `es`, `es-LATAM`, `uk`, `ru-RU`) sem criar nem apagar linhas.

### Masters e registos de língua

- O master é o texto `en-US`. `master_version` vem do ficheiro de importação. Gabarito, tarefa, citações e os outros campos do master nunca vêm das linhas de língua.
- Um registo de língua é `item_code + locale + version` (chave natural) e guarda `source_master_version`, a versão do master de que foi feito.
- Quando `master_version` sobe, os registos de língua feitos de uma versão anterior passam a `stale` sozinhos. Um registo `stale` não é mostrado ao candidato: aparece o inglês com a nota de tradução pendente.
- `language_status`: `draft`, `in_language_review`, `approved`, `stale`. `stale` só é definido pelo sistema.
- Um texto `approved` nunca é alterado no lugar: entra como nova versão e a anterior fica como histórico.
- Uma importação nunca sobrescreve um status definido por um revisor (`language_reviewer`), nunca define um status de master diferente de `draft` e nunca mexe em campos de revisão, liberação ou BCBA.
- Repetir um passo não cria duplicados, e repetir um passo antigo não desfaz um mais recente.

### Ethics Pilot: importar por fase e passo

Fonte: `Flow_ExamPrep_Ethics_Import_All_Locales_v2.xlsx` (Ethics Pilot v3.8, Checklist v1.7). Nunca importar tudo de uma vez em staging: os testes de `stale` e de versão só provam alguma coisa se o sistema marcar os registos entre passos. Depois de cada passo, guardar a saída de `pilot:evidence` como evidência.

| Fase | Passo | Como corre | Resultado esperado |
|---|---|---|---|
| 1 | 1 | deploy (`migrate`) | Locales exatamente os cinco; contagens iguais antes e depois |
| 1 | 2 | deploy (`TaxonomySeeder`, `terms:import`) | 43 tarefas, 50 termos, sem duplicados |
| 1 | 3 | `pilot:import --step=3` | 14 masters; `master_version` 2 em F-02-001, F-03-001, F-07-001 |
| 1 | 4 | `pilot:import --step=4` | 56 registos de língua, nenhum `stale` |
| 2 | 5 | `pilot:import --step=5` | `master_version` 3 em F-02-001; 2 em F-05-002 e F-10-001; 12 registos passam a `stale` |
| 2 | 6 | `pilot:import --step=6` | Nenhum registo atual `stale`; pt-BR e es-419 `draft`; uk-UA e ru-UA versão 2 `approved` (Yevheniia Lobas) |

## Deploy

- **Laravel Cloud** (recomendado): ver [`docs/DEPLOY-LARAVEL-CLOUD.md`](docs/DEPLOY-LARAVEL-CLOUD.md).
- **Cloudflare na frente** (DNS, HTTPS, cache de estáticos, WAF, Turnstile): ver [`docs/CLOUDFLARE.md`](docs/CLOUDFLARE.md).
- **Qualquer nuvem de contêiner** (Cloud Run, Fly.io, Render, ECS): `Dockerfile` pronto; o entrypoint roda migrations, taxonomia e importação a cada release (`RUN_MIGRATIONS=false` para desligar).

## Fora do escopo até o Gate 1

Contas de candidato, pagamentos, sugestões adaptativas, recursos de IA e simulado completo de 85 itens. A tela de resultados mostra apenas contagens brutas, sem linguagem de prontidão ou probabilidade de aprovação.

## Pendências conhecidas

- Gere e versione o `composer.lock` no primeiro `composer install`.
- A especificação de privacidade da captura de respostas (`response_time_ms`, `option_order_key`) ainda precisa ser escrita antes de expor candidatos.
- As traduções `uk-UA` e `ru-UA` da interface e dos domínios A–E são rascunhos. No domínio F, uk-UA e ru-UA estão aprovadas na revisão de língua; pt-BR e es-419 são rascunhos.
- Domínios A–E ainda não estão mapeados por tarefa; aparecem como “not mapped” na revisão.
- O nome do produto e o aviso de marca precisam ser conferidos com as diretrizes atuais de marcas do BACB.
