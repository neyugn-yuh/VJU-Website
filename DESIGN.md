---
version: 1.2.0
name: VJU-Linear-Trilingual-Dashboard-Design
description: "A Linear-inspired design system for VJU CMS Dashboard, balancing university academic authority with Linear's acclaimed software-craft precision. Featuring hairline micro-borders, near-black dark canvas (#0b0c0e), pure crisp light canvas (#f8fafc), signature Indigo/Navy accents (#5e6ad2 / #4f46e5), high information density, dual-theme parity, and comprehensive trilingual typography (English, Vietnamese, Japanese)."

themes:
  dark:
    canvas: "#0b0c0e"
    canvas-subtle: "#0f1013"
    surface-card: "#131518"
    surface-elevated: "#181a1f"
    surface-hover: "#1f2227"
    hairline: "rgba(255, 255, 255, 0.08)"
    hairline-hover: "rgba(255, 255, 255, 0.16)"
    hairline-focus: "#5e6ad2"
    ink-primary: "#f7f8f8"
    ink-secondary: "#d0d6e0"
    ink-muted: "#8a8f98"
    ink-faint: "#525866"
    accent-primary: "#5e6ad2"
    accent-hover: "#717de8"
    accent-glow: "rgba(94, 106, 210, 0.25)"
    semantic-success: "#10b981"
    semantic-success-bg: "rgba(16, 185, 129, 0.12)"
    semantic-warning: "#f59e0b"
    semantic-warning-bg: "rgba(245, 158, 11, 0.12)"
    semantic-danger: "#f43f5e"
    semantic-danger-bg: "rgba(244, 63, 94, 0.12)"
    semantic-info: "#0ea5e9"
    semantic-info-bg: "rgba(14, 165, 233, 0.12)"

  light:
    canvas: "#f8fafc"
    canvas-subtle: "#f1f5f9"
    surface-card: "#ffffff"
    surface-elevated: "#ffffff"
    surface-hover: "#f8fafc"
    hairline: "rgba(15, 23, 42, 0.08)"
    hairline-hover: "rgba(15, 23, 42, 0.16)"
    hairline-focus: "#4f46e5"
    ink-primary: "#0f172a"
    ink-secondary: "#334155"
    ink-muted: "#64748b"
    ink-faint: "#94a3b8"
    accent-primary: "#4f46e5"
    accent-hover: "#4338ca"
    accent-glow: "rgba(79, 70, 229, 0.15)"
    semantic-success: "#059669"
    semantic-success-bg: "rgba(5, 150, 105, 0.08)"
    semantic-warning: "#d97706"
    semantic-warning-bg: "rgba(217, 119, 6, 0.08)"
    semantic-danger: "#e11d48"
    semantic-danger-bg: "rgba(225, 29, 72, 0.08)"
    semantic-info: "#0284c7"
    semantic-info-bg: "rgba(2, 132, 199, 0.08)"

typography:
  font-family-sans: "'Inter', 'Noto Sans JP', 'Hiragino Sans', 'BIZ UDPGothic', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
  font-family-mono: "'JetBrains Mono', 'Fira Code', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace"

  rules_by_locale:
    en:
      line-height: "1.45"
      letter-spacing: "-0.015em"
      word-break: "normal"
    vi:
      line-height: "1.55"
      letter-spacing: "-0.005em"
      word-break: "normal"
    ja:
      line-height: "1.68"
      letter-spacing: "0.018em"
      word-break: "break-all"

  headline-xl:
    fontSize: "26px"
    fontWeight: "600"
    letterSpacing: "-0.025em"
    lineHeight: "1.25"
  headline-lg:
    fontSize: "20px"
    fontWeight: "600"
    letterSpacing: "-0.02em"
    lineHeight: "1.3"
  stat-value:
    fontSize: "28px"
    fontWeight: "700"
    letterSpacing: "-0.03em"
    lineHeight: "1.1"
  body-default:
    fontSize: "14px"
    fontWeight: "400"
    lineHeight: "1.5"
    letterSpacing: "-0.005em"
  caption:
    fontSize: "12px"
    fontWeight: "500"
    lineHeight: "1.4"
    letterSpacing: "0.01em"

radii:
  sm: "6px"
  md: "8px"
  lg: "12px"
  xl: "16px"
  pill: "9999px"

elevation:
  dark-card: "0 1px 2px rgba(0, 0, 0, 0.6), inset 0 1px 0 rgba(255, 255, 255, 0.05)"
  dark-hover: "0 8px 24px rgba(0, 0, 0, 0.7), inset 0 1px 0 rgba(255, 255, 255, 0.08)"
  light-card: "0 1px 3px rgba(15, 23, 42, 0.05), 0 1px 2px rgba(15, 23, 42, 0.03)"
  light-hover: "0 6px 18px rgba(15, 23, 42, 0.08), 0 2px 4px rgba(15, 23, 42, 0.04)"

components:
  hero-banner:
    purpose: "Linear-style ambient welcome card with real-time greetings, status indicators, and keyboard-friendly quick actions."
  stat-card:
    purpose: "Micro-metric card featuring value, label, comparison badge, custom icon container, and sparkline or visual trend indicator."
  command-pill:
    purpose: "Compact, interactive button with subtle border, icon, and hover highlight for rapid administrative tasks."
  language-switcher:
    purpose: "Linear-styled segmented pill control situated in the top navigation bar enabling instant switching between VI, EN, and JA."
  chart-panel:
    purpose: "Clean, distraction-free data visualization card using Linear-inspired palettes and subtle gridlines."
  activity-feed:
    purpose: "Dense audit trail showcasing actor avatars, status pills, and relative timestamps with zero visual clutter."
---

# VJU CMS Linear Design System (DESIGN.md)

This document formalizes the visual language and user experience for the VJU (Vietnam Japan University) Admin CMS Dashboard, inspired by the Linear design guidelines extracted in `awesome-design-md`.

## Core Philosophy

1. **Craft & Quiet Luxury**: Avoid heavy drop-shadows or oversaturated borders. Emphasize razor-sharp 1px hairline dividers, precise typography with negative tracking for Latin, and intentional spacing.
2. **Dual-Theme Parity**: Light and dark themes are designed as first-class citizens. The dark theme leverages OLED near-black (`#0b0c0e`) with translucent borders; the light theme uses crisp off-white (`#f8fafc`) and slate neutrals.
3. **Trilingual Typography Harmony**:
   - **English (`en`)**: Clean, compact tracking (`-0.015em`) with crisp numbers and concise labels.
   - **Vietnamese (`vi`)**: Slightly elevated line-height (`1.55`) to prevent diacritic clipping on complex tones (`ề`, `ở`, `ứ`, `ệ`).
   - **Japanese (`ja`)**: Ample line-height (`1.68`) with subtle positive tracking (`+0.018em`) and CJK fallback typography (`Noto Sans JP`, `Hiragino Sans`) to ensure legibility of intricate kanji strokes.
4. **High Information Density**: Present meaningful data (drafts, pending reviews, 30-day views, content velocity) in a compact, scannable layout.
5. **Action-Oriented Dashboard**: The hero banner provides immediate 1-click workflows for creating articles, building pages, uploading media, and previewing the public university website.
