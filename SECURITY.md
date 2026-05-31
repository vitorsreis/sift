# Security Policy

## Supported Versions

Sift is pre-2.0 while the clean rebuild is in progress. Security fixes target:

| Version | Supported                                    |
|---------|----------------------------------------------|
| `2.x`   | Yes, after first stable release              |
| `1.x`   | No planned fixes unless explicitly announced |

## Reporting a Vulnerability

Do not report security issues in public GitHub issues.

Report vulnerabilities privately through GitHub Security Advisories for `vitorsreis/sift`, or contact the maintainer
privately through the GitHub profile when advisories are unavailable.

Include:

- affected Sift version or commit
- operating system and PHP version
- affected entrypoint: Composer command, `vendor/bin/sift`, or PHAR
- exact command and config needed to reproduce
- impact, including whether arbitrary commands, writes outside the project, token leakage, or unsafe skill installation
  are possible
- any known workaround

## Scope

Security-relevant areas include:

- command execution and argument escaping
- raw mode and policy bypass attempts
- Composer read-only command enforcement
- Rector, Pint, php-cs-fixer, and Mago write-mode protections
- skill source validation and GitHub clone handling
- path traversal, symlink escapes, and managed target writes
- history redaction and secret persistence
- PHAR bootstrap and bundled resource loading

## Response Process

The maintainer will triage valid reports, reproduce the issue, decide severity, prepare a fix, and publish a release
note once a fixed version is available.

For high-impact issues, public details may be delayed until users have had reasonable time to upgrade.

## Safe Harbor

Good-faith research is welcome when it avoids data destruction, service disruption, persistence, privacy violations, and
access to secrets that are not your own.
