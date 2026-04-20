---
name: caveman-pr
description: >-
  Command-style skill for generating a pull request description. Use only when
  the user explicitly invokes `/caveman-pr`, tags this skill, or asks for it
  by name.
---

# Generate PR Description

Manual activation only: use this skill only when the user explicitly invokes
`/caveman-pr`, tags this skill, or asks for caveman PR mode by name.

## Overview

Create a comprehensive pull request description based on the changes in this branch and format it as proper markdown for use in a GitHub PR description.

You must utilize the caveman skill when writing this PR description [caveman](/Users/gruja/Main/coding/ai-rules/.agents/skills/commands/caveman/SKILL.md)

## Steps

1. **Summary**
    - Provide a clear, concise summary of what this PR accomplishes
2. **Changes Made**
    - List the key changes made in this PR
    - Include both code and non-code changes
    - Highlight any breaking changes
3. **Testing**
    - Describe how the changes were tested
    - Include any new test cases added
    - Note any manual testing performed
4. **Related Issues**
    - Link to any related issues or tickets
    - Use closing keywords if this PR resolves issues
5. **Additional Notes**
    - Any deployment considerations
    - Follow-up work required
    - Notes for reviewers
