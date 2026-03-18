---
name: "Review Pull Request"
description: "Review a GitHub pull request before merge. Use when you have a PR URL and want findings, focused validation, and a manual checklist for changed areas such as migrations, admin pages, queues, cron, config, or cache changes."
argument-hint: "GitHub PR URL and optional focus areas"
agent: "agent"
---

Review this pull request before merge using the existing PR review workflow from [pull-request-review](../../.agents/skills/pull-request-review/SKILL.md).

User input will contain:

- a GitHub pull request URL
- optional focus areas or risks to prioritize

Required behavior:

- parse the PR URL and confirm the current workspace matches the target repository before making git changes
- protect any local work before fetching or checking out the PR branch
- compare the PR branch against `origin/main`
- review with a code-review mindset, prioritizing bugs, regressions, risky migrations, deployment risk, stale routes, and missing tests
- run the smallest credible automated validation based on the changed files
- end with a manual review checklist tailored to the changed product areas

Response format:

1. PR summary
2. Findings ordered by severity
3. Automated validation run
4. Manual review checklist before merge
5. Residual risks or open questions

If there are no findings, say so explicitly and still provide the manual review checklist.
