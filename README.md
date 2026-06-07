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

## Local URL

Once started, the site is served at:

- **https://drupal-ai-demo.ddev.site** — front end
- **https://drupal-ai-demo.ddev.site/user/login** — admin login (`admin` / `admin`)

## Running the app

Requires [DDEV](https://ddev.com/get-started/) and a Docker provider
(Docker Desktop or OrbStack).

### Start an existing checkout

If the site is already installed locally, just bring the containers up:

```bash
cd drupal-ai-demo
ddev start            # start the containers
ddev launch           # open https://drupal-ai-demo.ddev.site in your browser
```

Useful day-to-day commands:

```bash
ddev drush uli        # one-time admin login link
ddev drush cr         # rebuild caches after theme/component changes
ddev stop             # shut the containers down
ddev describe         # URLs, ports, and service status
```

### First-time setup from a fresh clone

```bash
git clone https://github.com/Ryankolsen/drupal-ai-demo.git
cd drupal-ai-demo
ddev start
ddev composer install                     # restore core/contrib/vendor
ddev drush site:install standard -y       # fresh database
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
