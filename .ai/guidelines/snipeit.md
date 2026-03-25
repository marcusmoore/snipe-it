# Snipe-IT Application Guidelines

## Domain Overview

Snipe-IT is an IT asset management system. The core domain tracks Assets, Licenses, Accessories, Consumables, and Components through checkout/check-in workflows with full audit trails. Understanding this domain context is essential before modifying any models, controllers, or views.

## Base Model: SnipeModel

Most models extend `SnipeModel` (not Laravel's `Model` directly). `SnipeModel` provides shared attribute setters (empty string → `null`, currency parsing), API offset/limit scoping, EULA logic, and checkout button visibility. Check `SnipeModel` before adding shared behavior to individual models.

## Presenter Pattern

Each model has a corresponding Presenter class in `app/Presenters/` (e.g., `AssetPresenter`, `UserPresenter`) via the `Presentable` trait. All display-formatting logic — computed labels, formatted values, HTML badges — belongs in the Presenter, never directly on the model. Always check for an existing Presenter before adding display logic to a model.

## API Transformers, Not API Resources

The API uses custom Transformer classes in `app/Http/Transformers/` to format responses. Do not create or use Laravel Eloquent API Resources. When modifying API output, update the corresponding Transformer class.

## API Pagination: Offset/Limit

API controllers use offset/limit pagination via `setLimit()` and `setOffset()` scopes from `SnipeModel`. Do not use `->paginate()` in API controllers.

## Inline Model Validation

Models use the `watson/validating` package with a `$rules` property for inline validation. Form Request classes also exist for some operations but model-level rules are the primary validation layer. Check both before adding new validation rules.

## Multi-Company Scoping

The `CompanyableTrait` automatically scopes queries to the current user's company when full multi-company mode is enabled. Never bypass this by querying models directly without considering company scope.

## Audit Logging

All significant model changes are tracked via the polymorphic `Actionlog` model. Models use the `Loggable` trait. Do not add custom logging mechanisms — extend or use the existing `Actionlog` system.

## Checkout/Acceptance Workflow

Checkout flows may trigger a EULA acceptance requirement and digital signature capture via `CheckoutAcceptance`. This has legal/compliance implications. Do not simplify or shortcut checkout logic without fully understanding the acceptance chain.

## Custom Fields System

Assets support dynamic fields via `CustomField` and `CustomFieldset`. Fields can be encrypted. When working with asset attributes, consider whether the field may be a custom field rather than a model attribute. Encrypted field values require the `CustomField` decryption helpers.

## Frontend Stack: Bootstrap 3 + AdminLTE 2 + Laravel Mix

The frontend uses Bootstrap 3, AdminLTE 2, jQuery, and is bundled with Laravel Mix (not Vite). Do not use `@vite()`, Tailwind CSS, or modern JS framework patterns. Use `mix()` for asset URLs. If a frontend change is not reflected, the user may need to run `npm run build` or `npm run dev`.

## Searchable Trait

Models use a `Searchable` trait that provides full-text search across configured fields. When adding new filterable or searchable fields, update the model's `$searchableAttributes` (or equivalent) rather than writing raw query scopes from scratch.
