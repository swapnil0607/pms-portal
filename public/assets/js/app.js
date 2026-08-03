document.addEventListener('DOMContentLoaded', () => {
    initTimeRangeCalculators();

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const appBasePath = document.querySelector('meta[name="app-base-path"]')?.content || '';

    document.querySelectorAll('[data-copy-link]').forEach((button) => {
        button.addEventListener('click', async () => {
            const url = `${window.location.origin}${button.dataset.copyLink}`;
            await navigator.clipboard.writeText(url);
        });
    });

    // Client -> Phase -> Module cascading: picking a client narrows the phase
    // list to that client's own phases, picking a phase narrows the module
    // list further. Falls back to the broader (unfiltered) set whenever the
    // sibling field's current text doesn't match a known value yet, so a
    // brand-new client/phase can still be typed freehand.
    function pmsAutosuggestSource(input) {
        const data = window.__pmsSuggestions || {};
        const key = input.dataset.autosuggest;

        if (key === 'clients') {
            return data.clients || [];
        }

        const hierarchy = data.hierarchy || {};
        const form = input.closest('form') || document;
        const clientInput = form.querySelector('[data-autosuggest="clients"]');
        const clientValue = clientInput ? clientInput.value.trim() : '';
        const clientNode = Object.prototype.hasOwnProperty.call(hierarchy, clientValue) ? hierarchy[clientValue] : null;

        if (key === 'phases') {
            if (clientNode) {
                return Object.keys(clientNode);
            }
            const all = new Set();
            Object.values(hierarchy).forEach((phases) => Object.keys(phases).forEach((p) => all.add(p)));
            return Array.from(all);
        }

        if (key === 'taskLists') {
            const phaseInput = form.querySelector('[data-autosuggest="phases"]');
            const phaseValue = phaseInput ? phaseInput.value.trim() : '';

            if (clientNode) {
                if (Object.prototype.hasOwnProperty.call(clientNode, phaseValue)) {
                    return clientNode[phaseValue];
                }
                const clientModules = new Set();
                Object.values(clientNode).forEach((modules) => modules.forEach((m) => clientModules.add(m)));
                return Array.from(clientModules);
            }

            const all = new Set();
            Object.values(hierarchy).forEach((phases) => Object.values(phases).forEach((modules) => modules.forEach((m) => all.add(m))));
            return Array.from(all);
        }

        return [];
    }

    document.querySelectorAll('[data-autosuggest]').forEach((input) => {
        const wrap = input.parentElement;
        if (wrap && getComputedStyle(wrap).position === 'static') {
            wrap.style.position = 'relative';
        }

        const list = document.createElement('ul');
        list.className = 'autosuggest-list';
        list.hidden = true;
        input.insertAdjacentElement('afterend', list);

        let activeIndex = -1;

        const setActive = (index) => {
            const items = Array.from(list.children);
            items.forEach((item, i) => item.classList.toggle('active', i === index));
            if (index >= 0 && items[index]) {
                items[index].scrollIntoView({ block: 'nearest' });
            }
            activeIndex = index;
        };

        const render = () => {
            const source = pmsAutosuggestSource(input);
            const query = input.value.trim().toLowerCase();
            const matches = (query === '' ? source : source.filter((value) => value.toLowerCase().includes(query))).slice(0, 20);
            list.innerHTML = '';
            activeIndex = -1;

            if (!matches.length) {
                list.hidden = true;
                return;
            }

            matches.forEach((value) => {
                const item = document.createElement('li');
                item.textContent = value;
                item.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    input.value = value;
                    list.hidden = true;
                    input.focus();
                });
                list.appendChild(item);
            });
            list.hidden = false;
        };

        input.addEventListener('focus', render);
        input.addEventListener('input', render);

        input.addEventListener('blur', () => {
            window.setTimeout(() => {
                list.hidden = true;
            }, 120);
        });

        input.addEventListener('keydown', (event) => {
            if (list.hidden) {
                return;
            }

            const items = Array.from(list.children);
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                setActive(Math.min(activeIndex + 1, items.length - 1));
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                setActive(Math.max(activeIndex - 1, 0));
            } else if (event.key === 'Enter' && activeIndex >= 0 && items[activeIndex]) {
                event.preventDefault();
                input.value = items[activeIndex].textContent || '';
                list.hidden = true;
            } else if (event.key === 'Escape') {
                list.hidden = true;
            }
        });
    });

    document.querySelectorAll('[data-workload]').forEach((panel) => {
        const form = panel.closest('form');
        const assigneeSelect = form?.querySelector('select[name="assigned_to"]');
        if (!form || !assigneeSelect) {
            return;
        }

        const startInput = form.querySelector('input[name="start_date"]');
        const dueInput = form.querySelector('input[name="due_date"]');
        const hoursInput = form.querySelector('input[name="estimated_hours"]');
        const excludeTaskId = panel.dataset.excludeTaskId || '';

        // Local-calendar-date formatting: toISOString() converts to UTC first,
        // which silently shifts the date by a day for any timezone ahead of
        // UTC (e.g. IST) once local midnight crosses into the previous UTC day.
        const toDateStr = (date) => {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        };

        const addDays = (dateStr, amount) => {
            const date = new Date(`${dateStr}T00:00:00`);
            date.setDate(date.getDate() + amount);
            return toDateStr(date);
        };

        const weekdaysInRange = (startStr, dueStr) => {
            let cursor = new Date(`${startStr}T00:00:00`);
            const end = new Date(`${dueStr}T00:00:00`);
            if (cursor > end) {
                cursor = new Date(end);
            }
            const days = [];
            while (cursor <= end) {
                const dow = cursor.getDay();
                if (dow !== 0 && dow !== 6) {
                    days.push(toDateStr(cursor));
                }
                cursor.setDate(cursor.getDate() + 1);
            }
            return days.length ? days : [dueStr];
        };

        const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (ch) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[ch]));

        let debounceTimer = null;
        let requestToken = 0;

        const render = async () => {
            const userId = assigneeSelect.value;
            if (!userId) {
                panel.hidden = true;
                panel.innerHTML = '';
                return;
            }

            const due = dueInput ? dueInput.value : '';
            const start = startInput ? startInput.value : '';
            const hours = parseFloat(hoursInput ? hoursInput.value : '') || 0;

            let windowStart;
            let windowEnd;
            let thisTaskDays = [];
            if (due) {
                windowStart = start || due;
                windowEnd = due;
                thisTaskDays = weekdaysInRange(windowStart, windowEnd);
            } else {
                windowStart = toDateStr(new Date());
                windowEnd = addDays(windowStart, 13);
            }

            const params = new URLSearchParams({ user_id: userId, from_date: windowStart, to_date: windowEnd });
            if (excludeTaskId) {
                params.set('exclude_task_id', excludeTaskId);
            }

            const token = ++requestToken;
            let existing = {};
            try {
                const resp = await fetch(`${appBasePath}/tasks/workload?${params.toString()}`, { credentials: 'same-origin' });
                existing = await resp.json();
            } catch (e) {
                existing = {};
            }
            if (token !== requestToken) {
                return;
            }

            const thisTaskPerDay = {};
            if (hours > 0 && thisTaskDays.length) {
                const perDay = hours / thisTaskDays.length;
                thisTaskDays.forEach((date) => {
                    thisTaskPerDay[date] = (thisTaskPerDay[date] || 0) + perDay;
                });
            }

            const allDates = Array.from(new Set([...Object.keys(existing), ...Object.keys(thisTaskPerDay)])).sort();
            if (!allDates.length) {
                panel.innerHTML = '<p class="workload-empty">No existing workload for this person in this window.</p>';
                panel.hidden = false;
                return;
            }

            const rows = allDates.map((date) => {
                const existingHours = existing[date] || 0;
                const addHours = thisTaskPerDay[date] || 0;
                const total = existingHours + addHours;
                const level = total > 10 ? 'over' : total > 8 ? 'warn' : 'ok';
                const weekday = new Date(`${date}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short' });
                return `<tr>
                    <td>${escapeHtml(date.slice(5))}</td>
                    <td class="muted">${escapeHtml(weekday)}</td>
                    <td>${existingHours ? escapeHtml(existingHours.toFixed(1)) : ''}</td>
                    <td>${addHours ? '+' + escapeHtml(addHours.toFixed(1)) : ''}</td>
                    <td class="workload-total ${level}">${escapeHtml(total.toFixed(1))}h</td>
                </tr>`;
            }).join('');

            panel.innerHTML = `
                <p class="workload-caption">Workload preview <span class="muted">(8h/day capacity)</span></p>
                <table class="workload-table">
                    <thead><tr><th>Date</th><th></th><th>Existing</th><th>+This task</th><th>Total</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            `;
            panel.hidden = false;
        };

        const scheduleRender = () => {
            window.clearTimeout(debounceTimer);
            debounceTimer = window.setTimeout(render, 300);
        };

        [assigneeSelect, startInput, dueInput, hoursInput].forEach((el) => {
            if (!el) {
                return;
            }
            el.addEventListener('change', scheduleRender);
            el.addEventListener('input', scheduleRender);
        });

        scheduleRender();
    });

    const rangeModeInput = document.querySelector('[data-range-mode-input]');
    if (rangeModeInput) {
        const form = rangeModeInput.closest('form');
        form.querySelectorAll('[data-range-mode-btn]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const mode = btn.dataset.rangeModeBtn;
                rangeModeInput.value = mode;
                form.querySelectorAll('[data-range-mode-btn]').forEach((b) => b.classList.toggle('active', b === btn));
                form.querySelectorAll('[data-range-field]').forEach((field) => {
                    field.hidden = field.dataset.rangeField !== mode;
                });
            });
        });
    }

    document.querySelectorAll('[data-role-select]').forEach((select) => {
        let defaults = {};
        try {
            defaults = JSON.parse(select.dataset.roleDefaults || '{}');
        } catch (e) {
            defaults = {};
        }

        const grid = select.closest('form')?.querySelector('.permission-grid');
        if (!grid) {
            return;
        }

        select.addEventListener('change', () => {
            const pages = defaults[select.value] || [];
            grid.querySelectorAll('input[type="checkbox"]').forEach((box) => {
                box.checked = pages.includes(box.value);
            });
        });
    });

    document.querySelectorAll('.color-field').forEach((field) => {
        const colorInput = field.querySelector('input[type="color"]');
        const clearCheckbox = field.querySelector('input[type="checkbox"]');
        if (!colorInput || !clearCheckbox) {
            return;
        }
        clearCheckbox.addEventListener('change', () => {
            colorInput.disabled = clearCheckbox.checked;
        });
    });

    document.querySelectorAll('.editable-cell').forEach((cell) => {
        cell.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            startInlineEdit(cell);
        });
    });

    const workspace = document.querySelector('[data-project-id]');
    if (workspace) {
        const projectId = workspace.dataset.projectId;
        const toolbar = document.querySelector('[data-selection-toolbar]');
        const selectedCount = document.querySelector('[data-selected-count]');
        const checkboxes = Array.from(document.querySelectorAll('[data-task-select]'));

        const updateSelection = () => {
            const count = checkboxes.filter((box) => box.checked).length;
            if (selectedCount) {
                selectedCount.textContent = String(count);
            }
            if (toolbar) {
                toolbar.hidden = count === 0;
            }
        };

        checkboxes.forEach((box) => box.addEventListener('change', updateSelection));
        document.querySelector('[data-clear-selection]')?.addEventListener('click', () => {
            checkboxes.forEach((box) => {
                box.checked = false;
            });
            updateSelection();
        });

        document.querySelectorAll('.row-menu').forEach((menu) => {
            menu.addEventListener('toggle', () => {
                const panel = menu.querySelector('.row-menu-panel');
                if (!panel) {
                    return;
                }

                if (!menu.open) {
                    panel.removeAttribute('style');
                    return;
                }

                document.querySelectorAll('.row-menu[open]').forEach((otherMenu) => {
                    if (otherMenu !== menu) {
                        otherMenu.open = false;
                    }
                });

                positionMenu(menu, panel);
            });
        });

        document.addEventListener('pointerdown', (event) => {
            const clickedInsideMenu = event.target.closest('.row-menu');
            if (clickedInsideMenu) {
                return;
            }

            document.querySelectorAll('.row-menu[open]').forEach((menu) => {
                menu.open = false;
            });
        });

        window.addEventListener('scroll', repositionOpenMenu, true);
        window.addEventListener('resize', repositionOpenMenu);

        document.querySelectorAll('[data-collapse]').forEach((button) => {
            button.addEventListener('click', () => {
                const row = button.closest('.hierarchy-row');
                const isCollapsed = row.classList.toggle('collapsed');

                if (button.dataset.collapse === 'phase') {
                    const phaseId = button.dataset.phaseId || '';
                    document.querySelectorAll(`[data-parent-phase="${cssEscape(phaseId)}"]`).forEach((child) => {
                        child.hidden = isCollapsed;
                    });
                    return;
                }

                if (button.dataset.collapse === 'task-list') {
                    const taskListId = button.dataset.taskListId || '';
                    document.querySelectorAll(`[data-parent-task-list="${cssEscape(taskListId)}"]`).forEach((child) => {
                        child.hidden = isCollapsed;
                    });
                }
            });
        });

        let dragged = null;

        document.querySelectorAll('[draggable="true"]').forEach((row) => {
            row.addEventListener('dragstart', (event) => {
                dragged = row;
                row.classList.add('dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', row.dataset.rowType || '');
            });

            row.addEventListener('dragend', () => {
                row.classList.remove('dragging');
                document.querySelectorAll('.drop-target').forEach((target) => target.classList.remove('drop-target'));
                dragged = null;
            });
        });

        document.querySelectorAll('[data-row-type="phase"], [data-row-type="task-list"], [data-row-type="task"]').forEach((target) => {
            target.addEventListener('dragover', (event) => {
                if (!dragged || dragged === target) {
                    return;
                }

                const canDropTask = dragged.dataset.rowType === 'task' && target.dataset.rowType === 'task-list';
                const canDropTaskBeforeTask = dragged.dataset.rowType === 'task' && target.dataset.rowType === 'task';
                const canDropList = dragged.dataset.rowType === 'task-list' && target.dataset.rowType === 'phase';
                const canDropListBeforeList = dragged.dataset.rowType === 'task-list' && target.dataset.rowType === 'task-list';
                const canDropPhaseBeforePhase = dragged.dataset.rowType === 'phase'
                    && target.dataset.rowType === 'phase'
                    && target.dataset.phaseId !== '0';

                if (canDropTask || canDropTaskBeforeTask || canDropList || canDropListBeforeList || canDropPhaseBeforePhase) {
                    event.preventDefault();
                    target.classList.add('drop-target');
                }
            });

            target.addEventListener('dragleave', () => {
                target.classList.remove('drop-target');
            });

            target.addEventListener('drop', async (event) => {
                event.preventDefault();
                target.classList.remove('drop-target');

                if (!dragged || dragged === target) {
                    return;
                }

                if (dragged.dataset.rowType === 'phase' && target.dataset.rowType === 'phase') {
                    await postMove('/projects/phases/move', {
                        project_id: projectId,
                        phase_id: dragged.dataset.phaseId,
                        before_phase_id: target.dataset.phaseId || '',
                    });
                    window.location.reload();
                }

                if (dragged.dataset.rowType === 'task' && target.dataset.rowType === 'task-list') {
                    await postMove('/tasks/move', {
                        project_id: projectId,
                        task_id: dragged.dataset.taskId,
                        phase_id: target.dataset.phaseId || '',
                        task_list_id: target.dataset.taskListId || '',
                    });
                    window.location.reload();
                }

                if (dragged.dataset.rowType === 'task' && target.dataset.rowType === 'task') {
                    await postMove('/tasks/move', {
                        project_id: projectId,
                        task_id: dragged.dataset.taskId,
                        phase_id: target.dataset.parentPhase || '',
                        task_list_id: target.dataset.parentTaskList || '',
                        before_task_id: target.dataset.taskId || '',
                    });
                    window.location.reload();
                }

                if (dragged.dataset.rowType === 'task-list' && target.dataset.rowType === 'phase') {
                    await postMove('/projects/task-lists/move', {
                        project_id: projectId,
                        task_list_id: dragged.dataset.taskListId,
                        phase_id: target.dataset.phaseId || '',
                    });
                    window.location.reload();
                }

                if (dragged.dataset.rowType === 'task-list' && target.dataset.rowType === 'task-list') {
                    await postMove('/projects/task-lists/move', {
                        project_id: projectId,
                        task_list_id: dragged.dataset.taskListId,
                        phase_id: target.dataset.phaseId || '',
                        before_task_list_id: target.dataset.taskListId || '',
                    });
                    window.location.reload();
                }
            });
        });

        function repositionOpenMenu() {
            const menu = document.querySelector('.row-menu[open]');
            const panel = menu?.querySelector('.row-menu-panel');
            if (menu && panel) {
                positionMenu(menu, panel);
            }
        }

        function positionMenu(menu, panel) {
            const summary = menu.querySelector('summary');
            if (!summary) {
                return;
            }

            panel.style.position = 'fixed';
            panel.style.zIndex = '9999';
            panel.style.visibility = 'hidden';
            panel.style.left = '0px';
            panel.style.top = '0px';

            const rect = summary.getBoundingClientRect();
            const panelRect = panel.getBoundingClientRect();
            const margin = 8;
            const maxLeft = window.innerWidth - panelRect.width - margin;
            const left = Math.max(margin, Math.min(rect.left, maxLeft));
            let top = rect.bottom + margin;

            if (top + panelRect.height > window.innerHeight - margin) {
                top = Math.max(margin, rect.top - panelRect.height - margin);
            }

            panel.style.left = `${left}px`;
            panel.style.top = `${top}px`;
            panel.style.visibility = 'visible';
        }
    }

    // Generic expandable-row toggle: works for any table using the
    // data-breakdown-toggle/data-target-parent + data-breakdown-row/
    // data-parent-id/data-row-id convention (Time Logs breakdown, Clients'
    // project list, ...). Scoped to each table individually so IDs never
    // need to be unique across the whole page, only within one table.
    document.querySelectorAll('table').forEach((table) => {
        const toggles = table.querySelectorAll('[data-breakdown-toggle]');
        if (!toggles.length) {
            return;
        }

        const collapseDescendants = (parentId) => {
            table.querySelectorAll(`[data-parent-id="${cssEscape(parentId)}"]`).forEach((child) => {
                child.hidden = true;
                const childToggle = child.querySelector('[data-breakdown-toggle]');
                if (childToggle) {
                    childToggle.setAttribute('aria-expanded', 'false');
                    childToggle.classList.remove('expanded');
                }
                collapseDescendants(child.dataset.rowId);
            });
        };

        toggles.forEach((toggle) => {
            toggle.addEventListener('click', () => {
                const children = table.querySelectorAll(`[data-parent-id="${cssEscape(toggle.dataset.targetParent)}"]`);
                if (!children.length) {
                    return;
                }

                const expanding = children[0].hidden;
                children.forEach((child) => {
                    child.hidden = !expanding;
                });
                if (!expanding) {
                    children.forEach((child) => collapseDescendants(child.dataset.rowId));
                }
                toggle.setAttribute('aria-expanded', String(expanding));
                toggle.classList.toggle('expanded', expanding);
            });
        });
    });

    const kanbanBoard = document.querySelector('[data-kanban-board]');
    if (kanbanBoard) {
        let draggedCard = null;

        const updateKanbanCount = (columnEl) => {
            if (!columnEl) {
                return;
            }
            const countEl = columnEl.querySelector('[data-column-count]');
            const emptyEl = columnEl.querySelector('.kanban-empty');
            const cardCount = columnEl.querySelectorAll('.kanban-card').length;
            if (countEl) {
                countEl.textContent = String(cardCount);
            }
            if (emptyEl) {
                emptyEl.hidden = cardCount > 0;
            }
        };

        let activeDropColumn = null;

        const clearDropTarget = () => {
            if (activeDropColumn) {
                activeDropColumn.classList.remove('drop-target');
                activeDropColumn = null;
            }
        };

        // Delegated on the board itself (rather than per-card/per-column) so
        // there is exactly one listener per event type and no gap in
        // coverage — wherever inside the board the cursor is when it drops,
        // this always sees it via bubbling.
        kanbanBoard.addEventListener('dragstart', (event) => {
            const card = event.target.closest('.kanban-card[draggable="true"]');
            if (!card) {
                return;
            }
            draggedCard = card;
            card.classList.add('dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.taskId || '');
        });

        kanbanBoard.addEventListener('dragend', (event) => {
            const card = event.target.closest('.kanban-card[draggable="true"]');
            if (card) {
                card.classList.remove('dragging');
            }
            clearDropTarget();
            draggedCard = null;
        });

        kanbanBoard.addEventListener('dragover', (event) => {
            if (!draggedCard) {
                return;
            }
            const column = event.target.closest('.kanban-column');
            if (!column) {
                return;
            }
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            if (activeDropColumn !== column) {
                clearDropTarget();
                activeDropColumn = column;
                column.classList.add('drop-target');
            }
        });

        kanbanBoard.addEventListener('drop', async (event) => {
            const column = event.target.closest('.kanban-column');
            if (!column) {
                return;
            }
            event.preventDefault();
            clearDropTarget();

            // Capture the card now: the browser fires 'dragend' right after
            // 'drop' regardless of how long this async handler takes, and
            // that resets the shared draggedCard to null while we're still
            // awaiting the request below. Without this local copy, the DOM
            // update after the await silently fails (the server-side status
            // change still lands, so a refresh shows it — just not live).
            const card = draggedCard;
            if (!card) {
                return;
            }

            const sourceColumn = card.closest('.kanban-column');
            const status = column.dataset.status || '';
            if (!sourceColumn || sourceColumn === column) {
                return;
            }

            await postMove('/tasks/status', {
                task_id: card.dataset.taskId,
                project_id: card.dataset.kanbanProjectId,
                status,
            });

            const stack = column.querySelector('.kanban-stack');
            stack.insertBefore(card, stack.querySelector('.kanban-empty'));
            const statusSelect = card.querySelector('.kanban-move select[name="status"]');
            if (statusSelect) {
                statusSelect.value = status;
            }
            updateKanbanCount(sourceColumn);
            updateKanbanCount(column);
        });
    }

    async function postMove(url, fields) {
        const data = new FormData();
        data.append('_csrf', csrf);
        data.append('ajax', '1');
        Object.entries(fields).forEach(([key, value]) => data.append(key, value ?? ''));
        await fetch(`${appBasePath}${url}`, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
        });
    }

    function startInlineEdit(cell) {
        if (cell.dataset.editing === '1') {
            return;
        }

        cell.dataset.editing = '1';
        const originalText = cell.textContent.trim();
        const originalValue = cell.dataset.value ?? (originalText === '-' ? '' : originalText);
        const inputType = cell.dataset.inputType || 'text';
        const editor = inputType === 'priority' ? document.createElement('select') : document.createElement('input');

        if (inputType === 'priority') {
            ['low', 'medium', 'high', 'critical'].forEach((priority) => {
                const option = document.createElement('option');
                option.value = priority;
                option.textContent = priority;
                option.selected = priority === originalValue;
                editor.appendChild(option);
            });
        } else {
            editor.type = inputType === 'number' ? 'number' : inputType === 'date' ? 'date' : 'text';
            editor.value = originalValue;
            if (inputType === 'number') {
                editor.min = '0';
                editor.step = cell.dataset.step || '0.01';
            }
        }

        editor.className = 'inline-editor';
        cell.textContent = '';
        cell.appendChild(editor);
        editor.focus();
        if (editor.select) {
            editor.select();
        }

        let finished = false;

        const commit = async () => {
            if (finished) {
                return;
            }
            finished = true;
            const value = editor.value.trim();
            await saveInlineEdit(cell, value);
            cell.dataset.value = value;
            cell.dataset.editing = '0';
            cell.textContent = value || '-';
            if (cell.dataset.field === 'priority') {
                cell.className = `priority ${value} editable-cell`;
            }
        };

        const cancel = () => {
            if (finished) {
                return;
            }
            finished = true;
            cell.dataset.editing = '0';
            cell.textContent = originalText;
        };

        editor.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                commit();
            }
            if (event.key === 'Escape') {
                event.preventDefault();
                cancel();
            }
        });
        editor.addEventListener('blur', commit);
        if (editor.tagName === 'SELECT') {
            editor.addEventListener('change', commit);
        }
    }

    async function saveInlineEdit(cell, value) {
        const type = cell.dataset.editType;
        if (type === 'phase-name') {
            return postMove('/projects/phases/update-name', {
                project_id: cell.dataset.projectId,
                phase_id: cell.dataset.phaseId,
                name: value,
            });
        }
        if (type === 'task-list-name') {
            return postMove('/projects/task-lists/update-name', {
                project_id: cell.dataset.projectId,
                task_list_id: cell.dataset.taskListId,
                name: value,
            });
        }
        if (type === 'task-field') {
            return postMove('/tasks/quick-update', {
                project_id: cell.dataset.projectId,
                task_id: cell.dataset.taskId,
                field: cell.dataset.field,
                value,
            });
        }
        if (type === 'project-field') {
            return postMove('/projects/quick-update', {
                project_id: cell.dataset.projectId,
                field: cell.dataset.field,
                value,
            });
        }
        if (type === 'project-custom-field') {
            return postMove('/projects/quick-update', {
                project_id: cell.dataset.projectId,
                field: `custom:${cell.dataset.field}`,
                value,
            });
        }
    }

    function cssEscape(value) {
        return String(value).replace(/["\\]/g, '\\$&');
    }

    function initTimeRangeCalculators() {
        document.querySelectorAll('form').forEach((form) => {
            const from = form.querySelector('[data-time-from]');
            const to = form.querySelector('[data-time-to]');
            const hours = form.querySelector('[data-hours-input]');
            if (!from || !to || !hours) {
                return;
            }

            const updateHours = () => {
                if (!from.value || !to.value) {
                    return;
                }

                const fromMinutes = minutesFromTime(from.value);
                let toMinutes = minutesFromTime(to.value);
                if (fromMinutes === null || toMinutes === null) {
                    return;
                }
                if (toMinutes < fromMinutes) {
                    toMinutes += 24 * 60;
                }

                const decimalHours = (toMinutes - fromMinutes) / 60;
                hours.value = decimalHours > 0 ? decimalHours.toFixed(2).replace(/\.00$/, '') : '';
            };

            from.addEventListener('change', updateHours);
            to.addEventListener('change', updateHours);
        });
    }

    function minutesFromTime(value) {
        const parts = String(value).split(':').map(Number);
        if (parts.length < 2 || Number.isNaN(parts[0]) || Number.isNaN(parts[1])) {
            return null;
        }
        return parts[0] * 60 + parts[1];
    }
});
