---
title: Widgets & Dashboards
audience: developer
status: stable
locale: de
translated_from: en/concepts/widgets-and-dashboards.md
translated_at: 2026-10-02
---

# Widgets & Dashboards

Ein **Dashboard** ist ein Screen, der ein 12-spaltiges Raster aus **Widgets**
enthält.

```php
use Dskripchenko\LaravelAdmin\Widget\DashboardScreen;
use Dskripchenko\LaravelAdmin\Widget\StatsOverviewWidget;
use Dskripchenko\LaravelAdmin\Widget\ChartWidget;

final class ContentDashboardScreen extends DashboardScreen
{
    public static function slug(): string { return 'content'; }
    public function name(): string { return 'Analytik' ;}

    public function widgets(): array
    {
        return [
            StatsOverviewWidget::make()
                ->title('Artikel')
                ->size(3)
                ->stat('TOTAL', Article::count())
                ->trend(12.4, 'up'),

            ChartWidget::make()
                ->title('Tägliche Veröffentlichungen')
                ->size(8)
                ->rowSpan(2)
                ->chartType('bar')
                ->labels($days)
                ->dataset('Veröffentlicht', $values, '#10b981'),
        ];
    }
}
```

`stat()` nimmt die rohe Zahl; das Panel formatiert sie in seiner Locale. Für
Geldbeträge `money()` auf der Karte aufrufen: `->stat('Umsatz', $revenue)->money('USD')`
zeigt `$1,591,285` auf Englisch und `1 591 285 $` auf Russisch. `precision()`,
`prefix()` und `suffix()` beschreiben ebenfalls die zuletzt hinzugefügte Karte.

Registrierung: `Admin::screen([ContentDashboardScreen::class])`. URL:
`/admin/dashboard/content`.

## Eingebaute Widget-Typen

| Klasse | `widgetType()` | Verwendung |
|---|---|---|
| `StatsOverviewWidget` | `stats` | Einzelner Wert + Trend; KPI-Karten. |
| `ChartWidget` | `chart` | Line / Bar / Area / Radar (beliebig viele `dataset()`-Reihen, `stacked()` für Bar und Area) sowie Doughnut / Pie. |
| `RecentListWidget` | `recent_list` | Die letzten N Zeilen eines Eloquent-Modells. |
| `MarkdownWidget` | `markdown` | Statischer formatierter Text. |
| `IframeWidget` | `iframe` | Einbetten einer externen URL. |
| `TableWidget` | `table` | Flache, schreibgeschützte Daten. |
| `HeatmapWidget` | `heatmap` | Matrix `rows × cols × value` (z. B. Aktivität nach Stunde). |
| `GaugeWidget` | `gauge` | Einzelner Wert 0..max mit Schwellenwerten. |

## Größen

Jedes Widget hat `size()` (1..12 Spalten, Standard 6) und optional
`rowSpan()` (1..6 Zeilen à `140px`, Standard je nach Typ: stat=1,
chart/heatmap=2..3).

```php
ChartWidget::make()->size(8)->rowSpan(2);    // ~halbe Breite, ~296px hoch
StatsOverviewWidget::make()->size(3);        // Viertelbreite, Standard rowSpan=1
```

Der Benutzer kann beide Achsen im Bearbeitungsmodus überschreiben (untere
rechte Ecke ziehen).

## Polling

```php
ChartWidget::make()
    ->title('Live-Registrierungen')
    ->refresh(30);   // alle 30 Sekunden neu abrufen
```

Das Frontend berechnet das Minimum von `refresh` über die sichtbaren Widgets
und fragt `/api/admin/dashboard/widgets?key={slug}&period={p}` einmal pro
Intervall ab. Ein Timer für das gesamte Dashboard.

## Bearbeitungsmodus

Klicken Sie in der Dashboard-Werkzeugleiste auf „Edit“. Für jedes Widget
erscheinen Overlays:

- **☰** Ziehgriff — umsortieren
- **⚙** Konfigurieren — öffnet den Konfigurationsdialog des Widgets (Titel/Größe/typspezifisch)
- **×** Entfernen (oder Ausblenden, wenn es ein Manifest-Widget ist — weiches Überschreiben)
- **↘** Größe ändern — beide Achsen ziehen (X=Spalten, Y=Zeilen)

Der Benutzer kann außerdem **+ Add widget** wählen — das öffnet den Dialog zur
Typauswahl. Eigene Widgets erhalten `slug = "custom.{type}.{timestamp}"`.

Speichern → POST `/api/admin/dashboard/save` mit dem vollständigen
Widget-Array. Pro Benutzer in `admin_dashboard_layouts` gespeichert.

## Überschreibungen pro Benutzer

Das Modell ist:

```
Das Manifest deklariert die Widgets (der Host-Code definiert das kanonische Layout).
Das Benutzer-Layout (DashboardLayout-Zeile) liegt darüber — gleiche Slugs, andere
{size, position, hidden, rowSpan}; dazu selbst hinzugefügte Widgets.
```

Ändert sich das Manifest (neues Widget im Code hinzugefügt), erscheint es
standardmäßig am Ende des Rasters des Benutzers.

### Mehrere Widgets einer Klasse

Ein Dashboard unterscheidet seine Widgets anhand des Slugs, und der Slug eines
Widgets ergibt sich aus seiner Klasse — zwei `ChartWidget`s würden sich also
einen teilen. Das tun sie nicht: Die zweite und jede weitere Instanz eines
Slugs erhalten in der Reihenfolge ihrer Deklaration `-2`, `-3` angehängt
(`chart`, `chart-2`). Die erste behält den einfachen Slug, sodass ein zuvor
gespeichertes Layout weiterhin auf sie verweist.

Da ein Suffix der Deklarationsreihenfolge folgt, benennen Sie die Instanzen,
wenn sich die Reihenfolge ändern kann; dann bleiben die gespeicherten Layouts
mit dem richtigen Widget verbunden:

```php
public function widgets(): array
{
    return [
        ChartWidget::make()->withSlug('revenue')->title('Umsatz'),
        ChartWidget::make()->withSlug('signups')->title('Registrierungen'),
    ];
}
```

## Berechtigungen

Ein Dashboard und jedes seiner Widgets können abgesichert werden:

```php
final class SalesDashboardScreen extends DashboardScreen
{
    public function permission(): array|string|null
    {
        return 'sales.dashboard';            // ein Array bedeutet „alle davon“
    }

    public function widgets(): array
    {
        return [
            RevenueWidget::make()->permission('sales.revenue'),
            OrdersWidget::make()->canSee(fn () => auth('admin')->user()?->is_manager),
        ];
    }
}
```

Die Regeln werden auf dem Server durchgesetzt, wo auch immer das Dashboard
ausgeliefert wird:

- Ein Dashboard, das der Benutzer nicht öffnen darf, fehlt im Manifest und im
  Menü, und jeder Aufruf von `/api/admin/dashboard/*` dafür antwortet mit
  `403`;
- ein Widget, das der Benutzer nicht sehen darf, wird verworfen, bevor sein
  `data()` aufgerufen wird — seine Abfragen laufen nie —, im Manifest, in
  `dashboard/widgets` (Zeitraumwechsel und Polling) und in `layout()`;
- ein gespeichertes Benutzer-Layout kann ein solches Widget nicht zurückbringen:
  `dashboard/get` und `dashboard/save` entfernen es.

Legen Sie aufwendige Arbeit in das `data()` des Widgets. Alles, was
`widgets()` beim Aufbau der Liste berechnet (zum Beispiel
`->stat('TOTAL', Article::count())`), läuft für jeden Benutzer, der das
Dashboard öffnen kann, unabhängig von der eigenen Berechtigung des Widgets.

## Zeitraum

Die Dashboard-Werkzeugleiste hat einen Zeitraumumschalter (7 / 30 / 90 Tage /
gesamter Zeitraum). Der gewählte Zeitraum wird an
`/api/admin/dashboard/widgets?key={slug}&period={p}` gesendet, pro Benutzer
gespeichert und jedem Widget als `DashboardContext` übergeben:

```php
use Dskripchenko\LaravelAdmin\Widget\Widget;

class NewOrdersWidget extends Widget
{
    public function widgetType(): string { return 'stats'; }

    public function data(): array
    {
        $context = $this->dashboardContext();   // ->period, ->days(), ->from(), ->to()

        $count = $context->constrain(Order::query(), 'created_at')->count();

        return ['stats' => [['label' => 'Neue Bestellungen', 'value' => $count]]];
    }
}
```

`DashboardContext::constrain($query, $column)` fügt
`where($column, '>=', from)` hinzu und lässt die Abfrage bei `all` unverändert.
Ein Zeitraum ist `all` oder eine Anzahl von Tagen gefolgt von `d` (`7d`,
`14d`, `90d`).

Die eingebauten Listen-Widgets schalten das mit `withinPeriod()` ein:

```php
RecentListWidget::make()->model(Order::class)->column('number')->withinPeriod();
TableWidget::make()->model(Order::class)->withinPeriod('paid_at');
```

Ein Dashboard-Screen kann den Zeitraum auch selbst lesen, während er seine
Widgets aufbaut — `$this->period()`, `$this->periodDays()` oder
`$this->dashboardContext()` —, so wurden Dashboards geschrieben, bevor Widgets
einen Kontext hatten. Widgets, die den Zeitraum ignorieren, funktionieren
unverändert weiter.

Der Umschalter wird nur angezeigt, wenn etwas auf dem Dashboard vom Zeitraum
abhängt: ein Widget, das `dashboardContext()` in `data()` liest, eines, das mit
`->periodAware()` (oder `withinPeriod()`) markiert ist, oder ein Screen, der
seinen Zeitraum in `widgets()` liest. Um die Zeiträume explizit festzulegen:

```php
public function periods(): ?array
{
    return ['7d', '14d', '30d'];   // [] blendet den Umschalter aus, null = automatisch
}

public function defaultPeriod(): string
{
    return '14d';
}
```

## Eigene Widgets

```php
namespace App\Admin\Widgets;

use Dskripchenko\LaravelAdmin\Widget\Widget;

class WeatherWidget extends Widget
{
    public static function slug(): string { return 'weather'; }
    public function widgetType(): string { return 'weather'; }
    public function data(): array
    {
        return ['temp' => 23, 'icon' => 'sunny', 'city' => 'Moscow'];
    }
}
```

```php
public function widgets(): array
{
    return [WeatherWidget::make()->title('Wetter')->size(3)];
}
```

Frontend: Registrieren Sie eine Vue-Komponente für den Typ:

```ts
import { registerWidget } from '@dskripchenko/laravel-admin'
import WeatherWidget from './WeatherWidget.vue'
registerWidget('weather', WeatherWidget)
```

## Plugin-Widgets

Ein Paket hat keinen Ort, an dem es ein Widget deklarieren könnte — die
Dashboard-Klasse gehört dem Host. Daher registriert `$admin->widgets([...])`
Widget-Klassen, und jeder `DashboardScreen` des aktuellen Panels übernimmt sie,
nach seinen eigenen:

```php
public function boot(Admin $admin): void
{
    $admin->widgets([QueueDepthWidget::class]);
}
```

Sie erscheinen überall dort, wo das Dashboard ausgeliefert wird — im Manifest,
beim Aktualisieren über `dashboard/widgets` und in den gespeicherten Layouts —
unter denselben Berechtigungsregeln wie deklarierte Widgets, sodass ein
Plugin-Widget, das `permission()` setzt, nur den Benutzern angezeigt wird, die
diese Berechtigung besitzen. Das Widget wird über den Container erzeugt und
kann daher Abhängigkeiten in seinem Konstruktor anfordern. Duplikate werden
anhand des Slugs verworfen: Hat der Host dasselbe Widget selbst platziert, mit
eigenem Titel oder eigener Größe, wird keine zweite Kopie hinzugefügt. Ein
Widget, das nicht erzeugt werden kann, wird übersprungen, sodass eine defekte
Plugin-Bindung das Dashboard nicht lahmlegt.

## Indikatoren in der Kopfzeile

Ein benachbarter Mechanismus für denselben Fall — ein Paket hat etwas
mitzuteilen und keinen Ort dafür: `$admin->statusIndicators([...])` und das
Interface `Dskripchenko\LaravelAdmin\Status\StatusIndicator`. Das Panel
zeichnet den Indikator, das Plugin ist für seinen Zustand zuständig (`key()`
und `state()`) — siehe [system.status](../../ru/api/system.md) (auf Russisch).

## Siehe auch

- [Screens](screens.md) — `DashboardScreen` erweitert `Screen`
- [Berechtigungen](permissions.md)
- [Architektur](../architecture.md) — Form von Widget toArray
