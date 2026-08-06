# LAdmin — brand assets

Финальный знак: **Terminal Block** — `>_` + мигающий teal-курсор на zinc-900.

## Файлы

| Файл | Когда использовать |
|---|---|
| `logo.svg` | Master-источник, 64×64. Path-based глиф, без зависимости от системных шрифтов. |
| `logo-on-light.svg` | Полный lockup (mark + wordmark "LAdmin"), 240×64. Для шапок документации, сайта. |
| `logo-mono.svg` | Одноцветная версия — fill наследуется от `currentColor`. Для печати, гравировки, превью в emails. |
| `favicon.svg` | Уже подключён как inline data-URI в `index.html`. |
| `logo-16.png` … `logo-512.png` | Готовые растровые экспорты: 16, 32, 64, 128, 180, 512 px. |
| `og-image.png` | 1200×630, для og:image / twitter:image. |
| `Logo.tsx` | React-компонент. Tree-shakeable, без рантайм-зависимостей. См. props ниже. |

## Палитра знака

```
bg     #18181b   /* zinc-900 */
glyph  #2dd4bf   /* teal-400 — намеренно ярче, чем accent-500, для попа на тёмном */
```

В dark theme фон опускается до `#09090b` (zinc-950). См. `app.css` → `:root[data-theme="dark"] .sb__brand-mark`.

## Безопасная зона и минимальный размер

- **Safe area** — 12% отступ внутри bounding-box со всех сторон. Ничего не помещается ближе.
- **Минимальный размер** — 16×16 px (favicon). Ниже — курсор и текст сливаются, лучше использовать чистый `>_` без блока.
- **Clear space** вокруг знака — равно высоте курсора (≈30% размера).

## Что **нельзя** делать

- ❌ Менять цвет курсора на не-teal.
- ❌ Растягивать соотношение сторон — знак всегда квадрат с радиусом 25%.
- ❌ Переводить курсор в outline — он залит и моргает.
- ❌ Накладывать тени, blur, gradient на сам блок.

## React-компонент

```tsx
import { Logo } from "@/components/Logo";

// Sidebar (default, animated)
<Logo />

// Login / 2FA / splash (large, animated)
<Logo size={40} />

// Email signature, exported PDF, anywhere motion-reduced
<Logo size={28} animated={false} />

// Inverse contexts (e.g. teal banner)
<Logo variant="mono" />
```

Props:

| Prop | Type | Default |
|---|---|---|
| `size` | `number` | `28` |
| `animated` | `boolean` | `true` |
| `variant` | `"color" \| "mono"` | `"color"` |
| `className` | `string` | — |
| `title` | `string` | `"LAdmin"` |

При `prefers-reduced-motion: reduce` рекомендуется передать `animated={false}` через хук:

```tsx
const reduceMotion = useReducedMotion();
<Logo animated={!reduceMotion} />
```

## CSS-only версия (для серверных шаблонов Blade)

Если React недоступен (страницы ошибок, maintenance), используй чистый CSS — он уже есть в `app.css`:

```html
<div class="sb__brand-mark"></div>     <!-- 28px, в sidebar -->
<div class="auth-card__logo"></div>    <!-- 40px, на auth-экранах -->
```

Текст `>_` и курсор рендерятся через `::before` / `::after` — никакой JS не нужен.

## Экспорт PNG

Готовые растры уже лежат в этой папке (`logo-16.png` … `logo-512.png`). Если нужны нестандартные размеры — рендеры из `logo.svg`:

```bash
# через rsvg-convert
rsvg-convert -w 1024 -h 1024 logo.svg -o logo-1024.png

# через sharp (Node)
npx sharp-cli logo.svg -o logo-1024.png --width 1024
```

Для `favicon.ico` сконвертируй `logo-16.png` + `logo-32.png` через [realfavicongenerator.net](https://realfavicongenerator.net/) или CLI:

```bash
convert logo-16.png logo-32.png logo-favicon.ico
```
