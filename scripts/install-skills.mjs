#!/usr/bin/env node
// Symlink skills from .ai/skills/<name>/ into .claude/skills/<name>/ and
// .codex/skills/<name>/ based on .ai/skills/tiers.json.
//
// Usage:
//   pnpm install-skills                               # default tiers
//   pnpm install-skills --with automation             # default + automation
//   pnpm install-skills --tiers core,security         # explicit set
//   pnpm install-skills --all                         # every tier
//   pnpm install-skills --clean                       # remove all symlinks
//   pnpm install-skills --list                        # show tiers + memberships

import { existsSync, lstatSync, mkdirSync, readFileSync, readdirSync, rmSync, symlinkSync, unlinkSync } from 'node:fs';
import { dirname, join, relative, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = dirname(fileURLToPath(import.meta.url));
const repoRoot  = resolve(__dirname, '..');
const skillsDir = join(repoRoot, '.ai', 'skills');
const targets   = [
  join(repoRoot, '.claude', 'skills'),
  join(repoRoot, '.codex',  'skills'),
];

const args  = process.argv.slice(2);
const flags = new Set(args.filter(a => a.startsWith('--')));
const valOf = (name) => {
  const i = args.indexOf(name);
  return i >= 0 ? args[i + 1] : null;
};

const tiersJson = JSON.parse(readFileSync(join(skillsDir, 'tiers.json'), 'utf8'));

function selectedTiers() {
  if (flags.has('--all')) return Object.keys(tiersJson.tiers);
  const explicit = valOf('--tiers');
  if (explicit) return explicit.split(',').map(s => s.trim()).filter(Boolean);
  const base = [...tiersJson.default];
  const withFlag = valOf('--with');
  if (withFlag) base.push(...withFlag.split(',').map(s => s.trim()).filter(Boolean));
  return [...new Set(base)];
}

function selectedSkills() {
  const tiers = selectedTiers();
  const set = new Set();
  for (const t of tiers) {
    const def = tiersJson.tiers[t];
    if (!def) {
      console.error(`Unknown tier: ${t}`);
      process.exit(2);
    }
    for (const s of def.skills) set.add(s);
  }
  return [...set];
}

function ensureDir(d) {
  if (!existsSync(d)) mkdirSync(d, { recursive: true });
}

function purge(dir) {
  if (!existsSync(dir)) return;
  for (const entry of readdirSync(dir)) {
    const p = join(dir, entry);
    try {
      const stat = lstatSync(p);
      if (stat.isSymbolicLink() || stat.isDirectory() || stat.isFile()) rmSync(p, { recursive: true, force: true });
    } catch { /* ignore */ }
  }
}

function listMode() {
  console.log('Tiers:');
  for (const [name, def] of Object.entries(tiersJson.tiers)) {
    const isDefault = tiersJson.default.includes(name) ? ' (default)' : '';
    console.log(`  ${name}${isDefault} — ${def.description}`);
    for (const s of def.skills) console.log(`    • ${s}`);
  }
}

function cleanMode() {
  for (const t of targets) purge(t);
  console.log('Cleaned all skill symlinks.');
}

function installMode() {
  const skills  = selectedSkills();
  const missing = [];

  for (const target of targets) {
    purge(target);
    ensureDir(target);

    for (const skill of skills) {
      const src = join(skillsDir, skill);
      if (!existsSync(src)) { missing.push(skill); continue; }
      const dst = join(target, skill);
      // POSIX-style relative symlink so the repo is portable.
      symlinkSync(relative(target, src), dst, 'dir');
    }
  }

  console.log(`Installed ${skills.length} skill(s) into:`);
  for (const t of targets) console.log(`  • ${relative(repoRoot, t)}`);
  if (missing.length) {
    console.error(`Missing skill folders: ${[...new Set(missing)].join(', ')}`);
    process.exit(1);
  }
}

if (flags.has('--list'))      listMode();
else if (flags.has('--clean')) cleanMode();
else                           installMode();
