# Mitwirken

Danke, dass Sie einen Beitrag zu `dskripchenko/laravel-admin` erwägen. Dieses
Dokument beschreibt den Arbeitsablauf, den Code-Stil und das, was im Review
erwartet wird.

> 🌐 [English](../../.github/CONTRIBUTING.md) · **Deutsch** · [Русский](../ru/contributing.md) · [中文](../zh/contributing.md)

## Komponentenkatalog

```bash
npm run storybook        # http://localhost:6007
npm run storybook:build  # dasselbe als statische Dateien — genau das prüft die CI
```

Der Katalog rendert einen Screen in jeder Breite, ohne Backend und ohne Daten.
So treten Layout-Fehler zutage, die auf einem Entwicklermonitor unsichtbar
bleiben. Die Breiten im Umschalter sind dieselben, die printables CI prüft
(Telefon 402, Tablet 768, Desktop 1280): Ein hier gefundener Fehler lässt sich
im Test reproduzieren und umgekehrt.

Stories liegen neben ihren Komponenten als `*.stories.ts`.

## Schnellstart (lokale Entwicklung)

```bash
git clone https://github.com/dskripchenko/laravel-admin.git
cd laravel-admin
composer install
npm install
```

Bauen und prüfen:

```bash
npm run build              # Produktions-Bundle des Frontends
vendor/bin/pest            # Backend-Tests (801+)
npm test                   # Frontend-Tests (319+)
npx vue-tsc --noEmit       # Typprüfung
vendor/bin/pint            # PHP-Code-Stil (korrigiert selbst)
vendor/bin/phpstan analyse # statische Analyse (Level 5)
```

## Aufbau des Repositorys

| Verzeichnis | Inhalt |
|---|---|
| `src/` | PHP-Quellcode (Resource, Screen, Field, Layout, Action, Widget, Menu und weiteres) |
| `resources/ts/` | Single-Page-Anwendung mit Vue 3 und TypeScript |
| `resources/views/` | Blade-Vorlage der Shell |
| `config/` | Standardkonfiguration (`admin.php`) |
| `database/migrations/` | Mitgelieferte Migrationen |
| `routes/` | Routen des Panels (über `AdminServiceProvider` registriert) |
| `tests/` | Pest-Tests (Feature + Unit + Fixtures) |
| `docs/` | Dieser Dokumentationsbaum (`{en,ru,de,zh}/`) |

## Branches und Commits

- Zweigen Sie von `main` ab.
- Ein Thema pro Pull Request. Halten Sie sie klein.
- Commit-Nachricht im Imperativ, mit Bereichspräfix:
  ```
  feat(dashboard): widget polling по Widget::refresh
  fix(notifications): graceful fallback if table missing
  docs(menu): add MenuNode::dashboard examples
  test(screen): cover runMethod 422 path
  refactor(widget): inline rowSpan resolver
  chore: bump @dskripchenko/wysiwyg ^0.2.7
  ```
- Ein `Co-Authored-By`-Trailer für KI-gestützte Arbeit ist willkommen.

## Code-Stil

### PHP

- **PHP 8.5+**, `strict_types` am Anfang jeder Datei.
- **Pint** formatiert (`vendor/bin/pint`). Vor dem Commit ausführen.
- **PHPStan, Level 5.** Typen nicht aufweichen und nichts mit
  `@phpstan-ignore` stummschalten — beheben Sie die Ursache (oder halten Sie
  fest, warum das Problem ererbt ist).
- Typ-Importe über `use`, nicht als FQCN im Code.
- Docblocks `@param`/`@return` an öffentlichen Methoden nur dort, wo Typen
  allein nicht reichen: generische Arrays, Callable-Formen und Ähnliches.

### TypeScript und Vue

- Überall `<script setup lang="ts">`. Keine Options-API.
- Composables in `composables/` (camelCase, Präfix `use*`).
- Props über `withDefaults(defineProps<Props>(), { ... })`.
- Klassennamen im BEM-Stil: `.admin-{component}__{element}--{modifier}`.
- Primitive holt das Frontend aus `@dskripchenko/ui` — schreiben Sie kein
  eigenes Markup, nehmen Sie `UidButton`, `UidInput` und die übrigen.

### CSS

- Ausschließlich CSS-Custom-Properties (`var(--uid-...)`). Kein Tailwind,
  kein SCSS, kein CSS-in-JS.
- Kein `<style scoped>` — Themes müssen durchgreifen können.

## Tests

- **Backend**: Pest (`vendor/bin/pest`). Die Struktur von `src/` spiegelt sich
  in `tests/Feature/` und `tests/Unit/`. Fixtures liegen in `tests/Fixtures/`
  (per Composer-Classmap geladen, globaler Namensraum).
- **Frontend**: Vitest + jsdom + `@vue/test-utils`. Die Testdatei liegt neben
  dem geprüften Bauteil (`Component.test.ts`).
- Die Datenbank nicht mocken — nehmen Sie SQLite im Arbeitsspeicher, das ist
  über `Orchestra\Testbench` bereits eingerichtet.
- Der End-to-End-Smoketest mit Playwright liegt in `demo/e2e-full-flow.mjs`.

## Release

Das Paket geht aus einem Tag in zwei Registries: Composer liest den Tag selbst,
npm liest `package.json`. Sie dürfen auseinanderlaufen — eine Änderung, die nur
PHP betrifft, bekommt einen Tag ohne npm-Release.

1. `CHANGELOG.md` aktualisieren.
2. Hat sich das Frontend geändert, im selben Commit `version` in
   `package.json` erhöhen. Sonst unangetastet lassen.
3. `vX.Y.Z` taggen und den Tag pushen.

Der Push des Tags startet `Publish to npm`. Veröffentlicht wird nur, wenn
`package.json` exakt die Version des Tags nennt und diese Version noch nicht in
der Registry liegt. Alles andere — ein reiner PHP-Tag, ein erneuter Lauf auf
einem bereits ausgelieferten Tag — endet grün ohne Veröffentlichung, damit die
Historie nicht darüber lügt, was tatsächlich passiert ist.

Vor dem Veröffentlichen laufen `typecheck` und `test`: Ein Tag kann auf einem
Commit sitzen, den die Branch-CI nie gesehen hat, und eine misslungene Version
lässt sich aus npm nicht mehr zurückholen.

Wurde der Tag gepusht, bevor die Version nachgezogen war, erhöhen Sie sie auf
`main` und starten `Publish to npm` von Hand (`workflow_dispatch`) — dieselben
Prüfungen greifen.

## Geschwisterpakete

Dieses Repository ist der **Kern**. Die Geschwisterpakete (`starter`, `health`,
`jobs`, `media`, `pulse`, `search`, `quill`, `tinymce`) liegen in eigenen
Repositories. Sie hängen über Composer von diesem Paket ab; der
Versionsvertrag lautet `^1.x` bis zu einem API-Bruch.

## Fehler melden

Über GitHub-Issues. Bitte beilegen:
- Versionen von Laravel, PHP und des Pakets;
- eine minimale Reproduktion (Code-Ausschnitt oder Link auf ein Repository);
- was Sie erwartet haben und was geschah;
- Konsolenfehler und Stacktrace, falls vorhanden.

## Sicherheit

Sicherheitslücken bitte an `denskrp90@gmail.com` melden, nicht in öffentliche
Issues schreiben.

## Lizenz

Mit Ihrem Beitrag stimmen Sie zu, dass Ihre Arbeit unter der
[MIT-Lizenz](../../LICENSE) des Projekts steht.
