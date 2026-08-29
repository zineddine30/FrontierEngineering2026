# Query Doctor

**An agentic Laravel performance auditor** — an AI agent that reads a Laravel codebase, detects Eloquent performance issues (N+1 queries, missing indexes), proposes real fixes, and verifies the improvement numerically.

> 🚧 This README is a working draft. Sections marked `TODO` will be completed as later phases finish.

---

## Who has this problem?

A Laravel developer or small team who inherits (or maintains long-term) a codebase where query performance has never been systematically audited.

## What bottleneck makes it worth solving?

Detecting N+1 queries and missing indexes today relies on a developer manually reading Debugbar/Telescope output, spotting patterns, and remembering to check every endpoint after every change. It's slow, inconsistent between developers, and rarely re-checked once "fixed." No tool currently connects **detection → fix → verification** into a single automated, evidence-backed loop.

## Why this is generally useful (not just for this demo)

Query Doctor is not tied to this specific e-commerce schema. Point it at **any Laravel codebase** — a project you just inherited, a client's repo you're auditing, or your own team's app — and it runs the same detect → fix → verify loop: read the Controllers/Models, run a representative set of endpoints with query logging, flag N+1 patterns and missing-index opportunities, and report a measured before/after improvement. The demo dataset here (with its deliberate bugs) exists purely to give judges a controlled, reproducible way to see that loop in action.

## Does the agent solve it well?

Query Doctor:
1. Reads the target project's Controllers and Models to understand relationships and query patterns
2. Runs a representative set of endpoints/queries with query logging enabled
3. Detects N+1 patterns and missing-index opportunities from the query log
4. Proposes a concrete fix (eager loading or an index migration) and asks for human approval
5. Applies the approved fix, re-runs the same cases, and reports the measured before/after difference

**Result on this demo project:** the agent read the controller, proposed 8 structured fixes (via a `propose_fix` tool, each with exact code and reasoning), and — after a human reviewed and approved each one — applied them itself with automatic syntax validation and rollback-on-failure, then automatically re-ran the full benchmark to verify the result: **1,095 → 20 total queries across the 10 endpoints, a 98.2% reduction.** See the Improvement Changelog below.

## Can another person reproduce the result?

Yes — see the full [Reproduction Guide](#reproduction-guide) below: exact commands from a clean environment, expected data volumes, expected query counts, and approximate runtime/cost for every step.

---

## What existed before this project vs. what we built

| Existed before | Built for this hackathon |
|---|---|
| Laravel framework, Eloquent ORM, Laravel AI SDK | The demo e-commerce schema, seeded dataset (with deliberate bug scenarios), the 10 benchmark endpoints, the Query Doctor agent (tools + orchestration), the logging system, and the evaluation/changelog |

---

## Demo Dataset

A small e-commerce schema was built specifically to surface realistic performance issues:

```
categories (1) → (N) products (1) → (N) order_items (N) → (1) orders
products (1) → (N) reviews
```

- Seeded with a **fixed random seed** (`mt_srand(1234)`) for full reproducibility
- **Deliberate data-quality bug:** ~20% of products have a `null` `category_id`, simulating a developer forgetting to populate the field during a bulk import — a realistic, partial (not total) mistake for the agent to catch
- **Deliberate stress-test case:** one dedicated "enterprise" order (`ENT-00001`) with 120 line items spanning many different products, to make the cost of N+1 dramatically visible

---

## Improvement Changelog

| Stage | What was tried and why | Evidence | Decision / Learning |
|---|---|---|---|
| Baseline | Naive Eloquent queries across all 10 benchmark endpoints, no eager loading | 1,095 total queries logged via `DB::listen()` | Established the starting point |
| Agent Audit | Agent (`php artisan agent:run-query-doctor`) read `BenchmarkController.php` and called a structured `propose_fix` tool once per finding (8 calls), then ran `php artisan test` to confirm a healthy baseline | 8 fix proposals, each with exact old/new code and a one-line reason | Reviewed each individually and approved |
| Human-Approved Apply | Each approved fix applied automatically via a patch tool with built-in `php -l` syntax validation and rollback-on-failure | All 8 applied cleanly | Kept |
| Auto Re-Verification | The command automatically re-ran the full benchmark immediately after applying fixes — no manual comparison step | **20 total queries — a 98.2% reduction** | Kept. Verified against a clean, separately archived baseline (`storage/logs/benchmark-before/` vs `storage/logs/benchmark-after/`) |

**Official result:**

| Metric | Before | After | Change |
|---|---|---|---|
| Total queries (10 endpoints) | 1,095 | 20 | **98.2% reduction** |

---

## Reproduction Guide

### Requirements
- PHP 8.4+ (tested on 8.4.21, ZTS)
- Laravel 13.x (tested on 13.29.0)
- Composer
- MariaDB 12.x or MySQL 8+ (tested on MariaDB 12.3.2)
- A free Gemini API key ([ai.google.dev](https://ai.google.dev)) — DeepSeek key optional (configured as fallback, unused by default)

### Setup (from a clean clone)

```bash
composer install
copy .env.example .env          # Windows — use `cp` on macOS/Linux
php artisan key:generate
```

Edit `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=query_doctor
DB_USERNAME=your_mariadb_user
DB_PASSWORD=your_mariadb_password

GEMINI_API_KEY=your_key_here
GEMINI_MODEL=gemini-3.6-flash
```

Create the database (MariaDB), then:
```bash
php artisan migrate:fresh --seed
```
Expected: ~150 products, ~3,600 reviews, ~400–600 orders, one dedicated 120-item "enterprise" order (`ENT-00001`), ~20% of products with a deliberately null `category_id`. Seeded with a fixed seed (`mt_srand(1234)`) — output is identical on every run. Takes roughly 10–20 seconds.

### Step 1 — Reproduce the Baseline (before any fix)

The repository's `BenchmarkController.baseline.php` contains the original, unoptimized version of all 10 endpoints. To reproduce the baseline numbers exactly:

```bash
copy app\Http\Controllers\BenchmarkController.baseline.php app\Http\Controllers\BenchmarkController.php
rmdir /s /q storage\logs\benchmark & mkdir storage\logs\benchmark
php artisan serve
```
In a second terminal:
```bash
php artisan benchmark:run-all
```
Then visit `http://127.0.0.1:8000/benchmark-results` — expect the **Before** column from the table in `README.md` (1,095 total queries across the 10 endpoints).

### Step 2 — Run the Agent (Full Closed Loop)

```bash
php artisan agent:run-query-doctor --reset --provider=deepseek
```
`--reset` restores the naive baseline controller and clears logs automatically before running. `--provider` accepts `gemini` (default) or `deepseek` — use `deepseek` if Gemini's free-tier daily quota (20 requests/day) is exhausted.

Expected: the agent reads the controller, proposes 8 fixes via a structured `propose_fix` tool (each shown as a diff), asks for `yes`/`no` confirmation on each one, applies approved fixes with automatic syntax validation, then **automatically re-runs the full 10-endpoint benchmark** and prints a Before/After table. Full raw log saved to `storage/logs/agent-runs/run_<timestamp>.json` — see `AGENT_TRAJECTORY.md` for a worked example.

Expect the final table to show: **1,095 → 20 total queries (98.2% reduction)**.

### Step 3 — Manually Reproduce Both Sides Independently (optional, for full verification)

If you want to verify the before/after numbers outside the agent's own run:

```bash
copy app\Http\Controllers\BenchmarkController.baseline.php app\Http\Controllers\BenchmarkController.php /Y
del storage\logs\benchmark\*.json
php artisan benchmark:run-all
```
Visit `/benchmark-results` — expect **1,095** total queries (the Before column).

```bash
copy app\Http\Controllers\BenchmarkController.fixed.php.bak app\Http\Controllers\BenchmarkController.php /Y
del storage\logs\benchmark\*.json
php artisan benchmark:run-all
```
Visit `/benchmark-results` again — expect **20** total queries (the After column, 98.2% reduction).

### Cost & Runtime Summary

| Step | Approx. time | Approx. cost |
|---|---|---|
| `migrate:fresh --seed` | 10–20s | free (local) |
| `benchmark:run-all` (either version) | ~2s | free (local) |
| `agent:run-query-doctor` | ~5–10s | < $0.01 (Gemini API) |

---

## Main Failure Mode & Hot Take

**Main failure mode:** During development of the closed-loop apply step, one interactive session produced a `500` error across **all** 10 endpoints simultaneously after a batch of fixes — but `php -l` reported the file as perfectly valid PHP. The actual cause: a stray manual edit had renamed the class declared *inside* `BenchmarkController.php` without renaming the file itself, silently breaking PSR-4 autoloading for every route using that controller.

**Hot take:** Syntax validation (`php -l`) is necessary but not sufficient for safely letting an agent modify code — it catches malformed PHP, but it cannot catch a semantically broken file that is still syntactically valid, like a mismatched class name. The generalizable lesson: **when unrelated endpoints or tests fail uniformly and simultaneously, suspect a shared dependency (autoloading, a trait, a service provider, middleware) before re-reading the specific logic that was just changed** — a uniform failure pattern is itself diagnostic information, and it's a different debugging move than checking the diff line-by-line. If we built this again, we'd add a second, cheap runtime check after every agent-applied patch (e.g. hitting one endpoint and confirming a 200, not just `php -l`) as a belt-and-suspenders verification layer.

Full technical account: see [`AGENT_TRAJECTORY.md`](./AGENT_TRAJECTORY.md), §11.

---

## Tech Stack

- Laravel + Laravel AI SDK
- Gemini API (primary agent engine)
- DeepSeek API (fallback / cost-efficient iterations)

---

## Development Log

See [`NOTES.md`](./NOTES.md) for the full phase-by-phase development log, including issues found and fixed along the way.