# Changelog

## [3.0] — em produção

### Adicionado
- Painel admin "Zyrion SEO" em Configurações com 3 abas: Status, Configurações e Log IndexNow
- Todas as configurações migradas de constantes PHP para WordPress Options API (`zyrion_seo_options`)
- Aba Status: checklist visual de todos os módulos ativos com links rápidos
- Aba Configurações: campos editáveis (nome do site, chave IndexNow, URLs de política, redes sociais) e toggles on/off por módulo
- Aba Log IndexNow: tabela com data/hora, URL e status HTTP de cada ping; array circular de até 50 entradas; botão "Limpar log"
- IndexNow agora usa `blocking: true` para capturar o status HTTP da resposta e registrar no log
- Módulo de Performance com 6 itens controláveis individualmente pelo painel:
  - Remover emoji scripts/CSS (ativo por padrão)
  - Remover embed JS (oEmbed)
  - Remover jQuery Migrate (frontend)
  - Limpar wp_head (RSD, wlwmanifest, shortlink, X-Pingback, generator)
  - Remover versão de assets (?ver=x.x)
  - Desabilitar XML-RPC

### Alterado
- Constantes hardcoded (`ZYRION_SITE_NAME`, `ZYRION_INDEXNOW_KEY`, etc.) substituídas por valores lidos de `get_option('zyrion_seo_options')` com fallback para os mesmos valores anteriores

---

## [2.3] — em produção

### Adicionado
- Auto-update via GitHub: o WordPress detecta novas versões automaticamente
  consultando `update-info.json` no repositório público. Cache de 12h para
  não sobrecarregar a API do GitHub.
- `update-info.json`: arquivo de metadados de versão lido pelo mecanismo de update.

---

## [2.2] — em produção

### Adicionado
- Schema Article: campo `isAccessibleForFree: true` via filtro `wpseo_schema_article`
  (o Yoast não inclui esse campo por padrão na versão gratuita)

---

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
