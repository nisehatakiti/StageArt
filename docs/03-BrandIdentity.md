# StageArt Brand Identity

## Status

This document is the current canonical visual identity specification for implementation work. Claude Code and other implementation agents should treat the assets and rules below as the source of truth unless a later Blueprint explicitly supersedes them.

## 1. Core concept

StageArt's mark combines **S** and **A**.

- **S** represents Stage, movement, the flow of performance, and the connection between people.
- **A** represents Art and is deliberately shaped like a practical **ladder / stage support** rather than a clean geometric capital A.
- The mark should retain a slightly physical, workmanlike, theatre-backstage character. Do not over-polish it into a generic luxury-brand monogram.

The visual direction is not "luxury gold". The intended feeling is:

> Blackout darkness + warm stage lighting.

In other words, the base is the black of a theatre during blackout, while the accent range moves from warm orange/amber through warm off-white, like stage lights and white-balanced illumination.

## 2. Canonical assets

### App icon (selected)

Use this asset as the canonical app icon:

- `docs/assets/brand/stageart-icon.svg`

**Confirmed selection:** the beige circular version is the primary StageArt app/profile icon.

Usage:

- mobile app icon source
- favicon / PWA icon source where an SVG source is accepted
- profile/avatar placeholder for StageArt-owned system surfaces
- compact navigation mark
- small-size branding

Do not substitute a black, purple, gold-gradient, or rectangular variant unless a later specification explicitly adds one.

### Logo lockup

Use this asset as the canonical StageArt logo:

- `docs/assets/brand/stageart-logo.svg`

The logo lockup consists of:

1. the selected beige circular S+A symbol
2. the `StageArt` wordmark

Use the full logo where there is enough horizontal space, especially:

- app splash / login header
- web site header
- public landing pages
- documents and larger branding areas

Use the icon alone in compact spaces.

## 3. Color direction

The palette is derived from theatre conditions rather than from generic product-brand colors.

### Base

- **Blackout Black:** `#0A0A0A`
  - primary dark background
  - the emotional base of the product

### Icon field

- **Stage Beige:** `#E8D7B8`
  - canonical circular app icon background
  - warm neutral rather than pure white

### Stage-light accent range

- **Warm Amber:** `#C6892B`
- **Warm Gold:** `#E2B15A`
- **Stage Warm White:** `#F0D08A`
- **Soft Light:** `#FFF6E6`

These values are a working palette. Claude should preserve the relationship: **darkness -> warm orange/amber -> warm off-white**. It must not introduce unrelated purple or cool-blue branding as a primary identity color.

## 4. UI implementation rules

### General

- Avoid a glossy startup-like UI.
- Avoid excessive gradients, glass effects, neon, and decorative floating shapes.
- The design should feel like a practical platform made for people who actually make and watch theatre.
- Visual hierarchy should be created mainly through typography, spacing, strong surfaces, and restrained warm lighting accents.

### Dark mode / primary presentation

The default brand expression may use Blackout Black as the main background. Warm accents should feel like illumination entering a dark theatre rather than paint covering the whole screen.

### Light surfaces

When light surfaces are necessary, use warm whites and beige neutrals rather than cold pure white where practical.

### Logo treatment

- Do not redraw the S+A relationship independently for each screen.
- Use the canonical SVG assets as the source.
- Keep the icon circular when used as the selected app/profile mark.
- Do not stretch, crop, skew, or recolor the symbol in ways that break recognition.

## 5. Implementation instruction for Claude

When applying the StageArt visual redesign:

1. First inspect `docs/03-BrandIdentity.md`.
2. Reuse `docs/assets/brand/stageart-icon.svg` and `docs/assets/brand/stageart-logo.svg` as source assets.
3. If a platform requires raster icons, generate platform-specific raster derivatives from the canonical SVG rather than designing a new mark.
4. Keep the S+A symbol's ladder-like A readable at small sizes.
5. Preserve the brand concept of blackout black and warm stage illumination.
6. Do not use the older purple/gold or generic luxury-style variants as the primary identity.

## 6. Relationship to the product Blueprint

This document defines visual identity. Product navigation, onboarding, home content, public pages, organization pages, production pages, follow/favorite behavior, advertising placement, and role-based functionality remain governed by their respective Blueprint documents.

When visual implementation and product Blueprint requirements interact, keep the product behavior intact and apply this brand identity to the presentation layer.
