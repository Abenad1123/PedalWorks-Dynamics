<footer class="pw-glass-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="pw-footer-brand">
                    <span class="pw-brand-icon">
                        <i class="fa-solid fa-person-biking"></i>
                    </span>
                    <span>PedalWorks Dynamics</span>
                </div>
                <p class="pw-footer-text">
                    Engineered for high-altitude climbs, rugged mountain trails, and daily urban expeditions. Your trusted bicycle shop and master repair laboratory.
                </p>
            </div>

            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="pw-footer-head">Adventure Gear</h6>
                <ul class="pw-footer-links">
                    <li><a href="/project/PedalWorks-Dynamics/index.php#products"><i class="fa-solid fa-chevron-right fa-xs"></i>Mountain Rigs</a></li>
                    <li><a href="/project/PedalWorks-Dynamics/index.php#products"><i class="fa-solid fa-chevron-right fa-xs"></i>Gravel &amp; Trail</a></li>
                    <li><a href="/project/PedalWorks-Dynamics/index.php#products"><i class="fa-solid fa-chevron-right fa-xs"></i>Hydraulic Brakes</a></li>
                    <li><a href="/project/PedalWorks-Dynamics/index.php#products"><i class="fa-solid fa-chevron-right fa-xs"></i>Safety Helmets</a></li>
                    <li><a href="/project/PedalWorks-Dynamics/index.php#products"><i class="fa-solid fa-chevron-right fa-xs"></i>Cargo Racks</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6 col-6">
                <h6 class="pw-footer-head">Workshop Lab</h6>
                <ul class="pw-footer-links">
                    <li><a href="/project/PedalWorks-Dynamics/index.php#services"><i class="fa-solid fa-wrench fa-xs"></i>General Trail Tune-Up</a></li>
                    <li><a href="/project/PedalWorks-Dynamics/index.php#services"><i class="fa-solid fa-wrench fa-xs"></i>Hydraulic Bleed &amp; Align</a></li>
                    <li><a href="/project/PedalWorks-Dynamics/index.php#services"><i class="fa-solid fa-wrench fa-xs"></i>Spoke Wheel Truing</a></li>
                    <li><a href="/project/PedalWorks-Dynamics/index.php#services"><i class="fa-solid fa-wrench fa-xs"></i>Drivetrain Ultrasonic Clean</a></li>
                    <li><a href="/project/PedalWorks-Dynamics/index.php#services"><i class="fa-solid fa-wrench fa-xs"></i>Full Expedition Overhaul</a></li>
                </ul>
            </div>

            <div class="col-lg-3 col-md-6">
                <h6 class="pw-footer-head">Basecamp &amp; Contact</h6>
                <ul class="list-unstyled pw-footer-contact">
                    <li>
                        <i class="fa-solid fa-location-dot"></i>
                        <span>Km. 14 East Service Road, Western Bicutan, Taguig City 1630</span>
                    </li>
                    <li>
                        <i class="fa-solid fa-phone"></i>
                        <span>(02) 8823-2457 / +63 917 555 BIKE</span>
                    </li>
                    <li>
                        <i class="fa-solid fa-clock"></i>
                        <span>Mon – Sat: 8:00 AM – 6:30 PM<br>Sunday: 9:00 AM – 3:00 PM</span>
                    </li>
                    <li>
                        <i class="fa-solid fa-envelope"></i>
                        <span>service@pedalworks.ph</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="d-flex flex-column flex-md-row align-items-center justify-content-between pw-footer-bottom gap-3">
            <p class="mb-0 text-center text-md-start">
                &copy; <?php echo date('Y'); ?> <strong>PedalWorks Dynamics</strong>. Designed for the adventurous trail rider. All rights reserved.
            </p>
            <div class="d-flex align-items-center gap-3">
                <span class="text-secondary small">TUP Taguig BSIT-2B Information Management Project</span>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const nav = document.querySelector('.pw-glass-nav');
    window.addEventListener('scroll', function () {
        if (window.scrollY > 40) {
            nav?.classList.add('scrolled');
        } else {
            nav?.classList.remove('scrolled');
        }
    });
});
</script>
</body>
</html>
