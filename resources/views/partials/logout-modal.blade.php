<div class="logout-modal-backdrop" id="logoutModalBackdrop" onclick="closeLogoutModal()"></div>
<div class="logout-modal-card" id="logoutModalCard" role="alertdialog" aria-modal="true" aria-labelledby="logoutModalTitle">
    <div class="logout-modal-icon"><i class="fa-solid fa-right-from-bracket"></i></div>
    <h3 id="logoutModalTitle">Log out?</h3>
    <p>You'll need to sign in again to access your dashboard.</p>
    <div class="logout-modal-actions">
        <button type="button" class="logout-modal-cancel" onclick="closeLogoutModal()">Cancel</button>
        <button type="button" class="logout-modal-confirm" onclick="confirmLogoutSubmit()">Log out</button>
    </div>
</div>

<style>
    .logout-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(11, 38, 29, 0.45);
        backdrop-filter: blur(3px);
        z-index: 4000;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.18s ease;
    }
    .logout-modal-backdrop.is-open { opacity: 1; pointer-events: auto; }

    .logout-modal-card {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -46%);
        width: min(360px, 90vw);
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 30px 70px rgba(11, 38, 29, 0.28);
        padding: 28px 26px 22px;
        z-index: 4001;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.18s ease, transform 0.18s ease;
        text-align: center;
        font-family: 'Inter', sans-serif;
        box-sizing: border-box;
    }
    .logout-modal-card.is-open {
        opacity: 1;
        pointer-events: auto;
        transform: translate(-50%, -50%);
    }

    .logout-modal-icon {
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #FFF1EF;
        color: #b42318;
        display: grid;
        place-items: center;
        font-size: 20px;
        margin: 0 auto 16px;
    }

    .logout-modal-card h3 {
        font-family: 'Poppins', sans-serif;
        font-size: 18px;
        font-weight: 700;
        color: #15231c;
        margin: 0 0 8px;
    }

    .logout-modal-card p {
        font-size: 14px;
        color: #63736a;
        margin: 0 0 22px;
        line-height: 1.5;
    }

    .logout-modal-actions {
        display: flex;
        gap: 10px;
    }

    .logout-modal-actions button {
        flex: 1;
        padding: 11px 16px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        border: 1px solid transparent;
        font-family: 'Inter', sans-serif;
    }

    .logout-modal-cancel {
        background: #fff;
        border-color: #dfe6df;
        color: #15231c;
    }
    .logout-modal-cancel:hover { background: #f6f4ee; }

    .logout-modal-confirm {
        background: #b42318;
        color: #fff;
    }
    .logout-modal-confirm:hover { background: #8f1b12; }

    [data-theme="dark"] .logout-modal-card { background: #1c2622; }
    [data-theme="dark"] .logout-modal-card h3 { color: #e6ede9; }
    [data-theme="dark"] .logout-modal-card p { color: #9fb0a8; }
    [data-theme="dark"] .logout-modal-cancel { background: #1c2622; border-color: #33443c; color: #e6ede9; }
    [data-theme="dark"] .logout-modal-cancel:hover { background: #26332d; }
</style>

<script>
    var __pendingLogoutForm = null;

    function openLogoutModal(event, form) {
        if (event) event.preventDefault();
        __pendingLogoutForm = form || (event && event.target.closest('form'));
        document.getElementById('logoutModalBackdrop').classList.add('is-open');
        document.getElementById('logoutModalCard').classList.add('is-open');
        return false;
    }

    function closeLogoutModal() {
        document.getElementById('logoutModalBackdrop').classList.remove('is-open');
        document.getElementById('logoutModalCard').classList.remove('is-open');
        __pendingLogoutForm = null;
    }

    function confirmLogoutSubmit() {
        if (__pendingLogoutForm) {
            __pendingLogoutForm.requestSubmit();
        }
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeLogoutModal();
    });
</script>
