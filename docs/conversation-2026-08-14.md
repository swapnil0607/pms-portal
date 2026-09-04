# PMS Update Handover — 14 August 2026

## Current change

Simplified multiple assignees:

- Select a person from the assignee dropdown.
- The person is added immediately as a removable chip.
- Click only the small `×` button to remove that person.

## Upload for the current change

- `app/Views/tasks/show.php`
- `app/Views/projects/show.php`
- `public/assets/js/app.js`

No phpMyAdmin / SQL update is required.

## Important deployment note

After FileZilla upload, refresh the PMS using `Ctrl + F5` to clear cached CSS/JavaScript.

## Recent related updates

- Project Report now uses real project links and excludes client-only rows.
- Project Report filtering and Excel export use project-aware results.
- Daily Log includes a Task step and saves a `task_id` when a task is selected.
- Multiple assignees use the additive `task_assignees` table. The SQL upgrade must be run once before that feature is deployed:
  `database/task_assignees_upgrade.sql`.
- Projects table was adjusted for lower-resolution screens by hiding secondary metrics rather than using a sticky Action column.

## Data safety

The functional and UI updates do not delete existing tasks, projects, time logs, users, or client data.
