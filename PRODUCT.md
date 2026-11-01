# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Operators of the admin panel (admin and editor roles):** they run the Mitai youth football tournament in Posadas, Misiones (Argentina). They load the match sheets (planillas), results, payments and debts.
- **Public visitors:** families, players and teams. They check fixtures, standings, scorers, finals and champions on the public site.

## Product Purpose

Torneo Mitai manages a youth football tournament end to end. Organizers run editions, categories, groups, matches, finals, team rosters (lista de buena fe), match sheets and planilla payments. The public site publishes the results.

## Operating Context

- The player match sheet is loaded **before the match, always on a desktop PC** with keyboard and mouse. The operator checks each child, marks attendance and enters the shirt number. Goals and red cards are loaded too.
- The operator identifies players by **DNI**. Searching by DNI is done **per team**.
- Every field autosaves. The operator must be told when a player failed to save.
- Planilla payments and debts per team are shown on the same match sheet.

## Capabilities and Constraints

- Laravel 11, Blade, Alpine.js and Tailwind 3, served from Docker.
- Domain vocabulary is Spanish: planilla, fecha, edición, categoría, cancha, lista de buena fe.
- Real data can contain repeated shirt numbers within a team.

## Brand Commitments

- Brand tokens `--brand-navy` (dominant), `--brand-teal` (secondary) and `--brand-gold` (sparing accent) in `resources/css/app.css`.
- Barlow is the typeface.
- See AGENTS.md section 10 (avoid AI-slop).

## Evidence on Hand

- Real match data for prototypes: `design/planilla-jugadores/datos.js` (match 2240). It contains minors' DNIs: do not publish it.

## Product Principles

1. Speed of the operator's main task beats decoration.
2. Never lose a save silently.
3. Use the tournament's own vocabulary.
