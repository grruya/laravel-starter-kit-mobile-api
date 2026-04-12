# Review changes (multi-agent)

Run the **change review pipeline** by orchestrating the custom Codex agents defined in `.codex/agents/`. Do not improvise a different review shape. Follow the steps, explicitly spawn each agent, wait for its result, pass outputs between steps, and push every reviewer toward concrete, evidence-backed findings instead of generic observations because subagents do not share context.

This pipeline is for **change-level review only**.

The reviewers should understand the wider execution flow, but they should review and report findings only for the **changed files** in the requested change set.

## Step 1 — Resolve the review target

Determine exactly what the user wants reviewed.

The user may describe the review target in any reasonable way, for example:

- a PR diff
- the last commit
- a specific earlier commit such as the second commit in a branch
- staged changes
- unstaged changes
- an explicit diff range
- a list of files

Resolve and store:

- the user-requested review target
- the exact changed or requested file list
- the diff, commit, or change summary needed for review

## Step 2 — Map execution flow for context

Spawn the **`execution-flow`** agent using the changed or requested files from Step 1 as the starting point, then wait for its result.

Your goal in this step is **not** to widen the review target. Your goal is to build enough context so reviewers understand how the changed files behave inside the larger system.

Ask the subagent to identify:

- the execution flow touching the changed files
- entry points
- immediate callers and callees that materially affect the changed behavior
- validation, authorization, configuration, schema, jobs, events, policies, and side effects relevant to the changed behavior
- trust boundaries
- state mutations
- error and exception paths

Do not turn the whole related feature into the review target. Expand only enough to understand the changed behavior end to end.

**Prompt:**

```text
Map the execution flow needed to understand this review target:
{changed or requested files from Step 1}

Your job:
- trace the real execution flow needed to understand these changed files
- include only the supporting files and paths needed for context
- do not redefine the review target beyond the changed or requested files

Also identify:
- entry points
- trust boundaries
- authorization and validation checkpoints
- state mutations
- side effects
- error and exception paths
```

**Store for later steps:**

- the changed or requested file list
- the execution flow
- the supporting context files from the flow

## Step 3 — Parallel reviews using change target and execution flow

In **one assistant turn**, spawn these three agents **in parallel**, then wait for all three results:

- `security-reviewer`
- `logic-reviewer`
- `performance-reviewer`

Each agent already encodes what to look for. Pass all of the following to each one:

- the changed or requested file list from Step 1
- the execution flow from Step 2
- the supporting context files from Step 2

**Prompt template (each reviewer):**

```text
Review this change set.

Changed or requested files:
{changed or requested file list from Step 1}

Supporting context files:
{supporting context files from Step 2}

Execution flow:
{execution flow from Step 2}

Rules:
- only review changed files, do not review files in execution flow use them just for context
- report findings only when the issue is in a changed file
- do not report pre-existing issues that exist only in unchanged files
- keep findings concrete, reachable, and tied to this change set

Use your agent rubric. Report only concrete, reachable findings tied to this change set.
```

**Collect all issues** from the three agent results.

## Step 4 — Consolidate

Spawn **`deduplicate-issues`** with every issue from Step 3, then wait for the result.

**Prompt:**

```text
Deduplicate and organize these issues from multiple reviewers:
{all issues from Step 3}

Preserve each issue’s source agent id(s). When merging duplicates reported by different agents, keep all contributing agent names on that issue.
```
