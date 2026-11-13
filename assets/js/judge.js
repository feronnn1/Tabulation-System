/**
 * judge.js
 * Renders the criteria sliders + comment box for the selected contestant,
 * autosaves every change via the Fetch API (debounced, no page reload),
 * shows a live weighted-total preview, tracks per-contestant progress,
 * backs the explicit "Save" button with a full batch re-submit, and
 * handles the "Confirm Score" lock flow (with a warning modal) that
 * permanently prevents further edits for that contestant.
 */

let currentContestantId = null;
const saveTimers = {};        // one debounce timer per criteria slider
let commentTimer = null;      // debounce timer for the comment box
const lockedSet = new Set(typeof lockedContestantIds !== 'undefined' ? lockedContestantIds : []);

document.addEventListener('DOMContentLoaded', () => {
    if (typeof CONTESTANTS === 'undefined' || !CONTESTANTS.length) return;

    document.querySelectorAll('.contestant-chip').forEach(chip => {
        chip.addEventListener('click', () => selectContestant(parseInt(chip.dataset.id, 10)));
    });

    const commentBox = document.getElementById('commentBox');
    if (commentBox) {
        commentBox.addEventListener('input', onCommentInput);
    }

    const saveAllBtn = document.getElementById('saveAllBtn');
    if (saveAllBtn) {
        saveAllBtn.addEventListener('click', saveAllForCurrentContestant);
    }

    const confirmScoreBtn = document.getElementById('confirmScoreBtn');
    if (confirmScoreBtn) {
        confirmScoreBtn.addEventListener('click', () => {
            document.getElementById('confirmModal').classList.add('show');
        });
    }
    const confirmModalCancelBtn = document.getElementById('confirmModalCancelBtn');
    if (confirmModalCancelBtn) {
        confirmModalCancelBtn.addEventListener('click', () => {
            document.getElementById('confirmModal').classList.remove('show');
        });
    }
    const confirmModalYesBtn = document.getElementById('confirmModalYesBtn');
    if (confirmModalYesBtn) {
        confirmModalYesBtn.addEventListener('click', doConfirmScore);
    }

    refreshAllChipProgress();
    // Auto-select the first contestant on load.
    selectContestant(CONTESTANTS[0].contestant_id);
});

/* =========================================================
   Contestant switching
   ========================================================= */

function selectContestant(contestantId) {
    currentContestantId = contestantId;
    const isLocked = lockedSet.has(contestantId);

    document.querySelectorAll('.contestant-chip').forEach(chip => {
        chip.classList.toggle('active', parseInt(chip.dataset.id, 10) === contestantId);
    });

    renderCriteria(contestantId, isLocked);
    loadCommentForContestant(contestantId, isLocked);
    updateLiveTotal(contestantId);
    updateLockUI(isLocked);
}

function updateLockUI(isLocked) {
    const banner = document.getElementById('lockedBanner');
    const commentBox = document.getElementById('commentBox');
    const saveAllBtn = document.getElementById('saveAllBtn');
    const confirmScoreBtn = document.getElementById('confirmScoreBtn');

    if (banner) banner.style.display = isLocked ? 'block' : 'none';
    if (commentBox) commentBox.disabled = isLocked || EVENT_LOCKED;
    if (saveAllBtn) saveAllBtn.disabled = isLocked || EVENT_LOCKED;
    if (confirmScoreBtn) {
        confirmScoreBtn.disabled = isLocked || EVENT_LOCKED;
        confirmScoreBtn.innerHTML = isLocked
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Confirmed'
            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Confirm Score';
    }
}

function renderCriteria(contestantId, isLocked) {
    const container = document.getElementById('criteriaContainer');
    const scoresForContestant = existingScores[contestantId] || {};
    const disabled = isLocked || EVENT_LOCKED;

    let html = '';
    CRITERIA.forEach(crit => {
        const existing = scoresForContestant[crit.criteria_id];
        const value = existing !== undefined ? existing : 0;

        html += `
        <div class="criteria-block">
            <div class="criteria-header">
                <h3>${escapeHtml(crit.criteria_name)}</h3>
                <span class="criteria-weight">${crit.weight_percentage}% weight</span>
            </div>
            <div class="flex-between">
                <span class="muted small">0</span>
                <span class="score-value" id="score-display-${crit.criteria_id}">${value}</span>
                <span class="muted small">${MAX_SCORE}</span>
            </div>
            <input type="range"
                   min="0" max="${MAX_SCORE}" step="1"
                   value="${value}"
                   data-criteria-id="${crit.criteria_id}"
                   class="score-slider"
                   ${disabled ? 'disabled' : ''}
                   oninput="onSliderInput(${contestantId}, ${crit.criteria_id}, this.value)">
        </div>`;
    });

    container.innerHTML = html;
}

/* =========================================================
   Scoring: slider input, debounce, autosave
   ========================================================= */

function onSliderInput(contestantId, criteriaId, value) {
    if (lockedSet.has(contestantId)) return; // extra safety; slider should already be disabled

    document.getElementById(`score-display-${criteriaId}`).textContent = value;

    if (!existingScores[contestantId]) existingScores[contestantId] = {};
    existingScores[contestantId][criteriaId] = parseFloat(value);

    updateLiveTotal(contestantId);
    refreshAllChipProgress();

    const key = `${contestantId}-${criteriaId}`;
    clearTimeout(saveTimers[key]);
    showSaveStatus('saving');
    saveTimers[key] = setTimeout(() => {
        saveScore(contestantId, criteriaId, value);
    }, 300);
}

async function saveScore(contestantId, criteriaId, value) {
    try {
        const res = await fetch(`${BASE_URL}/judge/api/submit_score.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ contestant_id: contestantId, criteria_id: criteriaId, raw_score: value })
        });
        const data = await res.json();
        if (data.success) {
            showSaveStatus('saved');
        } else {
            showSaveStatus('error', data.message);
            showToast(data.message || 'Could not save that score.', 'error');
        }
    } catch (err) {
        showSaveStatus('error', 'Network error');
        showToast('Network error - that score may not have saved. Try pressing Save.', 'error');
    }
}

/* =========================================================
   Comments: debounce autosave + char counter
   ========================================================= */

function loadCommentForContestant(contestantId, isLocked) {
    const box = document.getElementById('commentBox');
    if (!box) return;
    box.value = existingRemarks[contestantId] || '';
    box.disabled = isLocked || EVENT_LOCKED;
    updateCharCount();
}

function onCommentInput() {
    updateCharCount();
    if (EVENT_LOCKED || currentContestantId === null || lockedSet.has(currentContestantId)) return;

    existingRemarks[currentContestantId] = document.getElementById('commentBox').value;

    clearTimeout(commentTimer);
    showSaveStatus('saving');
    commentTimer = setTimeout(() => {
        saveComment(currentContestantId, existingRemarks[currentContestantId]);
    }, 800);
}

function updateCharCount() {
    const box = document.getElementById('commentBox');
    const counter = document.getElementById('commentCharCount');
    if (box && counter) {
        counter.textContent = `${box.value.length} / 2000`;
    }
}

async function saveComment(contestantId, comment) {
    try {
        const res = await fetch(`${BASE_URL}/judge/api/save_remark.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ contestant_id: contestantId, comment })
        });
        const data = await res.json();
        if (data.success) {
            showSaveStatus('saved');
        } else {
            showSaveStatus('error', data.message);
            showToast(data.message || 'Could not save that comment.', 'error');
        }
    } catch (err) {
        showSaveStatus('error', 'Network error');
        showToast('Network error - comment may not have saved.', 'error');
    }
}

/* =========================================================
   Explicit "Save" button - re-submits everything for this contestant
   ========================================================= */

async function saveAllForCurrentContestant() {
    if (currentContestantId === null || EVENT_LOCKED || lockedSet.has(currentContestantId)) return;

    const btn = document.getElementById('saveAllBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    const scoresForContestant = existingScores[currentContestantId] || {};
    const scoresPayload = CRITERIA.map(crit => ({
        criteria_id: crit.criteria_id,
        raw_score: scoresForContestant[crit.criteria_id] !== undefined ? scoresForContestant[crit.criteria_id] : 0
    }));
    const comment = document.getElementById('commentBox') ? document.getElementById('commentBox').value : '';

    try {
        const res = await fetch(`${BASE_URL}/judge/api/save_all.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ contestant_id: currentContestantId, scores: scoresPayload, comment })
        });
        const data = await res.json();
        if (data.success) {
            showSaveStatus('saved');
            refreshAllChipProgress();
            showToast('Scores and comment saved for this contestant.', 'success');
        } else {
            showToast(data.message || 'Save failed.', 'error');
        }
    } catch (err) {
        showToast('Network error - please try again.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save';
    }
}

/* =========================================================
   Confirm & lock flow
   ========================================================= */

async function doConfirmScore() {
    if (currentContestantId === null) return;

    const yesBtn = document.getElementById('confirmModalYesBtn');
    yesBtn.disabled = true;
    yesBtn.textContent = 'Confirming...';

    try {
        const res = await fetch(`${BASE_URL}/judge/api/confirm_score.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ contestant_id: currentContestantId })
        });
        const data = await res.json();

        if (data.success) {
            lockedSet.add(currentContestantId);
            applyLockToChip(currentContestantId);
            updateLockUI(true);
            renderCriteria(currentContestantId, true);
            document.getElementById('confirmModal').classList.remove('show');
            showToast('Score confirmed and locked.', 'success');
        } else {
            showToast(data.message || 'Could not confirm this score.', 'error');
        }
    } catch (err) {
        showToast('Network error - please try again.', 'error');
    } finally {
        yesBtn.disabled = false;
        yesBtn.textContent = 'Yes, confirm & lock';
    }
}

function applyLockToChip(contestantId) {
    const chip = document.querySelector(`.contestant-chip[data-id="${contestantId}"]`);
    if (!chip) return;
    chip.classList.add('locked', 'complete');
    chip.classList.remove('partial');

    const oldDot = chip.querySelector('.done-dot');
    if (oldDot) oldDot.remove();

    if (!chip.querySelector('.lock-icon')) {
        const icon = document.createElement('span');
        icon.className = 'lock-icon';
        icon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>';
        chip.prepend(icon);
    }
}

/* =========================================================
   Live weighted-total preview
   ========================================================= */

function updateLiveTotal(contestantId) {
    const display = document.getElementById('liveTotalDisplay');
    if (!display) return;

    const scoresForContestant = existingScores[contestantId] || {};
    let total = 0;
    CRITERIA.forEach(crit => {
        const raw = scoresForContestant[crit.criteria_id] || 0;
        total += (raw / MAX_SCORE) * crit.weight_percentage;
    });

    display.innerHTML = `${total.toFixed(2)} <span class="muted small">/ 100</span>`;
}

/* =========================================================
   Progress tracking across all contestants
   ========================================================= */

function refreshAllChipProgress() {
    let fullyScoredCount = 0;

    CONTESTANTS.forEach(c => {
        if (lockedSet.has(c.contestant_id)) {
            fullyScoredCount++;
            return; // locked chips keep their lock icon; don't touch done-dot logic
        }

        const scoresForContestant = existingScores[c.contestant_id] || {};
        const scoredCriteriaCount = CRITERIA.filter(crit => scoresForContestant[crit.criteria_id] !== undefined).length;
        const isFull = CRITERIA.length > 0 && scoredCriteriaCount === CRITERIA.length;
        const isPartial = scoredCriteriaCount > 0 && !isFull;

        if (isFull) fullyScoredCount++;

        const chip = document.querySelector(`.contestant-chip[data-id="${c.contestant_id}"]`);
        if (chip) {
            chip.classList.toggle('complete', isFull);
            chip.classList.toggle('partial', isPartial);
            let dot = chip.querySelector('.done-dot');
            if (isFull || isPartial) {
                if (!dot) {
                    dot = document.createElement('span');
                    dot.className = 'done-dot';
                    chip.prepend(dot);
                }
            } else if (dot) {
                dot.remove();
            }
        }
    });

    const summary = document.getElementById('progressSummary');
    const fill = document.getElementById('progressFill');
    if (summary) {
        summary.textContent = `${fullyScoredCount} of ${CONTESTANTS.length} contestants fully scored`;
    }
    if (fill) {
        const pct = CONTESTANTS.length ? (fullyScoredCount / CONTESTANTS.length) * 100 : 0;
        fill.style.width = `${pct}%`;
    }
}

/* =========================================================
   Small save indicator next to the Save button
   ========================================================= */

function showSaveStatus(state, message) {
    const el = document.getElementById('savingStatus');
    if (!el) return;
    el.classList.remove('saving', 'error');

    if (state === 'saving') {
        el.textContent = 'Saving...';
        el.classList.add('saving');
    } else if (state === 'saved') {
        el.textContent = 'All changes saved';
    } else if (state === 'error') {
        el.textContent = message || 'Save failed';
        el.classList.add('error');
    }
}

/* =========================================================
   Toast notifications (top-right, stacking, auto-dismiss)
   ========================================================= */

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('show'));

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 250);
    }, 3000);
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
