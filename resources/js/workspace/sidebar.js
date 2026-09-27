document.addEventListener('DOMContentLoaded', function () {
    // Every collapse toggle (Layers dock, Properties dock, and the
    // collapsible info panels) goes through this one handler. The docks
    // (#workspaceSidebar, #propertiesDock) sit beside the Leaflet map as
    // real flex columns, so collapsing/expanding one changes the map's
    // container size. invalidateSize() alone updates Leaflet's internal
    // size and does fetch new tiles for the newly-revealed area (confirmed
    // via network trace), but a known Leaflet quirk leaves that area's
    // tile grid untransformed/blank until something actually pans the map.
    // A 1px pan-and-back (no visible movement) forces the redraw.
    document.querySelectorAll('[data-collapse-target]').forEach(function (button) {
        button.addEventListener('click', function () {
            var panel = document.getElementById(button.dataset.collapseTarget);
            panel?.classList.toggle('collapsed');
            setTimeout(function () {
                var map = window.workspaceMap;
                if (!map) return;
                map.invalidateSize();
                map.panBy([1, 0], { animate: false });
                map.panBy([-1, 0], { animate: false });
            }, 240);
        });
    });

    // Per-section collapse within a dock (e.g. tucking away "Layers" or
    // "Draw" to make room for "Mission Parameters" below it), independent
    // of the whole-dock collapse above. Purely a content-height change
    // inside the sidebar -- doesn't resize the map, so no invalidateSize()
    // dance needed here.
    document.querySelectorAll('.dock-section-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            var section = button.closest('.dock-section');
            if (!section) return;
            var collapsed = section.classList.toggle('collapsed');
            button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        });
    });
});
