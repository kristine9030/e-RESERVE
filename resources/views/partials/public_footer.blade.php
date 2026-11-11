<footer class="site-footer" id="contact">
    <span class="orb orb-gold orb-soft" style="width:360px;height:360px;top:-140px;right:-120px;"></span>

    <div class="container position-relative">
        <div class="row g-5">
            <div class="col-lg-4">
                <div class="brand-lockup mb-3">
                    <img class="brand-logo" src="{{ asset(ssc('logo_small')) }}" alt="Supreme Student Council seal">
                    <span class="brand-text">
                        <span class="brand-name d-block text-white">e-<span>RESERVE</span></span>
                        <span class="brand-sub">Register. Upload. Scan. Celebrate.</span>
                    </span>
                </div>
                <p style="font-size:.875rem;max-width:330px;">
                    The official student registration and ticketing system for
                    <strong class="text-white">{{ ev('name') }}</strong>, a flagship event of the
                    70th Founding Anniversary of {{ ev('host') }}.
                </p>
                <div class="d-flex gap-2 mt-4">
                    <a href="{{ ssc('facebook') }}" class="social-dot" target="_blank" rel="noopener" aria-label="SSC on Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="mailto:{{ ssc('email') }}" class="social-dot" aria-label="Email the SSC"><i class="fa-solid fa-envelope"></i></a>
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h6>Navigate</h6>
                <ul class="footer-links">
                    <li><a href="{{ route('home') . '#home' }}">Home</a></li>
                    <li><a href="{{ route('home') . '#about' }}">About SIGLA</a></li>
                    <li><a href="{{ route('home') . '#lineup' }}">Lineup</a></li>
                    <li><a href="{{ route('home') . '#guidelines' }}">How it works</a></li>
                    <li><a href="{{ route('home') . '#guidelines' }}">Guidelines</a></li>
                    <li><a href="{{ route('home') . '#faq' }}">FAQ</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6>My Account</h6>
                <ul class="footer-links">
                    @if (!is_logged_in() && !student_access_open())
                        <li><i class="fa-solid fa-hourglass-half text-gold me-2"></i>
                            Sign-in and account creation open on {{ student_access_opens_at('M j, Y') }}.</li>
                    @else
                        <li><a href="{{ route('login') }}">Sign In</a></li>
                        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li><a href="{{ route('register') }}">Create Account</a></li>
                        <li><a href="{{ route('documents.index') }}">Upload Documents</a></li>
                        <li><a href="{{ route('ticket') }}">View My Ticket</a></li>
                    @endif
                </ul>
            </div>

            <div class="col-lg-4">
                <h6>Get in Touch</h6>
                <ul class="footer-links">
                    <li>
                        <i class="fa-solid fa-location-dot text-gold me-2"></i>
                        BatStateU The NEU ARASOF-Nasugbu,<br>
                        <span class="ms-4">R. Martinez St., Bucana, Nasugbu, Batangas</span>
                    </li>
                    <li><i class="fa-solid fa-envelope text-gold me-2"></i>
                        <a href="mailto:{{ ssc('email') }}">{{ ssc('email') }}</a></li>
                    <li><i class="fa-brands fa-facebook text-gold me-2"></i>
                        <a href="{{ ssc('facebook') }}" target="_blank" rel="noopener">facebook.com/SSCARASOF</a></li>
                    <li><i class="fa-brands fa-facebook-messenger text-gold me-2"></i>
                        <span>{{ ssc('messenger') }}</span></li>
                </ul>
                <div class="mt-4 p-3" style="background:rgba(255,255,255,.05);border-radius:16px;">
                    <div class="footer-motto">{{ ev('motto') }}</div>
                    <div style="font-size:.76rem;">Supreme Student Council · ARASOF-Nasugbu</div>
                </div>
            </div>
        </div>

        <div class="footer-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>&copy; {{ date('Y') }} Supreme Student Council · Batangas State University TNEU. All rights reserved.</span>
            <span>
                {{ app_name() }} {{ config('ereserve.version') }} ·
                <a href="{{ route('privacy') }}">Privacy</a> ·
                <a href="{{ route('terms') }}">Terms</a>
            </span>
        </div>
    </div>
</footer>
