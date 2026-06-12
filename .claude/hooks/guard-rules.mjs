#!/usr/bin/env node
// Edit-time guardrails for the jperdior-template harness.
//
// A Claude Code PreToolUse hook (matcher: Edit|Write|MultiEdit). It turns a few
// of AGENTS.md's hard "Never" rules into live guardrails that block the edit at
// the moment it happens, instead of only catching it later in CI (deptrac /
// the generated-file freshness check).
//
// Design rules:
//   - Fail OPEN. Any parse error / unknown shape → allow. A guard must never be
//     the reason an edit can't happen.
//   - Only inspect NEW content, so deleting a forbidden line is never blocked.
//   - High-confidence rules only (no false positives). Extend deliberately.
//
// Contract (Claude Code PreToolUse): reads the tool call as JSON on stdin; to
// deny, print a hookSpecificOutput JSON with permissionDecision "deny" and
// exit 0.

import { readFileSync } from 'node:fs';
import { relative, resolve } from 'node:path';

function allow() {
  process.exit(0);
}

function deny(reason) {
  process.stdout.write(
    JSON.stringify({
      hookSpecificOutput: {
        hookEventName: 'PreToolUse',
        permissionDecision: 'deny',
        permissionDecisionReason: reason,
      },
    }),
  );
  process.exit(0);
}

let input;
try {
  input = JSON.parse(readFileSync(0, 'utf8') || '{}');
} catch {
  allow(); // unreadable payload → never block
}

const toolInput = (input && input.tool_input) || {};
const filePath = toolInput.file_path;
if (typeof filePath !== 'string' || filePath.length === 0) allow();

// Path relative to the project root, forward-slashed for stable matching.
const root = process.env.CLAUDE_PROJECT_DIR || process.cwd();
let rel = relative(root, resolve(filePath)).split('\\').join('/');
if (rel.startsWith('..')) rel = filePath.split('\\').join('/'); // outside root → raw

// Collect only the NEW content being written. Removing a forbidden line (it
// lives in old_string) must not trip a rule. Falls back to scanning every
// string if the tool input shape is unfamiliar, so the guard still applies.
function newContent(ti) {
  const out = [];
  if (typeof ti.content === 'string') out.push(ti.content); // Write
  if (typeof ti.new_string === 'string') out.push(ti.new_string); // Edit
  if (Array.isArray(ti.edits)) {
    for (const e of ti.edits) {
      if (e && typeof e.new_string === 'string') out.push(e.new_string); // MultiEdit
    }
  }
  if (out.length === 0) {
    (function walk(v) {
      if (typeof v === 'string') out.push(v);
      else if (Array.isArray(v)) v.forEach(walk);
      else if (v && typeof v === 'object') Object.values(v).forEach(walk);
    })(ti);
  }
  return out.join('\n');
}
const incoming = newContent(toolInput);

// ── Rule 1: generated files are never hand-edited ─────────────────────────────
// AGENTS.md: "Never edit generated files (apps/api/openapi.json,
// packages/api-client-ts/src/types.gen.ts) by hand."
const GENERATED = ['apps/api/openapi.json', 'packages/api-client-ts/src/types.gen.ts'];
if (GENERATED.includes(rel)) {
  deny(
    `Blocked: ${rel} is generated and must not be hand-edited (AGENTS.md). ` +
      'Change the API contract in apps/api/ and run `make gen-api` to regenerate it.',
  );
}

// ── Rule 2: no framework imports inside a Domain layer ────────────────────────
// AGENTS.md: "Never import framework code (Symfony*, Doctrine*, Predis*) inside
// Domain/." Enforced post-hoc by deptrac in CI; this catches it at edit time.
const isDomainPhp = /^apps\/api\/src\/[^/]+\/Domain\//.test(rel) && rel.endsWith('.php');
if (isDomainPhp) {
  const m = incoming.match(/^\s*use\s+(Symfony|Doctrine|Predis)\\/m);
  if (m) {
    deny(
      `Blocked: framework import \`use ${m[1]}\\…\` in a Domain file (${rel}). ` +
        'Domain/ must stay pure PHP — keep framework code in Infrastructure/. ' +
        '(AGENTS.md "Never"; deptrac enforces this in CI.)',
    );
  }
}

allow();
