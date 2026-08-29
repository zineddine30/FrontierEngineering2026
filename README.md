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

See [Reproduction Guide](#reproduction-guide) below. *(TODO: complete once Phase 7 is finalized.)*

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

### Step 2 — Run the Agent

```bash
php artisan agent:run-query-doctor
```
Expected: 3 steps, ~5,200 tokens, under $0.01 at current Gemini 3.6 Flash pricing, completing in a few seconds. Output: a 10-point audit report matching `AGENT_TRAJECTORY.md`. Full raw log saved to `storage/logs/agent-runs/run_<timestamp>.json`.

### Step 3 — Reproduce the Fixed Solution

Copy the fixed controller back in (this is the version already in the repo by default):
```bash
copy app\Http\Controllers\BenchmarkController.fixed.php.bak app\Http\Controllers\BenchmarkController.php
rmdir /s /q storage\logs\benchmark & mkdir storage\logs\benchmark
php artisan benchmark:run-all
```
Visit `/benchmark-results` again — expect the **After** column from `README.md` (22 total queries — a 98% reduction).

### Cost & Runtime Summary

| Step | Approx. time | Approx. cost |
|---|---|---|
| `migrate:fresh --seed` | 10–20s | free (local) |
| `benchmark:run-all` (either version) | ~2s | free (local) |
| `agent:run-query-doctor` | ~5–10s | < $0.01 (Gemini API) |

---

## Main Failure Mode & Hot Take

*(TODO — filled in during Phase 6/7, based on at least one experiment that was tried and removed. Candidate: an earlier "missing relationship" bug design was considered and dropped in favor of a data-quality bug, since it needed a different detection mechanism than query-log analysis — see NOTES.md.)*

---

## Tech Stack

- Laravel + Laravel AI SDK
- Gemini API (primary agent engine)
- DeepSeek API (fallback / cost-efficient iterations)

---

## Development Log

See [`NOTES.md`](./NOTES.md) for the full phase-by-phase development log, including issues found and fixed along the way.