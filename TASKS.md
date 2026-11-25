# Modular B2B & B2C CRM — Implementation Task Breakdown (`TASKS.md`)

**Architecture:** Modular Laravel 13 Monolith (`PHP 8.3+`, `Livewire 3`, `MySQL 8.0+` / `SQLite` test parity)  
**Frontend Stack:** Standard Laravel MVC + Blade + jQuery 3.7.1 AJAX + Composer-only Livewire 3 (Zero NPM dependencies, zero `@vite()` manifest build step)  
**Shared-Server Drivers:** `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `SESSION_DRIVER=database`

---

## Phase 1: Packaging, Core Foundation, Tenancy, RBAC & Hybrid JSON Custom Fields

- [x] **P1.1 Shared-Server Packaging & Zero-NPM UI Shell**
  - [x] Install `livewire/livewire:^3.8` via Composer only (`package.json` remains 100% untouched).
  - [x] Bundle static jQuery (`public/assets/js/jquery.min.js`), CRM stylesheet (`public/assets/css/crm.css`), and shared AJAX helpers (`public/assets/js/crm.js`).
  - [x] Build shared Blade layout (`resources/views/layouts/crm.blade.php`) loading static assets + `@livewireStyles` / `@livewireScripts` with zero `@vite(...)` calls.
  - [x] Register `routes/api.php`, middleware aliases (`tenant`, `tenant.role`, `api.token`), middleware priority ordering (`EnsureTenantContext` before `SubstituteBindings`), and JSON exception rendering in `bootstrap/app.php`.
  - [x] Configure `config/services.php` `meta` WhatsApp settings keys and `.env.example` `META_*` placeholders.
- [x] **P1.2 Database Migrations (MySQL 8.0+ & SQLite Compatible)**
  - [x] Extend `users` with `phone` and `is_superadmin`.
  - [x] Create `organizations` (`uuid`, `name`, `slug`, `registration_identifier`, `primary_contact_email`, `status`, `timezone`, `currency`, `settings`, `softDeletes`).
  - [x] Create `organization_user` RBAC pivot (`org_admin`, `sales_manager`, `sales_rep`, `read_only`).
  - [x] Create `organization_invitations` and `api_tokens` (SHA-256 `token_hash`).
  - [x] Create `custom_field_definitions` (`lead`, `contact`, `account`, `deal` across 7 data types).
- [x] **P1.3 Multi-Tenancy, Auth, Provisioning & RBAC**
  - [x] Implement `App\Models\User` (`#[Fillable]` excluding `is_superadmin`, `organizations()`, `roleIn()`, `hasRoleIn()`, `isSuperadmin()`) and `App\Models\Organization`.
  - [x] Implement `TenantContext` singleton, `OrganizationScope`, and `BelongsToOrganization` trait.
  - [x] Implement `EnsureTenantContext`, `AuthorizeTenantRole`, and `AuthenticateApiToken` middleware.
  - [x] Implement `TenantProvisioningService` (creates organization + `org_admin`, gracefully seeds default pipeline when `pipelines`/`pipeline_stages` exist, dispatches `OrganizationProvisionedEvent`).
  - [x] Implement Auth (`/login`, `/register`, `/logout`), Dashboard (`/dashboard`), Organization Switcher (MVC/AJAX + Livewire `OrganizationSwitcher`), Team Invitations, Member RBAC management, and Hashed API Token CRUD (`/settings/team`, `/ajax/team/*`, `/invitations/{token}/accept`).
  - [x] Register `OrganizationPolicy`, `CustomFieldDefinitionPolicy`, and Livewire persistent middleware in `AppServiceProvider`.
- [x] **P1.4 Hybrid JSON Custom Fields Engine**
  - [x] Implement `CustomFieldDefinition`, `CustomFieldRegistry` (plain-array caching compatible with `serializable_classes => false`), `CustomFieldValidationService` (4 entities, 7 data types, safe allowlisted `validation_rules`), and `HasCustomFields` trait.
  - [x] Implement `/settings/custom-fields`, `/ajax/custom-fields/*`, and Livewire `CustomFieldManager`.
- [x] **P1.5 Phase 1 Automated Tests**
  - [x] Unit tests for `TenantContext`, `OrganizationScope`, `BelongsToOrganization`, `CustomFieldValidationService`, and `HasCustomFields`.
  - [x] Feature tests for cross-tenant isolation, RBAC role matrix, custom field CRUD + runtime validation, and zero-Vite + explicit `database` cache/session/queue driver verification.

---

## Phase 2: Tracking Links, Click Logging, Dual-Mode B2B/B2C Leads & Attribution

- [x] **P2.1 Tracking Link Engine & Privacy-Safe Click Logging**
  - [x] Create `tracking_links` (`code`, `name`, `destination_url`, `channel`, UTM tags, `ad_spend`, `is_active`) and `link_clicks` (`click_token`, daily-HMAC `ip_hash`, `user_agent`, `referer`, `clicked_at`).
  - [x] Implement `LinkShortenerService` and public `GET /l/{code}` (`RedirectTrackingLinkController`, rate-limited 120/min/IP) recording clicks + setting `click_token` cookie without creating false lead or deal conversions.
  - [x] Build Tracking Links MVC + jQuery AJAX & Livewire views (`/tracking-links`, `/ajax/tracking-links`).
- [x] **P2.2 Dual-Mode Lead Inbox (B2B & B2C)**
  - [x] Create `leads` table (`type`: `B2B`/`B2C`, `status`, `first_name`, `last_name`, `email`, `phone`, `company_name`, `job_title`, `employee_count`, `location`, `source`, `assigned_user_id`, conversion pointers, `custom_fields` JSON).
  - [x] Implement `Lead`, `LeadIngestionData`, `LeadConversionData`, `LeadAssignmentService`, and `LeadPolicy`.
  - [x] Implement B2B vs B2C validation, `LeadInboxTable` / `LeadDetailModal`, and `/leads`, `/ajax/leads` endpoints.
- [x] **P2.3 Lead Conversion & Event-Driven Attribution Ledger**
  - [x] Create `touchpoints` ledger (`click`, `lead_captured`, `conversation_qualified`, `deal_won`).
  - [x] Create core `accounts`, `contacts`, `pipelines`, `pipeline_stages`, and `deals` tables/models required for conversion and Won-deal attribution.
  - [x] Implement `LeadConversionService` + `ConvertLeadWizard`: B2B -> `Account` + `Contact` + optional `Deal`; B2C -> standalone `Contact` (`account_id = NULL`) + optional `Deal`.
  - [x] Implement `AttributionCalculationService` (`First-Touch`, `Last-Touch`, `Linear` exact-sum revenue attribution strictly on `Won` deals).
- [x] **P2.4 Phase 2 Automated Tests**
  - [x] Verify `/l/{code}` logs `link_clicks` with zero false conversions.
  - [x] Verify B2B vs B2C conversion paths, custom field propagation, and First-Touch/Last-Touch/Linear attribution math.

---

## Phase 3: Accounts, Contacts, Multi-Pipeline Deals, Kanban & SLA Alerts

- [ ] **P3.1 Accounts & Contacts Directory**
  - [ ] Implement full CRUD, search/filtering, custom field validation, and B2B/B2C linkage views (`AccountList`, `ContactCard`, `/accounts`, `/contacts`, `/ajax/accounts/*`, `/ajax/contacts/*`) with RBAC policies.
- [ ] **P3.2 Multi-Pipeline & Ordered Stage Management**
  - [ ] Implement CRUD for `pipelines` and ordered `pipeline_stages` (`order_index`, `win_probability`, `category`: `open`/`won`/`lost`, `stale_days`) with deletion protection when active deals exist.
- [ ] **P3.3 Deal Management, Kanban Board & Stage Transitions**
  - [ ] Implement `DealStageTransitionService` (`stage_entered_at`, `closed_at`, mandatory `lost_reason` on `Lost` stages, `deal_won` touchpoint dispatch on `Won` stages).
  - [ ] Build `DealKanbanBoard` Livewire component and `/deals`, `/ajax/deals/*`, `PATCH /ajax/deals/{deal}/stage` endpoints.
- [ ] **P3.4 Activity Timeline & Stale Deal SLA Alerts**
  - [ ] Create polymorphic `activities` table/model and timeline endpoints (`POST /ajax/activities`) for Leads, Accounts, Contacts, and Deals.
  - [ ] Implement `StaleDealSlaService` and Artisan command flagging open deals exceeding stage `stale_days` (never flagging closed `Won`/`Lost` deals).
- [ ] **P3.5 Phase 3 Automated Tests**
  - [ ] Verify Account/Contact CRUD, pipeline stage ordering & deletion guard, Kanban/AJAX transitions, mandatory `lost_reason`, activity timeline, and stale-deal SLA detection.

---

## Phase 4: Optional Meta WhatsApp Cloud API & Coexistence

- [ ] **P4.1 Privacy-First Optional Module Separation**
  - [ ] House all WhatsApp code under `App\Modules\Communications\WhatsApp` so organizations without `whatsapp_accounts` operate 100% normally without errors.
- [ ] **P4.2 Meta Embedded Signup & Encrypted Credential Storage**
  - [ ] Create `whatsapp_accounts` (`Crypt`-encrypted `encrypted_access_token`), `whatsapp_conversations` (PHP `isWindowActive()` — no SQL `NOW()` generated column), `whatsapp_messages` (unique `meta_message_id` / `WAMID`), `whatsapp_templates`, `whatsapp_consents`.
  - [ ] Implement `MetaGraphApiClient` using Laravel `Http` facade (`Http::fake()` compatible), Embedded Signup OAuth exchange, and template sync (`/settings/whatsapp`, `/ajax/whatsapp/*`).
- [ ] **P4.3 Multi-Tenant Webhook Dispatcher & Coexistence Mode**
  - [ ] Implement `GET/POST /api/webhooks/whatsapp` (rate-limited 3,000/min, `X-Hub-Signature-256` HMAC validation returning `403` on invalid, immediate `200` + `ProcessWhatsAppWebhookJob` dispatch on `database` queue).
  - [ ] Implement `phone_number_id` tenant routing, `CoexistenceService` (`WAMID` deduplication/reconciliation), and `conversation_qualified` touchpoint on verified 2-way exchange.
- [ ] **P4.4 24-Hour Service Window Enforcer, Templates & Consent Opt-Out**
  - [ ] Implement `WindowEnforcementService` blocking free-form text outside 24h in UI and backend while allowing `APPROVED` templates via `SendWhatsAppTemplateJob`.
  - [ ] Implement `ConsentService` recording opt-in ledger and automatically opting out on inbound `STOP`, `UNSUBSCRIBE`, `CANCEL`.
  - [ ] Build `WhatsAppChatWidget`, `TemplateSelector`, and `/whatsapp`, `/ajax/whatsapp/*` endpoints.
- [ ] **P4.5 Phase 4 Automated Tests**
  - [ ] Integration (`Http::fake()`), webhook HMAC, queue dispatch, Coexistence `WAMID` reconciliation, 24h window & template enforcement, opt-out keyword blocking, and non-WhatsApp org regression tests.

---

## Phase 5: Analytics Dashboards, Notifications, GDPR Compliance & Audit Hardening

- [ ] **P5.1 Core Analytics Dashboards**
  - [ ] Implement `FunnelMetricsCalculator`, `ROASAggregator`, `ProductivityMetricsService`, and `WhatsAppHealthService`.
  - [ ] Build 4 dashboards (`FunnelVelocityDashboard`, `AttributionReportView` incl. CPA & ROAS, Rep Productivity, WhatsApp Channel Health) under `/analytics/*` and `/ajax/analytics/*`.
- [ ] **P5.2 Notification Engine (`database` + `mail`)**
  - [ ] Implement `LeadAssignedNotification`, `InboundWhatsAppNotification`, `DealStageChangedNotification`, `DealSlaWarningNotification` + UI notification bell (`/ajax/notifications/*`).
- [ ] **P5.3 GDPR / CCPA Data Subject Rights**
  - [ ] Implement `GdprExportService` (one-click JSON PII export for Contacts and Leads) and `GdprAnonymizationService` (cryptographic PII/IP-hash/message redaction while preserving `deals.amount` revenue totals).
- [ ] **P5.4 Immutable Audit Logging & Shared-Server Performance Hardening**
  - [ ] Create `audit_logs` table/model and `AuditLogService` logging critical RBAC, WhatsApp config, conversion, stage transition, custom field, and GDPR mutations (`/audit-logs`, `/ajax/audit-logs`).
  - [ ] Verify composite indexing, eager loading, and full `php artisan test` pass across all Unit, Feature, Integration, and Livewire suites using `database` drivers.
