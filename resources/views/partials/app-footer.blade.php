<footer class="site-footer">
    <div class="site-footer-inner">
        <div class="footer-brand">
            <span class="footer-mark"><img src="{{ asset('images/logo/ProjMangrove_Logo.svg') }}" alt="Project M.A.N.G.R.O.V.E logo"></span>
            <div>
                <strong>Project M.A.N.G.R.O.V.E</strong>
            </div>
        </div>
        <div class="footer-meta">
            <span>SPAMAST Malita Campus</span>
            <a href="mailto:info@projectmangrove.ph"><i class="fa-solid fa-headset"></i> Help &amp; Support</a>
            <button type="button" data-legal-open="privacy">Privacy Policy</button>
            <button type="button" data-legal-open="terms">Terms of Service</button>
            <span>&copy; {{ date('Y') }} Project M.A.N.G.R.O.V.E</span>
        </div>
    </div>
</footer>

@include('partials.terms-privacy-modal')
