# Drupal AI Demo

Companion site for the session **"Guardrails, Not Guesswork: Shipping Drupal
Features with Claude Code"** ([Drupal Asheville
2026](https://www.drupalasheville.com/events/2026/sessions/guardrails-not-guesswork-shipping-drupal-features-claude-code)).

A deliberately pokeable Drupal 11 site for demonstrating how to build features
safely with Claude Code — content types, fields, Views, contrib patches, config
capture, and **Single Directory Components** — all behind project guardrails.

## Stack

- **Drupal 11** (latest), managed with **Composer**
- **DDEV** for the local environment (Docker / OrbStack)
- **Drush** for site install and config
- Custom **`guardrails`** theme (Olivero subtheme) showcasing SDC

## Getting started

Requires [DDEV](https://ddev.com/get-started/) and a Docker provider.

```bash
git clone https://github.com/Ryankolsen/drupal-ai-demo.git
cd drupal-ai-demo
ddev start
ddev composer install
ddev drush site:install standard -y      # fresh DB
ddev launch
```

Composer-installed code (Drupal core, contrib, `vendor/`) is **not** committed —
`ddev composer install` restores it from `composer.lock`.

## Single Directory Components

The custom theme lives at `web/themes/custom/guardrails`. Its components are
under `components/` and are auto-discovered by core. See
[`web/themes/custom/guardrails/README.md`](web/themes/custom/guardrails/README.md)
for usage, including the example `card` component.

## Demo credentials

`admin` / `admin` — local demo only. Do not reuse.
