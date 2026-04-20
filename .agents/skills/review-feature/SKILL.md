---
name: review-feature
description: >-
  Command-style skill for running the multi-agent feature review pipeline. Use
  only when the user explicitly invokes `/review-feature`, tags this skill, or
  asks for it by name.
---

# Full code review (multi-agent)

Manual activation only: use this skill only when the user explicitly invokes
`/review-feature`, tags this skill, or asks for this review command by name.

Run the **code review pipeline** by orchestrating the custom Codex agents defined in `.codex/agents/`. Do not improvise a different review shape. Follow the steps, explicitly spawn each agent, wait for its result, pass outputs between steps, and push every reviewer toward concrete, evidence-backed findings instead of generic observations because subagents do not share context.

This pipeline is for **feature-level review only**.

## Step 1 — Map the full feature execution flow

Start from the feature or subsystem the user asked to review. Use that as the entry point to understand how the full feature works.

Your goal in this step is to identify the **entire feature scope** and how the feature behaves end to end.

Scope should include:

- directly relevant or explicitly requested files
- entry points for the feature
- immediate callers and callees that materially affect behavior
- related validation, authorization, configuration, schema, jobs, events, policies, and other files that change runtime behavior
- side-effect paths such as notifications, payments, persistence, cache, queues, and integrations

Do not expand scope into unrelated parts of the repo. Expand only until the feature behavior is understandable end to end.

Spawn the **`execution-flow`** agent with the initial feature description or starting files for that feature, then wait for its result.

**Prompt:**

```
Map the full execution flow for this feature:
{user-requested feature or starting file list}

Your job:
- identify the full feature scope, even if some files were not explicitly requested
- include all related files needed to understand how the feature behaves end to end
- trace the real execution flow through the system

Also identify:
- entry points
- trust boundaries
- authorization and validation checkpoints
- state mutations
- side effects
- error and exception paths
```

**Store for later steps:**

- the expanded feature file list
- the execution flow

## Step 2 — Parallel reviews using feature flow

In **one assistant turn**, spawn these three agents **in parallel**, then wait for all three results:

- `security-reviewer`
- `logic-reviewer`
- `performance-reviewer`

Each agent already encodes what to look for. Pass all of the following to each one:

- the expanded feature file list from Step 1
- the execution flow from Step 1

**Prompt template (each reviewer):**

```
Review this feature:

Feature files:
{expanded feature file list from Step 1}

Execution flow:
{execution flow from Step 1}

Use your agent rubric. Report only concrete, reachable findings tied to this flow.
```

**Collect all issues** from the three agent results.

## Step 3 — Consolidate

Spawn **`deduplicate-issues`** with every issue from Step 2, then wait for the result.

**Prompt:**

```
Deduplicate and organize these issues from multiple reviewers:
{all issues from Step 2}

Preserve each issue’s source agent id(s). When merging duplicates reported by different agents, keep all contributing agent names on that issue.
```

## Output format

Use the **caveman-review** comment style for the final result: terse, actionable, one line per finding.

Present the final result like this:

```text
🔍 Found issues:

<file>:L<line>: <severity label> <problem>. <fix>.
```

Formatting rules:

- one line per finding
- use `<file>:L<line>:` for location
- use severity labels in this style:
    - `🔴 bug:` for broken behavior or high-severity security/logic/performance issues
    - `🟡 risk:` for medium-severity or fragile behavior that can realistically fail
    - `🔵 nit:` only if a reviewer explicitly reports a low-signal issue worth mentioning
    - `❓ q:` only for genuine blocking uncertainty
- include the concrete problem and a concrete fix on the same line
- mention exact symbols in backticks when useful
- do not add evidence, impact, assumed logic, or attacker capability as separate fields in the final output
- do not add prose before or after individual findings
