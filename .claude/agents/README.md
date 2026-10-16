# Subagents — Boundless Gracia Children Foundation WordPress Build

These are Claude Code subagent definitions. Each `*.md` file has YAML
frontmatter (`name`, `description`, `tools`, `model`) plus a system prompt.
Claude Code auto-discovers them from `.claude/agents/`. All of them read the
project's `CLAUDE.md` for shared brand, stack, structure, and standards.

## Roster

| Agent | Role | Model |
|---|---|---|
| `wp-lead-orchestrator` | Plans phases, routes work, enforces gates, writes phase summaries | opus |
| `wordpress-architect` | Theme scaffold, data model, template hierarchy, conventions | opus |
| `theme-developer` | Theme PHP: templates, functions, enqueue, hooks, AJAX, plugin glue | opus |
| `uiux-designer` | Design system, SCSS, components, GSAP/Swiper motion, responsive | opus |
| `gutenberg-acf-developer` | CPTs, ACF field groups, custom Gutenberg blocks (CMS) | opus |
| `content-strategist` | Original NGO copy + sample content + image/alt manifest | sonnet |
| `seo-specialist` | Schema/JSON-LD, OG/Twitter, canonicals, breadcrumbs, sitemaps | sonnet |
| `accessibility-specialist` | WCAG 2.2 AA audit + fixes | sonnet |
| `performance-engineer` | 95+ Lighthouse, lazy load, WebP, caching, CWV | sonnet |
| `security-engineer` | Escaping, nonces, sanitization, uploads, reCAPTCHA, roles | opus |
| `devops-engineer` | Build tooling, env config, deployment guide | sonnet |
| `qa-verifier` | Independent phase-gate verification (read-only) | opus |

## How to use

Let the orchestrator drive, or call a specialist directly:

- "Use the wp-lead-orchestrator to plan Phase 1 and start the theme scaffold."
- "Have the wordpress-architect define the CPT + ACF data model."
- "Ask the uiux-designer to build the hero and program-card components."
- "Have qa-verifier review Phase 2 before I approve."

The orchestrator records tasks, routes them to specialists, and stops at each
phase gate for your approval.

## Phase -> agent map

1. Setup — architect (scaffold) + devops (tooling) + uiux (tokens/base)
2. Core layout — theme-developer + uiux + accessibility
3. Homepage — uiux + gutenberg-acf + content + theme-developer
4. Inner pages — theme-developer + gutenberg-acf + content
5. CMS/dynamic — gutenberg-acf + architect + theme-developer
6. Donations/volunteer — theme-developer + security + gutenberg-acf
7. Perf/SEO — performance + seo + accessibility
8. Test/deploy — qa-verifier + devops + security

Every phase ends with a `qa-verifier` pass, then a summary for your approval
before the next phase begins.

## Guardrails baked into every agent

WPCS + `bgcf_` prefix, escape-on-output / sanitize-on-input / nonces, ACF-driven
(no hardcoded content), WCAG 2.2 AA, design tokens only, original copyright-safe
work.
