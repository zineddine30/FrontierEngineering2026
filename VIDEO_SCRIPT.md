# Query Doctor — Full Video Script (5:00)

Use this as a shot-by-shot guide. Narration lines are near-verbatim — adapt to your own voice, don't read robotically.

---

## SEGMENT 1 — Hook & Problem (0:00–0:35)

**Shot:** Face-to-camera or voiceover over a title card ("Query Doctor").
**On-screen text:** "Who has this problem?"

**Narration:**
"You just inherited a Laravel project. It works. It's also never had its query performance audited — and nobody has time to check every endpoint by hand for N+1 queries. That's the problem Query Doctor solves: an AI agent that reads your codebase, finds the N+1 issues, proposes the exact fix, and proves the improvement with real numbers — not a guess."

---

## SEGMENT 2 — Show the pain live (0:35–1:10)

**Shot:** Screen recording — Postman or browser hitting the UNFIXED `orders-enterprise` endpoint (120-item order).
**On-screen text:** "One order. 120 products. No eager loading."

**Narration:**
"Here's a real order with 120 line items — think of it as one enterprise customer's bulk order. Watch the response time on the naive version of this endpoint."
*(let the delay actually show on screen — don't cut it)*

---

## SEGMENT 3 — The full baseline (1:10–1:35)

**Shot:** `/benchmark-results` page or the archived baseline screenshot, zoomed on `orders: 729` and the `1,095` total.
**On-screen text:** "Baseline: 1,095 queries across 10 endpoints"

**Narration:**
"We built 10 benchmark endpoints covering the app's core relationships, ran them all, and logged every single SQL query behind the scenes. The naive baseline: 1,095 queries total."

---

## SEGMENT 4 — Live agent run: diagnosis + structured proposals (1:35–2:20)

**Shot:** Terminal, full screen. Run `php artisan agent:run-query-doctor --reset` live and let it run through the diagnosis (~15s).
**On-screen text (appears as it happens):** "Step 1: reads the real Controller" → "Step 2: proposes 8 structured fixes" → "Step 3: runs the test suite"

**Narration (while it's running):**
"This is the agent, live, no editing. It calls a `read_file` tool to pull the actual Controller code. Then — this is the key part — it doesn't just describe the problems in a paragraph. It calls a `propose_fix` tool once per finding: the exact method, the exact broken code, the exact fixed code, and why."

---

## SEGMENT 5 — Human approval, one fix at a time (2:20–2:55)

**Shot:** Terminal, scrolled to the first `Apply this fix to BenchmarkController.php? (yes/no)` prompt. Show the red/green diff clearly.
**On-screen text:** "The agent never writes code itself"

**Narration:**
"Here's the boundary that matters: the agent can propose, but it can't apply anything on its own. Each fix is shown to me individually — the reasoning, the exact diff — and I have to type yes for each one. Watch this one: for counting related records, it didn't just add `with()` — it used `withCount()` instead, avoiding loading full models into memory just to call `.count()`. That's a real engineering judgment call, not a templated fix."
*(type `yes` on camera for 2-3 of the 8 prompts, don't skip past this)*

---

## SEGMENT 6 — Automatic re-verification (2:55–3:35)

**Shot:** Terminal continues automatically after the last approval — no cuts.
**On-screen text:** "No manual re-testing — the agent does this itself"

**Narration:**
"Once I approve a fix, it's applied instantly, checked for valid PHP syntax automatically, and rolled back if anything's wrong. After all 8 are applied, watch — I don't run anything else. The command re-runs the full benchmark itself and prints the result."

**Shot:** The final table appears: `1095 | 20 | 98.2% reduction`.
**On-screen text:** "1,095 → 20 queries · 98.2% reduction · fully agent-verified"

**Narration:**
"1,095 queries down to 20. Every number here — before, after, the percentage — came from the tool re-running the real benchmark against a separately archived baseline, not from the agent's own claim."

---

## SEGMENT 7 — Changelog & process (4:00–4:25)

**Shot:** README.md's Improvement Changelog table, scrolled slowly.
**Narration:**
"This is the full paper trail: baseline, agent audit, fix applied — each stage backed by a measured number, and the agent's own trajectory log is included so anyone can verify exactly what it did and didn't do."

---

## SEGMENT 8 — Hot take (4:25–4:50)

**Shot:** Section 11 ("Engineering Lesson From This Run") of AGENT_TRAJECTORY.md.
**On-screen text:** "The real lesson wasn't about N+1"

**Narration:**
"The hardest bug in this whole project wasn't a missing `with()` — it was a run where every single endpoint failed with a 500 error at once, but `php -l` said the syntax was perfectly valid. Turned out a stray edit had renamed the class inside the file without renaming the file itself, silently breaking autoloading for the entire controller. The lesson: when unrelated endpoints fail all at once, look for a shared dependency — autoloading, a trait, middleware — before you re-read the specific code you just changed."

---

## SEGMENT 9 — Close (4:50–5:00)

**Shot:** README.md top of page, or the repo's homepage.
**Narration:**
"Query Doctor: point it at any Laravel project, and it gives you a measured, reproducible performance audit — not a guess. Thanks for watching."

---

## Shot List Summary

| # | Segment | Screen needed |
|---|---|---|
| 1 | Hook | Face cam or title card |
| 2 | Pain | Postman/browser, unfixed endpoint |
| 3 | Baseline | `/benchmark-results` page |
| 4 | Agent run | Terminal, live, uncut |
| 5 | Fix | Code editor diff |
| 6 | Payoff | Postman + `/benchmark-results` again |
| 7 | Changelog | README.md scroll |
| 8 | Hot take | AGENT_TRAJECTORY.md scroll |
| 9 | Close | README.md top |

## Recording Tips
- Segments 4 and 6 (the live runs) should be real, unedited takes — a judge can tell a staged mock from a real terminal, and authenticity is worth more than polish here.
- If you're over time, trim Segment 8 (Hot Take) first — valuable but least essential to the core "does it work and is it measured" story.
- Keep narration conversational; these lines are a guide, not a script to read verbatim.