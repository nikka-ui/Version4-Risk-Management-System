---
description: Git workflow — feature branches, commit messages, no secrets
alwaysApply: true
---

# Git Workflow

## Branches

- Do **not** commit directly to `main` / production branches.
- Use feature branches: `feature/...`, `fix/...`, `chore/...`, or short descriptive names tied to the change.
- Open a PR for review; keep commits focused on one concern when practical.

## Commit messages

- Use clear, descriptive messages that explain **why**, not only what.
- Prefer imperative mood: `fix dept ticket auth for retired users`, `add feature tests for president queue`.
- Avoid vague messages: `update`, `fix stuff`, `wip`.

## Never commit

- `.env`, `.env.staging`, or files with real secrets
- `docker/secrets/*.txt` (commit only `*.example` templates)
- credentials, private keys, production dumps, or `.phpunit.result.cache` if it contains sensitive local state
- Vendor/build artifacts that belong in `.gitignore` (`vendor/`, `node_modules/`, compiled assets unless the repo intentionally tracks them)

## Before pushing

- Ensure tests for your change pass (`cd backend && php artisan test`).
- Do not force-push shared/default branches unless explicitly requested by a maintainer.
