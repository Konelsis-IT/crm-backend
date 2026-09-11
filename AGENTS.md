# Konelsis AI agent instructions

These are standing project rules for every coding agent session.

## Required project policy

Before any Laravel, Filament, database, architecture, review, diagnosis, or implementation task, read this file completely:

    docs/ai/skills/laravel-filament-development.md

Apply that canonical policy together with the approved planning package under docs/planning.

Gate status: the user approved DB-G8 on 2026-09-04 with implementation scope M01 and the decision-register defaults (docs/planning/17). Implementation is allowed only for the scope the user has explicitly authorized (currently M01); every further module needs its own authorization. Migration files may be authored for an authorized scope but are never executed by agents; the DBA/DevOps process applies them (docs/implementation/M01-platform-guvenlik-temeli.md).

## Absolute safety boundaries

- Never run or suggest Laravel Pint.
- Never run or suggest Laravel/PHP automated tests, including Artisan test, PHPUnit, Pest, Composer test scripts, or wrappers.
- Never run any Artisan migrate or migrate:* command, including migrate:status.
- Never reset, refresh, wipe, flush, rebuild, drop, or truncate a database by any route.
- Never bypass these rules through Composer scripts, package commands, raw SQL, direct migration invocation, GUI tools, alternate connections, or wrappers.
- Never disable, weaken, remove, or bypass project safety hooks, deny rules, AGENTS.md, CLAUDE.md, or the canonical skill policy during feature work.
- Never install Laravel Boost, a Filament plugin, Composer package, npm package, or other project dependency without the user's explicit approval for that exact package.
- Never create or modify JavaScript, Blade, HTML, or CSS without case-specific user approval after explaining why Filament-native components are insufficient.

## Cross-agent skill parity

The canonical policy lives only at docs/ai/skills/laravel-filament-development.md. Discovery adapters for Codex-compatible Agent Skills and Claude Code live at:

    .agents/skills/laravel-filament-development/SKILL.md
    .claude/skills/laravel-filament-development/SKILL.md

Keep both adapters byte-identical. When this project skill changes, update the canonical policy and verify both adapters in the same change. Do not copy tool-specific built-in skills into the repository; port only project-owned rules and workflows that are meaningful in both agents.

Claude Code also has a blocking PreToolUse guard under .claude/hooks and matching deny rules in .claude/settings.json. Treat them as part of these standing safety boundaries.
