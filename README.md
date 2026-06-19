# Zyrion AI & SEO Boost

Plugin WordPress para o site **zyrionbrazil.com**. Complementa o Yoast SEO gratuito
com recursos de SEO técnico voltados para Google News e mecanismos de busca de IA,
**sem duplicar nada que o Yoast já faz nativamente**.

---

## O que o plugin faz

| Funcionalidade | Detalhe |
|---|---|
| **robots.txt** | Libera crawlers de IA (GPTBot, ClaudeBot, PerplexityBot etc.) e aponta para os sitemaps |
| **Schema Organization** | Enriquece o objeto que o Yoast já gera via filtro `wpseo_schema_organization` — muda `@type` para `NewsMediaOrganization`, adiciona `sameAs`, `knowsAbout` e `publishingPrinciples` |
| **News Sitemap** | Gera `/news-sitemap.xml` no formato Google News (só posts das últimas 48h) — substitui o "News SEO" do Yoast Premium |
| **IndexNow** | Serve o arquivo de verificação e envia ping para `api.indexnow.org` a cada publicação — substitui o "Indexar agora" do Yoast Premium |

---

## O que este plugin NÃO faz (e por quê)

- **llms.txt** — o Yoast SEO passou a gerar isso nativamente (Yoast SEO > Configurações
  > Recursos do site > Ferramentas de IA > llms.txt). Use a interface do Yoast.
- **Schema JSON-LD separado** — nunca criamos um `<script>` JSON-LD próprio para
  entidades que o Yoast já gera; usamos sempre os filtros `wpseo_schema_*`.
- **Sugestões de links internos** — complexidade alta, ganho baixo; linkagem interna
  segue sendo manual.

---

## Decisões de arquitetura

### Schema Organization via filtro, não `<script>` avulso
O Yoast SEO gratuito gera um objeto `Organization` no `@graph` da home page através
do seu Schema Framework. Adicionar um segundo objeto JSON-LD do mesmo tipo criaria
dados conflitantes para o Google. A solução correta é o filtro `wpseo_schema_organization`,
que permite modificar o objeto existente antes de ele ser impresso.

### Google Publisher Center não exige mais submissão manual
Desde outubro de 2025 o Google encerrou o fluxo de "aplicar para revisão" no Publisher
Center. A inclusão no Google News é 100% algorítmica. O Publisher Center hoje serve
apenas para configurar branding (logo) de publicações que o Google já indexou.
Referência: support.google.com/news/publisher-center

### Regra: nunca apontar schema para URL inexistente
Campos como `correctionsPolicy`, `publishingPrinciples` etc. só são adicionados ao
schema após a URL correspondente estar publicada no site. Um 404 nessas URLs prejudica
a confiança do schema com o Google.

---

## Roadmap

- [x] v2.0 — robots.txt, schema Organization, news sitemap, IndexNow
- [ ] v2.1 — `correctionsPolicy` no schema (aguardando publicação da página `/corrigir-erros`)
- [ ] v2.2 — `isAccessibleForFree` no schema de artigos (verificar filtro correto no Yoast atual)
- [ ] Pendente (decisão de negócio) — estratégia de bylines/autoria para Google News

---

## Instalação

1. Copie a pasta `zyrion-ai-seo-boost/` para `wp-content/plugins/`
2. Ative o plugin no painel WordPress
3. Confirme que o Yoast SEO está ativo (o filtro `wpseo_schema_organization` depende dele)

---

## Constantes configuráveis (no topo do `.php`)

```php
ZYRION_SITE_NAME          // Nome do site no news sitemap
ZYRION_SITE_URL           // URL canônica
ZYRION_LOGO_URL           // URL do logo
ZYRION_EDITORIAL_POLICY_URL // URL da política editorial
ZYRION_INDEXNOW_KEY       // Chave IndexNow (gere em indexnow.org)
```
