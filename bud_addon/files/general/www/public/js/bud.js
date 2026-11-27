/**
 * bud.js
 *
 * Shared behaviour for every page. It loads in <head>, so the helpers are
 * ready for each page's own script:
 * - the sun/moon theme switch, saved as 'bud-theme' (header.php applies the
 *   saved theme before first paint)
 * - escapeHtml(), formatDate(), formatDateTime(), formatQty()
 * - pop-ups: openDialog(), closeDialog(), confirmAction(), forms with
 *   data-confirm-title that ask before they send, and buttons with
 *   data-notice-title that explain why something can't be done yet
 * - forms can't be sent twice by a double tap
 * - a form waits for Home Assistant to reconnect after a phone has been
 *   asleep, instead of being lost to a "401: Unauthorized" page
 * - exportTableCsv() for the CSV buttons
 * - createSignaturePad() for the signature boxes
 * - three clicks on the version number open the admin tools
 */

(function () {
    const html = document.documentElement;
    const sun = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path fill="currentColor" d="M8 11a3 3 0 1 1 0-6a3 3 0 0 1 0 6zm0 1a4 4 0 1 0 0-8a4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"/></svg>';
    const moon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><g fill="currentColor"><path d="M6 .278a.768.768 0 0 1 .08.858a7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277c.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316a.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71C0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278zM4.858 1.311A7.269 7.269 0 0 0 1.025 7.71c0 4.02 3.279 7.276 7.319 7.276a7.316 7.316 0 0 0 5.205-2.162c-.337.042-.68.063-1.029.063c-4.61 0-8.343-3.714-8.343-8.29c0-1.167.242-2.278.681-3.286z"/><path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.734 1.734 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.734 1.734 0 0 0 9.31 6.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.734 1.734 0 0 0 1.097-1.097l.387-1.162zM13.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.156 1.156 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.156 1.156 0 0 0-.732-.732l-.774-.258a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732L13.863.1z"/></g></svg>';

    function currentTheme() {
        return html.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function renderIcon(button) {
        // Show the icon for the theme you would switch TO
        button.innerHTML = currentTheme() === 'light' ? moon : sun;
    }

    // Close the phone menu after Back/Forward: the browser can restore its
    // tick box as "open"
    window.addEventListener('pageshow', () => {
        const navToggle = document.getElementById('nav-toggle');
        if (navToggle) navToggle.checked = false;
    });

    document.addEventListener('DOMContentLoaded', () => {
        const switchTheme = document.getElementById('theme_switcher');
        if (switchTheme) {
            renderIcon(switchTheme);
            switchTheme.addEventListener('click', (e) => {
                e.preventDefault();
                const next = currentTheme() === 'light' ? 'dark' : 'light';
                html.setAttribute('data-theme', next);
                try {
                    localStorage.setItem('bud-theme', next);
                } catch (err) { /* private browsing: the switch still works on this page */ }
                renderIcon(switchTheme);
                document.dispatchEvent(new CustomEvent('bud:themechange', { detail: next }));
            });
        }

        // The admin tools: three clicks on the version number within 2 seconds.
        // A click on it never follows the Dashboard link around it.
        const trigger = document.querySelector('[data-admin-trigger]');
        if (trigger) {
            let clicks = 0;
            let timer = null;
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                clicks++;
                clearTimeout(timer);
                if (clicks >= 3) {
                    clicks = 0;
                    window.location.href = 'admin.php';
                    return;
                }
                timer = setTimeout(() => { clicks = 0; }, 2000);
            });
        }

        // Buttons that close the pop-up they sit in
        document.addEventListener('click', (e) => {
            const closer = e.target.closest('[data-close-dialog]');
            if (closer) {
                const dialog = closer.closest('dialog');
                if (dialog) {
                    e.preventDefault();
                    closeDialog(dialog);
                }
            }
        });
    });
})();

/**
 * Escape a string for innerHTML, as text or inside a quoted attribute, so a
 * name like Joe's "Pharmacy" can't break the page.
 */
function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/** '2026-11-03' or '2026-11-03 14:10:00' as 03/11/2026, straight from the text. */
function formatDate(value) {
    const text = String(value ?? '');
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(text);
    return m ? `${m[3]}/${m[2]}/${m[1]}` : text;
}

/** '2026-11-03 14:10:00' as 03/11/2026 14:10 (a plain date stays a date). */
function formatDateTime(value) {
    const text = String(value ?? '');
    const m = /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/.exec(text);
    if (!m) return text;
    return `${m[3]}/${m[2]}/${m[1]}` + (m[4] ? ` ${m[4]}:${m[5]}` : '');
}

/** 1150 as 1,150 and 2.5 as 2.5 (at most 2 decimals), with a real minus sign. */
function formatQty(value) {
    return Number(value || 0).toLocaleString('en-NZ', { maximumFractionDigits: 2 }).replace(/^-/, '\u2212');
}

/** A short message at the bottom of the screen, e.g. when a fetch fails. */
function showToast(message, type = 'info', duration = 4500) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = 'toast toast--' + type;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.textContent = message;
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('toast--visible'));
    setTimeout(() => {
        toast.classList.remove('toast--visible');
        setTimeout(() => toast.remove(), 400);
    }, duration);
}

/** Open a pop-up (a <dialog> on the page) by its id. */
function openDialog(id) {
    const dialog = typeof id === 'string' ? document.getElementById(id) : id;
    if (!dialog) return null;
    if (typeof dialog.showModal === 'function') {
        if (!dialog.open) dialog.showModal();
    } else {
        dialog.setAttribute('open', '');
    }
    // Start on the close button: the browser would otherwise focus a tall
    // pop-up's scroll area (an outline round the whole box), and a text
    // field would pop up the keyboard on a phone straight away
    const start = dialog.querySelector('[autofocus]') || dialog.querySelector('[rel="prev"]');
    if (start) start.focus();
    return dialog;
}

/** Close a pop-up, given the <dialog> or its id. */
function closeDialog(id) {
    const dialog = typeof id === 'string' ? document.getElementById(id) : id;
    if (!dialog) return;
    if (typeof dialog.close === 'function') {
        dialog.close();
    } else {
        dialog.removeAttribute('open');
    }
}

/**
 * Double taps can't save twice: once a form is on its way to the server,
 * further submits are ignored until a new page loads.
 */
function markFormSubmitting(form) {
    form.dataset.submitting = '1';
    // Disable the buttons only after the browser has read the form
    setTimeout(() => {
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
    }, 0);
}

/** Let a form be sent again (Back button, or Home Assistant didn't come back). */
function resetFormSubmit(form) {
    delete form.dataset.submitting;
    delete form.dataset.confirmed;
    form.querySelectorAll('button[aria-busy], button[data-held]').forEach(button => {
        button.disabled = false;
        button.removeAttribute('aria-busy');
        delete button.dataset.held;
    });
}

/** Submit a form after a confirm step, at most once. */
function submitOnce(form) {
    if (form.dataset.submitting) return;
    sendWhenReachable(form, null);
}

// Capture phase: runs before a page's own submit handler, so a second tap
// doesn't even open a second confirm pop-up
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.dataset.submitting) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}, true);

// Forms marked data-confirm-title ask first, in a pop-up
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.dataset.confirmTitle || form.dataset.confirmed) return;
    event.preventDefault();
    if (typeof form.reportValidity === 'function' && !form.reportValidity()) return;
    confirmAction({
        title: form.dataset.confirmTitle,
        message: form.dataset.confirmMessage || '',
        confirmLabel: form.dataset.confirmLabel || 'Confirm',
        cancelLabel: form.dataset.cancelLabel || 'Cancel',
        danger: form.hasAttribute('data-confirm-danger'),
    }).then((ok) => {
        if (ok) {
            form.dataset.confirmed = '1';
            submitOnce(form);
        }
    });
});

// Buttons marked data-notice-title explain, in a pop-up with just OK, why
// something can't be done yet (say, retiring stock that's still on hand)
document.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-notice-title]');
    if (!button) return;
    event.preventDefault();
    confirmAction({
        title: button.dataset.noticeTitle,
        message: button.dataset.noticeMessage || '',
        confirmLabel: 'OK',
        cancelLabel: false,
    });
});

// Bubble phase: after the page's handlers. If none of them cancelled the
// submit, it's on its way. A form that saves something goes once BUD knows
// it can get through to Home Assistant.
document.addEventListener('submit', (event) => {
    if (event.defaultPrevented) return;
    const form = event.target;
    if ((form.getAttribute('method') || '').toLowerCase() !== 'post') {
        markFormSubmitting(form);
        return;
    }
    event.preventDefault();
    sendWhenReachable(form, event.submitter);
});

// Coming back with the Back button can restore a page mid-submit
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    document.querySelectorAll('form[data-submitting]').forEach(resetFormSubmit);
    budClearConnection();
});

/* --------------------------------------------------------------------------
 * Getting through to Home Assistant
 *
 * Home Assistant lets BUD's pages through on a session that lapses after a
 * quarter of an hour unused. Its own page renews the session every minute,
 * but that stops while a phone sleeps, and Home Assistant 2026.11 and older
 * only renew it on their next tick, up to a minute after the phone wakes.
 * Until then every request to BUD gets Home Assistant's own
 * "401: Unauthorized" page, so a form sent in that minute was lost,
 * signature and all.
 *
 * So a form asks ping.php first. If that doesn't get through, the form
 * waits, says so, and goes by itself once Home Assistant is back. A page
 * coming back after a while away checks straight away too, and holds a
 * tapped link until it can get through.
 * -------------------------------------------------------------------------- */

const BUD_WAIT_TEXT = 'Waiting for Home Assistant to reconnect. What you\'ve entered is kept, and BUD saves it as soon as Home Assistant is back.';
const BUD_FAILED_TEXT = 'BUD still can\'t reach Home Assistant, so nothing has been saved yet. Check the connection, then try again.';
const BUD_WAKE_TEXT = 'Reconnecting to Home Assistant. After the screen has been off, this can take up to a minute.';

function budSleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/** True when a request gets through Home Assistant to BUD. */
async function budCanReach() {
    const controller = typeof AbortController === 'function' ? new AbortController() : null;
    const timer = controller ? setTimeout(() => controller.abort(), 8000) : null;
    try {
        const response = await fetch('ping.php', {
            cache: 'no-store',
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined,
        });
        return response.ok;
    } catch (e) {
        return false; // offline, or no answer within 8 seconds
    } finally {
        clearTimeout(timer);
    }
}

/**
 * Resolves true once a request gets through. onWaiting runs if the first try
 * doesn't. After `tries` more tries, about three minutes for the default,
 * it gives up and resolves false. That covers Home Assistant missing a
 * renewal while its own connection comes back, and trying again a minute
 * later. Time the page spends asleep doesn't count.
 */
async function budWhenReachable(onWaiting, tries = 90) {
    if (await budCanReach()) return true;
    if (onWaiting) onWaiting();
    for (let i = 0; i < tries; i++) {
        await budSleep(i === 0 ? 1000 : (i < 90 ? 2000 : 10000));
        if (await budCanReach()) return true;
    }
    return false;
}

/**
 * Say what's happening: in the form when it's in a pop-up (the pop-up covers
 * the rest of the page), otherwise in a bar at the foot of the screen.
 */
function budShowConnection(form, submitter, text, failed = false) {
    let note;
    if (form && form.closest('dialog[open]')) {
        note = form.querySelector('.bud-reconnect');
        if (!note) {
            note = document.createElement('p');
            note.className = 'bud-reconnect';
            const button = submitter || form.querySelector('button[type="submit"], button:not([type])');
            const row = button && button.parentElement !== form ? button.parentElement : button;
            if (row && form.contains(row)) row.before(note); else form.appendChild(note);
        }
        note.classList.toggle('is-failed', failed);
    } else {
        note = document.getElementById('budReconnect');
        if (!note) {
            let container = document.getElementById('toastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toastContainer';
                document.body.appendChild(container);
            }
            note = document.createElement('div');
            note.id = 'budReconnect';
            note.className = 'toast toast--visible';
            container.appendChild(note);
        }
        note.classList.toggle('toast--error', failed);
    }
    note.setAttribute('role', failed ? 'alert' : 'status');
    note.textContent = text;
}

/** Take the waiting message away (from a form, and the bar). */
function budClearConnection(form) {
    if (form) form.querySelectorAll('.bud-reconnect').forEach(note => note.remove());
    const bar = document.getElementById('budReconnect');
    if (bar) bar.remove();
}

/**
 * Send a form once BUD can reach Home Assistant. Its buttons are held while
 * it waits. If Home Assistant isn't back after about three minutes, or the
 * pop-up the form is in gets closed, nothing is sent and the form can be
 * tried again.
 */
async function sendWhenReachable(form, submitter) {
    markFormSubmitting(form);
    form.querySelectorAll('button:not([disabled])').forEach(button => {
        button.disabled = true;
        button.dataset.held = '1';
    });
    const dialog = form.closest('dialog[open]');
    const ok = await budWhenReachable(() => budShowConnection(form, submitter, BUD_WAIT_TEXT));
    if (dialog && !dialog.open) {
        resetFormSubmit(form);
        budClearConnection(form);
        return;
    }
    if (!ok) {
        resetFormSubmit(form);
        budShowConnection(form, submitter, BUD_FAILED_TEXT, true);
        return;
    }
    budClearConnection(form);
    if (submitter && submitter.name) {
        // form.submit() leaves out the button that was pressed
        const pressed = document.createElement('input');
        pressed.type = 'hidden';
        pressed.name = submitter.name;
        pressed.value = submitter.value;
        form.appendChild(pressed);
    }
    // The prototype's submit, in case the form has a field called "submit"
    HTMLFormElement.prototype.submit.call(form);
}

// A page coming back after more than a minute away (a phone waking up)
// checks straight away, so a lapsed session shows before anyone taps
// anything. Chromium fires freeze and resume around a frozen page, other
// browsers only visibilitychange.
(function () {
    let awaySince = 0;
    let waking = null;

    function away() {
        if (!awaySince) awaySince = Date.now();
    }

    function back() {
        const gone = awaySince ? Date.now() - awaySince : 0;
        awaySince = 0;
        if (gone < 60000 || waking) return;
        waking = (async () => {
            // Home Assistant 2026.12 and later renew the session as the page
            // comes back, so give that a moment first
            await budSleep(1500);
            await budWhenReachable(() => budShowConnection(null, null, BUD_WAKE_TEXT), Infinity);
            budClearConnection();
            waking = null;
        })();
    }

    document.addEventListener('visibilitychange', () => (document.hidden ? away() : back()));
    document.addEventListener('freeze', away);
    document.addEventListener('resume', back);

    // A link tapped while BUD is reconnecting opens once it can get through
    document.addEventListener('click', (event) => {
        if (!waking || event.defaultPrevented || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[href]');
        if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;
        const url = new URL(link.href, document.baseURI);
        if (url.origin !== location.origin) return;
        // A jump within the page doesn't ask Home Assistant for anything
        if (url.hash && url.pathname === location.pathname && url.search === location.search) return;
        event.preventDefault();
        waking.then(() => { window.location.href = url.href; });
    });
})();

/**
 * A confirm pop-up. Resolves true (confirm) or false (cancel).
 * @param {Object} options title, message, items (a list), confirmLabel,
 *   cancelLabel (false for a notice with just the confirm button), danger
 *   (red confirm button)
 * @returns {Promise<boolean>}
 */
function confirmAction(options) {
    const opts = options || {};
    const title = opts.title || 'Are you sure?';
    const message = opts.message || '';
    const items = opts.items || [];
    const confirmLabel = opts.confirmLabel || 'Confirm';
    const showCancel = opts.cancelLabel !== false;
    const cancelLabel = opts.cancelLabel || 'Cancel';
    const danger = !!opts.danger;

    if (typeof HTMLDialogElement === 'undefined') {
        if (!showCancel) {
            window.alert(title + (message ? '\n\n' + message : ''));
            return Promise.resolve(true);
        }
        return Promise.resolve(window.confirm(title + (message ? '\n\n' + message : '')));
    }

    return new Promise((resolve) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'bud-modal';
        const list = items.length
            ? '<ul class="bud-modal-list">' + items.map(i => '<li>' + escapeHtml(i) + '</li>').join('') + '</ul>'
            : '';
        dialog.innerHTML = `
            <article>
                <h3>${escapeHtml(title)}</h3>
                ${message ? '<p class="modal-text">' + escapeHtml(message) + '</p>' : ''}
                ${list}
                <footer>
                    ${showCancel ? '<button type="button" class="secondary outline" data-modal-cancel>' + escapeHtml(cancelLabel) + '</button>' : ''}
                    <button type="button"${danger ? ' class="danger"' : ''} data-modal-confirm>${escapeHtml(confirmLabel)}</button>
                </footer>
            </article>`;
        document.body.appendChild(dialog);

        const close = (result) => {
            dialog.close();
            dialog.remove();
            resolve(result);
        };
        const cancelButton = dialog.querySelector('[data-modal-cancel]');
        if (cancelButton) cancelButton.addEventListener('click', () => close(false));
        dialog.querySelector('[data-modal-confirm]').addEventListener('click', () => close(true));
        dialog.addEventListener('cancel', (e) => { e.preventDefault(); close(false); });
        dialog.addEventListener('click', (e) => { if (e.target === dialog) close(false); });
        dialog.showModal();
    });
}

/** Download rows (arrays of text) as a CSV file, every value in quotes. */
function downloadCsv(rows, filename) {
    const csv = rows.map(cols => cols.map(v => '"' + String(v ?? '').replace(/"/g, '""') + '"').join(','));
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
}

/**
 * Download a table as CSV. A cell's data-csv value is used when it has one
 * (dates and quantities show the NZ way on screen but export as stored), and
 * cells marked data-nocsv are left out.
 */
function exportTableCsv(table, filename) {
    if (typeof table === 'string') table = document.getElementById(table);
    if (!table) {
        showToast('There is nothing to export.', 'error');
        return;
    }
    const rows = Array.from(table.rows).map(row => Array.from(row.cells)
        .filter(c => !c.hasAttribute('data-nocsv'))
        .map(c => c.hasAttribute('data-csv') ? c.getAttribute('data-csv') : c.innerText));
    downloadCsv(rows, filename);
}

/** 1536000 bytes as "1.5 MB". */
function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' bytes';
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

/**
 * Make a big photo smaller before it uploads: at most 2,400 px on its long
 * side and about 2 MB, as a JPEG, which keeps a signed form easy to read.
 * Anything that isn't a JPG, PNG or WebP photo comes back as it was.
 * @returns {Promise<File>}
 */
async function shrinkPhoto(file, maxBytes = 2 * 1024 * 1024, maxSide = 2400) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size <= maxBytes) return file;
    const url = URL.createObjectURL(file);
    try {
        const img = await new Promise((resolve, reject) => {
            const i = new Image();
            i.onload = () => resolve(i);
            i.onerror = reject;
            i.src = url;
        });
        let scale = Math.min(1, maxSide / Math.max(img.naturalWidth, img.naturalHeight));
        let quality = 0.85;
        for (let attempt = 0; attempt < 8; attempt++) {
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(img.naturalWidth * scale);
            canvas.height = Math.round(img.naturalHeight * scale);
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality));
            if (blob && (blob.size <= maxBytes || attempt === 7)) {
                return new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
            }
            if (quality > 0.6) {
                quality -= 0.1;
            } else {
                scale *= 0.85;
            }
        }
    } catch (e) {
        // A photo the browser can't read goes up as it is
    } finally {
        URL.revokeObjectURL(url);
    }
    return file;
}

/**
 * Make a <canvas> a signature box. Tracks whether anything was really drawn,
 * since an untouched canvas still exports a (blank) picture.
 * @returns {{clear: Function, hasDrawn: Function, toDataURL: Function}}
 */
function createSignaturePad(canvas, onChange) {
    const ctx = canvas.getContext('2d');
    let painting = false;
    let drawn = false;

    function point(e) {
        const rect = canvas.getBoundingClientRect();
        const source = e.touches && e.touches.length ? e.touches[0] : e;
        return {
            x: (source.clientX - rect.left) * (canvas.width / rect.width),
            y: (source.clientY - rect.top) * (canvas.height / rect.height),
        };
    }
    function start(e) {
        painting = true;
        ctx.beginPath();
        draw(e);
    }
    function end() {
        painting = false;
        ctx.beginPath();
    }
    function draw(e) {
        if (!painting) return;
        e.preventDefault();
        const p = point(e);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#000';
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
        if (!drawn) {
            drawn = true;
            if (onChange) onChange(true);
        }
    }
    canvas.addEventListener('mousedown', start);
    canvas.addEventListener('mousemove', draw);
    window.addEventListener('mouseup', end);
    canvas.addEventListener('touchstart', start, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', end);

    return {
        clear() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            drawn = false;
            if (onChange) onChange(false);
        },
        hasDrawn() {
            return drawn;
        },
        toDataURL() {
            return canvas.toDataURL();
        },
    };
}
