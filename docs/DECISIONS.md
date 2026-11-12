# Decision log

The canonical record of **why** this repository is the way it is: the reasoning and the **rejected alternatives** behind decisions that have closed. It is the guard against silently relitigating a settled decision.

**Consult it before acting.** Before proposing a change, reopening a question, or picking an approach, check here first: the path under consideration may already be a decided-and-recorded one, or an explicitly rejected one. If it is, follow the record (or make the case to revisit it); do not quietly re-decide.

**When a seam closes, record the verdict in both places.** Capture the *what*, *why* and rejected alternatives **here** (the cross-ticket index) **and** in the GitHub issue's `## Decision` section (the per-ticket record), keeping the two in sync. This file does not replace the issue tracker, which stays the system of record for a single ticket's status.

See the [`design-decision-forks`](../.claude/rules/design-decision-forks.md) rule for how a fork is surfaced and driven to resolution before its verdict lands here.

## How to add an entry

Oldest first, one entry per closed decision, so **a new entry is appended to the end of the file**. Keep it terse; the issue carries the depth. Each entry is preceded by exactly one blank line, which `scripts/check-decision-log-order.mjs` enforces along with the date order. The file is marked `merge=union` in `.gitattributes`, so two branches that each append an entry merge without a conflict.

```markdown
## YYYY-MM-DD: <short decision title> (<#issue or area>)

**Decision.** <what was decided, in a sentence or two>

**Why.** <the reasoning that settled it>

**Rejected.** <the alternatives considered, and why each lost>

**Companion.** <link to the GitHub issue holding the full reasoning>
```

---

## 2026-10-14: Started from the add-on template (repository setup)

**Decision.** This repository started from `UAMS-Web/statamic-addon-template`. It carries no synced files (`.agents/`, `.claude/`, `.cursor/`) and no `LICENSE`, and `composer.json` has no `license` field.

**Why.** The shared rules and skills reach a repository by onboarding it to the sync in `UAMS-Web/uams-claude-skills`, not by copying a snapshot that goes stale. The license waits on the general decision from UAMS legal in `UAMS-Web/uams-statamic#261`.

**Rejected.** Snapshotting the synced files into the template: every new add-on would begin with a stale copy that conflicts with its first sync pull request. Copying a license from an existing add-on: legal has not decided which license applies.

**Companion.** `UAMS-Web/uams-statamic#261`.

## 2026-11-02: Renamed from the template, and no config file yet (UAMS-Web/aether-incomplete-entries#2)

**Decision.** The package is `uams-web/aether-incomplete-entries` with namespace `UAMSWeb\Aether\IncompleteEntries\`. The template's placeholder `config/` file and its publish-tag test are dropped.

**Why.** The add-on has no settings until the audit moves in (the next ticket), and an empty config file would publish a file with nothing in it. The audit's ticket adds `config/aether-incomplete-entries.php`, with its tag, if the move turns up a site-specific value.

**Rejected.** Keeping the empty file renamed: it ships and publishes nothing, and the test that pins it would pin an empty array.

**Companion.** UAMS-Web/aether-incomplete-entries#2.

## 2026-11-12: The add-on goes to Packagist on MIT, ahead of UAMS legal's decision (UAMS-Web/aether-incomplete-entries#51)

**Decision.** The add-on is published on Packagist (`v0.1.0`, `v0.2.0` and `v0.2.1` are tagged) and is MIT licensed (`LICENSE`, `"license": "MIT"`), matching `UAMS-Web/aether-redirects`, ahead of the general decision in `UAMS-Web/uams-statamic#261`. The Marketplace is not part of this decision.

**Why.** A public Packagist package needs a declared license, and the sibling add-ons are MIT. One license across them keeps the choice in one place, and the file can change if UAMS legal decides otherwise.

**Rejected.** Waiting for UAMS legal before declaring a license: the package is already listed and tagged, and a listed package with no license grants consumers no rights to use it.

**Companion.** UAMS-Web/aether-incomplete-entries#51, `UAMS-Web/uams-statamic#261`.
