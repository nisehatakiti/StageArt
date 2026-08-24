# CLAUDE.md

This file provides guidance to Claude Code when working in this repository.

## Project

StageArt is a WordPress plugin, located under `plugin/`.

## Structure

- `plugin/stageart.php` — plugin entry point
- `plugin/src/Domain/` — domain layer
- `plugin/src/Application/` — application layer
- `plugin/src/Infrastructure/` — infrastructure layer
- `plugin/src/Presentation/` — presentation layer
- `plugin/assets/` — CSS / JS / images
- `plugin/templates/` — template files
- `plugin/languages/` — translation files
- `plugin/tests/` — PHPUnit tests (Domain/Application only; no WordPress dependency)
- `docs/` — project documentation

## Brand identity (important)

Before changing StageArt's visual presentation, read:

- `docs/03-BrandIdentity.md`

Canonical source assets are:

- `docs/assets/brand/stageart-icon.svg` — selected beige circular S+A icon
- `docs/assets/brand/stageart-logo.svg` — canonical icon + StageArt wordmark lockup

Do not redesign the primary logo independently per screen. Reuse these canonical assets and preserve the brand direction of **blackout black + warm stage illumination**.
