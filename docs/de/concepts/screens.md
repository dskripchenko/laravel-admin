---
title: Screens
audience: developer
status: stable
locale: de
translated_from: en/concepts/screens.md
translated_at: 2026-10-02
---

# Screens

Ein **Screen** ist eine Seite ohne CRUD: Kontaktformular, Statusbericht,
eigener Import-Assistent, Integrationsseite. Screens nutzen die Primitive
`Field`/`Layout`/`Action`, sind aber nicht an ein Eloquent-Modell gebunden.

```php
use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Layout\Rows;
use Dskripchenko\LaravelAdmin\Screen\Screen;

final class ContactScreen extends Screen
{
    public function name(): string { return 'Kontakt'; }

    public function query(mixed ...$params): array
    {
        return ['email' => '', 'message' => ''];
    }

    public function layout(): array
    {
        return [
            Rows::make([
                Input::make('email')->required()->type('email'),
                Textarea::make('message')->required()->rows(6),
            ]),
        ];
    }

    public function commandBar(): array
    {
        return [Button::make('Senden')->method('send')->primary()];
    }

    public function send(array $state): array
    {
        validator($state, [
            'email' => 'required|email',
            'message' => 'required|min:10',
        ])->validate();

        \Mail::to('team@example.com')->send(new \App\Mail\Contact($state));

        return [
            'message' => 'Gesendet',
            'state' => ['email' => '', 'message' => ''],
            'alerts' => [['type' => 'success', 'message' => 'Vielen Dank!']],
        ];
    }
}
```

Registrierung: `Admin::screen([ContactScreen::class])`.

URL: `/admin/screens/contact`.

Der Slug ist ein einzelnes Pfadsegment: `/admin/screens/{slug}` ist die
einzige Adresse eines Screens, und es gibt kein
`/admin/screens/{slug}/{anything}`.

## Query-String

Der Query-String der Seite wird mit dem Request für den State des Screens
mitgesendet, sodass ein Screen auf einem Tab, einem Zeitraum oder einem Filter
öffnen kann, der in der Adresse angegeben ist —
`/admin/screens/reports?period=30&tab=billing`. Eine Änderung des
Query-Strings (ein Link vom Screen auf `?tab=…`) lädt einen frischen Snapshot,
und ein Neuladen nach einer Command-Methode behält ihn bei.

`query()` erhält die Werte positionsbezogen, in der Reihenfolge des
Query-Strings (Schlüssel, die mit `_` beginnen, werden verworfen); über den
Namen sind sie über den Request zugänglich:

```php
public function query(mixed ...$params): array
{
    return [
        'tab' => request()->query('tab', 'overview'),
        'period' => (int) request()->query('period', 7),
    ];
}
```

Die Werte stammen aus der Adresszeile, validieren Sie sie also wie jede andere
Eingabe.

## Aufbau

| Methode | Zweck |
|---|---|
| `slug()` | Stabiler URL-Bezeichner. Standard — kebab-case des Klassen-Basisnamens ohne das Suffix `Screen`. |
| `name()` | Anzeigetitel in der Kopfzeile und der Seitenleiste. |
| `description()` | Optionaler Untertitel unter dem Titel. |
| `permission()` | Berechtigungsprüfung (String oder Liste). null = jeder angemeldete Admin. |
| `query(...$params)` | Liefert den initialen State. Erhält die Werte des Query-Strings der Seite als Positionsargumente (siehe unten). |
| `layout()` | Liefert `Renderable[]` (Rows/Columns/Tabs/Block/...). |
| `commandBar()` | Liefert `Action[]`, die in der Kopfzeile der Seite gerendert werden. |
| Öffentliche Methoden | Jede andere öffentliche Methode (nicht in der reservierten Menge) ist als Command über `Button::make('…')->method('xxx')` aufrufbar. |

Reservierte Methodennamen: `query`, `layout`, `name`, `description`,
`permission`, `commandBar`, `compile`, `slug`, `reservedMethods`,
`isCallableMethod`.

## Command-Methoden

Eine Command-Methode erhält ein einziges Argument: den State-Payload aus dem
Frontend (`{form_field: value, ...}`):

```php
public function send(array $state): array { ... }
```

Rückgabewerte:

- `array` — wird in einen normalisierten `ScreenMethodPayload` verpackt und
  zurückgesendet. Erkannte Schlüssel: `state`, `layouts`, `message`,
  `message_link`, `alerts`, `redirect_url`, `refresh`, `download_url`; jeder
  andere Schlüssel landet in `extra`.
- `JsonResponse` — wird unverändert durchgereicht.
- `null` / `void` — `{ok: true}`.

`message_link` ist der Link unter der Meldung. Akzeptierte Formen:
`['url' => '/r/jobs/7', 'label' => 'Auftrag öffnen']`,
`['href' => …, 'text' => …]`, ein Paar `['/r/jobs/7', 'Auftrag öffnen']` oder
ein bloßer URL-String. Ohne Label erhält er den Standardtext "Open"; ohne URL
wird er verworfen. `redirect_url` — ein Pfad innerhalb des Panels
(`/r/orders`; das Panel-Präfix darf stehen bleiben) wird über den Router
geöffnet, eine externe Adresse mit einem vollständigen Seitenaufruf; `refresh`
wird bei einer Weiterleitung übersprungen.

Validierung: Werfen Sie `\Illuminate\Validation\ValidationException` (z. B.
über `validator(...)->validate()`) — `useScreenStore.errors` im Frontend zeigt
die Feldfehler an. Eine inhaltliche Ablehnung ist eine
`ActionFailedException`
(`Dskripchenko\LaravelAdmin\Resource\ActionFailedException`): ein 422 mit
`errorKey: action_failed` und ihrer Meldung, die das Panel als Fehler anzeigt.

## Listener

`Layout::listener([...])->listen([...])->handler('method')` macht einen Teil
des Formulars eines Screens reaktiv: Die SPA sendet den State an
`POST /api/admin/{slug}/listener`, sobald sich die beobachteten Felder ändern,
und der Server antwortet mit dem neu gerenderten Teilbaum und einem
State-Patch. Siehe
[Layout-Referenz → Listener](../layouts-reference.md#listener-reaktiver-teil-eines-formulars).

## Beispiele

### Schreibgeschützter Screen (ohne Formular)

```php
public function layout(): array
{
    return [
        Rows::make([
            Block::make('Zustand', [
                Number::make('articles_total')->title('Artikel')->readonly(),
                Input::make('db_status')->title('DB')->readonly(),
            ]),
        ]),
    ];
}
```

`->readonly()` wird bei `Select` auf `disabled` abgebildet, bei
`Input`/`Number` auf das native `readonly`.

### Action mit Bestätigung

```php
Button::make('Zähler zurücksetzen')
    ->method('resetCounter')
    ->confirm('Sind Sie sicher? Dies kann nicht rückgängig gemacht werden.')
    ->destructive(),
```

### Neuladen nach einer Action

```php
public function reload(): array
{
    return ['message' => 'Aktualisiert', 'refresh' => true];
}
```

`refresh: true` löst nach der Action `useScreenStore.load()` aus.

### Download

```php
public function exportCsv(): array
{
    $url = Storage::temporaryUrl(...);
    return ['download_url' => $url];
}
```

### Weiterleitung

```php
public function publishAndOpen(array $state): array
{
    $article = Article::create($state);
    return ['redirect_url' => "/admin/r/articles/{$article->id}/edit"];
}
```

## Berechtigungen

```php
public function permission(): array|string|null
{
    return 'admin.contact';
}
```

`AdminAccess:admin.contact` wird automatisch an die beiden Actions `state` und
`runMethod` angehängt. Für getrennte Prüfungen je Methode — prüfen Sie
innerhalb der Command-Methode selbst.

## Unterschied zur Resource

| Aspekt | Resource | Screen |
|---|---|---|
| An ein Modell gebunden | Ja (Eloquent) | Nein |
| URL | `/r/{slug}` (+`/{id}/edit`, `/create`, `/{id}`) | `/screens/{slug}` |
| Endpoints | `meta`, `search`, `read`, `create`, `update`, `delete`, ... | `state` (GET), `runMethod` (POST) |
| Automatisch generierte UI | Ja | Nein (vom Host über `layout()` gesteuert) |
| Mehrere Datensätze | Ja (Tabelle) | Nein (ein einzelner State) |

## Siehe auch

- [Berechtigungen](permissions.md)
- [Layout-Referenz](../layouts-reference.md)
