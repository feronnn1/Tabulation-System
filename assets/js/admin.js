/**
 * admin.js
 * Handles all AJAX interactions for the Admin Portal
 * (dashboard.php and event.php).
 */

const BASE_URL = (function () {
    // Derive BASE_URL from the current path (works whether we're in /admin/ or /admin/api/)
    const path = window.location.pathname;
    const idx = path.indexOf('/admin/');
    return idx !== -1 ? path.substring(0, idx) : '';
})();

function showAlert(message, type = 'success') {
    const box = document.getElementById('alertBox');
    if (!box) {
        alert(message);
        return;
    }
    box.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
    setTimeout(() => { box.innerHTML = ''; }, 4000);
}

async function postJSON(url, payload) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });
    let data;
    try {
        data = await res.json();
    } catch (e) {
        data = { success: false, message: 'Unexpected server response.' };
    }
    return data;
}

/* =========================================================
   DASHBOARD PAGE (events list + create modal)
   ========================================================= */

function openCreateModal() {
    const modal = document.getElementById('createModal');
    if (modal) modal.classList.add('show');
}
function closeCreateModal() {
    const modal = document.getElementById('createModal');
    if (modal) modal.classList.remove('show');
}

document.addEventListener('DOMContentLoaded', () => {

    // ---- Create event form ----
    const createForm = document.getElementById('createEventForm');
    if (createForm) {
        createForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                event_name: document.getElementById('event_name').value,
                description: document.getElementById('description').value,
                max_score: document.getElementById('max_score').value
            };
            const data = await postJSON(`${BASE_URL}/admin/api/create_event.php`, payload);
            if (data.success) {
                window.location.reload();
            } else {
                showAlert(data.message, 'error');
            }
        });
    }

    // ---- Event status select (per card on dashboard) ----
    document.querySelectorAll('.status-select').forEach(sel => {
        sel.addEventListener('change', async () => {
            const eventId = sel.dataset.id;
            const status = sel.value;
            const data = await postJSON(`${BASE_URL}/admin/api/update_event_status.php`, { event_id: eventId, status });
            if (data.success) {
                showAlert('Status updated.', 'success');
                const card = sel.closest('[data-event-id]');
                if (card) {
                    const badge = card.querySelector('.badge');
                    if (badge) {
                        badge.className = `badge badge-${status}`;
                        badge.textContent = status;
                    }
                }
            } else {
                showAlert(data.message, 'error');
            }
        });
    });

    // ---- Delete event ----
    document.querySelectorAll('.delete-event-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Delete this event? This removes all its contestants, judges, criteria and scores permanently.')) return;
            const eventId = btn.dataset.id;
            const data = await postJSON(`${BASE_URL}/admin/api/delete_event.php`, { event_id: eventId });
            if (data.success) {
                btn.closest('[data-event-id]').remove();
            } else {
                showAlert(data.message, 'error');
            }
        });
    });

    /* =========================================================
       EVENT DETAIL PAGE (tabs, contestants, criteria, judges)
       ========================================================= */

    // ---- Tabs ----
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(`tab-${btn.dataset.tab}`).classList.add('active');
        });
    });

    // ---- Add contestant ----
    const addContestantForm = document.getElementById('addContestantForm');
    if (addContestantForm) {
        addContestantForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                event_id: typeof EVENT_ID !== 'undefined' ? EVENT_ID : document.querySelector('input[name=event_id]').value,
                contestant_number: document.getElementById('contestant_number').value,
                full_name: document.getElementById('full_name').value
            };
            const data = await postJSON(`${BASE_URL}/admin/api/add_contestant.php`, payload);
            if (data.success) {
                const tbody = document.getElementById('contestantsTableBody');
                const emptyRow = tbody.querySelector('td[colspan]');
                if (emptyRow) emptyRow.closest('tr').remove();
                const tr = document.createElement('tr');
                tr.dataset.id = data.contestant.contestant_id;
                tr.innerHTML = `
                    <td>#${data.contestant.contestant_number}</td>
                    <td>${escapeHtml(data.contestant.full_name)}</td>
                    <td style="text-align:right;">
                        <button class="btn btn-sm btn-danger delete-contestant-btn" data-id="${data.contestant.contestant_id}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                            Remove
                        </button>
                    </td>`;
                tbody.appendChild(tr);
                bindDeleteContestantButtons();
                addContestantForm.reset();
                showAlert('Contestant added.', 'success');
            } else {
                showAlert(data.message, 'error');
            }
        });
    }

    bindDeleteContestantButtons();

    // ---- Criteria rows ----
    const addCriteriaRowBtn = document.getElementById('addCriteriaRowBtn');
    if (addCriteriaRowBtn) {
        addCriteriaRowBtn.addEventListener('click', () => {
            const container = document.getElementById('criteriaRows');
            const row = document.createElement('div');
            row.className = 'flex-gap criteria-row';
            row.style.alignItems = 'flex-end';
            row.innerHTML = `
                <div style="flex:3; min-width:180px;">
                    <label>Criteria Name</label>
                    <input type="text" class="crit-name" placeholder="e.g. Talent">
                </div>
                <div style="flex:1; min-width:110px;">
                    <label>Weight (%)</label>
                    <input type="number" class="crit-weight" value="0" min="0" max="100" step="0.01">
                </div>
                <button type="button" class="btn btn-sm btn-danger remove-criteria-row">&times;</button>`;
            container.appendChild(row);
            bindRemoveCriteriaButtons();
            row.querySelectorAll('.crit-weight, .crit-name').forEach(inp => {
                inp.addEventListener('input', recalcWeightTotal);
            });
            recalcWeightTotal();
        });
    }

    bindRemoveCriteriaButtons();
    document.querySelectorAll('.crit-weight, .crit-name').forEach(inp => {
        inp.addEventListener('input', recalcWeightTotal);
    });

    const saveCriteriaBtn = document.getElementById('saveCriteriaBtn');
    if (saveCriteriaBtn) {
        saveCriteriaBtn.addEventListener('click', async () => {
            const rows = [...document.querySelectorAll('.criteria-row')].map(row => ({
                criteria_name: row.querySelector('.crit-name').value,
                weight_percentage: parseFloat(row.querySelector('.crit-weight').value) || 0
            }));
            const data = await postJSON(`${BASE_URL}/admin/api/save_criteria.php`, {
                event_id: EVENT_ID,
                criteria: rows
            });
            if (data.success) {
                showAlert('Criteria saved!', 'success');
                setTimeout(() => window.location.reload(), 800);
            } else {
                showAlert(data.message, 'error');
            }
        });
    }

    // ---- Add judge ----
    const addJudgeForm = document.getElementById('addJudgeForm');
    if (addJudgeForm) {
        addJudgeForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                event_id: EVENT_ID,
                judge_name: document.getElementById('judge_name').value
            };
            const data = await postJSON(`${BASE_URL}/admin/api/generate_judge.php`, payload);
            if (data.success) {
                const tbody = document.getElementById('judgesTableBody');
                const emptyRow = tbody.querySelector('td[colspan]');
                if (emptyRow) emptyRow.closest('tr').remove();
                const tr = document.createElement('tr');
                tr.dataset.id = data.judge.judge_id;
                tr.innerHTML = `
                    <td>${escapeHtml(data.judge.judge_name)}</td>
                    <td>
                        <span class="passcode-box">${escapeHtml(data.judge.passcode)}</span>
                        <button type="button" class="copy-btn" data-passcode="${escapeHtml(data.judge.passcode)}" onclick="copyPasscode(this)">Copy</button>
                    </td>
                    <td><span class="badge badge-ongoing">active</span></td>
                    <td style="text-align:right;">
                        <button class="btn btn-sm btn-danger delete-judge-btn" data-id="${data.judge.judge_id}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:13px;height:13px;"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                            Remove
                        </button>
                    </td>`;
                tbody.appendChild(tr);
                bindDeleteJudgeButtons();
                addJudgeForm.reset();
                showAlert(`Judge created. Passcode: ${data.judge.passcode}`, 'success');
            } else {
                showAlert(data.message, 'error');
            }
        });
    }

    bindDeleteJudgeButtons();
});

const WEIGHT_BAR_PALETTE = ['#7c5cff', '#22d3ee', '#34d399', '#fbbf24', '#f87171', '#a78bfa', '#38bdf8', '#fb923c'];

function recalcWeightTotal() {
    const rows = [...document.querySelectorAll('.criteria-row')];
    const total = rows.reduce((sum, row) => sum + (parseFloat(row.querySelector('.crit-weight').value) || 0), 0);

    const display = document.getElementById('weightTotalDisplay');
    if (display) {
        display.textContent = `Total: ${total.toFixed(2)}%`;
        display.className = `badge ${Math.abs(total - 100) < 0.01 ? 'badge-ongoing' : 'badge-upcoming'}`;
    }

    const bar = document.getElementById('weightBarContainer');
    if (bar) {
        bar.innerHTML = rows.map((row, i) => {
            const pct = Math.max(0, parseFloat(row.querySelector('.crit-weight').value) || 0);
            const name = row.querySelector('.crit-name').value || 'Untitled';
            const color = WEIGHT_BAR_PALETTE[i % WEIGHT_BAR_PALETTE.length];
            return `<div class="weight-bar-segment" style="width:${pct}%; background:${color};" title="${escapeHtml(name)}: ${pct}%"></div>`;
        }).join('');
    }
}

function copyPasscode(btn) {
    const code = btn.dataset.passcode;
    navigator.clipboard.writeText(code).then(() => {
        const original = btn.textContent;
        btn.textContent = 'Copied!';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.textContent = original;
            btn.classList.remove('copied');
        }, 1500);
    }).catch(() => {
        showAlert('Could not copy automatically - please copy the passcode manually.', 'error');
    });
}

function bindRemoveCriteriaButtons() {
    document.querySelectorAll('.remove-criteria-row').forEach(btn => {
        btn.onclick = () => {
            btn.closest('.criteria-row').remove();
            recalcWeightTotal();
        };
    });
}

function bindDeleteContestantButtons() {
    document.querySelectorAll('.delete-contestant-btn').forEach(btn => {
        btn.onclick = async () => {
            if (!confirm('Remove this contestant?')) return;
            const data = await postJSON(`${BASE_URL}/admin/api/delete_contestant.php`, { contestant_id: btn.dataset.id });
            if (data.success) {
                btn.closest('tr').remove();
            } else {
                showAlert(data.message, 'error');
            }
        };
    });
}

function bindDeleteJudgeButtons() {
    document.querySelectorAll('.delete-judge-btn').forEach(btn => {
        btn.onclick = async () => {
            if (!confirm('Remove this judge? Their passcode will stop working.')) return;
            const data = await postJSON(`${BASE_URL}/admin/api/delete_judge.php`, { judge_id: btn.dataset.id });
            if (data.success) {
                btn.closest('tr').remove();
            } else {
                showAlert(data.message, 'error');
            }
        };
    });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
