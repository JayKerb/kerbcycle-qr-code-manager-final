# KerbCycle CI Policy

This document explains how KerbCycle CI checks are grouped, which findings should fail pull requests, and which tools should run as advisory, scheduled, or manual checks.

The goal is to keep pull requests safe without making every scanner a noisy merge blocker.

## CI groups

KerbCycle CI checks are grouped into three categories:

1. Required PR checks
2. Advisory PR checks
3. Scheduled or manual deep scans

## 1. Required PR checks

Required PR checks should fail a pull request when they find a problem.

These checks are expected to be fast, deterministic, and high-confidence. They protect the repository from obvious breakage, unsafe workflow changes, dependency risk, and known security regressions.

### Required checks

| Tool / workflow              |               Should run on PRs? |                 Should fail PRs? | Reason                                                                          |
| ---------------------------- | -------------------------------: | -------------------------------: | ------------------------------------------------------------------------------- |
| PHPUnit smoke/security tests |                              Yes |                              Yes | Protects core plugin behavior and security-boundary assumptions.                |
| PHPCS changed-files gate     |                              Yes |                              Yes | Prevents new WordPress coding/security-standard issues in changed PHP files.    |
| PHPStan                      |                              Yes |                              Yes | Prevents new static-analysis regressions at the current accepted level.         |
| Dependency Review            |                              Yes |                              Yes | Blocks newly introduced vulnerable dependencies.                                |
| actionlint                   | Yes, especially workflow changes |                              Yes | Prevents invalid or broken GitHub Actions workflows.                            |
| zizmor                       | Yes, especially workflow changes | Yes for high-confidence findings | Helps detect risky GitHub Actions patterns.                                     |
| TruffleHog diff scan         |                              Yes |         Yes for verified secrets | Prevents accidentally committing secrets.                                       |
| ESLint                       |                 Yes, once stable |                              Yes | Prevents new JavaScript syntax/style regressions once the baseline is reliable. |

### Required-check policy

A required check should fail the PR when the finding is:

- New or introduced by the PR
- High-confidence
- Security-relevant or correctness-relevant
- Reasonably actionable by the PR author

Do not make a tool required if it frequently reports noisy, unclear, or environment-dependent findings.

## 2. Advisory PR checks

Advisory checks may run on pull requests, but they should not block ordinary PRs yet.

These tools are useful for visibility, backlog planning, and security review, but their findings may need human triage before they are safe to use as merge blockers.

### Advisory checks

| Tool / workflow     | Should run on PRs? |                        Should fail PRs? | Reason                                                                          |
| ------------------- | -----------------: | --------------------------------------: | ------------------------------------------------------------------------------- |
| Semgrep broad rules |                Yes | No, except custom high-confidence rules | Useful security signal, but broad rules can produce false positives.            |
| Snyk Code           |           Optional |                                      No | Useful as an advisory scanner, but should not block until baseline is reviewed. |
| SonarQube           |           Optional |                                      No | Best used for maintainability, complexity, and code-smell backlog.              |
| Codacy              |           Optional |                                      No | Useful for quality visibility, but not a primary security gate.                 |
| Codecov             |                Yes |                                 Not yet | Useful for coverage visibility. Later, patch coverage may become blocking.      |

### Advisory-check policy

Advisory checks should:

- Write a clear GitHub Actions summary
- Upload reports as artifacts when useful
- Avoid blocking normal PRs
- Be reviewed before security-sensitive merges
- Be converted to required only after the baseline is clean and the findings are reliable

## 3. Scheduled or manual deep scans

Scheduled/manual scans should not normally run on every PR.

These tools are slower, noisier, more expensive, or dependent on staging/server state.

### Scheduled/manual checks

| Tool / workflow                 | Run frequency    |     Should fail normal PRs? | Reason                                                                        |
| ------------------------------- | ---------------- | --------------------------: | ----------------------------------------------------------------------------- |
| Wordfence CLI                   | Weekly/manual    |                          No | Full repo malware scanning is useful, but not needed on every PR.             |
| ClamAV                          | Weekly/manual    |                          No | Useful malware scan, but better as scheduled/manual unless release-sensitive. |
| OWASP ZAP baseline              | Weekly/manual    |           No, not initially | Depends on staging availability and needs a reviewed baseline.                |
| WPScan, if added later          | Weekly/manual    |           No, not initially | Depends on staging/plugin state and needs triage.                             |
| Full OSV inventory scan         | Weekly/manual    | No, except urgent criticals | Useful for dependency inventory beyond PR-specific dependency changes.        |
| k6/load testing, if added later | Manual/scheduled |                          No | Performance tests should not block normal PRs unless thresholds are mature.   |

### Scheduled/manual policy

Scheduled/manual scans should:

- Upload reports as GitHub Actions artifacts
- Write a clear GitHub Actions summary
- Be reviewed before major releases
- Fail only on clear high-risk findings, such as confirmed malware or verified secrets

## Report tracking

CI reports should be easy to find from the GitHub Actions run page.

Recommended reporting pattern:

- Use GitHub Actions job summaries for human-readable results.
- Upload raw reports as artifacts.
- Use clear artifact names.
- Use SARIF/code-scanning uploads where supported.
- Keep scheduled scan artifacts longer than ordinary PR artifacts.

Recommended artifact names:

| Tool          | Artifact name         |
| ------------- | --------------------- |
| PHPCS         | phpcs-results         |
| PHPStan       | phpstan-results       |
| Semgrep       | semgrep-results       |
| Snyk          | snyk-results          |
| OSV Scanner   | osv-results           |
| Wordfence CLI | wordfence-cli-results |
| ClamAV        | clamav-results        |
| ZAP           | zap-baseline-report   |
| Codecov       | codecov-results       |

## Branch protection guidance

Only stable required checks should be added to GitHub branch protection.

Recommended required checks:

- required / phpunit
- required / phpcs
- required / phpstan
- required / dependency-review
- required / actionlint
- required / zizmor
- required / trufflehog

Do not require advisory or scheduled checks unless they have become stable and low-noise.

Do not require:

- advisory / semgrep
- advisory / snyk
- advisory / sonarqube
- advisory / codacy
- advisory / codecov
- scheduled / wordfence-cli
- scheduled / clamav
- scheduled / zap-baseline

## Change-management rule

CI changes should be made in small, reviewable PRs.

This PR should only organize CI behavior and reporting. It should not raise strictness levels, refactor plugin code, or add unrelated tools.

Future PRs may separately:

- Add Playwright browser smoke tests
- Add WPScan scheduled scanning
- Add k6 performance tests
- Tighten Semgrep rules
- Convert Codecov patch coverage into a required check
- Convert additional advisory checks into required checks after baseline review
