/**
 * LAdmin brand mark — "Terminal Block"
 *
 * Usage:
 *   <Logo />                          // 28×28 default — sidebar
 *   <Logo size={40} animated />       // login screen, with blinking cursor
 *   <Logo size={28} animated={false}/>// favicon-style, static
 *   <Logo variant="mono" />           // single-color, inherits currentColor
 *
 * Renders as a <span> so it can sit inline with text (wordmark).
 */

import * as React from "react";

type LogoProps = {
  /** Edge length in px. Default 28. */
  size?: number;
  /** Blinking cursor. Default true. Disable for print, SSR, or motion-reduced. */
  animated?: boolean;
  /** "color" = zinc-900 bg + teal cursor (brand). "mono" = single tone via currentColor. */
  variant?: "color" | "mono";
  className?: string;
  title?: string;
};

const TEAL = "#2dd4bf";   // teal-400 — pops on dark
const INK = "#18181b";    // zinc-900

export function Logo({
  size = 28,
  animated = true,
  variant = "color",
  className,
  title = "LAdmin",
}: LogoProps) {
  // Scale all internal dimensions off the root size so it stays crisp at any px.
  const radius = Math.round(size * 0.25);
  const fontSize = Math.round(size * 0.42);
  const padX = Math.round(size * 0.18);
  const cursorW = Math.max(2, Math.round(size * 0.18));
  const cursorH = Math.round(size * 0.30);

  const bg = variant === "mono" ? "currentColor" : INK;
  const fg = variant === "mono" ? "var(--ladmin-logo-fg, #fff)" : TEAL;

  return (
    <span
      role="img"
      aria-label={title}
      className={className}
      style={{
        display: "inline-flex",
        alignItems: "center",
        justifyContent: "flex-start",
        width: size,
        height: size,
        padding: `0 ${padX}px`,
        borderRadius: radius,
        background: bg,
        color: fg,
        fontFamily:
          "ui-monospace, 'IBM Plex Mono', 'Fira Code', Menlo, monospace",
        fontWeight: 700,
        fontSize,
        letterSpacing: "-0.02em",
        lineHeight: 1,
        boxShadow: "inset 0 1px 0 rgba(255,255,255,0.06)",
        flex: "none",
      }}
    >
      <span aria-hidden="true">{">_"}</span>
      <span
        aria-hidden="true"
        style={{
          display: "inline-block",
          width: cursorW,
          height: cursorH,
          marginLeft: Math.max(1, Math.round(size * 0.04)),
          background: fg,
          animation: animated ? "ladmin-cursor 1.1s steps(1, end) infinite" : undefined,
        }}
      />
      {animated && (
        <style>{`@keyframes ladmin-cursor { 50% { opacity: 0; } }`}</style>
      )}
    </span>
  );
}

export default Logo;
