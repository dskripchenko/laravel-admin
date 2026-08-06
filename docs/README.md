# Documentation

English is the default. Every user-facing document lives under a language
directory with a mirrored path, so `docs/en/concepts/menu.md` and
`docs/ru/concepts/menu.md` are the same page in two languages.

- [English](en/getting-started.md) · [Deutsch](de/README.md) · [Русский](ru/README.md) · [中文](zh/README.md)

## Layout

| Path | What lives there |
| --- | --- |
| `en/` `de/` `ru/` `zh/` | User-facing documentation, one directory per language |
| `internal/` | Working documents: the architecture draft, the roadmap, phase plans, the design handoff. Not translated — they are written for whoever is building the package, and they change too often to keep four copies honest. |
| `internal/superseded/` | Earlier, more detailed drafts whose content has not yet been folded into the language packs. Kept so nothing is lost while that happens. |

## Coverage

The language packs are not equally complete. This table is the honest state,
not a plan:

| Section | en | de | ru | zh |
| --- | :-: | :-: | :-: | :-: |
| getting-started, architecture, glossary | ✅ | ✅ | ✅ | ✅ |
| concepts: menu, resources, screens, widgets | ✅ | ✅ | ✅ | ✅ |
| concepts: actions, i18n, permissions, tenancy | ✅ | — | — | — |
| api-reference, fields, layouts, testing, migration, frontend-extension | ✅ | — | — | — |
| `api/` — endpoint reference (17 pages) | — | — | ✅ | — |
| `recipes/` — how-to guides (9 pages) | — | — | ✅ | — |
| `sister-packs/` — specifications of the optional packages | — | — | ✅ | — |

Two gaps, both real: the deeper English pages have no translations yet, and
the endpoint reference, recipes and sister-pack specs exist only in Russian.
They are being filled in; until then this table says which is which rather
than leaving a reader to discover it by following a dead link.
