# facciamoiconti - Project instructions

Framework: Laravel 8
PHP: 7.4
Environment: local XAMPP, deploy on GoogieHost
Frontend: Sneat Bootstrap 5 free - Analytics Dashboard
Templates: Blade

## Rules
- Do not upgrade Laravel.
- Do not upgrade PHP.
- Keep PHP 7.4 compatibility.
- Use @section('scripts'), not @push('scripts').
- Do not introduce new packages unless explicitly requested.
- Do not change routes, DB schema, or public method names unless explicitly requested.
- Prefer small, reviewable changes.
- Optimize queries before refactoring structure.
- Avoid loading large collections in memory.
- Avoid duplicated CSS.
- Preserve existing Blade partials: navbar, sidebar, footer.

## Current priority
Analyze performance issues in Panel Users / PanelUsersController.

## Output expectations
Before changing code:
1. explain the problem found
2. indicate files and methods involved
3. propose a minimal fix
4. wait for approval before applying changes
