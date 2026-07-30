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

    document.querySelectorAll('.timelog-table [data-breakdown-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const row = toggle.closest('tr')?.nextElementSibling;
            if (!row || !row.hasAttribute('data-breakdown-row')) {
                return;
            }
            const expanded = !row.hidden;
            row.hidden = expanded;
            toggle.setAttribute('aria-expanded', String(!expanded));
            toggle.classList.toggle('expanded', !expanded);
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

        kanbanBoard.querySelectorAll('.kanban-card[draggable="true"]').forEach((card) => {
            card.addEventListener('dragstart', (event) => {
                draggedCard = card;
                card.classList.add('dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', card.dataset.taskId || '');
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
                kanbanBoard.querySelectorAll('.kanban-column.drop-target').forEach((column) => column.classList.remove('drop-target'));
                draggedCard = null;
            });
        });

        // The whole column (not just the card stack) is the drop target, so
        // hovering over the header or empty padding still accepts the drop.
        kanbanBoard.querySelectorAll('.kanban-column').forEach((column) => {
            column.addEventListener('dragover', (event) => {
                if (!draggedCard) {
                    return;
                }
                event.preventDefault();
                event.dataTransfer.dropEffect = 'move';
                column.classList.add('drop-target');
            });

            column.addEventListener('dragleave', (event) => {
                if (!column.contains(event.relatedTarget)) {
                    column.classList.remove('drop-target');
                }
            });

            column.addEventListener('drop', async (event) => {
                event.preventDefault();
                column.classList.remove('drop-target');

                if (!draggedCard) {
                    return;
                }

                const sourceColumn = draggedCard.closest('.kanban-column');
                const status = column.dataset.status || '';
                if (!sourceColumn || sourceColumn === column) {
                    return;
                }

                await postMove('/tasks/status', {
                    task_id: draggedCard.dataset.taskId,
                    project_id: draggedCard.dataset.kanbanProjectId,
                    status,
                });

                const stack = column.querySelector('.kanban-stack');
                stack.insertBefore(draggedCard, stack.querySelector('.kanban-empty'));
                const statusSelect = draggedCard.querySelector('.kanban-move select[name="status"]');
                if (statusSelect) {
                    statusSelect.value = status;
                }
                updateKanbanCount(sourceColumn);
                updateKanbanCount(column);
            });
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
