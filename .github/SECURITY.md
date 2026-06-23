# Security Policy

## Reporting a Vulnerability

Please do not open a public GitHub issue for security vulnerabilities.

Use GitHub's private vulnerability reporting feature to report suspected security issues. This provides a private, structured disclosure path for security reports.

When reporting a vulnerability, please include as much of the following as possible:

* A clear description of the issue
* Steps to reproduce the issue
* Affected files, workflows, endpoints, or plugin features, if known
* The potential impact
* Any suggested mitigation or fix, if available

Reports will be reviewed as soon as practical. Follow-up questions may be asked before impact is confirmed.

## Scope

This policy applies to the KerbCycle QR Code Manager plugin repository and related GitHub Actions workflows.

Security issues may include, but are not limited to:

* Authentication or authorization bypass
* Privilege escalation
* Cross-site scripting
* Cross-site request forgery
* SQL injection
* Insecure REST API or AJAX endpoints
* Unsafe file handling
* Exposure of secrets or sensitive data
* GitHub Actions or CI/CD security issues
* Supply-chain or dependency-related vulnerabilities

## Out of Scope

Please do not perform testing against production systems, customer data, third-party services, payment processors, SMS providers, email providers, or live infrastructure without explicit permission.

Please avoid:

* Denial-of-service testing
* Spam or social engineering
* Attempts to access, modify, or exfiltrate data
* Testing that degrades service availability
* Public disclosure before a fix or mitigation has been coordinated

## Response Expectations

This project does not currently operate a paid bug bounty program and does not guarantee compensation for vulnerability reports.

Valid reports will be reviewed and addressed based on severity, reproducibility, and project risk.

Thank you for helping improve the security of KerbCycle.
