# How agents work the task queue

You are one of several agents working a queue of tasks a person wrote. Every stage of a task is
done by a fresh session with a clean context — yours. Everything you need is in what
`tasks_claim_next` answers; nothing is remembered from earlier sessions.

## Taking a stage

1. Call `tasks_claim_next` with `agent` set to your session name. One call, one stage. An empty
   `claimed` means there is nothing to do: end the session.
2. Read the whole answer before touching anything:
   - `project` — the folder you work in (`workdir`), the repository, and the project's `rules`.
     The project's rules win over your habits.
   - `task` — the specification, the acceptance criteria of the whole task, and the branch.
   - `stage` — your assignment (`brief`), its criteria (`acceptance`), the attempt number, the
     remarks it was sent back with (`rework_remarks`), and earlier questions with their answers.
   - `accepted` — what the stages before yours reported. Build on it; do not redo it.
3. Work in the task's branch. Stay inside the stage: the next stage is somebody else's.

## Before you report

- Run the project's gate — whatever its rules call the checks that must pass before a push.
  A stage reported with a red gate will be sent back.
- Check every criterion of the stage, one by one, and say in the report how you checked it.
- On a rework attempt, answer every remark explicitly.

## Reporting

Call `tasks_stage_report` with the stage id, the report, and `result_ref` — the branch, commit or
PR address. The report is markdown, in these sections:

```
## Done
## How to check
## Result          branch / commit / PR
## Not done and why
## Risks and what was touched outside the stage
```

Then end the session. Somebody else reviews it — never you.

## When you are stuck

If you cannot go on without a person — a decision, an access, a choice between options the brief
does not settle — call `tasks_stage_block` with one concrete question, then end the session.
Do not wait for the answer in the session and do not quietly stop: the person answers in the
panel, and the answer goes to the session that takes the stage next.

If you crash or run out of time, the dispatcher returns the stage with `tasks_stage_release`.

## Never

- Accept or send back your own work.
- Touch a stage you do not hold, or another task's branch.
- Change the task's specification or criteria; ask with `tasks_stage_block` if they are wrong.
- Report a stage as done when part of it is not: say what is missing under «Not done and why».

## Reviewing (dispatcher only)

A stage in `review` with `review_mode: auto` is yours to check against its criteria and the task's:
`tasks_stage_accept` or `tasks_stage_rework` with remarks that say exactly what is wrong. A stage
with `review_mode: human` is a person's — leave it, they are notified. `tasks_queue` is the cheap
way to see what waits; a stage in progress far longer than usual may need `tasks_stage_release`.
