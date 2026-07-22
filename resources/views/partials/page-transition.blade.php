<div class="page-transition-progress" aria-hidden="true">
    <span id="pageTransitionBar"></span>
</div>

<div class="page-transition-overlay" id="pageTransitionOverlay" role="status" aria-live="polite" aria-atomic="true" hidden>
    <div class="page-transition-card">
        <span class="page-transition-spinner" aria-hidden="true"></span>
        <span class="page-transition-copy">
            <span class="page-transition-title">Loading</span>
            <span class="page-transition-message" id="pageTransitionMessage">Preparing the next page...</span>
        </span>
    </div>
</div>

<script>
    (function () {
        var progressBar = null;
        var overlay = null;
        var message = null;
        var progressTimer = null;
        var transitionStarted = false;
        var currentProgress = 0;

        function isSameOrigin(url) {
            return url.origin === window.location.origin;
        }

        function isSamePage(url) {
            return url.pathname === window.location.pathname &&
                url.search === window.location.search &&
                url.hash === window.location.hash;
        }

        function updateProgress(value) {
            currentProgress = Math.max(currentProgress, value);
            if (progressBar) {
                progressBar.style.width = currentProgress + '%';
            }
        }

        function startProgress() {
            updateProgress(18);
            window.clearInterval(progressTimer);
            progressTimer = window.setInterval(function () {
                if (currentProgress < 74) {
                    updateProgress(currentProgress + Math.max(2, Math.round((82 - currentProgress) * 0.14)));
                }
            }, 220);
        }

        function finishProgress() {
            window.clearInterval(progressTimer);
            updateProgress(100);
            window.setTimeout(function () {
                document.body.classList.remove('is-navigating');
                if (overlay) overlay.hidden = true;
                if (progressBar) progressBar.style.width = '0%';
                currentProgress = 0;
                transitionStarted = false;
            }, 180);
        }

        function beginTransition(label) {
            if (transitionStarted) return;
            transitionStarted = true;

            if (message && label) {
                message.textContent = label;
            }

            if (overlay) overlay.hidden = false;
            document.body.classList.add('is-navigating');
            startProgress();
        }

        function getLinkLabel(link) {
            return link.dataset.loadingLabel ||
                link.getAttribute('aria-label') ||
                link.textContent.trim().replace(/\s+/g, ' ') ||
                'Opening page';
        }

        function shouldHandleLink(link, event) {
            if (!link || !link.href) return false;
            if (event.defaultPrevented || event.button !== 0) return false;
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
            if (link.target && link.target !== '_self') return false;
            if (link.hasAttribute('download')) return false;
            if (link.dataset.noPageTransition !== undefined) return false;
            if (link.getAttribute('aria-disabled') === 'true') return false;

            var href = link.getAttribute('href') || '';
            if (!href || href === '#' || href.indexOf('javascript:') === 0) return false;

            var url = new URL(link.href, window.location.href);
            if (!isSameOrigin(url)) return false;
            if (isSamePage(url)) return false;
            if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) return false;

            return true;
        }

        function shouldHandleForm(form) {
            if (!form || form.dataset.noPageTransition !== undefined) return false;
            if (form.target && form.target !== '_self') return false;
            if ((form.getAttribute('method') || 'get').toLowerCase() === 'dialog') return false;
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) return false;
            return true;
        }

        document.addEventListener('DOMContentLoaded', function () {
            progressBar = document.getElementById('pageTransitionBar');
            overlay = document.getElementById('pageTransitionOverlay');
            message = document.getElementById('pageTransitionMessage');

            document.body.classList.add('page-transition-ready');

            document.addEventListener('click', function (event) {
                var link = event.target.closest('a');
                if (!shouldHandleLink(link, event)) return;

                link.classList.add('is-transitioning');
                beginTransition('Opening ' + getLinkLabel(link) + '...');
            });

            document.addEventListener('submit', function (event) {
                var form = event.target;
                if (!shouldHandleForm(form)) return;

                var submitter = event.submitter || form.querySelector('[type="submit"]');
                if (submitter) submitter.classList.add('is-submitting');
                beginTransition(form.dataset.loadingLabel || 'Saving changes...');
            }, true);

            window.addEventListener('beforeunload', function () {
                if (transitionStarted) updateProgress(94);
            });

            window.addEventListener('pageshow', function (event) {
                if (event.persisted) {
                    window.location.reload();
                    return;
                }
                finishProgress();
                document.querySelectorAll('.is-transitioning, .is-submitting').forEach(function (element) {
                    element.classList.remove('is-transitioning', 'is-submitting');
                });
            });
        });
    })();
</script>
