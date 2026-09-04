# Projects, permissions, and timestamps update

Date: 13 August 2026 (Asia/Kolkata)

## Changes requested

1. Improve the Projects list presentation and add project-name search with suggestions.
2. Allow an administrator to grant selected users access to the Project Action controls.
3. Correct Task Created/Updated time display from UTC to Asia/Kolkata.
4. Preserve live data.

## Implementation

### Projects list

- Added a search field above the table.
- Suggestions appear as a project name is typed; selecting one filters the table.
- Search is client-side only and never writes data.
- Updated project-name styling with a colour marker and a small hover navigation cue.

### Allow project actions checkbox

- New User and Admin Edit User screens now include **Allow project actions**.
- This grants edit, archive, unarchive, and inline project updates. Creating or permanently deleting a project remains restricted to admins and managers.
- The permission is stored in the existing `users.permissions` JSON value under `project_actions`; no schema change or phpMyAdmin query is required.
- Existing admins and managers retain their current access.
- The permission is enforced at the routes as well as in the UI.

### Task timestamp display

- Task Created and Updated values are interpreted as UTC database timestamps and displayed in `Asia/Kolkata`.
- No database value is updated. Example: `2026-08-11 10:33:00` becomes `11 Aug 2026, 04:03 PM`.

## Files to upload

```text
app/Core/Permissions.php
app/Views/projects/index.php
app/Views/users/create.php
app/Views/users/edit.php
app/Views/tasks/show.php
app/bootstrap.php
public/assets/css/app.css
public/assets/js/app.js
public/index.php
```

## Deployment note

After an administrator ticks **Allow project actions** and saves a user, that person should sign out and sign back in once so their session receives the new permission.

## Data safety

No project, task, work log, client, or user data is edited by these code changes. The only data change occurs when an administrator explicitly ticks or clears the new user permission and saves that user.

## Shared stylesheet recovery

The raw/unformatted task page reported after deployment was caused by the shared stylesheet not applying, not by damaged task data or a broken task template. All authenticated pages use `app/Views/layout.php`, which loads the same application stylesheet.

`asset_url()` now serves files stored in `public/assets/` through the stable `/assets/` URL route defined by `.htaccess`, rather than exposing `/public/assets/` in browser URLs. This applies uniformly to CSS, JavaScript, the logo, and avatar files. Upload `app/bootstrap.php` with the other current updates, then hard-refresh the browser once (`Ctrl + F5`).
