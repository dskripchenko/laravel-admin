<script setup lang="ts">
/**
 * The LAdmin brand mark — the "Rounded block": two rounded corners of a
 * selection frame and a teal block in the middle, a selected record.
 *
 * Drawn on a 24×24 grid with a 2.4 stroke and round caps and joins, like the
 * Lucide icons of the interface, so it scales cleanly: 28 in the sidebar, 40
 * on the auth pages. The tile follows the theme (zinc-900 in light, zinc-950
 * in dark); teal is used for the central block only. The mark is static.
 */
interface Props {
  /** The side of the square, in pixels. */
  size?: number
  /**
   * tile — the mark on its dark tile (default); glyph — without the tile, for
   * headers on a light or dark surface; mono — a single tone, the tile takes
   * currentColor.
   */
  variant?: 'tile' | 'glyph' | 'mono' | 'color'
  title?: string
  /** Kept for compatibility with the former animated mark; ignored. */
  animated?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  size: 28,
  variant: 'tile',
  title: 'LAdmin',
  animated: false,
})

// 'color' was the former name of the default variant.
const kind = props.variant === 'color' ? 'tile' : props.variant
</script>

<template>
  <svg
    class="ladmin-logo"
    :class="`ladmin-logo--${kind}`"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    role="img"
    :aria-label="title"
  >
    <rect v-if="kind !== 'glyph'" class="ladmin-logo__tile" width="24" height="24" rx="5.3" />
    <path
      class="ladmin-logo__corners"
      d="M5.5 11V5.5H11M18.5 13v5.5H13"
      fill="none"
      stroke-width="2.4"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
    <rect class="ladmin-logo__block" x="9.4" y="9.4" width="5.2" height="5.2" rx="1.6" />
  </svg>
</template>

<style>
.ladmin-logo {
  display: block;
  flex: none;
  --ladmin-logo-tile: #18181b;
  --ladmin-logo-fg: #ffffff;
  --ladmin-logo-accent: #2dd4bf;
}
.ladmin-logo__tile { fill: var(--ladmin-logo-tile); }
.ladmin-logo__corners { stroke: var(--ladmin-logo-fg); }
.ladmin-logo__block { fill: var(--ladmin-logo-accent); }

/* Without the tile the corners take the text colour of the surface. */
.ladmin-logo--glyph {
  --ladmin-logo-fg: #18181b;
  --ladmin-logo-accent: #14b8a6;
}
.ladmin-logo--mono {
  --ladmin-logo-tile: currentColor;
  --ladmin-logo-accent: #ffffff;
}

:root[data-theme='dark'] .ladmin-logo--tile {
  --ladmin-logo-tile: #09090b;
  --ladmin-logo-fg: #f4f4f5;
}
:root[data-theme='dark'] .ladmin-logo--mono {
  --ladmin-logo-fg: #09090b;
  --ladmin-logo-accent: #09090b;
}
:root[data-theme='dark'] .ladmin-logo--glyph {
  --ladmin-logo-fg: #f4f4f5;
  --ladmin-logo-accent: #2dd4bf;
}
</style>
