const panel = document.getElementById('historial-calendar-panel');

if (panel) {
    const endpoint = panel.dataset.calendarEndpoint;
    const initialNode = document.getElementById('historial-calendar-initial');
    const monthLabelNode = document.getElementById('historial-month-label');
    const weeksNode = document.getElementById('historial-calendar-weeks');
    const detailsNode = document.getElementById('historial-day-details');
    const detailsDateNode = document.getElementById('historial-day-details-date');
    const detailsMealsNode = document.getElementById('historial-day-details-meals');
    const detailsWorkoutsNode = document.getElementById('historial-day-details-workouts');
    const prevButton = document.getElementById('historial-prev-month');
    const nextButton = document.getElementById('historial-next-month');

    let state = { month_value: '' };
    let isLoading = false;

    const escapeHtml = (value) =>
        String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');

    const readInitialPayload = () => {
        if (!initialNode) return null;
        try {
            return JSON.parse(initialNode.textContent || '{}');
        } catch (_error) {
            return null;
        }
    };

    const normalizeMonth = (value) => {
        if (typeof value !== 'string') return null;
        return /^\d{4}-\d{2}$/.test(value) ? value : null;
    };

    const shiftMonthValue = (monthValue, monthDelta) => {
        const safeValue = normalizeMonth(monthValue);
        if (!safeValue) return null;

        const [yearRaw, monthRaw] = safeValue.split('-');
        const year = Number.parseInt(yearRaw, 10);
        const monthIndex = Number.parseInt(monthRaw, 10) - 1;

        if (Number.isNaN(year) || Number.isNaN(monthIndex)) return null;

        const date = new Date(Date.UTC(year, monthIndex + monthDelta, 1));
        const nextYear = date.getUTCFullYear();
        const nextMonth = String(date.getUTCMonth() + 1).padStart(2, '0');

        return `${nextYear}-${nextMonth}`;
    };

    const formatDateLabel = (dateKey) => {
        if (typeof dateKey !== 'string') return '';
        const [year, month, day] = dateKey.split('-');
        if (!year || !month || !day) return dateKey;
        return `${day}/${month}/${year}`;
    };

    const mealBlock = (meal) => {
        const totals = meal?.totals ?? {};
        const items = Array.isArray(meal?.items) ? meal.items : [];
        const list = items.map((item) => `<li>${escapeHtml(item.name || 'Alimento')} (x${escapeHtml(item.quantity ?? 1)})</li>`).join('');
        return `
            <article class="day-record day-record--meal">
                <h5>${escapeHtml(meal?.meal_type || 'Comida')} - ${escapeHtml(meal?.registered_at || '')}</h5>
                <p>Kcal ${escapeHtml(totals.calories ?? 0)} | P ${escapeHtml(totals.protein ?? 0)} | C ${escapeHtml(totals.carbs ?? 0)} | G ${escapeHtml(totals.fat ?? 0)}</p>
                ${meal?.notes ? `<p class="day-record__notes">Nota: ${escapeHtml(meal.notes)}</p>` : ''}
                ${list ? `<ul>${list}</ul>` : ''}
            </article>
        `;
    };

    const workoutBlock = (workout) => {
        const totals = workout?.totals ?? {};
        const items = Array.isArray(workout?.items) ? workout.items : [];
        const list = items.map((item) => {
            const setWeights = Array.isArray(item.set_weights) ? item.set_weights : [];
            const weightsLabel = setWeights.length ? ` | Kg ${escapeHtml(setWeights.join(', '))}` : '';
            return `<li>${escapeHtml(item.name || 'Ejercicio')} (S ${escapeHtml(item.sets ?? 0)}, R ${escapeHtml(item.reps ?? 0)}${weightsLabel})</li>`;
        }).join('');
        return `
            <article class="day-record day-record--workout">
                <h5>${escapeHtml(workout?.training_type || 'Entrenamiento')} - ${escapeHtml(workout?.registered_at || '')}</h5>
                <p>Vol ${escapeHtml(totals.volume ?? 0)} | Series ${escapeHtml(totals.sets ?? 0)} | Reps ${escapeHtml(totals.reps ?? 0)}</p>
                ${workout?.notes ? `<p class="day-record__notes">Nota: ${escapeHtml(workout.notes)}</p>` : ''}
                ${list ? `<ul>${list}</ul>` : ''}
            </article>
        `;
    };

    const renderDayDetails = (dateKey) => {
        if (!detailsNode || !detailsDateNode || !detailsMealsNode || !detailsWorkoutsNode) return;

        const details = state.day_details?.[dateKey];
        const meals = Array.isArray(details?.meals) ? details.meals : [];
        const workouts = Array.isArray(details?.workouts) ? details.workouts : [];

        detailsDateNode.textContent = `Detalle de ${formatDateLabel(dateKey)}`;
        detailsMealsNode.innerHTML = meals.length
            ? meals.map(mealBlock).join('')
            : '<p class="day-empty">Sin comidas registradas.</p>';
        detailsWorkoutsNode.innerHTML = workouts.length
            ? workouts.map(workoutBlock).join('')
            : '<p class="day-empty">Sin entrenamientos registrados.</p>';
        detailsNode.hidden = false;
    };

    const renderWeeks = (weeks) => {
        if (!weeksNode) return;

        const html = (Array.isArray(weeks) ? weeks : []).map((week) => {
            const cells = (Array.isArray(week) ? week : []).map((cell) => {
                const day = Number.parseInt(String(cell.day ?? ''), 10) || '';
                const status = typeof cell.status === 'string' ? cell.status : 'none';
                const isCurrentMonth = Boolean(cell.is_current_month);
                const isToday = Boolean(cell.is_today);
                const hasWorkout = Boolean(cell.has_workout);
                const hasAnyRecord = Boolean(cell.has_any_record);
                const date = typeof cell.date === 'string' ? cell.date : '';

                return `
                    <div
                        class="historial-day historial-day--${status}${hasWorkout ? ' historial-day--workout' : ''}${isCurrentMonth ? '' : ' historial-day--muted'}${isToday ? ' historial-day--today' : ''}${hasAnyRecord ? ' historial-day--clickable' : ''}"
                        role="cell"
                        title="${formatDateLabel(date)}"
                        data-date="${date}"
                        data-has-record="${hasAnyRecord ? '1' : '0'}"
                    >
                        <span class="historial-day__number">${day}</span>
                    </div>
                `;
            }).join('');

            return `<div class="historial-calendar__week" role="row">${cells}</div>`;
        }).join('');

        weeksNode.innerHTML = html;
    };

    const setLoading = (loading) => {
        isLoading = loading;
        panel.classList.toggle('historial-panel--loading', loading);
        if (prevButton) prevButton.disabled = loading;
        if (nextButton) nextButton.disabled = loading;
    };

    const applyPayload = (payload, pushHistory = true) => {
        if (!payload || typeof payload !== 'object') return;

        const monthValue = normalizeMonth(payload.month_value);
        if (!monthValue) return;

        state = {
            ...state,
            ...payload,
            month_value: monthValue,
        };

        if (monthLabelNode) {
            monthLabelNode.textContent = payload.month_label || monthValue;
        }

        renderWeeks(payload.weeks || []);
        if (detailsNode) {
            detailsNode.hidden = true;
        }

        if (pushHistory) {
            const url = new URL(window.location.href);
            url.searchParams.set('month', monthValue);
            window.history.pushState({ month: monthValue }, '', url.toString());
        }
    };

    const loadMonth = async (monthValue, pushHistory = true) => {
        const safeMonth = normalizeMonth(monthValue);
        if (!safeMonth || !endpoint || isLoading) return;

        setLoading(true);

        try {
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('month', safeMonth);

            const response = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) throw new Error('failed_calendar_fetch');

            const payload = await response.json();
            applyPayload(payload, pushHistory);
        } catch (_error) {
            // Silent fail to keep UI stable if API temporarily fails.
        } finally {
            setLoading(false);
        }
    };

    prevButton?.addEventListener('click', () => {
        const targetMonth = shiftMonthValue(state.month_value, -1);
        if (targetMonth) loadMonth(targetMonth);
    });

    nextButton?.addEventListener('click', () => {
        const targetMonth = shiftMonthValue(state.month_value, 1);
        if (targetMonth) loadMonth(targetMonth);
    });

    window.addEventListener('keydown', (event) => {
        const activeTag = document.activeElement?.tagName?.toLowerCase();
        if (activeTag === 'input' || activeTag === 'textarea' || activeTag === 'select') {
            return;
        }

        if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
            return;
        }

        event.preventDefault();

        const baseDelta = event.key === 'ArrowRight' ? 1 : -1;
        const delta = event.shiftKey ? baseDelta * 12 : baseDelta;
        const targetMonth = shiftMonthValue(state.month_value, delta);
        if (targetMonth) {
            loadMonth(targetMonth);
        }
    });

    window.addEventListener('popstate', () => {
        const urlMonth = new URL(window.location.href).searchParams.get('month');
        const safeMonth = normalizeMonth(urlMonth);
        if (safeMonth) {
            loadMonth(safeMonth, false);
        }
    });

    weeksNode?.addEventListener('click', (event) => {
        const cell = event.target.closest('.historial-day');
        if (!cell || cell.dataset.hasRecord !== '1') return;

        const dateKey = cell.dataset.date || '';
        if (!dateKey) return;

        weeksNode.querySelectorAll('.historial-day--selected').forEach((node) => {
            node.classList.remove('historial-day--selected');
        });
        cell.classList.add('historial-day--selected');
        renderDayDetails(dateKey);
    });

    const initialPayload = readInitialPayload();
    applyPayload(initialPayload, false);
}
