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

        if (key === 'projectsOnly') {
            const form = input.closest('form') || document;
            const clientSelect = form.querySelector('select[name="client_id"]') || document.querySelector('#report_filter_client_id');
            const selectedClientId = clientSelect ? clientSelect.value.trim() : '';
            const allProjects = window.__pmsAllProjects || [];

            if (selectedClientId !== '' && Array.isArray(allProjects) && allProjects.length > 0) {
                const clientProjects = allProjects
                    .filter((p) => String(p.client_id) === String(selectedClientId))
                    .map((p) => p.name);
                if (clientProjects.length > 0) {
                    return clientProjects;
                }
            }
            return data.projectsOnly || (Array.isArray(allProjects) && allProjects.length > 0 ? allProjects.map((p) => p.name) : []);
        }

        if (key === 'clientsOnly') {
            return data[key] || [];
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
                    input.dispatchEvent(new Event('change', { bubbles: true }));
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
                input.dispatchEvent(new Event('change', { bubbles: true }));
            } else if (event.key === 'Escape') {
                list.hidden = true;
            }
        });
    });

    // The Daily Log needs a real Task, not just free-text activity. This
    // picker narrows tasks using the selected Project -> Phase -> Task List
    // Daily Log Task Autosuggest: instant type-to-search tasks with intelligent filtering
    // and writes the real task id to the hidden field submitted with the log.
    document.querySelectorAll('[data-task-autosuggest]').forEach((input) => {
        const form = input.closest('form');
        const taskIdInput = form?.querySelector('[data-task-id]');
        if (!form || !taskIdInput) {
            return;
        }

        const wrap = input.parentElement;
        if (wrap) {
            wrap.style.position = 'relative';
        }

        const projectInput = form.querySelector('input[name="project_group"]');
        const phaseInput = form.querySelector('input[name="phase"]');
        const taskListInput = form.querySelector('input[name="module_name"]');
        const list = document.createElement('ul');
        list.className = 'autosuggest-list';
        list.hidden = true;
        input.insertAdjacentElement('afterend', list);

        const resetSelection = () => {
            const curTaskId = taskIdInput.value;
            const data = window.__pmsSuggestions || {};
            const allTasks = data.tasks || [];
            const project = (projectInput?.value || '').trim().toLowerCase();
            const phase = (phaseInput?.value || '').trim().toLowerCase();
            const taskList = (taskListInput?.value || '').trim().toLowerCase();

            if (curTaskId) {
                const found = allTasks.find((t) => String(t.id) === String(curTaskId));
                if (found) {
                    const tProj = (found.project || '').toLowerCase().trim();
                    const tPhase = (found.phase || '').toLowerCase().trim();
                    const tList = (found.task_list || '').toLowerCase().trim();

                    if ((project && !tProj.includes(project) && !project.includes(tProj)) ||
                        (phase && !tPhase.includes(phase) && !phase.includes(tPhase)) ||
                        (taskList && !tList.includes(taskList) && !taskList.includes(tList))) {
                        taskIdInput.value = '';
                        input.value = '';
                    }
                } else {
                    taskIdInput.value = '';
                }
            }
        };

        const getLocalMatches = () => {
            const data = window.__pmsSuggestions || {};
            const allTasks = data.tasks || [];
            const project = (projectInput?.value || '').trim().toLowerCase();
            const phase = (phaseInput?.value || '').trim().toLowerCase();
            const taskList = (taskListInput?.value || '').trim().toLowerCase();
            const query = (input.value || '').trim().toLowerCase();

            let matches = allTasks.filter((t) => {
                const tProj = (t.project || '').toLowerCase().trim();
                const tPhase = (t.phase || '').toLowerCase().trim();
                const tList = (t.task_list || '').toLowerCase().trim();
                const tTitle = (t.title || '').toLowerCase().trim();

                if (project && !tProj.includes(project) && !project.includes(tProj)) {
                    return false;
                }
                if (phase && !tPhase.includes(phase) && !phase.includes(tPhase)) {
                    return false;
                }
                if (taskList && !tList.includes(taskList) && !taskList.includes(tList)) {
                    return false;
                }
                if (query && !tTitle.includes(query)) {
                    return false;
                }
                return true;
            });

            matches.sort((a, b) => (a.title || '').localeCompare(b.title || ''));

            return matches.slice(0, 30);
        };

        const renderList = (matches) => {
            list.innerHTML = '';
            if (!matches || !matches.length) {
                list.hidden = true;
                return;
            }

            matches.forEach((task) => {
                const item = document.createElement('li');
                const projPrefix = task.project ? `${task.project} › ` : '';
                item.innerHTML = `<strong>${task.title}</strong><small style="display:block; font-size:11.5px; color:#64748b; margin-top:2px;">📁 ${projPrefix}${task.phase} / ${task.task_list}</small>`;
                
                const selectTask = (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    input.value = task.title;
                    taskIdInput.value = task.id;
                    if (task.project && projectInput && (!projectInput.value || projectInput.value !== task.project)) {
                        projectInput.value = task.project;
                    }
                    if (task.phase && phaseInput && task.phase !== 'No Phase') {
                        phaseInput.value = task.phase;
                    }
                    if (task.task_list && taskListInput && task.task_list !== 'General') {
                        taskListInput.value = task.task_list;
                    }
                    list.hidden = true;
                    input.focus();
                };

                item.addEventListener('mousedown', selectTask);
                item.addEventListener('touchstart', selectTask, { passive: false });
                list.appendChild(item);
            });
            list.hidden = false;
        };

        const render = async () => {
            const localMatches = getLocalMatches();
            if (localMatches.length > 0) {
                renderList(localMatches);
                return;
            }

            // Fallback to server search if local suggestions didn't have it
            const project = projectInput?.value.trim() || '';
            const phase = phaseInput?.value.trim() || '';
            const taskList = taskListInput?.value.trim() || '';
            const query = input.value.trim();
            const params = new URLSearchParams({ project, phase, task_list: taskList, q: query });
            try {
                const response = await fetch(`${appBasePath}/tasks/daily-log-search?${params.toString()}`, { credentials: 'same-origin' });
                if (response.ok) {
                    const serverMatches = await response.json();
                    renderList(serverMatches);
                    return;
                }
            } catch (error) {}
            list.hidden = true;
        };

        [projectInput, phaseInput, taskListInput].forEach((field) => {
            field?.addEventListener('input', () => {
                resetSelection();
                if (document.activeElement === input) {
                    render();
                }
            });
            field?.addEventListener('change', () => {
                resetSelection();
                if (document.activeElement === input) {
                    render();
                }
            });
        });

        input.addEventListener('focus', render);
        input.addEventListener('input', () => {
            resetSelection();
            render();
        });

        form.addEventListener('submit', (event) => {
            if (!taskIdInput.value) {
                event.preventDefault();
                input.setCustomValidity('Please choose a task from the suggestion list.');
                input.reportValidity();
            }
        });

        input.addEventListener('blur', () => window.setTimeout(() => { list.hidden = true; }, 200));
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                list.hidden = true;
            }
        });
    });

    // Native Task selector fallback: works without any network request and
    // narrows the visible choices to the selected hierarchy when JavaScript
    // is available. The full list remains usable if JavaScript is blocked.
    document.querySelectorAll('[data-daily-log-task-select]').forEach((select) => {
        const form = select.closest('form');
        const projectInput = form?.querySelector('input[name="project_group"]');
        const phaseInput = form?.querySelector('input[name="phase"]');
        const taskListInput = form?.querySelector('input[name="module_name"]');
        if (!form) {
            return;
        }

        const filterOptions = () => {
            const project = projectInput?.value.trim() || '';
            const phase = phaseInput?.value.trim() || '';
            const taskList = taskListInput?.value.trim() || '';
            Array.from(select.options).forEach((option) => {
                if (!option.value) {
                    return;
                }
                const matchesProject = !project || option.dataset.project === project;
                const matchesPhase = !phase || option.dataset.phase === phase;
                const matchesList = !taskList || option.dataset.taskList === taskList;
                option.hidden = !(matchesProject && matchesPhase && matchesList);
            });
            if (select.selectedOptions[0]?.hidden) {
                select.value = '';
            }
        };

        [projectInput, phaseInput, taskListInput].forEach((field) => {
            field?.addEventListener('input', filterOptions);
            field?.addEventListener('change', filterOptions);
        });
        filterOptions();
    });

    document.querySelectorAll('[data-assignee-picker]').forEach((picker) => {
        const chips = picker.querySelector('[data-assignee-chips]');
        const select = picker.querySelector('[data-assignee-picker-select]');
        const empty = picker.querySelector('[data-assignee-empty]');
        if (!chips || !select) {
            return;
        }

        const setOptionState = (id, disabled) => {
            const option = Array.from(select.options).find((item) => item.value === id);
            if (option) {
                option.disabled = disabled;
            }
        };

        const refreshEmptyState = () => {
            const hasPeople = chips.querySelector('[data-assignee-id]') !== null;
            if (empty) {
                empty.hidden = hasPeople;
            }
        };

        const removeChip = (chip) => {
            setOptionState(chip.dataset.assigneeId || '', false);
            chip.remove();
            refreshEmptyState();
            picker.dispatchEvent(new CustomEvent('assigneeschange', { bubbles: true }));
        };

        chips.querySelectorAll('[data-assignee-id]').forEach((chip) => {
            setOptionState(chip.dataset.assigneeId || '', true);
            chip.querySelector('[data-remove-assignee]')?.addEventListener('click', () => removeChip(chip));
        });

        const addSelectedPerson = () => {
            const id = select.value;
            const option = select.options[select.selectedIndex];
            if (!id || !option || chips.querySelector(`[data-assignee-id="${id}"]`)) {
                return;
            }

            const chip = document.createElement('span');
            chip.className = 'assignee-chip';
            chip.dataset.assigneeId = id;
            chip.append(document.createTextNode(option.text));

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'assignee_ids[]';
            input.value = id;
            chip.append(input);

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'assignee-chip-remove';
            remove.dataset.removeAssignee = '';
            remove.setAttribute('aria-label', `Remove ${option.text}`);
            remove.addEventListener('click', () => removeChip(chip));
            remove.textContent = '\u00d7';
            chip.append(remove);

            chips.append(chip);
            setOptionState(id, true);
            select.value = '';
            refreshEmptyState();
            picker.dispatchEvent(new CustomEvent('assigneeschange', { bubbles: true }));
        };

        select.addEventListener('change', addSelectedPerson);

        refreshEmptyState();
    });

    document.querySelectorAll('[data-workload]').forEach((panel) => {
        const form = panel.closest('form');
        const assigneeSelect = form?.querySelector('[data-assignee-picker-select], select[name="assigned_to"]');
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
            // A task may have several people. Preview the first added
            // person's existing workload; the task itself saves all people.
            const userId = form.querySelector('input[name="assignee_ids[]"]')?.value || assigneeSelect.value;
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
        form.addEventListener('assigneeschange', scheduleRender);

        scheduleRender();
    });

    document.querySelectorAll('[data-month-step]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const form = btn.closest('form');
            const input = form?.querySelector('[data-month-input]');
            if (!input || !input.value) {
                return;
            }
            const [year, month] = input.value.split('-').map(Number);
            const stepped = new Date(year, month - 1 + Number(btn.dataset.monthStep), 1);
            const yyyy = stepped.getFullYear();
            const mm = String(stepped.getMonth() + 1).padStart(2, '0');
            input.value = `${yyyy}-${mm}`;
            form.submit();
        });
    });

    document.querySelectorAll('[data-sortable-table]').forEach((table) => {
        const headerRow = table.querySelector('thead tr');
        const tbody = table.querySelector('tbody');
        if (!headerRow || !tbody) {
            return;
        }
        const headers = Array.from(headerRow.children);

        // Prefer an inline editable-cell's raw data-value (e.g. "1500.5", not
        // "1,500.50"); otherwise fall back to the cell's own text. Blank/"-"
        // cells sort as null so they can always be pushed to the bottom.
        const sortValueOf = (td) => {
            const valueEl = td?.querySelector('[data-value]');
            const raw = valueEl ? valueEl.dataset.value : (td?.textContent ?? '');
            const text = raw.trim();
            return text === '' || text === '-' ? null : text;
        };

        const compare = (a, b, type = 'text') => {
            if (type === 'number') {
                const na = parseFloat(a.replace(/,/g, '').replace(/%$/, ''));
                const nb = parseFloat(b.replace(/,/g, '').replace(/%$/, ''));
                if (!Number.isNaN(na) && !Number.isNaN(nb)) {
                    return na - nb;
                }
            }
            if (type === 'date' || (/^\d{4}-\d{2}-\d{2}/.test(a) && /^\d{4}-\d{2}-\d{2}/.test(b))) {
                const da = Date.parse(a);
                const db = Date.parse(b);
                if (!Number.isNaN(da) && !Number.isNaN(db)) {
                    return da - db;
                }
            }
            return a.localeCompare(b);
        };

        headers.forEach((th) => {
            if (th.hasAttribute('data-no-sort')) {
                return;
            }
            th.classList.add('sortable-col');
            th.addEventListener('click', (event) => {
                if (event.target.closest('.column-resizer')) {
                    return;
                }
                const currentHeaders = Array.from(headerRow.children);
                const index = currentHeaders.indexOf(th);
                if (index < 0) {
                    return;
                }
                const dir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';
                currentHeaders.forEach((h) => {
                    delete h.dataset.sortDir;
                    h.classList.remove('sorted-asc', 'sorted-desc');
                });
                th.dataset.sortDir = dir;
                th.classList.add(dir === 'asc' ? 'sorted-asc' : 'sorted-desc');

                const rows = Array.from(tbody.children);
                rows.sort((rowA, rowB) => {
                    const va = sortValueOf(rowA.children[index]);
                    const vb = sortValueOf(rowB.children[index]);
                    if (va === null || vb === null) {
                        if (va === vb) {
                            return 0;
                        }
                        return va === null ? 1 : -1;
                    }
                    const cmp = compare(va, vb, th.dataset.columnType || 'text');
                    return dir === 'asc' ? cmp : -cmp;
                });
                rows.forEach((row) => tbody.appendChild(row));
            });
        });
    });

    document.querySelectorAll('[data-project-column-manager]').forEach((table) => {
        const headerRow = table.querySelector('thead tr');
        const tbody = table.querySelector('tbody');
        if (!headerRow || !tbody) {
            return;
        }

        const storageKey = 'eduriser-pms-project-columns-v1';
        const headers = () => Array.from(headerRow.querySelectorAll('th[data-column-id]'));
        const orderCells = (ids) => {
            const order = new Map(ids.map((id, index) => [id, index]));
            [headerRow, ...tbody.querySelectorAll('tr')].forEach((row) => {
                Array.from(row.children)
                    .sort((a, b) => (order.get(a.dataset.columnId) ?? 999) - (order.get(b.dataset.columnId) ?? 999))
                    .forEach((cell) => row.appendChild(cell));
            });
        };
        const save = () => {
            const widths = {};
            headers().forEach((header) => {
                widths[header.dataset.columnId] = header.style.width || '';
            });
            localStorage.setItem(storageKey, JSON.stringify({ order: headers().map((header) => header.dataset.columnId), widths }));
        };

        // Tag data cells from their matching header so headers and every row
        // always move together when users rearrange columns.
        const initialHeaders = Array.from(headerRow.children);
        [headerRow, ...tbody.querySelectorAll('tr')].forEach((row) => {
            Array.from(row.children).forEach((cell, index) => {
                cell.dataset.columnId = initialHeaders[index]?.dataset.columnId || `column-${index}`;
            });
        });

        try {
            const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
            const available = headers().map((header) => header.dataset.columnId);
            if (Array.isArray(saved.order)) {
                const order = [...saved.order.filter((id) => available.includes(id)), ...available.filter((id) => !saved.order.includes(id))];
                orderCells(order);
            }
            if (saved.widths && typeof saved.widths === 'object') {
                headers().forEach((header) => {
                    const width = saved.widths[header.dataset.columnId];
                    if (width) {
                        header.style.width = width;
                    }
                });
            }
        } catch (error) {
            // A blocked or malformed browser storage entry should never break the table.
        }

        document.querySelector('[data-reset-project-columns]')?.addEventListener('click', () => {
            localStorage.removeItem(storageKey);
            window.location.reload();
        });

        let draggedHeader = null;
        headers().forEach((header) => {
            header.draggable = true;
            header.addEventListener('dragstart', (event) => {
                if (event.target.closest('.column-resizer')) {
                    event.preventDefault();
                    return;
                }
                draggedHeader = header;
                header.classList.add('column-dragging');
                event.dataTransfer.effectAllowed = 'move';
            });
            header.addEventListener('dragend', () => {
                headers().forEach((item) => item.classList.remove('column-dragging', 'column-drop-target'));
                draggedHeader = null;
            });
            header.addEventListener('dragover', (event) => {
                if (!draggedHeader || draggedHeader === header) return;
                event.preventDefault();
                header.classList.add('column-drop-target');
            });
            header.addEventListener('dragleave', () => header.classList.remove('column-drop-target'));
            header.addEventListener('drop', (event) => {
                event.preventDefault();
                if (!draggedHeader || draggedHeader === header) return;
                const allHeaders = headers();
                const draggedIndex = allHeaders.indexOf(draggedHeader);
                const targetIndex = allHeaders.indexOf(header);
                const newOrder = allHeaders.map((item) => item.dataset.columnId);
                newOrder.splice(draggedIndex, 1);
                newOrder.splice(targetIndex + (draggedIndex < targetIndex ? 0 : 0), 0, draggedHeader.dataset.columnId);
                orderCells(newOrder);
                save();
            });

            const resizer = document.createElement('span');
            resizer.className = 'column-resizer';
            resizer.setAttribute('aria-hidden', 'true');
            header.appendChild(resizer);
            resizer.addEventListener('mousedown', (event) => {
                event.preventDefault();
                event.stopPropagation();
                const startX = event.clientX;
                const startWidth = header.getBoundingClientRect().width;
                const resize = (moveEvent) => {
                    header.style.width = `${Math.max(80, startWidth + moveEvent.clientX - startX)}px`;
                    table.style.width = 'max-content';
                };
                const stopResize = () => {
                    document.removeEventListener('mousemove', resize);
                    document.removeEventListener('mouseup', stopResize);
                    save();
                };
                document.addEventListener('mousemove', resize);
                document.addEventListener('mouseup', stopResize);
            });
        });
    });

    document.querySelectorAll('[data-role-select]').forEach((select) => {
        let defaults = {};
        let rightsDefaults = {};
        try {
            defaults = JSON.parse(select.dataset.roleDefaults || '{}');
            rightsDefaults = JSON.parse(select.dataset.roleRightsDefaults || '{}');
        } catch (e) {
            defaults = {};
            rightsDefaults = {};
        }

        const form = select.closest('form');
        if (!form) {
            return;
        }

        select.addEventListener('change', () => {
            const pages = defaults[select.value] || [];
            form.querySelectorAll('input[name="pages[]"]').forEach((box) => {
                box.checked = pages.includes(box.value);
            });

            const rights = rightsDefaults[select.value] || (select.value === 'viewer' ? ['read', 'export'] : ['read', 'write', 'export']);
            form.querySelectorAll('input[name="rights[]"]').forEach((box) => {
                box.checked = rights.includes(box.value);
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

        // Individual Collapse Toggles
        document.querySelectorAll('[data-collapse]').forEach((button) => {
            button.addEventListener('click', () => {
                const row = button.closest('.hierarchy-row');
                const isCollapsed = row.classList.toggle('collapsed');

                if (button.dataset.collapse === 'phase') {
                    const phaseId = button.dataset.phaseId || '';
                    document.querySelectorAll(`[data-parent-phase="${cssEscape(phaseId)}"]`).forEach((child) => {
                        if (isCollapsed) {
                            child.hidden = true;
                        } else {
                            if (child.dataset.rowType === 'task-list') {
                                child.hidden = false;
                            } else if (child.dataset.rowType === 'task') {
                                const taskListId = child.dataset.parentTaskList || '';
                                const listRow = document.querySelector(`.task-list-row[data-task-list-id="${cssEscape(taskListId)}"]`);
                                if (!listRow || !listRow.classList.contains('collapsed')) {
                                    child.hidden = false;
                                }
                            } else {
                                child.hidden = false;
                            }
                        }
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

        // 1-Click: Collapse Tasks (Show Phase & Task Lists only)
        const btnCollapseTasks = document.getElementById('btnCollapseTasks');
        if (btnCollapseTasks) {
            btnCollapseTasks.addEventListener('click', () => {
                // Ensure all Phases are open and visible
                document.querySelectorAll('.hierarchy-row[data-row-type="phase"]').forEach(row => {
                    row.classList.remove('collapsed');
                    row.hidden = false;
                });
                // Ensure all Task Lists are visible, but marked collapsed
                document.querySelectorAll('.hierarchy-row[data-row-type="task-list"]').forEach(row => {
                    row.classList.add('collapsed');
                    row.hidden = false;
                });
                // Hide all individual tasks and placeholder rows
                document.querySelectorAll('.task-data-row, [data-parent-task-list]').forEach(child => {
                    child.hidden = true;
                });
                // Mobile view
                document.querySelectorAll('.mobile-phase-card').forEach(card => {
                    card.classList.remove('collapsed');
                });
            });
        }

        // 1-Click: Collapse Phases (Show Phase headers only)
        const btnCollapsePhases = document.getElementById('btnCollapsePhases');
        if (btnCollapsePhases) {
            btnCollapsePhases.addEventListener('click', () => {
                document.querySelectorAll('.hierarchy-row[data-row-type="phase"]').forEach(row => {
                    row.classList.add('collapsed');
                });
                document.querySelectorAll('[data-parent-phase]').forEach(child => {
                    child.hidden = true;
                });
                // Mobile view
                document.querySelectorAll('.mobile-phase-card').forEach(card => {
                    card.classList.add('collapsed');
                });
            });
        }

        // 1-Click: Expand All (Show Everything)
        const btnExpandAll = document.getElementById('btnExpandAll');
        if (btnExpandAll) {
            btnExpandAll.addEventListener('click', () => {
                document.querySelectorAll('.hierarchy-row[data-row-type="phase"], .hierarchy-row[data-row-type="task-list"]').forEach(row => {
                    row.classList.remove('collapsed');
                    row.hidden = false;
                });
                document.querySelectorAll('[data-parent-phase], [data-parent-task-list]').forEach(child => {
                    child.hidden = false;
                });
                document.querySelectorAll('.mobile-phase-card').forEach(card => {
                    card.classList.remove('collapsed');
                });
            });
        }

        // ==========================================
        // Smooth Glitch-Free Drag & Drop Controller
        // ==========================================
        let draggedRow = null;
        let dragType = null;

        // Prevent accidental drag when clicking inputs, buttons, editable cells or links
        document.querySelectorAll('.hierarchy-row').forEach(row => {
            row.addEventListener('dragstart', (e) => {
                if (e.target.closest('input, select, button, a, .editable-cell') && !e.target.closest('.drag-handle')) {
                    e.preventDefault();
                    return;
                }

                draggedRow = row;
                dragType = row.dataset.rowType;
                row.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', dragType);
            });

            row.addEventListener('dragend', () => {
                row.classList.remove('dragging');
                document.querySelectorAll('.drop-above, .drop-below, .drop-inside, .drop-target').forEach(el => {
                    el.classList.remove('drop-above', 'drop-below', 'drop-inside', 'drop-target');
                });
                draggedRow = null;
                dragType = null;
            });

            row.addEventListener('dragover', (e) => {
                if (!draggedRow || draggedRow === row) return;

                const targetType = row.dataset.rowType;
                let canDrop = false;
                let dropMode = 'inside';

                const rect = row.getBoundingClientRect();
                const relY = (e.clientY - rect.top) / rect.height;
                const isTopHalf = relY < 0.5;

                // 1. Dragging a TASK
                if (dragType === 'task') {
                    if (targetType === 'task') {
                        canDrop = true;
                        dropMode = isTopHalf ? 'above' : 'below';
                    } else if (targetType === 'task-list') {
                        canDrop = true;
                        dropMode = 'inside';
                    }
                }

                // 2. Dragging a TASK LIST
                else if (dragType === 'task-list') {
                    if (targetType === 'task-list' && row.dataset.taskListId !== '0') {
                        canDrop = true;
                        dropMode = isTopHalf ? 'above' : 'below';
                    } else if (targetType === 'phase') {
                        canDrop = true;
                        dropMode = 'inside';
                    }
                }

                // 3. Dragging a PHASE
                else if (dragType === 'phase') {
                    if (targetType === 'phase' && row.dataset.phaseId !== '0' && draggedRow.dataset.phaseId !== '0') {
                        canDrop = true;
                        dropMode = isTopHalf ? 'above' : 'below';
                    }
                }

                if (canDrop) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';

                    document.querySelectorAll('.drop-above, .drop-below, .drop-inside, .drop-target').forEach(el => {
                        if (el !== row) el.classList.remove('drop-above', 'drop-below', 'drop-inside', 'drop-target');
                    });

                    row.classList.remove('drop-above', 'drop-below', 'drop-inside');
                    if (dropMode === 'above') row.classList.add('drop-above');
                    else if (dropMode === 'below') row.classList.add('drop-below');
                    else row.classList.add('drop-inside');
                }
            });

            row.addEventListener('dragleave', (e) => {
                if (!row.contains(e.relatedTarget)) {
                    row.classList.remove('drop-above', 'drop-below', 'drop-inside', 'drop-target');
                }
            });

            row.addEventListener('drop', async (e) => {
                e.preventDefault();
                e.stopPropagation();

                const target = row;
                const isAbove = target.classList.contains('drop-above');
                target.classList.remove('drop-above', 'drop-below', 'drop-inside', 'drop-target');

                if (!draggedRow || draggedRow === target) return;

                const targetType = target.dataset.rowType;

                // --- Scenario A: Dropping a Task on another Task (Reordering / Moving) ---
                if (dragType === 'task' && targetType === 'task') {
                    let beforeTaskId = '';
                    if (isAbove) {
                        beforeTaskId = target.dataset.taskId;
                    } else {
                        let nextRow = target.nextElementSibling;
                        if (nextRow && nextRow.dataset.rowType === 'task') {
                            beforeTaskId = nextRow.dataset.taskId || '';
                        }
                    }

                    const targetPhaseId = target.dataset.parentPhase || '';
                    const targetTaskListId = target.dataset.parentTaskList || '';

                    await postMove('/tasks/move', {
                        project_id: projectId,
                        task_id: draggedRow.dataset.taskId,
                        phase_id: targetPhaseId,
                        task_list_id: targetTaskListId,
                        before_task_id: beforeTaskId,
                    });
                    window.location.reload();
                }

                // --- Scenario B: Dropping a Task on a Task List Header ---
                else if (dragType === 'task' && targetType === 'task-list') {
                    await postMove('/tasks/move', {
                        project_id: projectId,
                        task_id: draggedRow.dataset.taskId,
                        phase_id: target.dataset.phaseId || '',
                        task_list_id: target.dataset.taskListId || '',
                    });
                    window.location.reload();
                }

                // --- Scenario C: Dropping a Task List on another Task List (Reordering) ---
                else if (dragType === 'task-list' && targetType === 'task-list') {
                    let beforeTaskListId = '';
                    if (isAbove) {
                        beforeTaskListId = target.dataset.taskListId;
                    } else {
                        let nextRow = target.nextElementSibling;
                        while (nextRow && nextRow.dataset.rowType !== 'task-list' && nextRow.dataset.rowType !== 'phase') {
                            nextRow = nextRow.nextElementSibling;
                        }
                        if (nextRow && nextRow.dataset.rowType === 'task-list') {
                            beforeTaskListId = nextRow.dataset.taskListId || '';
                        }
                    }

                    await postMove('/projects/task-lists/move', {
                        project_id: projectId,
                        task_list_id: draggedRow.dataset.taskListId,
                        phase_id: target.dataset.phaseId || '',
                        before_task_list_id: beforeTaskListId,
                    });
                    window.location.reload();
                }

                // --- Scenario D: Dropping a Task List on a Phase Header (Moving Phase) ---
                else if (dragType === 'task-list' && targetType === 'phase') {
                    await postMove('/projects/task-lists/move', {
                        project_id: projectId,
                        task_list_id: draggedRow.dataset.taskListId,
                        phase_id: target.dataset.phaseId || '',
                    });
                    window.location.reload();
                }

                // --- Scenario E: Dropping a Phase on another Phase (Reordering Phases) ---
                else if (dragType === 'phase' && targetType === 'phase') {
                    let beforePhaseId = '';
                    if (isAbove) {
                        beforePhaseId = target.dataset.phaseId;
                    } else {
                        let nextRow = target.nextElementSibling;
                        while (nextRow && nextRow.dataset.rowType !== 'phase') {
                            nextRow = nextRow.nextElementSibling;
                        }
                        beforePhaseId = nextRow ? (nextRow.dataset.phaseId || '') : '';
                    }

                    await postMove('/projects/phases/move', {
                        project_id: projectId,
                        phase_id: draggedRow.dataset.phaseId,
                        before_phase_id: beforePhaseId,
                    });
                    window.location.reload();
                }
            });
        });

    }

    // Row-action dropdowns (<details class="row-menu">): works on any page,
    // not just the project workspace - e.g. the Projects list's Archive/
    // Delete menu reuses the exact same markup and behavior.
    if (document.querySelector('.row-menu')) {
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

            // Fixed compact sizing - prevents zoom distortions and stretching
            panel.style.width = '130px';
            panel.style.minWidth = '120px';
            panel.style.maxWidth = '140px';
            panel.style.boxSizing = 'border-box';
            panel.style.position = 'fixed';
            panel.style.zIndex = '999999';
            panel.style.visibility = 'hidden';
            panel.style.display = 'flex';
            panel.style.flexDirection = 'column';

            const rect = summary.getBoundingClientRect();
            const panelWidth = 130;
            const panelHeight = panel.offsetHeight || 68;
            const margin = 4;

            // Align the right edge of the dropdown directly under the 3-dots button
            let left = rect.right - panelWidth;
            if (left < 8) {
                left = 8;
            }
            if (left + panelWidth > window.innerWidth - 8) {
                left = window.innerWidth - panelWidth - 8;
            }

            let top = rect.bottom + margin;

            // If it would overflow viewport bottom, flip cleanly above the 3-dots button
            if (top + panelHeight > window.innerHeight - 8) {
                top = Math.max(8, rect.top - panelHeight - margin);
            }

            panel.style.left = `${Math.round(left)}px`;
            panel.style.top = `${Math.round(top)}px`;
            panel.style.visibility = 'visible';
        }

        function setActiveRowMenu(menu, isActive) {
            const tr = menu.closest('tr');
            const td = menu.closest('td');
            if (isActive) {
                document.querySelectorAll('.row-menu-active').forEach((el) => el.classList.remove('row-menu-active'));
                if (tr) tr.classList.add('row-menu-active');
                if (td) td.classList.add('row-menu-active');
            } else {
                if (tr) tr.classList.remove('row-menu-active');
                if (td) td.classList.remove('row-menu-active');
            }
        }

        document.querySelectorAll('.row-menu').forEach((menu) => {
            const summary = menu.querySelector('summary');
            const panel = menu.querySelector('.row-menu-panel');

            if (summary && panel) {
                summary.addEventListener('click', () => {
                    requestAnimationFrame(() => {
                        if (menu.open) {
                            setActiveRowMenu(menu, true);
                            positionMenu(menu, panel);
                        } else {
                            setActiveRowMenu(menu, false);
                        }
                    });
                });
            }

            menu.addEventListener('toggle', () => {
                const p = menu.querySelector('.row-menu-panel');
                if (!p) {
                    return;
                }

                if (!menu.open) {
                    setActiveRowMenu(menu, false);
                    p.removeAttribute('style');
                    return;
                }

                document.querySelectorAll('.row-menu[open]').forEach((otherMenu) => {
                    if (otherMenu !== menu) {
                        otherMenu.open = false;
                    }
                });

                setActiveRowMenu(menu, true);
                positionMenu(menu, p);
            });
        });

        document.addEventListener('pointerdown', (event) => {
            const clickedInsideMenu = event.target.closest('.row-menu');
            if (clickedInsideMenu) {
                return;
            }

            document.querySelectorAll('.row-menu[open]').forEach((menu) => {
                menu.open = false;
                setActiveRowMenu(menu, false);
            });
        });

        window.addEventListener('scroll', repositionOpenMenu, true);
        window.addEventListener('resize', repositionOpenMenu);
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

    const clientDndTable = document.querySelector('[data-client-dnd]');
    if (clientDndTable) {
        let draggedProject = null;
        let activeDropRow = null;

        const clearClientDropTarget = () => {
            if (activeDropRow) {
                activeDropRow.classList.remove('drop-target');
                activeDropRow = null;
            }
        };

        clientDndTable.addEventListener('dragstart', (event) => {
            const item = event.target.closest('.client-project-item[draggable="true"]');
            if (!item) {
                return;
            }
            draggedProject = item;
            item.classList.add('dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.dataset.projectId || '');
        });

        clientDndTable.addEventListener('dragend', () => {
            if (draggedProject) {
                draggedProject.classList.remove('dragging');
            }
            clearClientDropTarget();
            draggedProject = null;
        });

        clientDndTable.addEventListener('dragover', (event) => {
            if (!draggedProject) {
                return;
            }
            const row = event.target.closest('.client-row');
            if (!row || row.dataset.clientId === draggedProject.dataset.currentClientId) {
                return;
            }
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            if (activeDropRow !== row) {
                clearClientDropTarget();
                activeDropRow = row;
                row.classList.add('drop-target');
            }
        });

        clientDndTable.addEventListener('drop', async (event) => {
            const row = event.target.closest('.client-row');
            if (!row || !draggedProject || row.dataset.clientId === draggedProject.dataset.currentClientId) {
                return;
            }
            event.preventDefault();
            clearClientDropTarget();

            await postMove('/clients/reassign-project', {
                project_id: draggedProject.dataset.projectId,
                client_id: row.dataset.clientId,
            });
            window.location.reload();
        });
    }

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

    // Profile photo picker: swap the avatar circle's contents for a local
    // preview of the chosen file before the form is even submitted.
    const avatarInput = document.querySelector('[data-avatar-input]');
    const avatarPreview = document.querySelector('[data-avatar-preview]');
    if (avatarInput && avatarPreview) {
        avatarInput.addEventListener('change', () => {
            const file = avatarInput.files?.[0];
            if (!file) {
                return;
            }

            const reader = new FileReader();
            reader.onload = () => {
                avatarPreview.innerHTML = `<img src="${reader.result}" alt="">`;
            };
            reader.readAsDataURL(file);
        });
    }

    // Projects list: filter rows locally and offer matching project names as a
    // compact type-ahead list, so no project data is changed by searching.
    const projectSearch = document.querySelector('[data-project-search]');
    const projectSearchSuggestions = document.querySelector('[data-project-search-suggestions]');
    if (projectSearch && projectSearchSuggestions) {
        const table = document.querySelector(`.${projectSearch.dataset.projectSearchTable}`);
        const rows = table ? Array.from(table.querySelectorAll('tbody tr[data-project-name]')) : [];
        const cards = Array.from(document.querySelectorAll('[data-project-card]'));
        const count = document.querySelector('[data-project-search-count]');
        const projectNames = rows.length 
            ? rows.map((row) => row.dataset.projectName || '').filter(Boolean)
            : cards.map((c) => c.dataset.projectName || '').filter(Boolean);

        const filterRows = () => {
            const query = projectSearch.value.trim().toLowerCase();
            let visible = 0;
            rows.forEach((row) => {
                const text = ((row.dataset.projectName || '') + ' ' + (row.textContent || '')).toLowerCase();
                const matches = query === '' || text.includes(query);
                row.hidden = !matches;
                if (matches) visible += 1;
            });
            cards.forEach((card) => {
                const text = ((card.dataset.projectName || '') + ' ' + (card.dataset.projectCode || '') + ' ' + (card.dataset.projectOwner || '') + ' ' + (card.textContent || '')).toLowerCase();
                const matches = query === '' || text.includes(query);
                card.style.display = matches ? 'flex' : 'none';
            });
            if (count) count.textContent = `${visible || cards.filter(c => c.style.display !== 'none').length} project${(visible || cards.filter(c => c.style.display !== 'none').length) === 1 ? '' : 's'}`;
        };

        const renderSuggestions = () => {
            const query = projectSearch.value.trim().toLowerCase();
            const matches = (query === '' ? [] : projectNames.filter((name) => name.toLowerCase().includes(query))).slice(0, 8);
            projectSearchSuggestions.innerHTML = '';
            matches.forEach((name) => {
                const item = document.createElement('li');
                item.textContent = name;
                item.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    projectSearch.value = name;
                    projectSearchSuggestions.hidden = true;
                    filterRows();
                });
                projectSearchSuggestions.appendChild(item);
            });
            projectSearchSuggestions.hidden = matches.length === 0;
        };

        projectSearch.addEventListener('input', () => { filterRows(); renderSuggestions(); });
        projectSearch.addEventListener('focus', renderSuggestions);
        projectSearch.addEventListener('blur', () => setTimeout(() => { projectSearchSuggestions.hidden = true; }, 150));
        projectSearch.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                projectSearchSuggestions.hidden = true;
                projectSearch.blur();
            }
        });
    }

    // Home page "Find a Task" search: type-ahead against /tasks/search (title
    // match, optionally narrowed by the Client/Project/Phase/Task List
    // filters, which cascade client-side from the preloaded filter tree),
    // clicking a result navigates straight to that task.
    const taskSearchInput = document.getElementById('task-search-input');
    if (taskSearchInput) {
        const resultsList = document.getElementById('task-search-results');
        const filterToggle = document.getElementById('task-search-filter-toggle');
        const filtersPanel = document.getElementById('task-search-filters');
        const clientSelect = document.getElementById('task-filter-client');
        const projectSelect = document.getElementById('task-filter-project');
        const phaseSelect = document.getElementById('task-filter-phase');
        const taskListSelect = document.getElementById('task-filter-tasklist');
        const filterData = window.__pmsTaskFilters || { clients: [], projects: [], phases: [], taskLists: [] };

        const fillSelect = (select, items, placeholder) => {
            const current = select.value;
            select.innerHTML = '';
            const blank = document.createElement('option');
            blank.value = '';
            blank.textContent = placeholder;
            select.appendChild(blank);
            items.forEach((item) => {
                const opt = document.createElement('option');
                opt.value = String(item.id);
                opt.textContent = item.name;
                select.appendChild(opt);
            });
            if (items.some((item) => String(item.id) === current)) {
                select.value = current;
            }
        };

        const refreshProjectOptions = () => {
            const clientId = clientSelect.value;
            const projects = clientId
                ? filterData.projects.filter((p) => String(p.client_id) === clientId)
                : filterData.projects;
            fillSelect(projectSelect, projects, 'All Projects');
        };

        const refreshPhaseAndTaskListOptions = () => {
            const projectId = projectSelect.value;
            const phases = projectId
                ? filterData.phases.filter((p) => String(p.project_id) === projectId)
                : filterData.phases;
            const taskLists = projectId
                ? filterData.taskLists.filter((t) => String(t.project_id) === projectId)
                : filterData.taskLists;
            fillSelect(phaseSelect, phases, 'All Phases');
            fillSelect(taskListSelect, taskLists, 'All Task Lists');
        };

        fillSelect(clientSelect, filterData.clients, 'All Clients');
        refreshProjectOptions();
        refreshPhaseAndTaskListOptions();

        let searchAbort = null;
        let searchDebounce = null;

        const renderResults = (tasks) => {
            resultsList.innerHTML = '';

            if (!tasks.length) {
                const li = document.createElement('li');
                li.className = 'task-search-empty';
                li.textContent = 'No matching tasks.';
                resultsList.appendChild(li);
                resultsList.hidden = false;
                return;
            }

            tasks.forEach((task) => {
                const li = document.createElement('li');
                const strong = document.createElement('strong');
                strong.textContent = task.title;
                const small = document.createElement('small');
                small.textContent = [task.project_name, task.phase_name, task.task_list_name].filter(Boolean).join(' › ');
                li.appendChild(strong);
                li.appendChild(small);
                li.addEventListener('mousedown', (event) => {
                    event.preventDefault();
                    window.location.href = `${appBasePath}/tasks/show?id=${task.id}`;
                });
                resultsList.appendChild(li);
            });

            resultsList.hidden = false;
        };

        const runSearch = () => {
            const q = taskSearchInput.value.trim();
            const clientId = clientSelect.value;
            const projectId = projectSelect.value;
            const phaseId = phaseSelect.value;
            const taskListId = taskListSelect.value;

            if (q.length < 2 && !clientId && !projectId && !phaseId && !taskListId) {
                resultsList.hidden = true;
                resultsList.innerHTML = '';
                return;
            }

            const params = new URLSearchParams();
            if (q) params.set('q', q);
            if (clientId) params.set('client_id', clientId);
            if (projectId) params.set('project_id', projectId);
            if (phaseId) params.set('phase_id', phaseId);
            if (taskListId) params.set('task_list_id', taskListId);

            if (searchAbort) {
                searchAbort.abort();
            }
            searchAbort = new AbortController();

            fetch(`${appBasePath}/tasks/search?${params.toString()}`, {
                credentials: 'same-origin',
                signal: searchAbort.signal,
            })
                .then((resp) => resp.json())
                .then(renderResults)
                .catch((err) => {
                    if (err.name !== 'AbortError') {
                        resultsList.hidden = true;
                    }
                });
        };

        taskSearchInput.addEventListener('input', () => {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(runSearch, 300);
        });

        taskSearchInput.addEventListener('focus', () => {
            if (resultsList.children.length) {
                resultsList.hidden = false;
            }
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.task-search-bar')) {
                resultsList.hidden = true;
            }
        });

        [clientSelect, projectSelect, phaseSelect, taskListSelect].forEach((select) => {
            select.addEventListener('change', () => {
                if (select === clientSelect) {
                    refreshProjectOptions();
                    refreshPhaseAndTaskListOptions();
                } else if (select === projectSelect) {
                    refreshPhaseAndTaskListOptions();
                }
                runSearch();
            });
        });

        if (filterToggle && filtersPanel) {
            filterToggle.addEventListener('click', () => {
                const isHidden = filtersPanel.hidden;
                filtersPanel.hidden = !isHidden;
                filterToggle.setAttribute('aria-expanded', String(isHidden));
            });
        }
    }

    // -------------------------------------------------------------------------
    // Sidebar Collapse / Toggle & Persistence
    // -------------------------------------------------------------------------
    const sidebarToggleBtn = document.getElementById('sidebarToggle');
    const appShell = document.getElementById('appShell');

    function syncSidebarState(collapsed) {
        if (collapsed) {
            document.documentElement.classList.add('sidebar-is-collapsed');
            appShell?.classList.add('sidebar-collapsed');
            try { localStorage.setItem('pms_sidebar_collapsed', '1'); } catch (e) {}
        } else {
            document.documentElement.classList.remove('sidebar-is-collapsed');
            appShell?.classList.remove('sidebar-collapsed');
            try { localStorage.setItem('pms_sidebar_collapsed', '0'); } catch (e) {}
        }
    }

    // Sync on DOM ready
    try {
        if (localStorage.getItem('pms_sidebar_collapsed') === '1') {
            syncSidebarState(true);
        }
    } catch (e) {}

    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const currentlyCollapsed = document.documentElement.classList.contains('sidebar-is-collapsed') ||
                                       (appShell && appShell.classList.contains('sidebar-collapsed'));
            syncSidebarState(!currentlyCollapsed);
        });
    }

    // -------------------------------------------------------------------------
    // Client Renewals & Batches Interactivity
    // -------------------------------------------------------------------------
    initClientRenewalsModule();

    function initClientRenewalsModule() {
        const clientsData = window.__pmsClientsWithProjects || [];

        // Global Modal open/close triggers
        document.querySelectorAll('[data-open-modal]').forEach((trigger) => {
            trigger.addEventListener('click', () => {
                const targetId = trigger.getAttribute('data-open-modal');
                const modal = document.getElementById(targetId);
                if (modal) {
                    modal.hidden = false;
                    const firstInput = modal.querySelector('input:not([type="hidden"]), select');
                    firstInput?.focus();
                }
            });
        });

        document.querySelectorAll('[data-close-modal]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const modal = btn.closest('.modal-backdrop');
                if (modal) modal.hidden = true;
            });
        });

        document.querySelectorAll('.modal-backdrop').forEach((modal) => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.hidden = true;
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-backdrop:not([hidden])').forEach((m) => {
                    m.hidden = true;
                });
            }
        });

        // Helper to populate project dropdown for a selected client
        function populateProjectSelect(clientSelectEl, projectSelectEl, selectedProjectId) {
            if (!clientSelectEl || !projectSelectEl) return;
            const clientId = parseInt(clientSelectEl.value, 10);
            projectSelectEl.innerHTML = '';

            if (!clientId) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'Select Client First';
                projectSelectEl.appendChild(opt);
                projectSelectEl.disabled = true;
                return;
            }

            const clientObj = clientsData.find((c) => parseInt(c.id, 10) === clientId);
            const projects = clientObj ? clientObj.projects || [] : [];

            if (projects.length === 0) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'No projects for this client';
                projectSelectEl.appendChild(opt);
                projectSelectEl.disabled = true;
                return;
            }

            projectSelectEl.disabled = false;
            const defaultOpt = document.createElement('option');
            defaultOpt.value = '';
            defaultOpt.textContent = 'Select Project';
            projectSelectEl.appendChild(defaultOpt);

            projects.forEach((p) => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.code ? `${p.name} (${p.code})` : p.name;
                if (selectedProjectId && parseInt(p.id, 10) === parseInt(selectedProjectId, 10)) {
                    opt.selected = true;
                }
                projectSelectEl.appendChild(opt);
            });
        }

        // Add Modal cascading
        const addModalClient = document.getElementById('add-modal-client');
        const addModalProject = document.getElementById('add-modal-project');
        if (addModalClient && addModalProject) {
            addModalClient.addEventListener('change', () => {
                populateProjectSelect(addModalClient, addModalProject, null);
            });
        }

        // Auto-compute 1 year renewal date from creation date in Add Modal
        const addCreationDate = document.getElementById('add-modal-creation-date');
        const addRenewalDate = document.getElementById('add-modal-renewal-date');
        if (addCreationDate && addRenewalDate) {
            addCreationDate.addEventListener('change', () => {
                if (addCreationDate.value) {
                    const cDate = new Date(addCreationDate.value);
                    if (!isNaN(cDate.getTime())) {
                        cDate.setFullYear(cDate.getFullYear() + 1);
                        addRenewalDate.value = cDate.toISOString().split('T')[0];
                    }
                }
            });
        }

        // Edit Modal cascading & setup
        const editModalClient = document.getElementById('edit-modal-client');
        const editModalProject = document.getElementById('edit-modal-project');
        if (editModalClient && editModalProject) {
            editModalClient.addEventListener('change', () => {
                populateProjectSelect(editModalClient, editModalProject, null);
            });
        }

        // Edit Batch Button triggers
        document.querySelectorAll('[data-action="edit"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                try {
                    const batch = JSON.parse(btn.getAttribute('data-batch'));
                    if (!batch) return;

                    const modal = document.getElementById('editBatchModal');
                    if (!modal) return;

                    document.getElementById('edit-modal-id').value = batch.id || '';
                    if (editModalClient) {
                        editModalClient.value = batch.client_id || '';
                        populateProjectSelect(editModalClient, editModalProject, batch.project_id);
                    }
                    document.getElementById('edit-modal-batch-name').value = batch.batch_name || '';
                    document.getElementById('edit-modal-region').value = batch.region_department || '';
                    document.getElementById('edit-modal-count').value = batch.licence_count || '';
                    document.getElementById('edit-modal-creation-date').value = batch.creation_date || '';
                    document.getElementById('edit-modal-renewal-date').value = batch.renewal_date || '';
                    document.getElementById('edit-modal-given-by').value = batch.given_by || '';
                    document.getElementById('edit-modal-notes').value = batch.notes || '';

                    const platformStartInput = document.getElementById('edit-modal-platform-start');
                    const platformRenewalInput = document.getElementById('edit-modal-platform-renewal');
                    if (platformStartInput) platformStartInput.value = batch.platform_start_date || '';
                    if (platformRenewalInput) platformRenewalInput.value = batch.platform_renewal_date || '';

                    modal.hidden = false;
                } catch (err) {
                    console.error('Error opening edit batch modal:', err);
                }
            });
        });

        // History Modal Triggers & AJAX Loading
        document.querySelectorAll('[data-action="history"]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                const batchId = btn.getAttribute('data-batch-id');
                const batchName = btn.getAttribute('data-batch-name') || 'Batch';
                const modal = document.getElementById('batchHistoryModal');
                const container = document.getElementById('historyTimelineContainer');
                const titleEl = document.getElementById('history-modal-title');
                if (!modal || !container) return;

                if (titleEl) titleEl.textContent = `Licence Audit Trail — ${batchName}`;
                container.innerHTML = '<div class="empty-state slim"><p>Loading audit timeline...</p></div>';
                modal.hidden = false;

                try {
                    const res = await fetch(`${appBasePath}/client-renewals/batch-history-api?batch_id=${encodeURIComponent(batchId)}`, { credentials: 'same-origin' });
                    const history = await res.json();

                    if (!Array.isArray(history) || history.length === 0) {
                        container.innerHTML = '<div class="empty-state slim"><p>No previous history logs found for this batch.</p></div>';
                        return;
                    }

                    let html = '';
                    history.forEach((h, index) => {
                        const isInitial = h.action_type === 'created' || index === history.length - 1;
                        let actionType = (h.action_type || 'renewed').toLowerCase();
                        let actionLabel = actionType ? actionType.charAt(0).toUpperCase() + actionType.slice(1) : 'Renewed';
                        const notesLower = (h.notes || '').toLowerCase();
                        if (notesLower.includes('batch archived') || actionType === 'archived') {
                            actionType = 'archived';
                            actionLabel = 'Archived';
                        } else if (notesLower.includes('batch unarchived') || actionType === 'unarchived') {
                            actionType = 'unarchived';
                            actionLabel = 'Unarchived';
                        }

                        const startDate = h.period_start ? formatDateStr(h.period_start) : '-';
                        const endDate = h.period_end ? formatDateStr(h.period_end) : '-';
                        const countFormatted = Number(h.licence_count || 0).toLocaleString();

                        html += `
                            <div class="timeline-item ${isInitial ? 'first-period' : ''}">
                                <div class="timeline-header">
                                    <span class="timeline-dates">${startDate} &rarr; <strong>${endDate}</strong></span>
                                    <span class="action-tag ${h.action_type || 'renewed'}">${actionLabel}</span>
                                </div>
                                <div class="timeline-body">
                                    <div><strong>${countFormatted} Licences</strong> ${h.given_by ? `&bull; Given by: <em>${escapeHtml(h.given_by)}</em>` : ''}</div>
                                    ${h.notes ? `<div class="timeline-notes">${escapeHtml(h.notes)}</div>` : ''}
                                </div>
                                <div class="timeline-footer">
                                    <span>Logged by ${escapeHtml(h.creator_name || 'Admin')}</span>
                                    <span>${h.created_at ? formatDateTimeStr(h.created_at) : ''}</span>
                                </div>
                            </div>
                        `;
                    });

                    container.innerHTML = html;
                } catch (err) {
                    console.error('Failed to load history:', err);
                    container.innerHTML = '<div class="empty-state slim"><p class="text-danger">Failed to load history records. Please try again.</p></div>';
                }
            });
        });

        function formatDateStr(dateStr) {
            try {
                const d = new Date(dateStr);
                if (isNaN(d.getTime())) return dateStr;
                const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                return `${String(d.getDate()).padStart(2, '0')} ${months[d.getMonth()]} ${d.getFullYear()}`;
            } catch (e) {
                return dateStr;
            }
        }

        function formatDateTimeStr(dateStr) {
            if (!dateStr) return '-';
            try {
                const parts = String(dateStr).trim().split(/[- :T]/);
                if (parts.length >= 5) {
                    const year = parseInt(parts[0], 10);
                    const month = parseInt(parts[1], 10) - 1;
                    const day = parseInt(parts[2], 10);
                    const hour = parseInt(parts[3], 10);
                    const minute = parseInt(parts[4], 10);
                    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    const ampm = hour >= 12 ? 'PM' : 'AM';
                    const h12 = hour % 12 || 12;
                    const mStr = String(minute).padStart(2, '0');
                    return `${String(day).padStart(2, '0')} ${months[month]} ${year}, ${h12}:${mStr} ${ampm}`;
                }
                const d = new Date(dateStr);
                if (isNaN(d.getTime())) return dateStr;
                const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                let hours = d.getHours();
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12 || 12;
                return `${String(d.getDate()).padStart(2, '0')} ${months[d.getMonth()]} ${d.getFullYear()}, ${hours}:${String(d.getMinutes()).padStart(2, '0')} ${ampm}`;
            } catch (e) {
                return dateStr;
            }
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Expand/Collapse All Client Cards in Hierarchy View
        const btnExpandHierarchy = document.getElementById('btnExpandAllHierarchy');
        const btnCollapseHierarchy = document.getElementById('btnCollapseAllHierarchy');

        if (btnExpandHierarchy) {
            btnExpandHierarchy.addEventListener('click', () => {
                document.querySelectorAll('.client-renewal-card').forEach((card) => {
                    card.open = true;
                });
            });
        }

        if (btnCollapseHierarchy) {
            btnCollapseHierarchy.addEventListener('click', () => {
                document.querySelectorAll('.client-renewal-card').forEach((card) => {
                    card.open = false;
                });
            });
        }

        // Export Modal Date Presets
        document.querySelectorAll('[data-export-preset]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const preset = btn.getAttribute('data-export-preset');
                const fromInput = document.getElementById('export-from-date');
                const toInput = document.getElementById('export-to-date');
                if (!fromInput || !toInput) return;

                const now = new Date();
                const currentYear = now.getFullYear();

                if (preset === 'all') {
                    fromInput.value = '';
                    toInput.value = '';
                } else if (preset === 'this_year') {
                    fromInput.value = `${currentYear}-01-01`;
                    toInput.value = `${currentYear}-12-31`;
                } else if (preset === 'last_year') {
                    fromInput.value = `${currentYear - 1}-01-01`;
                    toInput.value = `${currentYear - 1}-12-31`;
                } else if (preset === 'next_90') {
                    fromInput.value = now.toISOString().split('T')[0];
                    const future = new Date();
                    future.setDate(future.getDate() + 90);
                    toInput.value = future.toISOString().split('T')[0];
                }
            });
        });

        // Export Modal Client -> Project Cascade
        const exportClient = document.getElementById('export-client-select');
        const exportProject = document.getElementById('export-project-select');
        if (exportClient && exportProject) {
            exportClient.addEventListener('change', () => {
                const clientId = exportClient.value;
                const options = exportProject.querySelectorAll('option');
                options.forEach((opt) => {
                    if (!opt.value) return;
                    const optClientId = opt.getAttribute('data-client-id');
                    if (!clientId || optClientId === clientId) {
                        opt.hidden = false;
                    } else {
                        opt.hidden = true;
                    }
                });
                const selectedOpt = exportProject.options[exportProject.selectedIndex];
                if (selectedOpt && selectedOpt.value && selectedOpt.getAttribute('data-client-id') !== clientId) {
                    exportProject.value = '';
                }
            });
        }

        // ==========================================
        // REPORTS: Live Filter & Client-Project Cascade
        // ==========================================
        const reportsFilterForm = document.getElementById('reportsFilterForm');
        if (reportsFilterForm) {
            // Live submit on dropdown changes
            reportsFilterForm.querySelectorAll('select').forEach((sel) => {
                sel.addEventListener('change', () => {
                    if (sel.name === 'client_id') {
                        const projectInput = reportsFilterForm.querySelector('input[name="project_group"]');
                        if (projectInput && projectInput.value.trim() !== '') {
                            const curProject = projectInput.value.trim();
                            const allProjects = window.__pmsAllProjects || [];
                            const clientId = sel.value;
                            if (clientId) {
                                const matchingProject = allProjects.find(
                                    (p) => p.name.toLowerCase() === curProject.toLowerCase() && String(p.client_id) === String(clientId)
                                );
                                if (!matchingProject) {
                                    projectInput.value = '';
                                }
                            }
                        }
                    }
                    reportsFilterForm.submit();
                });
            });

            // Live submit on date changes
            reportsFilterForm.querySelectorAll('input[type="date"]').forEach((dateInput) => {
                dateInput.addEventListener('change', () => {
                    reportsFilterForm.submit();
                });
            });

            // Project input auto-detect client & auto submit on selection
            const projectInput = reportsFilterForm.querySelector('input[name="project_group"]');
            if (projectInput) {
                projectInput.addEventListener('change', () => {
                    const val = projectInput.value.trim();
                    const clientSelect = reportsFilterForm.querySelector('select[name="client_id"]');
                    const allProjects = window.__pmsAllProjects || [];
                    if (val && clientSelect && !clientSelect.value) {
                        const match = allProjects.find((p) => p.name.toLowerCase() === val.toLowerCase());
                        if (match && match.client_id) {
                            clientSelect.value = String(match.client_id);
                        }
                    }
                    reportsFilterForm.submit();
                });

                projectInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        reportsFilterForm.submit();
                    }
                });
            }
        }

        // ==========================================
        // REPORTS: Smart Excel Export Modal
        // ==========================================
        const reportsExportModal = document.getElementById('reportsExportModal');
        const btnOpenReportsExport = document.querySelector('[data-open-modal="reportsExportModal"]');
        if (reportsExportModal) {
            if (btnOpenReportsExport) {
                btnOpenReportsExport.addEventListener('click', () => {
                    // Sync current screen filter values into modal
                    if (reportsFilterForm) {
                        const fromVal = reportsFilterForm.querySelector('input[name="from_date"]')?.value || '';
                        const toVal = reportsFilterForm.querySelector('input[name="to_date"]')?.value || '';
                        const clientVal = reportsFilterForm.querySelector('select[name="client_id"]')?.value || '';
                        const projectVal = reportsFilterForm.querySelector('input[name="project_group"]')?.value || '';
                        const userVal = reportsFilterForm.querySelector('select[name="user_id"]')?.value || '';
                        const billingVal = reportsFilterForm.querySelector('select[name="billing_status"]')?.value || '';
                        const phaseVal = reportsFilterForm.querySelector('select[name="phase"]')?.value || '';
                        const moduleVal = reportsFilterForm.querySelector('select[name="module_name"]')?.value || '';

                        const mFrom = document.getElementById('export-modal-from-date');
                        const mTo = document.getElementById('export-modal-to-date');
                        const mClient = document.getElementById('export-modal-client-select');
                        const mProject = document.getElementById('export-modal-project-group');
                        const mUser = document.getElementById('export-modal-user-select');
                        const mBilling = document.getElementById('export-modal-billing-status');
                        const mPhase = document.getElementById('export-modal-phase-select');
                        const mModule = document.getElementById('export-modal-module-select');

                        if (mFrom) mFrom.value = fromVal;
                        if (mTo) mTo.value = toVal;
                        if (mClient) mClient.value = clientVal;
                        if (mProject) mProject.value = projectVal;
                        if (mUser) mUser.value = userVal;
                        if (mBilling) mBilling.value = billingVal;
                        if (mPhase) mPhase.value = phaseVal;
                        if (mModule) mModule.value = moduleVal;
                    }
                    reportsExportModal.hidden = false;
                });
            }

            // Quick Date Range Presets in Export Modal
            reportsExportModal.querySelectorAll('[data-export-preset]').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const preset = btn.getAttribute('data-export-preset');
                    const fromInput = document.getElementById('export-modal-from-date');
                    const toInput = document.getElementById('export-modal-to-date');
                    if (!fromInput || !toInput) return;

                    const now = new Date();
                    const currentYear = now.getFullYear();
                    const currentMonth = now.getMonth(); // 0-indexed

                    if (preset === 'all') {
                        fromInput.value = '';
                        toInput.value = '';
                    } else if (preset === 'this_month') {
                        const firstDay = new Date(currentYear, currentMonth, 1);
                        const lastDay = new Date(currentYear, currentMonth + 1, 0);
                        const fmt = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                        fromInput.value = fmt(firstDay);
                        toInput.value = fmt(lastDay);
                    } else if (preset === 'last_month') {
                        const firstDay = new Date(currentYear, currentMonth - 1, 1);
                        const lastDay = new Date(currentYear, currentMonth, 0);
                        const fmt = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                        fromInput.value = fmt(firstDay);
                        toInput.value = fmt(lastDay);
                    } else if (preset === 'this_year') {
                        fromInput.value = `${currentYear}-01-01`;
                        toInput.value = `${currentYear}-12-31`;
                    } else if (preset === 'last_year') {
                        fromInput.value = `${currentYear - 1}-01-01`;
                        toInput.value = `${currentYear - 1}-12-31`;
                    } else if (preset === 'last_90') {
                        const past = new Date();
                        past.setDate(past.getDate() - 90);
                        const fmt = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                        fromInput.value = fmt(past);
                        toInput.value = fmt(now);
                    }
                });
            });

            // Client -> Project Cascade inside Export Modal
            const modalClientSelect = document.getElementById('export-modal-client-select');
            const modalProjectInput = document.getElementById('export-modal-project-group');
            if (modalClientSelect && modalProjectInput) {
                modalClientSelect.addEventListener('change', () => {
                    const clientId = modalClientSelect.value;
                    const curProject = modalProjectInput.value.trim();
                    const allProjects = window.__pmsAllProjects || [];
                    if (clientId && curProject) {
                        const match = allProjects.find(
                            (p) => p.name.toLowerCase() === curProject.toLowerCase() && String(p.client_id) === String(clientId)
                        );
                        if (!match) {
                            modalProjectInput.value = '';
                        }
                    }
                });

                modalProjectInput.addEventListener('change', () => {
                    const curProject = modalProjectInput.value.trim();
                    const allProjects = window.__pmsAllProjects || [];
                    if (curProject && !modalClientSelect.value) {
                        const match = allProjects.find((p) => p.name.toLowerCase() === curProject.toLowerCase());
                        if (match && match.client_id) {
                            modalClientSelect.value = String(match.client_id);
                        }
                    }
                });
            }

            // Report Type Toggle for Group-By in Export Modal
            const exportReportType = document.getElementById('export-report-type');
            const exportGroupByBox = document.getElementById('export-modal-group-by-box');
            if (exportReportType && exportGroupByBox) {
                const updateGroupByVisibility = () => {
                    exportGroupByBox.style.display = exportReportType.value === 'detailed' ? 'block' : 'none';
                };
                exportReportType.addEventListener('change', updateGroupByVisibility);
                updateGroupByVisibility();
            }
        }

        // Quick Renew Modal triggers
        document.querySelectorAll('[data-action="renew"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                try {
                    const batch = JSON.parse(btn.getAttribute('data-batch'));
                    if (!batch) return;

                    const modal = document.getElementById('renewBatchModal');
                    if (!modal) return;

                    document.getElementById('renew-modal-id').value = batch.id || '';
                    document.getElementById('renew-modal-batch-name').textContent = batch.batch_name || 'Licence Batch';
                    document.getElementById('renew-modal-context').textContent = `${batch.client_name} › ${batch.project_name} (Current: ${batch.licence_count} licences)`;

                    // Set cycle start date (defaults to current renewal date or today)
                    const startInput = document.getElementById('renew-modal-start');
                    if (startInput) {
                        startInput.value = batch.renewal_date || new Date().toISOString().split('T')[0];
                    }

                    // Default new renewal date: 1 year from cycle start date
                    let baseDate = batch.renewal_date ? new Date(batch.renewal_date) : new Date();
                    if (isNaN(baseDate.getTime())) baseDate = new Date();
                    baseDate.setFullYear(baseDate.getFullYear() + 1);
                    document.getElementById('renew-modal-date').value = baseDate.toISOString().split('T')[0];
                    document.getElementById('renew-modal-count').value = batch.licence_count || '';
                    const givenByInput = document.getElementById('renew-modal-given-by');
                    if (givenByInput) givenByInput.value = batch.given_by || '';

                    const contractHoursInput = document.getElementById('renew-modal-contract-hours');
                    if (contractHoursInput) {
                        const defaultHrs = parseFloat(batch.run_hours || 0) + parseFloat(batch.build_hours || 0);
                        contractHoursInput.value = defaultHrs > 0 ? defaultHrs.toFixed(2) : '';
                    }
                    const invoiceRefInput = document.getElementById('renew-modal-invoice-ref');
                    if (invoiceRefInput) invoiceRefInput.value = '';

                    const renewNotesInput = document.getElementById('renew-modal-notes');
                    if (renewNotesInput) renewNotesInput.value = batch.notes || '';

                    // Dual-Timeline Platform fields prefill
                    const renewPlatformStart = document.getElementById('renew-modal-platform-start');
                    const renewPlatformRenewal = document.getElementById('renew-modal-platform-renewal');
                    const renewHasPlatform = document.getElementById('renew-modal-has-platform-dates');
                    const renewPlatformFields = document.getElementById('renew-modal-platform-fields');
                    if (renewPlatformStart && renewPlatformRenewal) {
                        const hasPlatformDates = Boolean(batch.platform_renewal_date && batch.platform_renewal_date !== batch.renewal_date);
                        if (renewHasPlatform) renewHasPlatform.checked = hasPlatformDates;
                        if (renewPlatformFields) renewPlatformFields.hidden = !hasPlatformDates;
                        renewPlatformStart.value = batch.platform_renewal_date || '';
                        if (hasPlatformDates && batch.platform_renewal_date) {
                            let pBaseDate = new Date(batch.platform_renewal_date);
                            if (!isNaN(pBaseDate.getTime())) {
                                pBaseDate.setFullYear(pBaseDate.getFullYear() + 1);
                                renewPlatformRenewal.value = pBaseDate.toISOString().split('T')[0];
                            } else {
                                renewPlatformRenewal.value = '';
                            }
                        } else {
                            renewPlatformRenewal.value = '';
                        }
                    }

                    modal.hidden = false;
                } catch (err) {
                    console.error('Error opening renew batch modal:', err);
                }
            });
        });

        // Quick Extend preset buttons (+1 Year, +6 Months, +3 Months)
        document.querySelectorAll('[data-extend-months]').forEach((presetBtn) => {
            presetBtn.addEventListener('click', () => {
                const months = parseInt(presetBtn.getAttribute('data-extend-months'), 10);
                const dateInput = document.getElementById('renew-modal-date');
                if (!dateInput || isNaN(months)) return;

                let d = dateInput.value ? new Date(dateInput.value) : new Date();
                if (isNaN(d.getTime())) d = new Date();
                d.setMonth(d.getMonth() + months);
                dateInput.value = d.toISOString().split('T')[0];
            });
        });

        // Dedicated Extension Modal Handler
        document.querySelectorAll('[data-action="extend"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                try {
                    const batch = JSON.parse(btn.getAttribute('data-batch'));
                    if (!batch) return;

                    const modal = document.getElementById('extendBatchModal');
                    if (!modal) return;

                    document.getElementById('extend-modal-id').value = batch.id || '';
                    document.getElementById('extend-modal-batch-name').textContent = batch.batch_name || 'Licence Batch';
                    document.getElementById('extend-modal-context').textContent = `${batch.client_name} › ${batch.project_name} (Current Licences: ${batch.licence_count})`;

                    const startInput = document.getElementById('extend-modal-start');
                    if (startInput) {
                        startInput.value = batch.renewal_date || new Date().toISOString().split('T')[0];
                    }

                    // Default extension: 15 days from current expiry
                    let baseDate = batch.renewal_date ? new Date(batch.renewal_date) : new Date();
                    if (isNaN(baseDate.getTime())) baseDate = new Date();
                    baseDate.setDate(baseDate.getDate() + 15);
                    document.getElementById('extend-modal-date').value = baseDate.toISOString().split('T')[0];
                    document.getElementById('extend-modal-count').value = batch.licence_count || '';

                    const givenByInput = document.getElementById('extend-modal-given-by');
                    if (givenByInput) givenByInput.value = batch.given_by || '';

                    const reasonInput = document.getElementById('extend-modal-reason');
                    if (reasonInput) reasonInput.value = batch.extension_reason || '';

                    const extendNotesInput = document.getElementById('extend-modal-notes');
                    if (extendNotesInput) extendNotesInput.value = batch.notes || '';

                    // Dual-Timeline Platform fields prefill for extension
                    const extendPlatformDate = document.getElementById('extend-modal-platform-date');
                    const extendHasPlatform = document.getElementById('extend-modal-has-platform-dates');
                    const extendPlatformFields = document.getElementById('extend-modal-platform-fields');
                    if (extendPlatformDate && extendHasPlatform && extendPlatformFields) {
                        const hasPlatformDates = Boolean(batch.platform_renewal_date && batch.platform_renewal_date !== batch.renewal_date);
                        extendHasPlatform.checked = hasPlatformDates;
                        extendPlatformFields.hidden = !hasPlatformDates;
                        if (hasPlatformDates && batch.platform_renewal_date) {
                            let pBaseDate = new Date(batch.platform_renewal_date);
                            if (!isNaN(pBaseDate.getTime())) {
                                pBaseDate.setDate(pBaseDate.getDate() + 15);
                                extendPlatformDate.value = pBaseDate.toISOString().split('T')[0];
                            } else {
                                extendPlatformDate.value = '';
                            }
                        } else {
                            extendPlatformDate.value = '';
                        }
                    }

                    modal.hidden = false;
                } catch (err) {
                    console.error('Error opening extend batch modal:', err);
                }
            });
        });

        // Quick Day presets for Extension Modal (+15 Days, +30 Days, +45 Days, +60 Days)
        document.querySelectorAll('[data-extend-days]').forEach((presetBtn) => {
            presetBtn.addEventListener('click', () => {
                const days = parseInt(presetBtn.getAttribute('data-extend-days'), 10);
                const dateInput = document.getElementById('extend-modal-date');
                const startInput = document.getElementById('extend-modal-start');
                if (!dateInput || isNaN(days)) return;

                let base = startInput && startInput.value ? new Date(startInput.value) : new Date();
                if (isNaN(base.getTime())) base = new Date();
                base.setDate(base.getDate() + days);
                dateInput.value = base.toISOString().split('T')[0];
            });
        });

        // Filter bar cascading
        const filterClient = document.querySelector('[data-client-filter]');
        const filterProject = document.querySelector('[data-project-filter]');
        if (filterClient && filterProject) {
            filterClient.addEventListener('change', () => {
                const clientId = filterClient.value;
                const options = filterProject.querySelectorAll('option');
                options.forEach((opt) => {
                    if (!opt.value) {
                        opt.hidden = false;
                        return;
                    }
                    const optClientId = opt.getAttribute('data-client-id');
                    if (!clientId || optClientId === clientId) {
                        opt.hidden = false;
                    } else {
                        opt.hidden = true;
                    }
                });
                const selectedOpt = filterProject.options[filterProject.selectedIndex];
                if (selectedOpt && selectedOpt.value && selectedOpt.getAttribute('data-client-id') !== clientId) {
                    filterProject.value = '';
                }
            });
        }

        // Quick permissions toggle in Users table
        document.querySelectorAll('[data-quick-perm-toggle]').forEach((checkbox) => {
            checkbox.addEventListener('change', async () => {
                const userId = checkbox.getAttribute('data-user-id');
                const perm = checkbox.getAttribute('data-perm');
                const enabled = checkbox.checked ? 1 : 0;
                const parentLabel = checkbox.closest('.perm-toggle-item');
                const indicator = document.getElementById(`saving-indicator-${userId}`);

                if (parentLabel) {
                    parentLabel.classList.toggle('active', checkbox.checked);
                }

                try {
                    const formData = new FormData();
                    formData.append('user_id', userId);
                    formData.append('permission', perm);
                    formData.append('enabled', enabled);
                    formData.append('csrf_token', csrf);

                    const res = await fetch(`${appBasePath}/users/quick-permissions-update`, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                    });

                    const data = await res.json();
                    if (!data.ok) {
                        throw new Error(data.error || 'Failed to update permission');
                    }

                    if (indicator) {
                        indicator.classList.add('show');
                        clearTimeout(indicator._timer);
                        indicator._timer = setTimeout(() => {
                            indicator.classList.remove('show');
                        }, 1400);
                    }
                } catch (err) {
                    console.error('Quick permission update failed:', err);
                    alert(err.message || 'Could not save permission change.');
                    // Revert checkbox
                    checkbox.checked = !checkbox.checked;
                    if (parentLabel) {
                        parentLabel.classList.toggle('active', checkbox.checked);
                    }
                }
            });
        });
        // ================= PROJECT FIELDS REORDERING & SETTINGS =================
        const projectFieldsTbody = document.getElementById('projectFieldsTbody');
        if (projectFieldsTbody) {
            function updateFieldOrderBadges() {
                const rows = projectFieldsTbody.querySelectorAll('tr.reorderable-row');
                rows.forEach((row, idx) => {
                    const badge = row.querySelector('[data-order-badge]');
                    if (badge) {
                        badge.textContent = idx + 1;
                    }
                });
            }

            // Move Up / Move Down buttons
            projectFieldsTbody.addEventListener('click', (e) => {
                const moveUpBtn = e.target.closest('[data-move-row="up"]');
                const moveDownBtn = e.target.closest('[data-move-row="down"]');
                if (!moveUpBtn && !moveDownBtn) return;

                const row = e.target.closest('tr.reorderable-row');
                if (!row) return;

                if (moveUpBtn) {
                    const prev = row.previousElementSibling;
                    if (prev) {
                        projectFieldsTbody.insertBefore(row, prev);
                        updateFieldOrderBadges();
                        row.classList.remove('row-highlight');
                        void row.offsetWidth; // trigger reflow
                        row.classList.add('row-highlight');
                    }
                } else if (moveDownBtn) {
                    const next = row.nextElementSibling;
                    if (next) {
                        projectFieldsTbody.insertBefore(next, row);
                        updateFieldOrderBadges();
                        row.classList.remove('row-highlight');
                        void row.offsetWidth;
                        row.classList.add('row-highlight');
                    }
                }
            });

            // Drag and drop reordering
            let draggedRow = null;
            projectFieldsTbody.querySelectorAll('tr.reorderable-row').forEach((row) => {
                row.addEventListener('dragstart', (e) => {
                    draggedRow = row;
                    row.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });

                row.addEventListener('dragend', () => {
                    if (draggedRow) {
                        draggedRow.classList.remove('dragging');
                    }
                    projectFieldsTbody.querySelectorAll('tr.reorderable-row').forEach((r) => r.classList.remove('drag-over'));
                    draggedRow = null;
                    updateFieldOrderBadges();
                });

                row.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    if (!draggedRow || draggedRow === row) return;
                    row.classList.add('drag-over');
                });

                row.addEventListener('dragleave', () => {
                    row.classList.remove('drag-over');
                });

                row.addEventListener('drop', (e) => {
                    e.preventDefault();
                    row.classList.remove('drag-over');
                    if (!draggedRow || draggedRow === row) return;

                    const allRows = Array.from(projectFieldsTbody.querySelectorAll('tr.reorderable-row'));
                    const draggedIdx = allRows.indexOf(draggedRow);
                    const targetIdx = allRows.indexOf(row);

                    if (draggedIdx < targetIdx) {
                        projectFieldsTbody.insertBefore(draggedRow, row.nextElementSibling);
                    } else {
                        projectFieldsTbody.insertBefore(draggedRow, row);
                    }
                    updateFieldOrderBadges();
                });
            });

            // Reset field order button
            const btnReset = document.getElementById('btnResetFieldOrder');
            if (btnReset) {
                const defaultKeys = [
                    'code', 'name', 'owner_name', 'status', 'billed_learners',
                    'learners_on_platform', 'learners_connected', 'started_with_courses',
                    'total_time', 'average_time_per_learner', 'adoption_percent',
                    'start_date', 'due_date', 'tasks'
                ];

                btnReset.addEventListener('click', () => {
                    const rows = Array.from(projectFieldsTbody.querySelectorAll('tr.reorderable-row'));
                    const rowMap = {};
                    rows.forEach((r) => {
                        rowMap[r.getAttribute('data-field-key')] = r;
                    });

                    defaultKeys.forEach((key) => {
                        if (rowMap[key]) {
                            projectFieldsTbody.appendChild(rowMap[key]);
                        }
                    });

                    updateFieldOrderBadges();
                });
            }
        }

        // Custom fields reordering
        const customFieldsList = document.getElementById('customFieldsList');
        const customFieldsForm = document.getElementById('customFieldsReorderForm');
        if (customFieldsList && customFieldsForm) {
            function updateCustomOrderBadges() {
                const items = customFieldsList.querySelectorAll('details.template-editor');
                items.forEach((item, idx) => {
                    const badge = item.querySelector('[data-custom-order-badge]');
                    if (badge) badge.textContent = idx + 1;
                });
            }

            customFieldsList.addEventListener('click', (e) => {
                const moveUp = e.target.closest('[data-move-custom="up"]');
                const moveDown = e.target.closest('[data-move-custom="down"]');
                if (!moveUp && !moveDown) return;

                const item = e.target.closest('details.template-editor');
                if (!item) return;

                if (moveUp) {
                    const prev = item.previousElementSibling;
                    if (prev) {
                        customFieldsList.insertBefore(item, prev);
                        updateCustomOrderBadges();
                        customFieldsForm.submit();
                    }
                } else if (moveDown) {
                    const next = item.nextElementSibling;
                    if (next) {
                        customFieldsList.insertBefore(next, item);
                        updateCustomOrderBadges();
                        customFieldsForm.submit();
                    }
                }
            });
        }

        // ================= SERVICE HOURS MODAL & PREVIEW =================
        const editServiceHoursModal = document.getElementById('editServiceHoursModal');
        const modalBuildInput = document.getElementById('modal_build_hours');
        const modalRunInput = document.getElementById('modal_run_hours');
        const modalIsOpenPo = document.getElementById('modal_is_open_po');
        const modalBuildWrap = document.getElementById('modal_build_hours_wrap');
        const modalRunWrap = document.getElementById('modal_run_hours_wrap');
        const modalTotalAllocatedPreview = document.getElementById('modal_total_allocated_preview');

        function updateServiceHoursPreview() {
            if (!modalTotalAllocatedPreview) return;
            const isOpenPo = modalIsOpenPo && modalIsOpenPo.checked;

            if (isOpenPo) {
                if (modalBuildInput) modalBuildInput.disabled = true;
                if (modalRunInput) modalRunInput.disabled = true;
                if (modalBuildWrap) modalBuildWrap.style.opacity = '0.45';
                if (modalRunWrap) modalRunWrap.style.opacity = '0.45';
                modalTotalAllocatedPreview.innerHTML = '<span style="color:#0284c7;">♾️ Open PO (Hourly / No Budget Cap)</span>';
            } else {
                if (modalBuildInput) modalBuildInput.disabled = false;
                if (modalRunInput) modalRunInput.disabled = false;
                if (modalBuildWrap) modalBuildWrap.style.opacity = '1';
                if (modalRunWrap) modalRunWrap.style.opacity = '1';
                const build = parseFloat(modalBuildInput?.value || 0) || 0;
                const run = parseFloat(modalRunInput?.value || 0) || 0;
                modalTotalAllocatedPreview.textContent = `${(build + run).toFixed(2)} hrs`;
            }
        }

        if (modalBuildInput) modalBuildInput.addEventListener('input', updateServiceHoursPreview);
        if (modalRunInput) modalRunInput.addEventListener('input', updateServiceHoursPreview);
        if (modalIsOpenPo) modalIsOpenPo.addEventListener('change', updateServiceHoursPreview);

        // Also handle project create/edit page Open PO checkbox
        const projectIsOpenPo = document.getElementById('project_is_open_po');
        const projectBuildHours = document.getElementById('project_build_hours');
        const projectRunHours = document.getElementById('project_run_hours');
        if (projectIsOpenPo) {
            function syncProjectOpenPo() {
                const checked = projectIsOpenPo.checked;
                if (projectBuildHours) {
                    projectBuildHours.disabled = checked;
                    projectBuildHours.closest('label').style.opacity = checked ? '0.45' : '1';
                }
                if (projectRunHours) {
                    projectRunHours.disabled = checked;
                    projectRunHours.closest('label').style.opacity = checked ? '0.45' : '1';
                }
            }
            projectIsOpenPo.addEventListener('change', syncProjectOpenPo);
            syncProjectOpenPo();
        }

        document.querySelectorAll('[data-action="edit-capacity"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (!editServiceHoursModal) return;
                const projectId = btn.getAttribute('data-project-id');
                const projectName = btn.getAttribute('data-project-name');
                const buildHours = btn.getAttribute('data-build-hours') || '0.00';
                const runHours = btn.getAttribute('data-run-hours') || '0.00';
                const isOpenPo = btn.getAttribute('data-is-open-po') === '1';

                document.getElementById('service_hours_project_id').value = projectId || '';
                const projNameEl = document.getElementById('serviceHoursModalProjectName');
                if (projNameEl) projNameEl.textContent = projectName || 'Project';

                if (modalIsOpenPo) modalIsOpenPo.checked = isOpenPo;
                if (modalBuildInput) modalBuildInput.value = parseFloat(buildHours).toFixed(2);
                if (modalRunInput) modalRunInput.value = parseFloat(runHours).toFixed(2);
                updateServiceHoursPreview();

                editServiceHoursModal.hidden = false;
            });
        });

        // ================= DETAILED WORK LOG: BILLING STATUS & BULK ACTIONS =================
        const workLogsTable = document.getElementById('workLogsReportTable');
        const bulkBillingToolbar = document.getElementById('bulkBillingToolbar');
        const selectAllCheckbox = document.getElementById('selectAllLogsCheckbox');
        const selectedLogCount = document.getElementById('selectedLogCount');
        const selectedLogHours = document.getElementById('selectedLogHours');
        const markBilledModal = document.getElementById('markBilledModal');
        const markBilledHiddenInputs = document.getElementById('markBilledHiddenInputs');
        const markBilledSummary = document.getElementById('markBilledSummary');

        function getSelectedLogCheckboxes() {
            return workLogsTable ? Array.from(workLogsTable.querySelectorAll('.log-select-checkbox:checked')) : [];
        }

        function updateBulkToolbar() {
            if (!bulkBillingToolbar || !workLogsTable) return;
            const checked = getSelectedLogCheckboxes();
            if (checked.length > 0) {
                let totalHours = 0;
                checked.forEach((cb) => {
                    totalHours += parseFloat(cb.getAttribute('data-hours')) || 0;
                });
                if (selectedLogCount) selectedLogCount.textContent = checked.length;
                if (selectedLogHours) selectedLogHours.textContent = totalHours.toFixed(2);
                bulkBillingToolbar.hidden = false;
            } else {
                bulkBillingToolbar.hidden = true;
                if (selectAllCheckbox) selectAllCheckbox.checked = false;
            }
        }

        if (workLogsTable) {
            workLogsTable.addEventListener('change', (e) => {
                if (e.target.classList.contains('log-select-checkbox')) {
                    updateBulkToolbar();
                }
            });
        }

        if (selectAllCheckbox && workLogsTable) {
            selectAllCheckbox.addEventListener('change', () => {
                const checkboxes = workLogsTable.querySelectorAll('.log-select-checkbox');
                checkboxes.forEach((cb) => {
                    cb.checked = selectAllCheckbox.checked;
                });
                updateBulkToolbar();
            });
        }

        const btnClearSelected = document.getElementById('btnClearSelected');
        if (btnClearSelected && workLogsTable) {
            btnClearSelected.addEventListener('click', () => {
                const checkboxes = workLogsTable.querySelectorAll('.log-select-checkbox');
                checkboxes.forEach((cb) => {
                    cb.checked = false;
                });
                if (selectAllCheckbox) selectAllCheckbox.checked = false;
                updateBulkToolbar();
            });
        }

        // Open Bulk Mark as Billed Modal
        const btnOpenBulkBillModal = document.getElementById('btnOpenBulkBillModal');
        if (btnOpenBulkBillModal && markBilledModal) {
            btnOpenBulkBillModal.addEventListener('click', () => {
                const checked = getSelectedLogCheckboxes();
                if (checked.length === 0) return;

                let totalHours = 0;
                let hiddenHtml = '';
                checked.forEach((cb) => {
                    const h = parseFloat(cb.getAttribute('data-hours')) || 0;
                    totalHours += h;
                    hiddenHtml += `<input type="hidden" name="ids[]" value="${cb.value}">`;
                });

                if (markBilledHiddenInputs) markBilledHiddenInputs.innerHTML = hiddenHtml;
                if (markBilledSummary) markBilledSummary.textContent = `${checked.length} entries selected (${totalHours.toFixed(2)} total billable hours)`;
                const subtext = document.getElementById('markBilledSubtext');
                if (subtext) subtext.textContent = 'Bulk update selected time entries';

                markBilledModal.hidden = false;
            });
        }

        // Bulk Mark as Unbilled
        const btnBulkUnbill = document.getElementById('btnBulkUnbill');
        if (btnBulkUnbill) {
            btnBulkUnbill.addEventListener('click', () => {
                const checked = getSelectedLogCheckboxes();
                if (checked.length === 0) return;
                if (!confirm(`Mark ${checked.length} selected entries as Unbilled?`)) return;

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `${appBasePath}/work-logs/mark-unbilled`;
                form.innerHTML = csrf_field_html();
                checked.forEach((cb) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = cb.value;
                    form.appendChild(input);
                });
                document.body.appendChild(form);
                form.submit();
            });
        }

        // Helper to obtain CSRF input html if available
        function csrf_field_html() {
            const tokenInput = document.querySelector('input[name="_csrf"]');
            return tokenInput ? `<input type="hidden" name="_csrf" value="${tokenInput.value}">` : '';
        }

        // Inline Billing Chip Click (1-click toggle / modal prompt)
        if (workLogsTable) {
            workLogsTable.addEventListener('click', (e) => {
                const unbilledChip = e.target.closest('.billing-chip.unbilled');
                const billedChip = e.target.closest('.billing-chip.billed');

                if (unbilledChip && markBilledModal) {
                    const logId = unbilledChip.getAttribute('data-log-id');
                    const row = unbilledChip.closest('tr');
                    const hours = row ? (row.getAttribute('data-hours') || '1.00') : '1.00';

                    if (markBilledHiddenInputs) {
                        markBilledHiddenInputs.innerHTML = `<input type="hidden" name="ids[]" value="${logId}">`;
                    }
                    if (markBilledSummary) {
                        markBilledSummary.textContent = `Time Log #${logId} (${parseFloat(hours).toFixed(2)} billable hours)`;
                    }
                    const subtext = document.getElementById('markBilledSubtext');
                    if (subtext) subtext.textContent = 'Record invoice number for this time log';

                    markBilledModal.hidden = false;
                } else if (billedChip) {
                    const logId = billedChip.getAttribute('data-log-id');
                    if (confirm(`Change this log back to Unbilled (pending invoice)?`)) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = `${appBasePath}/work-logs/mark-unbilled`;
                        form.innerHTML = `${csrf_field_html()}<input type="hidden" name="ids[]" value="${logId}">`;
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            });
        }

        // ================= PROJECT-WISE BILLING ACTIONS =================
        const markProjectBillingModal = document.getElementById('markProjectBillingModal');
        document.querySelectorAll('[data-action="bill-project"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (!markProjectBillingModal) return;
                const projectId = btn.getAttribute('data-project-id');
                const projectName = btn.getAttribute('data-project-name');
                const unbilledHours = btn.getAttribute('data-unbilled-hours') || '0.00';

                document.getElementById('modal_bill_project_id').value = projectId || '';
                const nameEl = document.getElementById('modal_bill_project_name');
                if (nameEl) nameEl.textContent = projectName || 'Project';
                const hoursEl = document.getElementById('modal_bill_project_unbilled_hours');
                if (hoursEl) hoursEl.textContent = unbilledHours;

                markProjectBillingModal.hidden = false;
            });
        });

        document.querySelectorAll('[data-action="unbill-project"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const projectId = btn.getAttribute('data-project-id');
                const projectName = btn.getAttribute('data-project-name') || 'Project';
                if (!confirm(`Mark all billed time logs for "${projectName}" as Unbilled?`)) return;

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `${appBasePath}/billing/mark-project`;
                form.innerHTML = `${csrf_field_html()}<input type="hidden" name="project_id" value="${projectId}"><input type="hidden" name="action" value="unbilled">`;
                document.body.appendChild(form);
                form.submit();
            });
        });

        // ================= PHASE-WISE BILLING ACTIONS =================
        const markPhaseBillingModal = document.getElementById('markPhaseBillingModal');
        document.querySelectorAll('[data-action="bill-phase"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (!markPhaseBillingModal) return;
                const phaseId = btn.getAttribute('data-phase-id');
                const phaseName = btn.getAttribute('data-phase-name');
                const projectName = btn.getAttribute('data-project-name');
                const unbilledHours = btn.getAttribute('data-unbilled-hours') || '0.00';

                document.getElementById('modal_bill_phase_id').value = phaseId || '';
                const projEl = document.getElementById('modal_bill_phase_project_name');
                if (projEl) projEl.textContent = projectName || 'Project';
                const nameEl = document.getElementById('modal_bill_phase_name');
                if (nameEl) nameEl.textContent = phaseName || 'Milestone Phase';
                const hoursEl = document.getElementById('modal_bill_phase_unbilled_hours');
                if (hoursEl) hoursEl.textContent = unbilledHours;

                markPhaseBillingModal.hidden = false;
            });
        });

        document.querySelectorAll('[data-action="unbill-phase"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const phaseId = btn.getAttribute('data-phase-id');
                const phaseName = btn.getAttribute('data-phase-name') || 'Phase';
                if (!confirm(`Mark all billed time logs for "${phaseName}" as Unbilled?`)) return;

                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `${appBasePath}/billing/mark-phase`;
                form.innerHTML = `${csrf_field_html()}<input type="hidden" name="phase_id" value="${phaseId}"><input type="hidden" name="action" value="unbilled">`;
                document.body.appendChild(form);
                form.submit();
            });
        });

        // ================= MOBILE DRAWER & BOTTOM NAV =================
        const mobileDrawer = document.getElementById('mobileDrawer');
        const mobileDrawerBackdrop = document.getElementById('mobileDrawerBackdrop');
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileBottomMoreBtn = document.getElementById('mobileBottomMoreBtn');
        const mobileDrawerClose = document.getElementById('mobileDrawerClose');

        function openMobileDrawer() {
            if (mobileDrawer && mobileDrawerBackdrop) {
                mobileDrawerBackdrop.hidden = false;
                void mobileDrawerBackdrop.offsetWidth;
                mobileDrawerBackdrop.classList.add('is-active');
                mobileDrawer.classList.add('is-active');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeMobileDrawer() {
            if (mobileDrawer && mobileDrawerBackdrop) {
                mobileDrawerBackdrop.classList.remove('is-active');
                mobileDrawer.classList.remove('is-active');
                document.body.style.overflow = '';
                setTimeout(() => {
                    if (!mobileDrawer.classList.contains('is-active')) {
                        mobileDrawerBackdrop.hidden = true;
                    }
                }, 280);
            }
        }

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', openMobileDrawer);
        }
        if (mobileBottomMoreBtn) {
            mobileBottomMoreBtn.addEventListener('click', openMobileDrawer);
        }
        if (mobileDrawerClose) {
            mobileDrawerClose.addEventListener('click', closeMobileDrawer);
        }
        if (mobileDrawerBackdrop) {
            mobileDrawerBackdrop.addEventListener('click', closeMobileDrawer);
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && mobileDrawer && mobileDrawer.classList.contains('is-active')) {
                closeMobileDrawer();
            }
        });

        // ================= MOBILE KANBAN COLUMN SWITCHER =================
        const mobileKanbanTabs = document.querySelectorAll('.mobile-kanban-tab');
        const kanbanBoard = document.getElementById('kanbanBoard');

        if (mobileKanbanTabs.length && kanbanBoard) {
            mobileKanbanTabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const targetStatus = tab.getAttribute('data-status-target');
                    if (!targetStatus) return;

                    mobileKanbanTabs.forEach(t => {
                        t.classList.remove('active');
                        t.setAttribute('aria-selected', 'false');
                    });
                    tab.classList.add('active');
                    tab.setAttribute('aria-selected', 'true');

                    const columns = kanbanBoard.querySelectorAll('.kanban-column');
                    columns.forEach(col => {
                        if (col.getAttribute('data-status') === targetStatus) {
                            col.classList.add('mobile-column-active');
                        } else {
                            col.classList.remove('mobile-column-active');
                        }
                    });
                });
            });
        }

        // ================= VIRTUAL KEYBOARD BOTTOM NAV HANDLING =================
        const formInputs = document.querySelectorAll('input:not([type="checkbox"]):not([type="radio"]), textarea, select');
        formInputs.forEach(input => {
            input.addEventListener('focus', () => {
                document.body.classList.add('keyboard-open');
            });
            input.addEventListener('blur', () => {
                // Small delay to allow tapping another input without flickering
                setTimeout(() => {
                    const activeTag = document.activeElement ? document.activeElement.tagName : '';
                    if (!['INPUT', 'TEXTAREA', 'SELECT'].includes(activeTag)) {
                        document.body.classList.remove('keyboard-open');
                    }
                }, 100);
            });
        });

        if (window.visualViewport) {
            const baseHeight = window.visualViewport.height;
            window.visualViewport.addEventListener('resize', () => {
                if (window.visualViewport.height < baseHeight * 0.75) {
                    document.body.classList.add('keyboard-open');
                } else if (!document.activeElement || !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
                    document.body.classList.remove('keyboard-open');
                }
            });
        }

        // ================= PROJECT ADD MENU TAB SWITCHING =================
        document.querySelectorAll('.add-menu').forEach(menu => {
            const tabs = menu.querySelectorAll('[data-add-tab]');
            const panes = menu.querySelectorAll('[data-add-pane]');
            tabs.forEach(tab => {
                tab.addEventListener('click', (e) => {
                    e.preventDefault();
                    const target = tab.getAttribute('data-add-tab');
                    tabs.forEach(t => t.classList.toggle('active', t === tab));
                    panes.forEach(pane => {
                        pane.classList.toggle('active', pane.getAttribute('data-add-pane') === target);
                    });
                });
            });
        });

        // ================= MOBILE PROJECT ACCORDION COLLAPSE =================
        document.addEventListener('click', (e) => {
            const phaseBanner = e.target.closest('[data-mobile-collapse="phase"]');
            if (phaseBanner) {
                const phaseCard = phaseBanner.closest('.mobile-phase-card');
                if (phaseCard) {
                    phaseCard.classList.toggle('collapsed');
                }
                return;
            }

            const listBanner = e.target.closest('[data-mobile-collapse="task-list"]');
            if (listBanner) {
                const listSection = listBanner.closest('.mobile-tasklist-section');
                if (listSection) {
                    listSection.classList.toggle('collapsed');
                }
                return;
            }
        });

        // ================= NOTIFICATION CENTER (Desktop + Mobile) =================
        const notifWrappers = document.querySelectorAll('[data-notifications-wrapper], #notificationsWrapper');
        if (notifWrappers.length > 0) {
            const escapeHtmlSafe = (str) => {
                if (str === null || str === undefined) return '';
                return String(str).replace(/[&<>"']/g, (s) => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                })[s]);
            };

            const timeAgo = (dateStr) => {
                if (!dateStr) return '';
                const d = new Date(dateStr.replace(' ', 'T') + '+05:30');
                const now = new Date();
                const diffSec = Math.floor((now - d) / 1000);
                if (isNaN(diffSec)) return dateStr;
                if (diffSec < 60) return 'Just now';
                if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m ago`;
                if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h ago`;
                if (diffSec < 172800) return 'Yesterday';
                return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
            };

            const getIcon = (type) => {
                switch (type) {
                    case 'task_assigned': return '📌';
                    case 'renewal_alert': return '⏳';
                    case 'urgent_task':
                    case 'important_notice': return '🚨';
                    case 'task_status': return '✅';
                    default: return '🔔';
                }
            };

            const renderNotifications = (data) => {
                const count = parseInt(data.count, 10) || 0;
                
                // Update all badges across desktop and mobile
                document.querySelectorAll('[data-notifications-badge], #notificationsBadge').forEach(badge => {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.style.display = count > 0 ? 'inline-block' : 'none';
                });

                // Update all count pills
                document.querySelectorAll('[data-notifications-count-pill], #notificationsCountPill').forEach(pill => {
                    pill.textContent = count;
                });

                const items = (data.notifications || []).slice(0, 20);
                const html = items.length === 0
                    ? '<div class="notifications-empty">All caught up! No new notifications.</div>'
                    : items.map(n => {
                        const isUnread = parseInt(n.is_read, 10) === 0;
                        const icon = getIcon(n.type);
                        const time = timeAgo(n.created_at);
                        const link = n.link_url || '#';
                        return `
                            <a href="${escapeHtmlSafe(link)}" class="notification-item ${isUnread ? 'unread' : ''}" data-notif-id="${n.id}">
                                <div class="notification-icon-wrap ${escapeHtmlSafe(n.type)}">${icon}</div>
                                <div class="notification-body">
                                    <div class="notification-item-title">${escapeHtmlSafe(n.title)}</div>
                                    <div class="notification-item-msg">${escapeHtmlSafe(n.message)}</div>
                                    <div class="notification-item-time">${escapeHtmlSafe(time)}</div>
                                </div>
                                ${isUnread ? '<div class="notification-unread-dot"></div>' : ''}
                            </a>
                        `;
                    }).join('');

                // Populate all list containers
                document.querySelectorAll('[data-notifications-list], #notificationsList').forEach(list => {
                    list.innerHTML = html;
                    list.querySelectorAll('.notification-item').forEach(item => {
                        item.addEventListener('click', (e) => {
                            const notifId = item.getAttribute('data-notif-id');
                            if (item.classList.contains('unread') && notifId) {
                                fetch(appBasePath + '/api/notifications/mark-read', {
                                    method: 'POST',
                                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                    body: 'id=' + encodeURIComponent(notifId)
                                }).catch(() => {});
                            }
                        });
                    });
                });
            };

            const fetchNotifications = () => {
                fetch(appBasePath + '/api/notifications')
                    .then(res => res.json())
                    .then(data => renderNotifications(data))
                    .catch(() => {});
            };

            // Toggle dropdown for each wrapper instance
            notifWrappers.forEach(wrapper => {
                const trigger = wrapper.querySelector('[data-notifications-trigger], #notificationsTriggerBtn');
                const dropdown = wrapper.querySelector('[data-notifications-dropdown], #notificationsDropdown');
                if (!trigger || !dropdown) return;

                trigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isHidden = dropdown.style.display === 'none' || !dropdown.style.display;
                    // Close other dropdowns first
                    document.querySelectorAll('[data-notifications-dropdown], #notificationsDropdown').forEach(d => {
                        if (d !== dropdown) d.style.display = 'none';
                    });
                    dropdown.style.display = isHidden ? 'flex' : 'none';
                    if (isHidden) {
                        fetchNotifications();
                    }
                });
            });

            // Close all dropdowns on click outside
            document.addEventListener('click', (e) => {
                if (!e.target.closest('[data-notifications-wrapper], #notificationsWrapper')) {
                    document.querySelectorAll('[data-notifications-dropdown], #notificationsDropdown').forEach(d => {
                        d.style.display = 'none';
                    });
                }
            });

            // Mark all read button listeners
            document.querySelectorAll('[data-notifications-mark-all-btn], #notificationsMarkAllBtn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    fetch(appBasePath + '/api/notifications/mark-all-read', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                    })
                    .then(res => res.json())
                    .then(() => {
                        document.querySelectorAll('[data-notifications-badge], #notificationsBadge').forEach(b => b.style.display = 'none');
                        document.querySelectorAll('[data-notifications-count-pill], #notificationsCountPill').forEach(p => p.textContent = '0');
                        document.querySelectorAll('.notification-item.unread').forEach(el => {
                            el.classList.remove('unread');
                            const dot = el.querySelector('.notification-unread-dot');
                            if (dot) dot.remove();
                        });
                    })
                    .catch(() => {});
                });
            });

            // Quick Links / App Switcher dropdown toggle
            const quickLinksWrappers = document.querySelectorAll('[data-quick-links-wrapper]');
            quickLinksWrappers.forEach(wrapper => {
                const trigger = wrapper.querySelector('[data-quick-links-trigger]');
                const dropdown = wrapper.querySelector('[data-quick-links-dropdown]');
                if (!trigger || !dropdown) return;

                trigger.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const isHidden = dropdown.style.display === 'none' || !dropdown.style.display;
                    // Close other dropdowns first
                    document.querySelectorAll('[data-notifications-dropdown], [data-quick-links-dropdown]').forEach(d => {
                        if (d !== dropdown) d.style.display = 'none';
                    });
                    dropdown.style.display = isHidden ? 'flex' : 'none';
                });
            });

            // Close quick links dropdown on click outside
            document.addEventListener('click', (e) => {
                if (!e.target.closest('[data-quick-links-wrapper]')) {
                    document.querySelectorAll('[data-quick-links-dropdown]').forEach(d => {
                        d.style.display = 'none';
                    });
                }
            });

            // Initial fetch & 60s periodic background sync
            fetchNotifications();
            setInterval(fetchNotifications, 60000);
        }

        // Universal Double-Submit Prevention for forms (Time Logs, Tasks, Projects)
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.dataset.submitting === 'true') {
                e.preventDefault();
                return;
            }

            const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
            if (submitBtn) {
                form.dataset.submitting = 'true';
                setTimeout(() => {
                    submitBtn.style.opacity = '0.7';
                    submitBtn.style.pointerEvents = 'none';
                }, 50);

                // Auto-reset after 5 seconds in case of validation or canceled request
                setTimeout(() => {
                    form.dataset.submitting = 'false';
                    submitBtn.style.opacity = '';
                    submitBtn.style.pointerEvents = '';
                }, 5000);
            }
        });
    }
});





