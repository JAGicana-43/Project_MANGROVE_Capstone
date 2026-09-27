{{-- Rename/Archive/Delete for a project card -- shared by dashboard/lobby.blade.php
     (Atlas rail) and projects/index.blade.php (All Projects grid), each of
     which triggers these via a per-card "..." menu calling
     openRenameModal/openArchiveModal/openDeleteModal. Posts as plain forms
     (full page redirect on submit), matching ProjectController::rename()/
     toggleArchive()/destroy(). --}}
<style>
    .modal-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 1400;
        background: rgba(20, 25, 22, 0.32);
        backdrop-filter: blur(2px);
    }

    .modal-card {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 1500;
        width: min(420px, calc(100vw - 32px));
        max-height: calc(100vh - 48px);
        overflow-y: auto;
        background: var(--surface);
        border: 1px solid var(--border-soft);
        border-radius: 12px;
        box-shadow: 0 24px 60px hsl(var(--shadow-color) / 0.22);
        padding: 24px;
    }

    .modal-backdrop.is-open, .modal-card.is-open { display: block; }

    .modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
    }

    .modal-header h2 {
        font-family: 'Poppins', sans-serif;
        font-size: 18px;
        color: var(--heading-accent);
        margin-bottom: 4px;
    }

    [data-theme="dark"] .modal-header h2 {
        color: var(--dark-gray);
    }

    .modal-header p {
        color: var(--text-muted);
        font-size: 12.5px;
    }

    .modal-close {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        background: rgba(33, 37, 41, 0.06);
        color: var(--dark-gray);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
        border: none;
    }

    #renameProjectForm, #deleteProjectForm { display: flex; flex-direction: column; gap: 13px; }
    #renameProjectForm label, #deleteProjectForm label {
        display: flex; flex-direction: column; gap: 6px; font-size: 12.5px; font-weight: 700; color: var(--text-muted);
    }
    #renameProjectForm input, #deleteProjectForm input {
        height: 42px;
        padding: 0 12px;
        border-radius: 8px;
        border: 1px solid var(--border-soft);
        font: inherit;
        font-size: 13.5px;
        color: var(--dark-gray);
        background: var(--surface);
    }
    #renameProjectForm input:focus {
        outline: 2px solid rgba(116, 198, 157, 0.45);
        border-color: var(--forest-green);
    }
    #deleteProjectForm input:focus {
        outline: 2px solid rgba(180, 35, 24, 0.3);
        border-color: #B42318;
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
        margin-top: 4px;
    }

    .btn-primary.btn-danger {
        background: #B42318;
    }
    .btn-primary.btn-danger:hover {
        background: #8f1b13;
    }
    .btn-primary.btn-danger:disabled {
        background: rgba(180, 35, 24, 0.35);
        cursor: not-allowed;
    }
</style>

<div class="modal-backdrop" id="archiveModalBackdrop" onclick="closeArchiveModal()"></div>
<div class="modal-card" id="archiveModalCard">
    <div class="modal-header">
        <div>
            <h2 id="archiveModalTitle">Archive project?</h2>
            <p id="archiveModalSubtitle">This hides it from your active project list. You can restore it anytime from the Archived filter.</p>
        </div>
        <button type="button" class="modal-close" onclick="closeArchiveModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form id="archiveProjectForm" method="POST" data-no-page-transition>
        @csrf
        @method('PATCH')
        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="closeArchiveModal()">Cancel</button>
            <button type="submit" class="btn-primary" id="archiveModalConfirmBtn">Archive</button>
        </div>
    </form>
</div>

<div class="modal-backdrop" id="renameModalBackdrop" onclick="closeRenameModal()"></div>
<div class="modal-card" id="renameModalCard">
    <div class="modal-header">
        <div>
            <h2>Rename project</h2>
        </div>
        <button type="button" class="modal-close" onclick="closeRenameModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form id="renameProjectForm" method="POST" data-no-page-transition>
        @csrf
        @method('PATCH')
        <label>Project name
            <input type="text" name="name" id="renameProjectInput" maxlength="255" required>
        </label>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="closeRenameModal()">Cancel</button>
            <button type="submit" class="btn-primary">Save</button>
        </div>
    </form>
</div>

<div class="modal-backdrop" id="deleteModalBackdrop" onclick="closeDeleteModal()"></div>
<div class="modal-card" id="deleteModalCard">
    <div class="modal-header">
        <div>
            <h2>Permanently delete project?</h2>
            <p>This cannot be undone. Every AOI, drone mission, orthomosaic, classification result, and report for
                "<strong id="deleteModalProjectName"></strong>" will be erased, including uploaded files on disk.</p>
        </div>
        <button type="button" class="modal-close" onclick="closeDeleteModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form id="deleteProjectForm" method="POST" data-no-page-transition>
        @csrf
        @method('DELETE')
        <label>Type the project name to confirm
            <input type="text" id="deleteConfirmInput" oninput="checkDeleteConfirmInput()" autocomplete="off" required>
        </label>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancel</button>
            <button type="submit" class="btn-primary btn-danger" id="deleteConfirmBtn" disabled>Delete Permanently</button>
        </div>
    </form>
</div>

<script>
    // ============ ARCHIVE / RESTORE PROJECT MODAL ============
    function openArchiveModal(projectId, projectName, currentStatus) {
        var isArchived = currentStatus === 'archived';
        var titleEl = document.getElementById('archiveModalTitle');
        var subtitleEl = document.getElementById('archiveModalSubtitle');
        var confirmBtn = document.getElementById('archiveModalConfirmBtn');
        var form = document.getElementById('archiveProjectForm');

        if (titleEl) titleEl.textContent = (isArchived ? 'Restore "' : 'Archive "') + projectName + '"?';
        if (subtitleEl) {
            subtitleEl.textContent = isArchived
                ? 'This moves it back to your active project list. Nothing was ever deleted.'
                : 'This hides it from your active project list. You can restore it anytime from the Archived filter — nothing is deleted.';
        }
        if (confirmBtn) confirmBtn.textContent = isArchived ? 'Restore' : 'Archive';
        if (form) form.action = '/projects/' + encodeURIComponent(projectId) + '/archive';

        closeCardMenus();
        document.getElementById('archiveModalBackdrop')?.classList.add('is-open');
        document.getElementById('archiveModalCard')?.classList.add('is-open');
    }

    function closeArchiveModal() {
        document.getElementById('archiveModalBackdrop')?.classList.remove('is-open');
        document.getElementById('archiveModalCard')?.classList.remove('is-open');
    }

    // ============ RENAME PROJECT MODAL ============
    function openRenameModal(projectId, projectName) {
        var form = document.getElementById('renameProjectForm');
        var input = document.getElementById('renameProjectInput');

        if (form) form.action = '/projects/' + encodeURIComponent(projectId) + '/rename';
        if (input) input.value = projectName;

        closeCardMenus();
        document.getElementById('renameModalBackdrop')?.classList.add('is-open');
        document.getElementById('renameModalCard')?.classList.add('is-open');

        // Pre-select the name so typing immediately replaces it, same as
        // most "rename" affordances (Explorer, Finder, etc.) -- requires a
        // tick after the modal becomes visible/focusable in some browsers.
        window.setTimeout(function () {
            input?.focus();
            input?.select();
        }, 0);
    }

    function closeRenameModal() {
        document.getElementById('renameModalBackdrop')?.classList.remove('is-open');
        document.getElementById('renameModalCard')?.classList.remove('is-open');
    }

    // ============ PERMANENT DELETE PROJECT MODAL ============
    // Requires typing the exact project name to enable the confirm button --
    // this is a real, irreversible destructive action (erases uploaded
    // files on disk too, not just DB rows), so it gets more friction than
    // archive/rename.
    var deleteModalProjectName = '';

    function openDeleteModal(projectId, projectName) {
        deleteModalProjectName = projectName;
        var form = document.getElementById('deleteProjectForm');
        var nameEl = document.getElementById('deleteModalProjectName');
        var input = document.getElementById('deleteConfirmInput');
        var confirmBtn = document.getElementById('deleteConfirmBtn');

        if (form) form.action = '/projects/' + encodeURIComponent(projectId);
        if (nameEl) nameEl.textContent = projectName;
        if (input) input.value = '';
        if (confirmBtn) confirmBtn.disabled = true;

        closeCardMenus();
        document.getElementById('deleteModalBackdrop')?.classList.add('is-open');
        document.getElementById('deleteModalCard')?.classList.add('is-open');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModalBackdrop')?.classList.remove('is-open');
        document.getElementById('deleteModalCard')?.classList.remove('is-open');
    }

    function checkDeleteConfirmInput() {
        var input = document.getElementById('deleteConfirmInput');
        var confirmBtn = document.getElementById('deleteConfirmBtn');
        if (confirmBtn) confirmBtn.disabled = (input.value !== deleteModalProjectName);
    }

    // ============ PER-CARD "..." MENU ============
    // One open menu at a time; closes on outside click, Escape, or when any
    // modal above opens (closeCardMenus() calls sprinkled in openXModal()).
    function closeCardMenus() {
        document.querySelectorAll('.pcard-menu-dropdown:not([hidden])').forEach(function (dropdown) {
            dropdown.hidden = true;
            dropdown.closest('.pcard-menu')?.querySelector('.pcard-menu-btn')?.setAttribute('aria-expanded', 'false');
        });
    }

    function toggleCardMenu(button) {
        var dropdown = button.nextElementSibling;
        if (!dropdown) return;
        var willOpen = dropdown.hidden;
        closeCardMenus();
        if (willOpen) {
            dropdown.hidden = false;
            button.setAttribute('aria-expanded', 'true');
        }
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.pcard-menu')) closeCardMenus();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeCardMenus();
    });
</script>
