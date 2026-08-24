# StageArt Blueprint

# 03 - Startup Experience

## Status

Confirmed specification.

This document supersedes the earlier curtain-opening / ladder-climbing / S-drawing startup animation concept where they conflict.

---

## 1. Purpose

The StageArt startup should feel like a very short opening cue in a theatre.

It is not a decorative logo animation. The intended experience is:

> Blackout → warm stage light enters → the StageArt symbol becomes visible → the StageArt name appears → the app begins.

The direction must preserve StageArt's practical, slightly physical and backstage-oriented character. Avoid magical sparkles, luxury-brand reveals, curtain graphics, purple/gold branding, or long cinematic animation.

---

## 2. Confirmed sequence

```text
[Blackout]
    ↓
[Warm overhead stage light slowly enters]
    ↓
[Beige circular S+A symbol emerges]
    ↓
[StageArt wordmark fades in]
    ↓
[Transition directly to Login / Home]
```

The S+A symbol is the canonical beige circular icon defined in `03-BrandIdentity.md`.

The wordmark is the canonical StageArt logo lockup. Do not redraw the S+A relationship independently for the startup screen.

---

## 3. Timing

Target total duration: **0.8–1.2 seconds**.

Recommended timing:

- **0.0–0.15 s**: complete Blackout Black.
- **0.15–0.45 s**: warm light from above becomes visible and the beige circular icon emerges.
- **0.45–0.80 s**: icon settles; StageArt wordmark fades in slightly after the icon.
- **0.80–1.10 s**: brief hold, then transition to the resolved destination.

The animation must not deliberately delay authentication, session restoration, or data loading. Startup work may continue underneath the animation.

---

## 4. Visual direction

### Background

Use **Blackout Black `#0A0A0A`** as the base.

The opening state should feel like an actual darkened stage rather than a generic black app background.

### Light

The light should suggest a soft overhead stage light / "サス明かり".

Use restrained warm illumination in the direction:

```text
warm orange / amber
    ↓
warm gold
    ↓
warm off-white
```

The light is not a visible sci-fi beam. It should mainly be perceived as darkness gently revealing the symbol.

### Symbol

Use the confirmed beige circular S+A icon.

The icon should fade in and become visible; it should not be reconstructed through a star trail or an animated line drawing.

### Wordmark

The `StageArt` wordmark appears after the icon, not simultaneously from the first frame.

The staggered order should feel like:

> 明かりが入る → 舞台が見える → StageArtが現れる

---

## 5. Native splash and in-app animation

Separate the startup into two layers.

### Native splash

Shown before the React Native application is ready.

- Blackout Black background.
- No blue Expo default presentation.
- No unrelated legacy branding.
- Keep this layer visually quiet and short.

The canonical StageArt icon should be used when a platform-specific raster splash asset is available. Raster assets must be generated from the canonical SVG rather than newly designed.

### In-app startup animation

Shown after the JavaScript application is ready.

- Blackout base.
- Warm overhead illumination.
- Canonical beige circular S+A icon.
- StageArt wordmark.
- Immediate transition to Login or Home.

Do not show a second, unrelated animation after the native splash. Both layers must read as one continuous opening.

---

## 6. Accessibility

Respect the operating system's reduced-motion setting.

When reduced motion is enabled, skip the animated reveal and proceed directly to the application destination without adding artificial delay.

---

## 7. Implementation rules for Claude

Before changing startup behavior:

1. Read `docs/03-BrandIdentity.md`.
2. Read this document.
3. Remove the previous red curtain-opening presentation from the active implementation.
4. Do not use the earlier star-trail S-drawing concept.
5. Do not introduce purple, cool blue, or generic Expo splash branding.
6. Keep the total visible in-app startup animation within approximately 0.8–1.2 seconds.
7. Keep authentication/session resolution running underneath the visual animation.
8. Reuse canonical icon and logo assets; generate raster derivatives only from those assets when required by native platforms.
