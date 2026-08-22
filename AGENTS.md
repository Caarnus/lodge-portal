# WorkingTools Codex Instructions

## Windows shell command conventions

When inspecting files, avoid assigning `Get-Content` output to PowerShell variables
or composing large PowerShell scripts unless it is genuinely necessary.

Prefer simple direct commands that are easier for Codex execution-policy rules to match.

Prefer:

- `rg -n "pattern" path`
- `rg --files`
- `Get-Content -Raw path`
- `Get-Content path | Select-Object -Skip N -First N`
- `git status --short`
- `git diff --check`
- `git diff`
- `php -l file.php`
- `php artisan route:list`
- `php artisan schedule:list`
- `php artisan test`
- `vendor/bin/pint --test`
- `npm run typecheck`
- `npm run lint`
- `npm run build`

Avoid patterns such as:

```powershell
$lines = Get-Content file.php
$lines[100..200] -join "`n"
```

Prefer instead:

```powershell
Get-Content file.php | Select-Object -Skip 100 -First 101
```

Also avoid combining several unrelated inspection operations into one
`pwsh -Command` invocation when separate read-only commands will suffice.

## Repository inspection

For source and documentation searches:

- Prefer `rg` over PowerShell recursive file scanning where practical.
- Prefer `rg --files` for file discovery.
- Exclude `vendor`, `node_modules`, generated build output, and test artifacts unless
  the task specifically requires inspecting them.
- Prefer targeted searches over reading large files in full.

## Validation conventions

Use the narrowest relevant validation first.

Typical checks:

- PHP syntax: `php -l <file>`
- PHP formatting check: `vendor/bin/pint --test`
- Laravel tests: `php artisan test`
- Frontend type checking: `npm run typecheck`
- Browser-test type checking: `npm run typecheck:e2e`
- Frontend linting: `npm run lint`
- Production build: `npm run build`
- Git whitespace check: `git diff --check`

Do not run destructive or state-changing commands merely for inspection or validation.

## Commands requiring care

Do not assume approval for commands that alter repository, dependency, database,
container, or filesystem state.

Examples include:

- `git add`
- `git commit`
- `git push`
- `git reset`
- `git restore`
- `git clean`
- `git rm`
- `composer require`
- `composer update`
- `npm install`
- `npm uninstall`
- `php artisan migrate`
- `php artisan migrate:fresh`
- `php artisan db:seed`
- `php artisan tinker`
- `vendor/bin/pint` without `--test`
- `docker compose up`
- `docker compose down`
- `docker compose restart`
- `docker compose build`
- `docker compose exec`
- `docker compose run`
- `Remove-Item`
- `Move-Item`
- `Copy-Item`
- `Set-Content`
- `Out-File`

Prefer read-only alternatives when the task only requires investigation.

## Command composition

When multiple independent read-only checks are needed, prefer issuing them as separate
commands instead of creating one large PowerShell script.

This is especially important on Windows because Codex execution-policy matching is more
reliable for small, predictable commands than for compound `pwsh.exe -Command` scripts.

## General implementation behavior

- Preserve unrelated changes in the working tree.
- Do not revert or overwrite user changes unless explicitly instructed.
- Use existing project conventions before introducing new patterns.
- Keep controllers thin and place domain logic in appropriate services/domain classes.
- Do not silently change an established schema, authorization rule, tenancy rule, or
  documented implementation contract. Surface the conflict instead.
- Run focused tests for the area changed before running broader validation.
