---
name: caveman-commit
description: >-
  Command-style skill for writing ultra-compressed Conventional Commit messages.
  Use only when the user explicitly invokes `/caveman-commit`, tags this
  skill, or asks for it by name.
---

# Caveman commit message

Manual activation only: use this skill only when the user explicitly invokes
`/caveman-commit`, tags this skill, or asks for caveman commit mode by name.

## What to do

1. **Resolve the diff** the user wants summarized: staged (`git diff --cached`), unstaged (`git diff`), a commit range, or paths they name. If they did not specify, prefer **staged** first, then unstaged; run the commands yourself when you need the actual changes.
2. **Write only the commit message** using the rules below — no `git commit`, no staging, no amend.
3. **Output** the message inside a single fenced code block so it is ready to paste.

If the user asks for normal/verbose commits or says **stop caveman-commit** / **normal mode**, skip compression and write a standard detailed message instead.

## Tone

Terse and exact. No fluff. **Why** over **what** (the diff already shows what).

## Rules

**Subject line:**

- `<type>(<scope>): <imperative summary>` — `<scope>` optional
- Types: `feat`, `fix`, `refactor`, `perf`, `docs`, `test`, `chore`, `build`, `ci`, `style`, `revert`
- Imperative mood: "add", "fix", "remove" — not "added", "adds", "adding"
- ≤50 chars when possible, hard cap 72
- No trailing period
- Match project convention for capitalization after the colon

**Body (only if needed):**

- Skip entirely when subject is self-explanatory
- Add body only for: non-obvious _why_, breaking changes, migration notes, linked issues
- Wrap at 72 chars
- Bullets `-` not `*`
- Reference issues/PRs at end: `Closes #42`, `Refs #17`

**What NEVER goes in:**

- "This commit does X", "I", "we", "now", "currently" — the diff says what
- "As requested by..." — use Co-authored-by trailer
- "Generated with Claude Code" or any AI attribution
- Emoji (unless project convention requires)
- Restating the file name when scope already says it

## Examples

Diff: new endpoint for user profile with body explaining the why

- ❌ "feat: add a new endpoint to get user profile information from the database"
- ✅

    ```
    feat(api): add GET /users/:id/profile

    Mobile client needs profile data without the full user payload
    to reduce LTE bandwidth on cold-launch screens.

    Closes #128
    ```

Diff: breaking API change

- ✅

    ```
    feat(api)!: rename /v1/orders to /v1/checkout

    BREAKING CHANGE: clients on /v1/orders must migrate to /v1/checkout
    before 2026-06-01. Old route returns 410 after that date.
    ```

## Auto-clarity

Always include a body for: **breaking changes**, **security fixes**, **data migrations**, anything **reverting** a prior commit. Never compress these into subject-only — future readers need the context.

## Boundaries

Only generate the commit message. Do not run `git commit`, do not stage files, do not amend.
