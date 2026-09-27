        (function () {
            var toggleBtn = document.getElementById('themeToggle');
            var toggleIcon = document.getElementById('themeToggleIcon');
            var toggleLabel = document.getElementById('themeToggleLabel');

            function applyIcon(theme) {
                if (toggleIcon) toggleIcon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
                // Labeled by the theme a click would switch TO, not the
                // current one -- "Switch to light mode" while dark is
                // active, matching the sun/moon icon's own meaning.
                if (toggleLabel) toggleLabel.textContent = theme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
            }

            applyIcon(document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light');

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function () {
                    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
                    var nextTheme = isDark ? 'light' : 'dark';

                    if (nextTheme === 'dark') {
                        document.documentElement.setAttribute('data-theme', 'dark');
                    } else {
                        document.documentElement.removeAttribute('data-theme');
                    }

                    localStorage.setItem('workspaceTheme', nextTheme);
                    applyIcon(nextTheme);
                });
            }
        })();
