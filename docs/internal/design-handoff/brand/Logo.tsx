/**
 * LAdmin brand mark: "Rounded block".
 * Two rounded frame corners + a teal block in the center (a selected record).
 *
 *   <Logo />                 // 28px, sidebar
 *   <Logo size={40} />       // auth screens
 *   <Logo theme="dark" />    // dark UI (zinc-950 tile)
 *   <Logo variant="glyph" /> // no tile, for docs / headers on light
 *   <Logo variant="mono" />  // single color, tile = currentColor
 */
import * as React from "react";

type LogoProps = {
  size?: number;
  theme?: "light" | "dark";
  variant?: "tile" | "glyph" | "mono";
  title?: string;
  className?: string;
};

const C = {
  light: { bg: "#18181b", fg: "#ffffff", accent: "#2dd4bf" },
  dark: { bg: "#09090b", fg: "#f4f4f5", accent: "#2dd4bf" },
};

export function Logo({ size = 28, theme = "light", variant = "tile", title = "LAdmin", className }: LogoProps) {
  const c = C[theme];
  const fg = variant === "glyph" ? (theme === "dark" ? "#f4f4f5" : "#18181b") : variant === "mono" ? "#ffffff" : c.fg;
  const accent = variant === "mono" ? "#ffffff" : variant === "glyph" && theme === "light" ? "#14b8a6" : c.accent;
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" role="img" aria-label={title} className={className} style={{ flex: "none", display: "block" }}>
      {variant !== "glyph" && <rect width="24" height="24" rx="5.3" fill={variant === "mono" ? "currentColor" : c.bg} />}
      <path d="M5.5 11V5.5H11M18.5 13v5.5H13" fill="none" stroke={fg} strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" />
      <rect x="9.4" y="9.4" width="5.2" height="5.2" rx="1.6" fill={accent} />
    </svg>
  );
}

export default Logo;
