---
title: Hierarchisches Menü
audience: developer
status: stable
locale: de
translated_from: en/concepts/menu.md
translated_at: 2026-10-02
---

# Hierarchisches Menü

Das Menü der Seitenleiste kann beliebig tief sein. Verwenden Sie `Admin::menu()`,
um es explizit zu deklarieren; es lässt sich mit automatisch erkannten
Resources/Screens kombinieren.

## Automatischer Modus (ohne Einrichtung)

Wenn Sie kein Menü konfigurieren, erhalten Sie eine flache Liste: Jede
registrierte Resource und jeder Custom Screen wird zu einem Eintrag der obersten
Ebene. Nützlich für kleine Admin-Panels.

## Explizite Hierarchie

```php
use Dskripchenko\LaravelAdmin\Menu\MenuNode;

Admin::menu()->add(
    MenuNode::make('content', 'Inhalte')->icon('book')->children([
        MenuNode::resource('articles'),
        MenuNode::make('tags', 'Tags')->icon('tag')->children([
            MenuNode::make('tags-tech', 'Technik')->url('/r/articles?tag=tech')->children([
                MenuNode::make('tags-tech-vue', 'Vue')->url('/r/articles?tag=tech.vue'),
                MenuNode::make('tags-tech-php', 'PHP')->url('/r/articles?tag=tech.php'),
            ]),
        ]),
    ]),
);
Admin::menu()->add(
    MenuNode::make('shop', 'Shop')->icon('shopping-cart')->children([
        MenuNode::resource('products'),
        MenuNode::resource('orders'),
    ]),
);
Admin::menu()->add(
    MenuNode::make('analytics', 'Analysen')->icon('chart-bar')->children([
        MenuNode::dashboard('content'),
    ]),
);
```

## MenuNode-Fabriken

| Fabrik | Ermittelt |
|---|---|
| `MenuNode::make($key, $label)` | Manueller Knoten — setzen Sie `icon()`/`url()`/`routeName()` selbst. |
| `MenuNode::resource($slug)` | Übernimmt Beschriftung/URL/Berechtigungen aus `ResourceRegistry`. |
| `MenuNode::screen($slug)` | Übernimmt Beschriftung/URL aus `ScreenRegistry`. Erkennt DashboardScreen automatisch und leitet stattdessen auf `/dashboard/{slug}`. |
| `MenuNode::dashboard($slug)` | Expliziter DashboardScreen-Helper — `/dashboard/{slug}`. |

Manuelle Überschreibungen haben Vorrang vor automatisch ermittelten Werten:

```php
MenuNode::resource('articles')->label('Alle Artikel')->icon('newspaper'),
```

## Fluent-API

```php
MenuNode::make($key, $label)
    ->icon('lucide-name')
    ->url('/custom/path')                  // oder
    ->routeName('admin.custom.route')      // hat Vorrang vor url
    ->badge(42)                            // Zahl oder String
    ->permissions(['admin.articles.view']) // Array, String oder null
    ->order(10)                            // Sortierung innerhalb der Gruppe
    ->group('Abschnitt')                   // Abschnittsüberschrift (nur oberste Ebene)
    ->children([ MenuNode::... ])
    ->add(MenuNode::...);                  // Kindknoten anhängen
```

## In einen vorhandenen Elternknoten einfügen

```php
Admin::menu()->under('shop', [
    MenuNode::resource('coupons'),
    MenuNode::resource('discounts'),
]);
```

`under($parentKey, [...])` sucht rekursiv. Existiert `$parentKey` nicht, wird ein
Platzhalter-Elternknoten angelegt.

## Steuerung des automatischen Auffüllens

Standard: Jede Resource bzw. jeder Custom Screen, der in Ihrem Baum nicht erwähnt
wird, wird automatisch angehängt (Screens unter der Gruppe `Tools`, Resources unter
ihrer eigenen `$group` oder ohne Gruppe, wenn diese nicht gesetzt ist). Zum
Deaktivieren:

```php
Admin::menu()->withAuto(false);
```

Dann müssen Sie jeden sichtbaren Eintrag explizit aufführen.

Um das automatische Auffüllen aktiviert zu lassen, aber eine einzelne Resource oder
einen einzelnen Screen auszulassen — etwa eine untergeordnete Resource, die nur
eingebettet in ihrem Elternelement angezeigt wird:

```php
Admin::menu()->hideAuto('order-items');
```

## Berechtigungen

Ein Knoten wird ausgeblendet, wenn seine `permissions` vom aktuellen Benutzer nicht
erfüllt werden. Wurde `MenuNode::resource()` verwendet, ist die Prüfung
standardmäßig `admin.{slug}.view`.

Teilbäume werden ebenfalls gefiltert: Ein Elternknoten bleibt sichtbar, wenn
mindestens ein Kindknoten die Prüfung besteht; andernfalls wird der gesamte Zweig
ausgeblendet.

## Visuelle Hierarchie

Das Frontend rendert Knoten rekursiv (`AdminSidebarNode.vue`):

- Tiefe 0..2: zunehmend eingerückt (`14px` pro Ebene).
- Tiefe ≥ 3: Einrückung fest bei `28px`, ersetzt durch einen vertikalen Streifen
  links (Farbe = primary, mit der Tiefe abnehmendes Alpha: 0.85 → 0.67 → ...).
- Elternknoten mit Kindknoten zeigen einen Chevron; ein Klick klappt auf und zu.
- Die aktive Route klappt die Kette ihrer Vorfahren automatisch auf.

## Response-Struktur des Backends

```json
{
  "items": [
    {
      "key": "content",
      "label": "Inhalte",
      "icon": "book",
      "url": null,
      "routeName": null,
      "badge": null,
      "group": null,
      "order": 0,
      "permissions": [],
      "children": [
        { "key": "resource.articles", "label": "Articles", "url": "/r/articles", ..., "children": [] }
      ]
    }
  ]
}
```

## Siehe auch

- [Resources](resources.md)
- [Screens](screens.md)
- [Berechtigungen](permissions.md)
