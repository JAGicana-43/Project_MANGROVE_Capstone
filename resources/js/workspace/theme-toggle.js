        (function () {
            var toggleBtn = document.getElementById('themeToggle');
            var toggleIcon = document.getElementById('themeToggleIcon');

            function applyIcon(theme) {
                if (!toggleIcon) return;
                toggleIcon.className = theme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
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
