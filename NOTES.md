# Query Doctor — Project Development Log
## micro1 Agentic Workflows Hackathon

---

## 🎯 Project Idea
An AI agent that reads a Laravel project's code, detects Eloquent performance issues (N+1 queries, missing indexes), proposes real fixes, then verifies the improvement numerically by running tests before and after.

**Target user:** A Laravel developer/team inheriting code with undetected performance issues.

**Bottleneck:** Manually detecting N+1 issues is slow and relies on developer experience; no tool currently connects detection, fixing, and verification into a single automated loop.

---

## ✅ Completed So Far

### 1. Infrastructure Setup
- Created a new Laravel project
- Integrated Laravel AI SDK into the project
- Configured connection to both **Gemini API** and **DeepSeek API** — connection successfully verified with both providers

### 2. Technical Decision: Choosing the Primary Agent
- **Gemini** = primary engine for analysis and decision-making (wider context + more reliable tool calling)
- **DeepSeek** = fallback/cost-efficient option for running extra iterations without draining budget

### 3. Database Schema — Final for Phase 1
- Schema: `categories → products → orders → order_items → reviews`
- Migrations, Factories, and Seeder written and tested (`migrate:fresh --seed` runs successfully)
- **Fixed seed** (`mt_srand(1234)`) added at the top of the seeder for full reproducibility

**Deliberate data-quality scenario — "forgotten category assignment":**
- `products.category_id` is nullable. ~80% of products get a real category; ~20% are deliberately left `null`.
- This simulates a realistic developer mistake (forgetting to populate a foreign key during a bulk insert/import) rather than an unrealistic "never linked at all" scenario.
- Detectable by the agent at runtime: querying `product->category` will resolve to null for a meaningful chunk of records, which the agent should flag as a data-integrity finding distinct from a pure N+1 issue.

**Deliberate stress-test case — "enterprise order":**
- One dedicated order (`ENT-00001`) is seeded with **120 order_items spanning many different products**, separate from the normal 1-4 orders / 1-5 items per product used for the rest of the dataset.
- This is the "challenging case" required by the hackathon's evaluation section — it makes the N+1 cost dramatically visible (120 individual product lookups without eager loading vs. 1-2 with `with()`), and gives the baseline-vs-solution comparison a clear standout data point for the demo video.

**Still open (non-blocking, deferred):**
- [ ] The "missing index" scenario should target a non-FK column (e.g. unindexed `orders.date` for range filters, or `products.label` for search) — not yet built into the endpoints; will be addressed in Phase 2.
- [ ] (Stretch goal, out of MVP scope) A "relationship never defined" bug (missing FK column + missing relation method entirely) was considered but deferred — it requires static code-reading rather than runtime query-log analysis, and risks diluting focus before the deadline. Noted here as a future extension idea for the Hot Take section.

---

## 🔜 Upcoming Work (in order)

### Phase 1: Database (Models / Migrations / Seeders) — ✅ DONE
- [x] Design schema
- [x] Create Migrations
- [x] Create Models with relationships
- [x] Create Factories
- [x] Build Seeder with fixed seed, realistic volume, "forgotten category" bug, and the enterprise stress-test order
- [x] Re-verify with `migrate:fresh --seed` + tinker spot checks

### Phase 2: Build Endpoints (Test Cases) — NEXT
- [ ] Build 10 endpoints/queries representing realistic usage (e.g. `GET /api/orders`, `GET /api/orders/{enterprise_id}`, `GET /api/products?category=`)
- [ ] Include the enterprise order and a null-category product as two of the 10 cases specifically
- [ ] Enable `DB::listen()` to automatically log every query

### Phase 3: Measure the Baseline
- [ ] Run the 10 cases and record: query count, execution time (ms) per case
- [ ] Save these numbers as the baseline reference for later comparison

### Phase 4: Build the Agent (Tool/Function Calling)
- [ ] `read_file` tool — read a file's content from the project
- [ ] `list_files` tool — list files in a directory
- [ ] `run_command` tool — run whitelisted commands only (e.g. `php artisan test`)
- [ ] Connect the tools to Gemini API via function declarations
- [ ] Write a system prompt directing the agent to inspect Controllers, then run tests

### Phase 5: Logging
- [ ] Every tool call is automatically logged to a separate JSON file per run (`logs/run_<timestamp>.json`)
- [ ] Log: the decision, the tool called, inputs, output, timestamp

### Phase 6: Verification & Iteration Loop — ✅ DONE (fully automated closed loop)
- [x] Extended the agent with a `propose_fix` tool (structured method/old_code/new_code/reason) instead of free-text-only findings
- [x] Added a human-approval gate: each proposed fix is shown as a diff and requires explicit confirmation before being applied
- [x] Added `PatchApplierTool`: applies the confirmed patch, validates PHP syntax via `php -l` immediately after writing, and automatically rolls back to a timestamped backup if the patch is invalid
- [x] After all approved fixes are applied, the command automatically re-runs the full benchmark suite and prints a Before/After/Change table — no manual comparison step needed
- [x] Added a DeepSeek fallback path (OpenAI-compatible tool-calling) for when Gemini's free-tier daily quota (20 requests/day) is exhausted
- [x] Added `--reset` flag to fully automate restoring the naive baseline and clearing logs before each test run

**Official final measured result (fully agent-driven, human-approved, auto-verified):**

| Metric | Before | After | Change |
|---|---|---|---|
| Total queries (10 endpoints) | 1,095 | 22 | **98.2% reduction** |

**Improvement Changelog:**

| Stage | What was tried and why | Evidence | Decision |
|---|---|---|---|
| Baseline | Naive Eloquent queries, no eager loading, across all 10 endpoints | 1,095 total queries | Established starting point |
| Agent Audit | Agent read `BenchmarkController.php`, called `propose_fix` once per finding (8 calls), and ran `php artisan test` to confirm a healthy starting state | 8 structured fix proposals, each with exact old/new code and a one-line reason | Reviewed and approved each individually |
| Human-Approved Apply | Each approved fix applied via `PatchApplierTool`, with automatic `php -l` validation and rollback-on-failure | All 8 applied cleanly | Kept |
| Auto Re-Verification | Command automatically re-ran the full benchmark suite immediately after applying fixes | 22 total queries (98.2% reduction) | Kept — this is the final, agent-verified result |

**Engineering debugging notes (kept for Hot Take / Insights):**
- A `500` error across *all* 10 endpoints after applying fixes was traced not to a syntax error (`php -l` passed) but to a **class name mismatch**: a stray edit had renamed the class inside `BenchmarkController.php` without renaming the file, breaking PSR-4 autoloading for every route using that controller. Lesson: a uniform failure across unrelated endpoints points to a shared dependency (autoloading, a trait, middleware) rather than the specific logic just changed.
- Calling a sub-command via `Artisan::call()` from inside another Artisan command buffers its output silently; `$this->call()` must be used instead to forward output to the console in real time.

### Phase 7: Final Deliverables — 🔶 IN PROGRESS
- [x] README (user, problem, solution, Changelog, main failure and lesson learned) — complete with measured results
- [x] Reproduction Guide (exact commands from a clean environment) — complete
- [x] Agent Trajectories (`AGENT_TRAJECTORY.md`) — finalized: full audit matrix, credibility boundaries ("what the agent did not do"), retries/engineering iterations during development, consistency check across multiple runs, safety boundaries mapped to Ground Rules #4/#5
- [x] Video script (`VIDEO_SCRIPT.md`) — full shot-by-shot script with near-verbatim narration, ready to record
- [ ] Record the actual video (≤5 minutes)
- [ ] Publish to GitHub (public repo) — `.env` excluded, `.env.example` included, evidence log folders (`storage/logs/benchmark-before/`, `benchmark-after/`, `agent-runs/`) explicitly un-ignored and tracked
- [ ] Decision made: **GitHub only, no live hosting** — avoids exposing the Gemini API key and matches the brief's "reproduce from a clean environment" requirement rather than a live demo

---

## ⚠️ Mistakes to Avoid (Lessons Learned)
- Not capping the number of agent steps → risk of an infinite loop
- Letting the agent execute any command literally → risk of destructive commands (use a whitelist)
- Trusting the agent's text report without actual programmatic verification of the numbers
- Sending the entire project in one request → context size overflow
- Not fixing the data seed → non-reproducible results
- Postponing documentation until the end → forgetting decision details
- Assuming a foreign key column has no index by default — MySQL/InnoDB auto-indexes `constrained()` FK columns; plan "missing index" demos on non-FK columns instead
- Building seed data at too small a scale to demonstrate a real performance difference
- **[New]** Misreading "one order with 100+ items" as "many orders" — `factory(N)` sets the *count of records*, not an attribute value; double-check which axis (count vs. size) a scenario actually needs
- **[New]** Setting a bug scenario to 100% of records makes it look like a hardcoded default rather than a realistic intermittent mistake — partial application (e.g. ~20%) tells a more convincing story

---

*Last updated: August 29, 2026*