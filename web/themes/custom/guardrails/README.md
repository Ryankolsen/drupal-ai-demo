# Guardrails Demo theme

An Olivero subtheme used for the *"Guardrails, Not Guesswork: Shipping Drupal
Features with Claude Code"* session. It exists to demonstrate
**Single Directory Components (SDC)** in Drupal 11.

## Single Directory Components

Components live under `components/<name>/`. Drupal core auto-discovers them —
no module to enable, no library to declare. Each component is a folder
containing:

- `<name>.component.yml` — metadata: prop/slot schema (validated in dev).
- `<name>.twig` — the markup, using props and `{% block %}` slots.
- `<name>.css` / `<name>.js` — optional assets, auto-attached on render.

### Example: the `card` component

Render it from any Twig template with `include` (props) ...

```twig
{{ include('guardrails:card', {
  heading: 'Guardrails, not guesswork',
  body: 'Ship Drupal features with Claude Code — safely.',
  url: '/about',
  cta: 'Learn more',
  modifier: 'accent',
}) }}
```

... or with `embed` when you need to fill the `content` slot:

```twig
{% embed 'guardrails:card' with { heading: 'Slotted card' } %}
  {% block content %}
    <ul><li>Anything renderable</li></ul>
  {% endblock %}
{% endembed %}
```

## Local development

This site runs under [DDEV](https://ddev.com):

```bash
ddev start
ddev launch            # open the site
ddev drush cr          # rebuild caches after theme/component changes
```

Admin login: `admin` / `admin` (demo only — do not reuse).
