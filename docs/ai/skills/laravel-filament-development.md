# Canonical Laravel and Filament development policy

## Scope and precedence

Apply this policy to every Laravel or Filament planning, implementation, modification, review, diagnosis, database-design, integration, or verification task in the Konelsis project.

This is the tool-neutral source of truth shared by Codex and Claude Code. Platform adapters may explain how to discover this policy but must not redefine it.

Use the following project-source order:

1. The user's latest explicit decision.
2. Approved decision records and the planning package under docs/planning.
3. This canonical engineering policy.
4. Existing application conventions.

The hard safety boundaries below are the user's permanent project restrictions. Do not reinterpret broad filesystem access, autonomy, or an implementation request as permission to cross them.

## Current implementation gate

DB-G8 (model freeze) was approved by the user on 2026-09-04 with implementation scope M01 and the decision-register defaults recorded in docs/planning/17.

- Implement only the scope the user has explicitly authorized. Authorized so far: M01 (platform security foundation; see docs/implementation/M01-platform-guvenlik-temeli.md).
- Each further module (M02 and later, SM01/SM02) requires its own explicit authorization from the user; a general "continue" is not authorization for a new module.
- Migration files may be authored for an authorized scope but are never executed by agents; the DBA/DevOps process applies them and confirms the applied batch.
- Decisions marked "Şirket verisi ✓" in docs/planning/17 need real company data from the user before the dependent module is implemented.
- Database execution restrictions remain in force permanently.

## Binding product decisions (user revision, 2026-09-05)

These are recorded as D-15R and D-42…D-59 in docs/planning/17 §3.6. They override any earlier design note that contradicts them.

- Primary keys are classic AUTO_INCREMENT BIGINT UNSIGNED. UUIDs are not used anywhere, and no identifier value is ever shown in the interface.
- A person is personnel. One `personnel` record is both the employee card and the login account; there is no separate users or employees table, and the word "user" is not used as a domain or interface term.
- There is no actor/principal abstraction and no system-account table. Records link directly to personnel (`*_personnel_id`); when the application itself acts, the personnel field is null and the interface shows "Sistem".
- The who-did-what log is permanently named Personel Hareketleri (`personnel_activities`) and is the single source of event and status history. There is no outbox and no separate status-change table; do not reintroduce either.
- Interface language is honoured: when Turkish is active, every visible label, action name, status, and field name is a Turkish phrase. Never show snake_case, a technical key, or a raw enum value to the user.
- There is no integration surface in the interface. External systems are connected in code, and the user is meant to experience one single system. Connection details come from `.env` or `config`, never from a managed screen or table. Personnel login passwords stay hashed.
- Notifications use Filament's own notification bell only. There is no notification entry in the navigation menu, and no separate notification screen. The `notifications` table stays for Filament database notifications.
- There is no two-factor authentication in this project. Do not add it back.
- Personel Hareketleri is not a menu item. It lives as a relation list under the personnel record.
- These fields are never shown in the interface: personnel number, language, time zone, department code. Their columns stay in the schema, and the department code is generated from the name.
- Every person reaches their own record from the user menu (top right) and can change their password there. Assignment fields stay read-only on that page.
- Give fields a meaningful icon where it helps reading, and skip it where it does not (department, job title). Phone, e-mail and WhatsApp must be clickable and open the call, mail and chat apps.
- Deferred scope is listed at the end of the plan in docs/planning/02 §11. Do not implement a deferred item without explicit new authorization.
- Business exceptions follow rule S-1 and business writes follow rule S-2, both defined under "Layered Laravel architecture". New code has no exemption from either.
- MySQL 8 rejects a `CHECK` constraint that refers to an `AUTO_INCREMENT` column. Enforce "a row cannot point at itself" rules in the service, not in the schema.

## Absolute command and database prohibitions

Never execute, recommend as a verification step, or indirectly trigger:

- Laravel Pint in any form, including vendor/bin/pint, php vendor/bin/pint, Composer scripts, IDE actions, CI wrappers, or package commands that invoke Pint.
- Laravel or PHP automated test suites, including php artisan test, PHPUnit, Pest, Composer test scripts, or any wrapper that can execute project tests. Project tests can refresh the working database.
- Any Artisan migration execution command, including php artisan migrate and every migrate:* command such as migrate:fresh, migrate:refresh, migrate:reset, migrate:rollback, migrate:status, or package aliases.
- Any database reset, wipe, refresh, flush, rebuild, drop, truncate, schema recreation, destructive seed, or equivalent action through raw SQL, framework code, scripts, packages, containers, GUIs, or administration tools.

Do not bypass these restrictions by:

- invoking a migration class directly;
- applying migration SQL manually;
- changing to another database connection;
- using a temporary database as a substitute;
- calling a Composer, shell, PowerShell, npm, container, CI, or editor wrapper with the same effect.
- disabling, editing around, removing, or bypassing project safety hooks, deny rules, or agent instruction files during feature work.

After DB-G8, creating or editing a migration file is allowed only when the user authorizes that implementation. Never execute it. Hand the unexecuted migration to the authorized DBA/DevOps process.

## Safe verification

Use only checks that cannot execute application tests or mutate a database.

Suitable checks include:

- targeted static inspection;
- PHP syntax checking on touched PHP files with php -l;
- configuration or package-file inspection;
- non-mutating framework inspection only after confirming the command cannot boot code that writes to the database;
- focused manual reasoning against the approved data model and acceptance criteria.

Do not run a broad Composer script without first inspecting its definition. At handoff, report only checks actually performed and explicitly state which normally expected Pint, test, or migration checks were not run.

## Filament-native interface boundary

Use supported Filament APIs as the first and default UI approach:

- Actions and action modals for user operations;
- Forms and schema components for data entry and layout;
- Tables for listings, filters, search, summaries, bulk actions, and row actions;
- Infolists, Widgets, Notifications, Resources, Pages, Clusters, panel configuration, authorization, validation, relationships, and lifecycle APIs where appropriate.

Do not create or modify JavaScript, Blade, HTML, or CSS for convenience, branding preference, layout shortcuts, or behavior that Filament supports natively.

If custom frontend code is genuinely required:

1. Stop before editing any frontend file.
2. Explain the exact limitation of the native Filament approach.
3. Identify the proposed files and the smallest custom scope.
4. Ask for case-specific user approval.
5. Continue only after explicit approval for that proposal.

A broad request to build a feature and full repository access are not frontend approval.

### Date input standard (user decision, 2026-09-16)

There is exactly one date input in this project. Every date-related field, on every page, modal, relation manager, wizard step, and repeater row, is:

- `Filament\Forms\Components\DatePicker` — never `DateTimePicker`; time-of-day is not entered through date fields. `TimePicker` is only for time-only fields.
- Native browser date input (`AppServiceProvider::configureFilamentDefaults()` applies `->native()`); never write `->native(false)`.
- Display format `d.m.Y` (the provider sets it as default; `->displayFormat('d.m.Y')` is the only value ever written explicitly).
- Short width: `FieldGrid::fields()` sizes a `DatePicker` to `FieldGrid::SHORT` (1/6 of a page section, 1/4 of an action modal); never override its column span.
- Reference implementation: the "Tarihler ve durum" section of `app/Filament/Resources/Projects/RelationManagers/SupplyItemsRelationManager.php`.

Any other date input type, format, or size is forbidden. `tools/safe-verify.php` fails on `DateTimePicker`, `->native(false)`, a non-`d.m.Y` display format, or `->seconds()` under `app/Filament`.
### Create-and-create-another button is removed (user decision, 2026-09-16)

The "Oluştur & yeni oluştur" (create & create another) button is disabled system-wide and must never come back in any resource, page, relation manager, action, or new development:

- `App\Providers\AppServiceProvider::configureFilamentDefaults()` calls `CreateRecord::disableCreateAnother()` and configures every `CreateAction` with `->createAnother(false)`. Keep both lines.
- Never write `->createAnother()`, `->createAnother(true)`, `->createAnotherAction()`, `$canCreateAnother = true`, or override `canCreateAnother()` / `getCreateAnotherFormAction()` anywhere under `app/`.
- Do not design flows that depend on creating another record from the same form; the user saves, then opens a new form.
- `tools/safe-verify.php` fails when any of these appear or when the provider lines are missing.

### Field width standard (user decision, 2026-09-16)

Do not default new form/infolist fields to a full-width row just because that is the easiest thing to write. `FieldGrid::fields()` already sizes every field by content (`FieldGrid::SHORT`/`NORMAL`/`WIDE`/`HALF`/`FULL`); only reach for `->columnSpanFull()` when the field genuinely needs the whole row (a textarea, an upload, a repeater, a long free-text field). A short field (a code, a date, a two-option select, an amount) belongs at `SHORT`/`NORMAL`, never `FULL`, so several of them share a row instead of stacking one per line.

- Before writing `->columnSpanFull()` on a `TextInput`/`Select`/`Toggle`/short field, check whether it could sit at `FieldGrid::SHORT`, `NORMAL`, `WIDE`, or `HALF` next to its neighbours instead. Only genuinely wide content (textarea, rich text, file upload, repeater, a field that must stand alone) gets `FULL`.
- Two `Section`s can sit side by side (`w-1/2` each) by giving both `->columnSpan(FieldGrid::HALF)` and putting the parent `Step`/`Schema` on `FieldGrid::COLUMNS` (a `->columns(1)` parent makes any child `columnSpan` a no-op — every child fills the single column regardless of the span you gave it).
- A `Section` set to `FieldGrid::HALF` must also get its own inner grid narrowed, or its fields collapse into unreadably thin columns. Tailwind breakpoints react to the viewport, not to how wide the section actually renders (there is no container query here): a half-width section whose own grid still claims the full `FieldGrid::COLUMNS` (`xl` 12) sizes a `SHORT` field at `2/12` of the *page*, not of the section it is actually in, so at `xl` it renders at roughly `2/12` of half the page — a few dozen pixels, with the label text wrapping one letter per line. Give that section `FieldGrid::HALF_COLUMNS` instead (`FieldGrid::group($fields, ['my_group' => [..., 'columnSpan' => FieldGrid::HALF, 'columns' => FieldGrid::HALF_COLUMNS]])`) so its own fields size themselves against the space it actually has.
- After changing a shared layout like this, verify what actually renders — a schema built in a scratch script only proves the `columnSpan`/`columns` values are correct, not that the page looks right. Open the page in the browser preview at a desktop width (`xl` breakpoint, e.g. 1440px) and confirm nothing is visually cramped or wrapped before calling the change done.
- Reference implementation: `app/Filament/Support/BusinessCaseWizard.php::caseSections()` (the "İş dosyası" step: "Müşteri ve başlık" + "Sınıflandırma" side by side, "Ticari bilgiler" + "Sorumlular" side by side, each narrowed with `FieldGrid::HALF_COLUMNS`).

## Plugin and dependency boundary

A maintained free Filament plugin may be recommended when it materially fits the requirement. Before recommending it, verify its Laravel/Filament compatibility, license, maintenance status, security posture, and tradeoffs from authoritative sources.

Never install, require, configure, publish, update, or modify a Filament plugin or any other Composer/npm dependency without explicit user approval for that exact package. A recommendation is not authorization. Prefer official Filament functionality when it covers the requirement.

Laravel Boost is not a prerequisite for this project. Do not install or run Laravel Boost unless the user explicitly requests and approves that exact dependency and operation, subject to all database and test prohibitions.

## Layered Laravel architecture

Keep Filament Resources and Pages thin and declarative.

### Filament UI layer

Resources, Pages, Widgets, schemas, tables, and Actions:

- collect input;
- enforce UI-level authorization and validation;
- call a service;
- present results, notifications, and navigation.

They do not contain business workflows, transaction orchestration, raw SQL, or explicit Eloquent/query-builder chains.

Filament already performs plain create, update and delete through its own resource, form and table APIs. Leave it to do that. Reach for a service only when the work is genuinely more than a form save, or when the same work must run outside the panel (a job, a command, a scheduled task, an external call).

### Service layer (rule S-2)

There is no Application layer and no one-class-per-operation. All business writes live in `app/Services` and every service extends `App\Services\AbstractService`.

`AbstractService` supplies five operations to every subclass: `index`, `show`, `create`, `update`, `delete`. An empty subclass already has all five:

```php
final class PersonnelService extends AbstractService {}

app(PersonnelService::class)->delete(12);
```

Rules:

- The model is resolved from the service name (`PersonnelService` → `Personnel`). Set the `$model` property only when the name does not map.
- Override one of the five methods when that operation needs an extra rule; the other four keep coming from the parent. Do not copy them.
- Put anything outside the five under its own method on the same service (`changeStatus`, `transfer`, `archive`). Do not create a separate class per operation.
- Every write runs inside one transaction and records a Personel Hareketleri entry. The action code is `{subject_type}.{operation}`; add the Turkish and English label to `lang/*/activity.php`.
- Optimistic locking is applied automatically when the incoming data carries `row_version`.
- Shape incoming data by overriding `prepare()` rather than duplicating `create()` and `update()`.
- Supporting collaborators (transaction runner, optimistic lock, activity recorder, actor context, feature flags, role resolver) live under `app/Services/<Area>` and are injected, not instantiated.

Keep Filament notifications and redirects in the UI layer.

### Exceptions (rule S-1)

Every business exception extends `App\Exceptions\AbstractException` and lives under `app/Exceptions`. General ones sit directly in that folder; domain-specific ones sit in a subfolder named after the domain, for example `app/Exceptions/Personnel`.

An exception class carries no message and no factory method. It carries a code when a code is useful, and nothing else:

```php
final class EmailAlreadyInUseException extends AbstractException
{
    protected $code = 4001;
}
```

Rules:

- The message comes from `lang/tr/exceptions.php` and `lang/en/exceptions.php`. Never write a message inside the class, and never concatenate one at the throw site.
- The translation key is derived from the class name: `App\Exceptions\StaleRecordException` → `exceptions.stale_record`; `App\Exceptions\Personnel\EmailAlreadyInUseException` → `exceptions.personnel.email_already_in_use`. Override `$translationKey` only for an exception to that rule.
- Throw with `::make()`, passing placeholder values: `throw EmailAlreadyInUseException::make(['email' => $email]);`
- Both language files must gain the key in the same change. A missing key falls back to `exceptions.generic`, so the user never sees a raw key.
- The interface layer calls `userMessage()`; it does not build its own text per exception type.

### Query layer

Put raw SQL, query-builder chains, explicit Eloquent queries, aggregates, reports, projections, and complex filters in dedicated query services or focused read services. Do not place them in a Filament Resource or Page.

### Domain/model layer

Models own relationships, casts, attributes, focused scopes, and local invariants. Do not use models as general workflow or service containers.

Use enums, DTOs, value objects, policies, events, jobs, repositories, and projections only when they clarify a real boundary. Do not create layers mechanically.

### Infrastructure layer

Isolate external APIs, object storage, mail, queues, PDF/document engines, social providers, accounting integrations, and other third-party systems behind focused adapters called by the service layer.

Use Policies and Filament authorization APIs. Hiding an action in the interface is not sufficient authorization.

## MySQL and data-model rules

The target database is MySQL 8.4 LTS with InnoDB and utf8mb4. SQLite is only a temporary local skeleton database and PostgreSQL is not a target.

- Treat docs/planning/03-veri-tabani-tasarim-plani.md and docs/planning/05-veri-tabani-er-diyagramlari.md as coupled design sources.
- Use AUTO_INCREMENT BIGINT UNSIGNED, UTC DATETIME(6), explicit foreign keys, versioned immutable records, and the approved generated-guard/composite-index patterns.
- Keep project, business-case, document, approval, stage, and operational lineage explicit.
- Keep historical/audit records append-only where defined.
- Do not invent polymorphic links in core domains. Controlled general references are limited to the approved horizontal infrastructure boundaries.
- Put transaction, locking, idempotency, and retry rules in the service that owns the write.
- Never infer that an ERD is permission to generate or execute DDL.

## AI separation boundary

AI implementation belongs to a separately developed project.

Do not add a model runtime, provider SDK, prompt workflow, RAG pipeline, embedding/vector store, fine-tuning, agent runtime, clone memory, training-data store, or AI decision logic inside this Laravel repository.

The Laravel application may expose a Filament Action and controlled API-request infrastructure for an explicitly requested external AI capability. In that case:

- call the remote service through an infrastructure adapter and a service;
- represent it as a service account (system account);
- enforce normal authorization, data scope, classification, consent, audit, idempotency, timeout, retry, and human approval;
- never grant direct database access;
- never allow it to bypass domain transitions, approval policies, or immutable histories;
- store only the minimum approved request/result metadata.

If the request cannot fit this boundary, stop and explain the boundary before implementation.

## Working discipline

Before editing:

1. Read the relevant planning documents and existing project conventions.
2. Confirm whether the current request is planning, diagnosis, review, or authorized implementation.
3. Map interface needs to native Filament components.
4. Identify service/query/domain/infrastructure boundaries; confirm the work is not something Filament already does on its own.
5. Identify migrations, custom frontend, plugins, dependencies, and external mutations before crossing any approval boundary.

During work:

- preserve existing data and unrelated changes;
- keep changes scoped;
- use services for writes and query services for explicit reads; extend AbstractService instead of writing a class per operation;
- record exact-version and audit requirements for critical actions;
- do not broaden the task into dependency installation, frontend customization, schema execution, deployment, or external publication.

At handoff:

- summarize the architecture and decisions;
- identify migration files created but not executed, if implementation was authorized;
- identify recommended but uninstalled plugins;
- report safe validation performed;
- state that Pint, Laravel/PHP tests, and migration/database commands were not run.

## Maintaining cross-agent parity

When this policy changes:

1. Update this canonical file.
2. Keep the two discovery adapters byte-identical:

       .agents/skills/laravel-filament-development/SKILL.md
       .claude/skills/laravel-filament-development/SKILL.md

3. Keep the absolute safety summary in AGENTS.md and preserve the CLAUDE.md import of that file.
4. Validate both skill folders with a frontmatter/skill validator that does not run project code.

Do not duplicate this full policy inside either adapter. The single canonical body prevents Codex and Claude from drifting.
