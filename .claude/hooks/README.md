# Edit-time guardrails

`guard-rules.mjs` is a Claude Code **PreToolUse** hook (matcher `Edit|Write|MultiEdit`).
It turns a few of AGENTS.md's hard **"Never"** rules into live guardrails that block
the edit *at the moment it happens*, instead of only catching it later in CI
(deptrac / the generated-file freshness check).

## Rules enforced

| # | Rule | Action |
|---|------|--------|
| 1 | No hand-editing generated files (`apps/api/openapi.json`, `packages/api-client-ts/src/types.gen.ts`). Use `make gen-api`. | **block** |
| 2 | No framework imports (`use Symfony\`, `use Doctrine\`, `use Predis\`) inside `apps/api/src/<Context>/Domain/`. | **block** |

Both correspond to existing AGENTS.md "Never" rules. They are deliberately
**high-confidence** (no false positives). Rule 2 only inspects *new* content, so
removing a forbidden import is never blocked.

The hook **fails open**: any unreadable payload, unknown shape, or parse error
results in *allow*. A guardrail must never be the reason an edit can't happen.

## Activation

Registered via the `hooks.PreToolUse` block in `.claude/settings.json` (committed,
so it applies to everyone on the project). Settings are read at session start, so
a new hook takes effect on the next session.

To verify locally:

```bash
printf '%s' '{"tool_name":"Write","tool_input":{"file_path":"'"$PWD"'/apps/api/openapi.json","content":"{}"}}' \
  | node .claude/hooks/guard-rules.mjs        # → permissionDecision: deny
```

## Extending

Add a rule by appending another `deny(...)` guard in `guard-rules.mjs`. Keep new
rules high-confidence; prefer `warn` (a non-blocking message) over `block` when a
false positive is plausible. Candidate next rules (currently left out to avoid
false positives): `#[ORM\*]` attributes on domain entities, controllers calling
`em->find()` directly instead of going through a query bus.
