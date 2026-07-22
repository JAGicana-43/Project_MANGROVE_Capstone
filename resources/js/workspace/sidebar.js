document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.getElementById('workspaceSidebar');
    var toggleButtons = [
        document.getElementById('navbarSidebarToggle')
    ];

    toggleButtons.forEach(function (button) {
        if (!button) return;
        button.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            setTimeout(function () {
                window.workspaceMap?.invalidateSize();
            }, 240);
        });
    });

    document.querySelectorAll('[data-collapse-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            var panel = document.getElementById(button.dataset.collapseTarget);
            panel?.classList.toggle('collapsed');
        });
    });
});
