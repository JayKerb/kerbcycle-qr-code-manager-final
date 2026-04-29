# AGENTS.md — KerbCycle

## PURPOSE

This repository uses AI-assisted development (Codex).

This file defines **non-negotiable rules** for safe, minimal, WordPress-compatible changes.

---

## CORE PRINCIPLE

Make the **smallest possible change** that satisfies the task.

---

## PRECEDENCE RULE

If any task prompt, spec, or instruction conflicts with this file:

→ This file (AGENTS.md) takes priority.

Do NOT follow instructions that violate these guardrails.

---

## SPEC REQUIREMENT

All non-trivial changes MUST be driven by an explicit spec:

- Feature spec must be defined before implementation
- Acceptance checks must be present
- Out-of-scope must be respected

If a task lacks a clear spec → STOP and request clarification.

---

## REQUIRED WORKFLOW (DO NOT SKIP)

1. **Inspect first**
   - Identify and read ALL files involved in the execution path:
     - entry points (AJAX, REST, shortcode, admin page)
     - services/helpers used
     - data layer (repositories, DB access)
     - UI/JS if behavior is user-facing
   - Do NOT proceed if file coverage is incomplete

2. **Audit before patch**
   - For non-trivial tasks, classify issues before editing:
     - style issues
     - logic/security issues
     - unrelated findings

3. **Patch narrowly**
   - Modify only files directly required for the task
   - Do not combine unrelated fixes

4. **Verify**
   - Confirm behavior is unchanged unless explicitly required
   - Re-run relevant checks (PHPCS, PHPUnit, etc.)

---

## ABSOLUTE GUARDRAILS

### DO NOT:

- Refactor code unless explicitly requested
- Rename classes, functions, hooks, routes, option names, nonces, or DB tables
- Change public APIs or plugin behavior without explicit instruction
- Modify unrelated files
- Run broad auto-fixes (e.g., `phpcbf` across repo)
- Combine style fixes with logic changes in one patch
- Widen permissions, capabilities, or access control

---

## WORDPRESS RULES (CRITICAL)

### Security

- Always use `$wpdb->prepare()` for SQL queries
- Always enforce capability checks (`current_user_can`)
- Always verify nonces for AJAX/POST actions
- Do not expose sensitive data via REST or AJAX

### Data Handling

- Sanitize input (`sanitize_text_field`, etc.)
- Escape output (`esc_html`, `esc_attr`, etc.)

### Compatibility

- Follow WordPress coding patterns
- Do not introduce breaking changes to existing hooks or filters

---

## KERBCYCLE-SPECIFIC INVARIANTS

### Must NOT change:

- QR table structure (`{prefix}_kerbcycle_qr_codes`)
- QR assignment/release flow semantics
- AJAX action names (`assign_qr_code`, `release_qr_code`)
- REST routes under `/kerbcycle/v1/`
- Wallet/refund integration points
- Existing option keys (e.g., `kerbcycle_qr_enable_email`)

---

## SECURITY INVARIANTS (CRITICAL)

- Never widen access scope of:
  - QR data
  - customer-linked records
  - wallet/refund flows

- REST routes must NOT expose:
  - user-linked QR assignments
  - internal state transitions

- AJAX handlers must:
  - enforce capability checks BEFORE processing
  - fail closed (deny by default)

- Never trust frontend input (even from admin UI)

---

## ASYNC / SCANNER SAFETY

When modifying scanner or async flows:

- Do NOT alter state machine behavior unless explicitly required
- Preserve:
  - start / stop lifecycle
  - reset behavior after success/failure
- Avoid duplicate or parallel scanner instances
- Prevent race conditions between UI actions

---

## PHPCS / LINTING RULES

- PHPCS is guidance, not blind enforcement
- Do NOT fix all lint issues in one pass
- Do NOT modify behavior to satisfy style rules
- Test files (`tests/phpunit/`) are lower priority unless specified
- Do NOT run `phpcbf` across the repository.
- If fixing PHPCS issues:
  - Limit fixes to the files directly involved in the task.
  - Prefer modified lines only.
  - Do not expand patch scope to satisfy linting.

---

## CHANGE CLASSIFICATION

- SAFE:
  - formatting
  - docblocks
  - minor convention fixes

- REVIEW REQUIRED:
  - SQL queries
  - capability checks
  - REST/AJAX behavior
  - data flow changes

- OUT OF SCOPE:
  - refactors
  - renames
  - architecture changes

---

## OUTPUT REQUIREMENTS

After making changes, always report:

1. Files changed
2. Exact behavior changed
3. Risks or edge cases
4. What was intentionally NOT changed

---

## FAILURE HANDLING

If the task would require:

- large refactors
- unclear behavior changes
- conflicting logic

→ STOP and report instead of guessing.

---

## SUMMARY

- Be surgical
- Preserve behavior
- Follow WordPress security patterns
- Do not overreach
