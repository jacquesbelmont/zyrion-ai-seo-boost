# Changelog

## [2.1] — em produção

### Adicionado
- Schema Organization: campo `correctionsPolicy` apontando para
  `https://zyrionbrazil.com/corrigir-erros/`

---

## [2.0] — em produção

### Adicionado
- robots.txt: liberação explícita de crawlers de IA (GPTBot, ClaudeBot, PerplexityBot,
  Google-Extended, CCBot, ChatGPT-User, anthropic-ai) e referência aos sitemaps
- Schema Organization: filtro `wpseo_schema_organization` enriquece o objeto que o
  Yoast já gera — `@type` alterado para `NewsMediaOrganization`, campos `sameAs`,
  `knowsAbout` e `publishingPrinciples` adicionados
- News Sitemap: endpoint `/news-sitemap.xml` com posts das últimas 48h no formato
  Google News (namespace `news:`)
- IndexNow: arquivo de verificação em `/{key}.txt` e ping automático para
  `api.indexnow.org` a cada post publicado

### Removido
- Geração de `llms.txt` — funcionalidade migrada para o Yoast SEO nativo
  (Yoast SEO > Configurações > Recursos do site > Ferramentas de IA)

---

## [1.x] — versões anteriores

Não documentadas formalmente. O histórico completo está no repositório git.
