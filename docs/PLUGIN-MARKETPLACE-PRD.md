# PRD: Claude Code Plugin Marketplace for the Drupal Guardrails Skills

> **How to use this doc:** This PRD describes work to be done in a **new, separate GitHub
> repository** (not this `drupal-ai-demo` repo). Start a fresh Claude Code chat inside the
> new repo and hand it this file. The skills to bundle currently live at
> `…/drupal-ai-demo/.claude/skills/` — the implementing agent will copy them across and
> generalize the repo-specific ones.

## Problem Statement

Over the course of building the board-games demo for the "Guardrails, Not Guesswork" talk,
we've accumulated 17 reusable Claude skills in `.claude/skills/`. Right now they're trapped
in one project repo: the only way for someone to get them is to clone the whole Drupal demo
and copy directories by hand. There's no single command to install them, no versioning, no
README explaining what each does, and no clean attribution for the two skills that originated
elsewhere. The meta-goal of the talk — *shipping a starter set of reusable, shareable skills*
— isn't actually shareable yet.

## Solution

Publish the skills as a **Claude Code plugin distributed through a plugin marketplace** on the
user's GitHub account. A person should be able to run two commands in Claude Code
(`/plugin marketplace add <owner>/<repo>` then `/plugin install <plugin>`) and have all the
skills available in any project. The repo carries a README that explains installation, lists
every skill with a one-line description, and credits Matt Pocock (github.com/mattpocock/skills)
for the `to-prd` and `to-issues` skills. Skills that were written specifically for the
board-games demo are generalized so they're useful in any Drupal (or, where applicable, any)
project.

## User Stories

1. As a developer who saw the talk, I want to add the marketplace with one command, so that I don't have to clone an unrelated Drupal demo to get the skills.
2. As that developer, I want to install the plugin with one command, so that all 17 skills become available in my own projects immediately.
3. As a Claude Code user, I want each skill to keep its bundled `REFERENCE.md` / `EXAMPLES.md` / `scripts/` files, so that progressive-disclosure skills still work after install.
4. As a user, I want a README that lists every skill with a one-line summary, so that I can tell at a glance what the plugin gives me.
5. As a user, I want clear install/update/uninstall instructions in the README, so that I'm not guessing at the plugin lifecycle commands.
6. As Matt Pocock (and as an honest author), I want the `to-prd` and `to-issues` skills explicitly credited to github.com/mattpocock/skills, so that attribution is preserved.
7. As a Drupal developer on a *different* project, I want the generalized `do-work`, `commit`, and `drupal-*` skills to not assume a "board-games" domain or this specific repo, so that they apply to my codebase.
8. As the plugin author, I want a `marketplace.json` that validates and a `plugin.json` with proper name/version/description/author metadata, so that Claude Code recognizes and lists the plugin correctly.
9. As the plugin author, I want the plugin to use semantic versioning, so that I can ship updates and users can see what version they have.
10. As a user, I want skills to be auto-discovered by Claude Code after install (no manual registration), so that they "just work" by description-matching.
11. As the plugin author, I want a chosen name that does not collide with my existing `rko-claude-skills` marketplace namespace, so that both can be installed side by side.
12. As a user evaluating the plugin, I want the README to note which skills are Drupal-specific vs. generally reusable, so that I know what I'm getting before installing.
13. As the plugin author, I want a license file, so that others know the terms of reuse.
14. As the plugin author, I want the README to link back to the talk and the original demo repo, so that the context is discoverable.

## Implementation Decisions

### Repository layout (decided)

Single new repo = **one marketplace containing one plugin**. The plugin lives in its own
subdirectory; skills live under that plugin's `skills/` directory:

```
<repo>/
├── .claude-plugin/
│   └── marketplace.json          # lists the single plugin, source points at the plugin dir
├── <plugin-name>/
│   ├── .claude-plugin/
│   │   └── plugin.json           # name, version, description, author
│   └── skills/
│       ├── add-canvas-sdc/       # SKILL.md + scripts/
│       ├── adding-fields/
│       ├── build-a-view/         # + REFERENCE.md
│       ├── build-feature/
│       ├── commit/
│       ├── compose-canvas-page/  # + REFERENCE.md
│       ├── create-content-type/  # + EXAMPLES.md
│       ├── create-patch/
│       ├── do-work/              # + REFERENCE.md
│       ├── drupal-code-review/
│       ├── drupal-core-update/
│       ├── editing-views/        # + EXAMPLES.md + REFERENCE.md
│       ├── seed-content-from-fixture/
│       ├── test-module/          # + REFERENCE.md
│       ├── to-issues/
│       ├── to-prd/
│       └── trim-a-skill/         # + scripts/
├── README.md
└── LICENSE
```

### Naming (proposed — confirm in the fresh chat)

- **Repo:** `drupal-guardrails-skills`
- **Marketplace name** (in `marketplace.json`): `drupal-guardrails`
- **Plugin name** (in `plugin.json` and the install command): `drupal-guardrails`

These avoid the user's existing `rko-claude-skills` namespace. If a single combined namespace
is preferred instead, that's a one-line change.

### Manifests

- `marketplace.json`: `name`, `owner` (name + GitHub URL/email), and a `plugins` array with one
  entry whose `source` points at the plugin subdirectory and which carries `name`,
  `description`, and `version`.
- `plugin.json`: `name`, `version` (start at `0.1.0`, semver), `description`, `author`,
  optional `homepage`/`repository` pointing at the talk and original demo repo.
- Skills are auto-discovered from the plugin's `skills/` directory by Claude Code; no manual
  per-skill registration is required.

### Skill content (decided: "all 17, generalized")

Copy every skill directory **wholesale** (including bundled `REFERENCE.md` / `EXAMPLES.md` /
`scripts/`), then generalize the ones that hardcode this project:

- **`do-work`** — currently scoped to "this Drupal 11 + DDEV **board-games** repo." Strip the
  board-games domain; keep the Drupal/DDEV guardrails and the "which skill to reach for"
  decision map, but phrase them so they apply to any Drupal project using these skills.
- **`commit`** — currently "the house way" for *this* repo. Generalize to "the conventions
  configured for the current repo" while keeping the concrete rules (split logical commits,
  WHY-focused messages, no Co-Authored-By trailer).
- **`drupal-*`, `build-a-view`, `create-content-type`, etc.** — already generic to Drupal;
  scrub any stray board-games examples but otherwise leave intact. These stay Drupal-specific
  by nature and the README will say so.
- Cross-skill `[[wikilink]]`-style references and "see the X skill" pointers must still resolve
  within the bundled set after the move.

### Attribution

- `to-prd` and `to-issues` keep their content but gain a credit line, and the README has a
  dedicated **Credits / Attribution** section naming Matt Pocock and linking
  github.com/mattpocock/skills.

### README contents

Install (`/plugin marketplace add` + `/plugin install`), update, and uninstall instructions;
a table of all 17 skills (name, one-line description, Drupal-specific vs. general); a
prerequisites note (some skills assume Drupal 11 + DDEV); the attribution section; and links
back to the talk and the `drupal-ai-demo` repo.

### Validation / done criteria

- `marketplace.json` and `plugin.json` parse as valid JSON and contain the required keys.
- After `/plugin marketplace add` + `/plugin install` against the repo, all 17 skills appear
  in Claude Code's skill list and each is invocable.
- Bundled resource files (`REFERENCE.md`, `EXAMPLES.md`, `scripts/`) are present for the skills
  that ship them.
- No skill body references "board-games" except where intentionally kept as an example.

## Out of Scope

- Splitting the skills into multiple plugins (e.g. drupal vs. workflow) — single plugin for now.
- Authoring brand-new skills, or `commands/`, `agents/`, `hooks/`, or `.mcp.json` components —
  this plugin ships **skills only**.
- Changing what any skill *does* beyond removing project-specific coupling.
- Publishing to any registry other than the user's own GitHub repo.
- CI, automated marketplace validation workflows, or release automation.
- Modifying the skills in the original `drupal-ai-demo` repo (they stay as the source of truth
  there; the new repo gets generalized copies).

## Further Notes

- The user already maintains an `rko-claude-skills` marketplace/plugin namespace; the chosen
  names must not collide so both can be installed together.
- The generic-vs-Drupal distinction matters: a non-Drupal user installing this plugin will see
  Drupal skills that won't match their work — that's acceptable as long as the README sets the
  expectation. A future split into a separate "workflow" plugin is noted but out of scope.
- Source skills to copy from: `…/drupal-ai-demo/.claude/skills/` (17 directories).
```
