---
description: Plan a feature with questions, then implement it directly and self-review
argument-hint: Feature to build, e.g. GPX download button in the shortcode
agent: agent
---

Plan and implement this feature: ${input:feature}

Follow `.github/copilot-instructions.md`. Run three phases, each exactly as its prompt and agent define it:

1. **Plan** (no code): `.github/prompts/plan-feature.prompt.md` with the roles in `.github/agents/planner.agent.md`. Ask your questions and wait for the answers. Then give the plan, and ask whether it should be adjusted. Continue to phase 2 unless answers are still open.
2. **Implement**: `.github/prompts/implement-feature.prompt.md` with the roles in `.github/agents/implementer.agent.md`.
3. **Review**: `.github/prompts/review-feature.prompt.md` with the roles in `.github/agents/reviewer.agent.md`. Fix every finding.

Keep one todo list from the start that covers all phases: one item in progress, mark items done immediately. Keep answers concise and list only the review findings and fixes.
