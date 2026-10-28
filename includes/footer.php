<?php
// includes/footer.php - Stitch UI Unified Footer Shell
?>
    <!-- BOTTOM CLEARANCE SPACER -->
    <div style="height: 36px; width: 100%; pointer-events: none;" aria-hidden="true"></div>
    <footer class="tech-system-footer">
        <div class="tech-footer-brand">
            <span class="tech-footer-dot"></span>
            <span>&copy; <?= date('Y') ?> <b>EDURADAR // THCS</b> &mdash; Data-Dense Early Warning Telemetry Engine (TT22/2021/TT-BGDDT).</span>
        </div>
        <div class="tech-footer-metrics">
            <span class="tech-footer-chip" style="display: inline-flex; align-items: center; gap: 5px;">
                <?= function_exists('get_simple_icon') ? get_simple_icon('php', 14) : '' ?>
                <span>PHP 8.0</span>
            </span>
            <span class="tech-footer-chip" style="display: inline-flex; align-items: center; gap: 5px;">
                <?= function_exists('get_simple_icon') ? get_simple_icon('mysql', 14) : '' ?>
                <span>MARIADB</span>
            </span>
            <span class="tech-footer-chip" style="display: inline-flex; align-items: center; gap: 5px;">
                <?= function_exists('get_simple_icon') ? get_simple_icon('github', 13) : '' ?>
                <span>EDURADAR CORE</span>
            </span>
        </div>
    </footer>
</main>
<script src="assets/js/gsap.min.js"></script>
<?php 
if (empty($extraScripts) || strpos($extraScripts, 'assets/js/main.js') === false) {
    echo '<script src="assets/js/main.js?v=' . (file_exists(__DIR__ . '/../assets/js/main.js') ? filemtime(__DIR__ . '/../assets/js/main.js') : time()) . '"></script>' . "\n";
}
if (!empty($extraScripts)) echo $extraScripts; 
?>
</body>
</html>
