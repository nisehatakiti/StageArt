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
