# LAdmin: brand assets

Знак **Rounded block**: два скруглённых угла рамки выделения и teal-блок в центре, то есть «выделенная запись». Знак построен на сетке 24×24, штрих 2.4, скруглённые концы и стыки (как у иконок Lucide в интерфейсе).

## Файлы

- `logo.svg`: основной знак на плашке zinc-900 (светлая тема)
- `logo-dark.svg`: плашка zinc-950 для тёмной темы
- `logo-glyph.svg`: знак без плашки, для документации и шапок на светлом фоне
- `logo-mono.svg`: одноцветный вариант, плашка `currentColor`
- `logo-on-light.svg` / `logo-on-dark.svg`: знак вместе со словом «LAdmin»
- `favicon.svg`, `logo-16.png` … `logo-512.png`: favicon, apple-touch-icon (180), PWA (512)
- `og-image.png`: 1200×630 для og:image
- `Logo.tsx`: React-компонент без зависимостей

## Геометрия

```
tile    rect 24×24, rx 5.3 (22%)
corners M5.5 11V5.5H11  M18.5 13v5.5H13   stroke 2.4, round caps/joins
block   rect x9.4 y9.4 5.2×5.2, rx 1.6
```

## Цвета

| | light | dark |
|---|---|---|
| tile | `#18181b` zinc-900 | `#09090b` zinc-950 |
| corners | `#ffffff` | `#f4f4f5` zinc-100 |
| block | `#2dd4bf` teal-400 | `#2dd4bf` teal-400 |
| glyph without tile | corners `#18181b`, block `#14b8a6` teal-500 | corners `#f4f4f5`, block `#2dd4bf` |

## Правила

- Teal используется только для центрального блока.
- Минимальный размер знака на плашке 16 px, без плашки 20 px.
- Вокруг знака оставлять свободное поле не меньше ¼ его размера.
- Не вращать знак, не менять пропорции, не добавлять тени и градиенты.
- Знак статичный, без анимации.

## Использование

```tsx
import { Logo } from "@/components/Logo";
<Logo />                       // sidebar, 28px
<Logo size={40} />             // login / 2FA
<Logo theme="dark" />
<Logo variant="glyph" size={20} />
```

CSS без React (Blade, страницы ошибок): классы `.sb__brand-mark` (28px) и `.auth-card__logo` (40px) в `app.css` подставляют знак через SVG data-URI. Тёмный вариант включается по `:root[data-theme="dark"]`.

## favicon.ico

```bash
convert logo-16.png logo-32.png favicon.ico
```
