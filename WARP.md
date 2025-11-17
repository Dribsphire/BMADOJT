# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

---

## 1. Project overview & key docs

**Project:** OJT Route – Student OJT Tracking & Management System  
**Stack:** PHP 8+ (vanilla, MVC-style) + MySQL 8, server-side rendered, no JS framework.

This is a monolithic, server-rendered PHP application for tracking student OJT hours, documents, and communication (students, instructors, admins). The system is optimized for mobile browsers and shared hosting (Hostinger/XAMPP).

Authoritative docs:

- **Architecture:** `docs/architecture.md` – canonical description of tech stack, layers (MVC, services, repositories, middleware), and key workflows (attendance, documents, messaging, email queue, dashboards).
- **Requirements:** `docs/prd.md` – detailed functional/non-functional requirements, epics, and UX goals.
- **Stories:** `docs/stories/` – user stories by epic/feature; treat these as the primary source of truth when implementing or modifying features.
- **BMad / AI workflows:**
  - `.bmad-core/` – BMad method core (agents, tasks, checklists, data). In particular: `enhanced-ide-development-workflow.md` defines how Dev/QA/SM agents collaborate and how QA gates work.
  - `.cursor/rules/bmad/*.mdc` – Cursor agent rules, especially `dev.mdc` (Full Stack Dev persona) and `architect.mdc` (Architect persona), which describe how other AI tools drive story-based development using `.bmad-core`.
  - `.claude/commands/BMad/` – Claude command wrappers for the same BMad workflows.

When in doubt about behavior or domain rules, prefer **docs/architecture.md**, **docs/prd.md**, and the **stories** over inferring from individual source files.

---

## 2. Local development & environment

Local dev is designed around **XAMPP** and Composer, with Apache serving `public/` as the web root.

### 2.1 Initial setup

From the repo root (`C:\xampp\htdocs\bmadOJT`):

- Install PHP dependencies:
  - `composer install`
- Start local services (via XAMPP UI):
  - Start **Apache** and **MySQL**.
- Database:
  - Create the database (e.g. `ojtroute_db`) and apply the SQL in `migrations/` via phpMyAdmin or CLI.
- Environment:
  - Copy `.env` (or `.env.example` if added later) and configure DB credentials, SMTP, and app settings to match your local environment.
- Verify app:
  - Open `http://localhost/bmadOJT/public/` in a browser (Apache `htdocs` is assumed to be `C:\xampp\htdocs`).

There is **no build step** (no Webpack/Vite); PHP, CSS, and JS are served directly.

### 2.2 Running the app without Apache (optional)

If you need a quick PHP built-in server instead of Apache:

- From repo root:
  - `php -S localhost:8000 -t public`
- Then browse to `http://localhost:8000/`.

Prefer Apache + MySQL for flows that depend on the full stack matching production.

---

## 3. Common commands

All commands assume you are in the repo root (`C:\xampp\htdocs\bmadOJT`).

### 3.1 Dependencies & autoloading

- Install / update dependencies:
  - `composer install`
- Regenerate Composer autoloader after adding classes under `src/`:
  - `composer dump-autoload`

### 3.2 Tests (PHPUnit)

PHPUnit is installed via Composer (`phpunit/phpunit` in `require-dev`), with tests under `tests/` (e.g. `tests/GeolocationServiceTest.php`).

- Run all tests:
  - `composer test`
  - or `vendor/bin/phpunit`
- Run tests with coverage (HTML report in `coverage/`):
  - `composer test-coverage`
- Run a single test file:
  - `vendor/bin/phpunit tests/GeolocationServiceTest.php`
- Run a specific test method (example):
  - `vendor/bin/phpunit --filter test_haversineDistance_calculatesDistanceCorrectly`

There are **no dedicated lint or static-analysis scripts** defined in `composer.json`. If you introduce tools like PHP-CS-Fixer or PHPStan, add Composer scripts and update this section.

### 3.3 Cron-style scripts (for reference)

Production uses cron-like scripts for background jobs:

- `cron/check_overdue_documents.php` – checks and flags overdue documents / sends related notifications.

These are normally invoked by the hosting environment (e.g. Hostinger cron). For local debugging you can run, from repo root:

- `php cron/check_overdue_documents.php`

---

## 4. Architecture & code layout

### 4.1 High-level architecture

The system is a **monolithic PHP MVC application** with:

- **Server-side rendering**: PHP templates and page scripts under `public/` render HTML directly; there is no separate frontend framework.
- **Database**: MySQL (InnoDB) with ~10 core tables (`users`, `student_profiles`, `sections`, `documents`, `student_documents`, `attendance_records`, `forgot_timeout_requests`, `messages`, `email_queue`, `activity_logs`).
- **Domain services** encapsulating business logic for:
  - Attendance & geolocation (GPS + Haversine-based geofencing).
  - Document workflows and dashboards.
  - Forgot time-out handling.
  - Messaging & notifications (including email queue).
  - Sections, users, maintenance, and admin analytics.
- **Middleware** enforcing:
  - Authentication / role-based access (student/instructor/admin).
  - Document compliance gates (7/7 required docs before attendance).
  - Attendance preconditions (workplace set, within geofence, etc.).

### 4.2 Core directories

Only the most important directories are listed here; use normal file browsing for details.

- `public/`
  - Apache document root and PHP entrypoints (e.g. `index.php`, `admin/`, `instructor/`, `student/`).
  - Contains UI pages and assets (`css/`, `js/`, `images/`, `assets/`).
  - Page scripts typically bootstrap config and delegate to `src/` controllers/services.

- `src/`
  - **Controllers/** – Request handlers for admin/instructor/student flows. They orchestrate services and shape data for templates.
  - **Middleware/** – Cross-cutting concerns:
    - `AuthMiddleware.php` – auth and role checks.
    - `DocumentMiddleware.php` – document-compliance gate.
    - `AttendanceMiddleware.php` – attendance-related preconditions (e.g., workplace set, docs approved).
  - **Services/** – Business logic per domain. Key examples:
    - `AttendanceService.php`, `AttendanceIntegrationService.php`, `SectionAttendanceService.php`, `AdminAttendanceService.php` – attendance core, integration, and reporting.
    - `GeolocationService.php` – GPS distance and geofencing logic (Haversine, accuracy checks).
    - `DocumentService.php`, `StudentDocumentService.php`, `InstructorDocumentService.php`, `DocumentDashboardService.php`, `DocumentIntegrationService.php` – document templates, submissions, approvals, dashboards.
    - `ForgotTimeoutService.php`, `ForgotTimeoutReviewService.php` – forgot time-out workflows.
    - `EmailService.php`, `NotificationService.php` – email queueing and notification fan-out.
    - `MessageService.php` – private/group messaging.
    - `UserService.php`, `SectionService.php`, `AdminAttendanceService.php`, `SystemMaintenanceService.php`, `OverdueService.php`, `WorkplaceEditRequestService.php`, `FileUploadService.php` – user/section management, maintenance, overdue logic, upload handling.
  - **Repositories/** – DB access layer for core entities, built on prepared statements and `config/database.php`.
  - **Models/** – PHP representations of domain entities (users, attendance, documents, etc.).
  - **Templates/** – PHP view fragments used by the public-facing pages.
  - **Utils/** – Shared utilities (e.g. time/date helpers, validation, response helpers).

- `config/`
  - `database.php` – DB connection config and helpers.
  - Other configuration files for environment-specific settings.

- `migrations/`
  - SQL / migration scripts for setting up and evolving the database schema.

- `cron/`
  - Long-running / scheduled tasks such as `check_overdue_documents.php` (overdue documents, reminders, etc.).

- `tests/`
  - PHPUnit tests (currently focused on core logic like `GeolocationServiceTest.php`).
  - Namespaces are configured via Composer (`"Tests\\": "tests/"`).

- `uploads/`
  - Runtime file storage for user uploads (documents, attendance photos, etc.).

- `docs/`
  - Product docs: `architecture.md`, `prd.md`, additional service and story docs under `docs/services/` and `docs/stories/`.

- `.bmad-core/`, `.cursor/`, `.claude/`, `.trae/`
  - BMad/agent ecosystem used by other AI tools to drive story-based development, QA, and architecture work.

### 4.3 Typical request flow

A typical authenticated request (e.g. student time-in) flows roughly as:

1. Browser hits a page under `public/` (e.g. a student attendance page).
2. The script bootstraps configuration, loads Composer autoloader, and invokes:
   - `AuthMiddleware` → ensures logged-in user with correct role.
   - `DocumentMiddleware` → ensures required documents are approved.
   - `AttendanceMiddleware` → ensures workplace is set, checks high-level attendance constraints.
3. Controllers in `src/Controllers/` call domain services in `src/Services/`.
4. Services coordinate with repositories in `src/Repositories/` and models in `src/Models/`.
5. Side effects such as email notifications go through `EmailService` / `NotificationService` (which may write to `email_queue` and rely on cron for dispatch).
6. A PHP template from `src/Templates/`/`public/` renders the HTML response.

Understanding and preserving this layering (public → middleware → controllers → services → repositories/models) is important when introducing new behavior.

---

## 5. Testing & quality

- PHPUnit tests live under `tests/` and are autoloaded via Composer (`autoload-dev`).
- Current tests focus on critical logic (e.g., `GeolocationServiceTest.php` for geofencing distance calculations).
- The broader test strategy (manual vs. automated, coverage expectations, QA gates) is described in:
  - `docs/architecture.md` – "Testing Strategy" section.
  - `docs/prd.md` – "Technical Assumptions" → Testing Requirements.
  - `.bmad-core/enhanced-ide-development-workflow.md` – how the QA/Test Architect agent is expected to participate (risk assessment, test design, gate decisions).

When adding or modifying functionality, align new tests with these documents and follow the structure/style of existing PHPUnit tests.

---

## 6. AI/agent workflows & how Warp should behave

This repo is wired for a BMad-style agent workflow (Dev, Architect, QA, SM, etc.). Warp should cooperate with, not override, those conventions.

Key points:

- **Story-centric work:**
  - Stories in `docs/stories/` are the primary unit of work. When asked to implement or change behavior, first locate the relevant story and use its acceptance criteria and notes as the main reference.
- **Respect BMad docs and tasks:**
  - `.bmad-core/` contains agents, checklists, and tasks used by other tools. When the user explicitly references BMad tasks (e.g. risk assessments, test design, gates), base your reasoning and outputs on those docs rather than inventing new processes.
- **Architecture & design requests:**
  - For high-level design questions, prefer `docs/architecture.md` and `docs/prd.md` and keep your recommendations consistent with the documented monolithic PHP + MySQL architecture unless the user explicitly requests a change.
- **Quality gates:**
  - The enhanced QA workflow (risk → design → trace → NFR → review → gate) in `.bmad-core/enhanced-ide-development-workflow.md` describes how quality is evaluated. When asked to help with testing/QA, align with that sequence and terminology.

If you introduce new commands, scripts, or non-trivial structural changes, update this `WARP.md` so future Warp instances stay aligned with the repo’s actual behavior.