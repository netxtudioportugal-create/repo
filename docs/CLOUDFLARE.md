# Cloudflare na frente do Flow Exam Prep

O Laravel continua sendo a aplicação. O Cloudflare entra na frente para DNS, HTTPS, cache de arquivos estáticos, firewall (WAF), limite de requisições e, opcionalmente, Turnstile no login da equipe.

Os nomes de menus do painel do Cloudflare e do Laravel Cloud mudam com frequência. Confira a documentação atual de cada um ao seguir os passos.

## O que já está no código

| Arquivo | O que faz |
|---|---|
| `app/Http/Middleware/EdgeHeaders.php` | Toda página dinâmica sai com `Cache-Control: no-store, private` e `Cloudflare-CDN-Cache-Control: no-store`, além de cabeçalhos básicos de segurança. Nenhuma página com respostas, notas ou dados da equipe pode ficar no cache da borda. |
| `public/.htaccess` | CSS, JS e ícones têm cache de 1 ano. Os links levam `?v=<data do arquivo>`, então cada deploy gera URLs novas. |
| `bootstrap/app.php` | `trustProxies('*')` faz o Laravel ler o IP real do candidato no `X-Forwarded-For`. Só é seguro se a origem não aceitar tráfego direto (ver Caminho B). |
| `app/Services/Turnstile.php` | Verificação do Turnstile no `/staff/login`. Fica desligado enquanto `TURNSTILE_SITE_KEY` e `TURNSTILE_SECRET_KEY` estiverem vazios. |

## 1. Colocar o domínio no Cloudflare

1. Adicione o domínio (por exemplo, `flowexamprep.com`) ao Cloudflare. O plano gratuito basta para o piloto.
2. Troque os nameservers no registrador pelos que o Cloudflare indicar.
3. Em **SSL/TLS**:
   - modo **Full (strict)**;
   - **Always Use HTTPS** ligado;
   - **Minimum TLS Version** 1.2.
4. Ative o HSTS só depois de confirmar que tudo funciona em HTTPS, inclusive subdomínios. Desfazer o HSTS é lento.

## 2. Escolher o caminho da origem

### Caminho A: Laravel Cloud

O Laravel Cloud já entrega HTTPS e uma rede de borda própria. Por isso, um domínio que também passa pelo proxy do seu Cloudflare (nuvem laranja) pode ter conflito de certificado ou de roteamento.

1. Em Laravel Cloud → ambiente `production` → **Domains**, adicione `app.seudominio.com`.
2. Crie no Cloudflare o registro DNS que o Laravel Cloud pedir, normalmente um CNAME.
3. Siga a orientação atual do Laravel Cloud sobre o proxy:
   - Se pedirem **DNS only** (nuvem cinza), deixe assim. Você mantém o DNS no Cloudflare e a proteção de borda do próprio Laravel Cloud. As regras de WAF, cache e limite do seu painel Cloudflare **não** se aplicam a esse hostname.
   - Se permitirem **Proxied** (nuvem laranja), siga as seções 3 a 6 abaixo normalmente.
4. Teste: `curl -I https://app.seudominio.com/up` deve responder `200`.

### Caminho B: contêiner próprio (Cloud Run, Fly.io, Render, VPS)

Aqui o Cloudflare é a borda completa. A origem não pode ficar exposta, senão alguém pode chamá-la diretamente, contornar o WAF e falsificar o `X-Forwarded-For`.

**Opção recomendada: Cloudflare Tunnel**

1. Em **Zero Trust → Networks → Tunnels**, crie um túnel e copie o token.
2. Rode o `cloudflared` ao lado do app, por exemplo no `docker-compose.yml`:

   ```yaml
     tunnel:
       image: cloudflare/cloudflared:latest
       command: tunnel --no-autoupdate run
       environment:
         TUNNEL_TOKEN: ${CLOUDFLARE_TUNNEL_TOKEN}
       depends_on: [app]
   ```

3. No túnel, aponte `app.seudominio.com` para `http://app:8080`.
4. Remova o mapeamento `ports: ["8080:8080"]` do serviço `app` em produção. A origem deixa de ter porta pública.

**Alternativa sem túnel**

1. Instale um **Origin Certificate** do Cloudflare na origem.
2. Configure o firewall da origem para aceitar só os IPs publicados em `cloudflare.com/ips`.

## 3. Regras de cache

O Cloudflare não armazena HTML por padrão, e o `EdgeHeaders` reforça isso. Mesmo assim, deixe as regras explícitas em **Caching → Cache Rules**:

1. **Bypass**: URI path começa com `/practice`, `/staff`, `/about`, `/welcome` ou `/locale`, ou é exatamente `/`. Cache: *Bypass*.
2. **Estáticos**: URI path começa com `/css/` ou `/js/`, ou é `/favicon.svg`. Cache: *Eligible*, respeitando os cabeçalhos da origem.

Nunca ative **Cache Everything** neste hostname. Se ativar, uma pessoa poderia ver a página de resultados de outra.

## 4. Firewall e limite de requisições

Em **Security → WAF**:

- **Managed rules**: ligue o conjunto gerenciado do Cloudflare, se o plano permitir.
- **Rate limiting rule**: limite `POST /staff/login` a cerca de 10 requisições por minuto por IP e bloqueie por 10 minutos. O app já limita 5 tentativas por e-mail e IP; esta regra para o abuso antes de chegar ao servidor. O plano gratuito costuma permitir só uma regra de rate limiting; use-a aqui.
- **Rotas de prática**: não ponha desafio (CAPTCHA) em `/practice/*`. Um desafio no meio de um conjunto cronometrado prejudica o candidato. O app já limita essas rotas a 120 requisições por minuto.
- **Exceção para monitoramento**: crie uma regra *Skip* para `/up`, para que o health check não seja desafiado.
- **Bot Fight Mode**: deixe desligado durante o piloto. Ele pode desafiar candidatos legítimos em redes móveis ou VPN.
- **Bloqueio por país**: não bloqueie. O público inclui EUA, Brasil, países hispânicos, Ucrânia e a diáspora.

## 5. Turnstile no login da equipe (opcional)

1. Em **Turnstile**, crie um widget no modo **Managed** para o hostname do app.
2. Coloque as chaves no ambiente:

   ```
   TURNSTILE_SITE_KEY=...
   TURNSTILE_SECRET_KEY=...
   ```

3. Faça o redeploy. O widget aparece em `/staff/login`. Se a verificação falhar ou o Cloudflare não responder, o login é recusado.

Para testar em staging, o Cloudflare publica chaves de teste que sempre passam ou sempre falham. Confira os valores atuais na documentação do Turnstile, em "Testing".

Candidatos não veem Turnstile. Eles não têm login, e um desafio no onboarding seria uma barreira desnecessária.

## 6. Privacidade

A tela "Seus dados" do app diz que **não há analytics**. Para isso continuar verdadeiro:

- Deixe **Web Analytics** (RUM) e **Zaraz** desligados neste hostname.
- Em **Speed → Optimization**, deixe o **Rocket Loader** desligado. Ele reescreve os scripts da página sem necessidade.
- O Cloudflare passa a processar o IP de cada visitante. Inclua isso na especificação de privacidade que já está pendente, antes de expor candidatos.

## 7. Verificação

```bash
# Página dinâmica: nunca vem do cache
curl -sI https://app.seudominio.com/welcome | grep -iE 'cf-cache-status|cache-control'
#   cf-cache-status: DYNAMIC
#   cache-control: no-store, private

# Estático: HIT a partir da segunda requisição
curl -sI "https://app.seudominio.com/css/app.css" | grep -iE 'cf-cache-status|cache-control'
#   cf-cache-status: HIT
#   cache-control: public, max-age=31536000, immutable

# Origem não acessível diretamente (Caminho B): deve falhar ou dar timeout
curl -sI http://IP-DA-ORIGEM:8080/up
```

Por fim, confira o IP real nos logs do app. Faça uma tentativa de login errada e verifique se o bloqueio do `RateLimiter` usa o IP do seu computador, e não um IP do Cloudflare.
