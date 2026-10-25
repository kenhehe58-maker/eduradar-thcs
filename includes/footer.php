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
            <span class="tech-footer-chip">ENGINE: PHP 8.0</span>
            <span class="tech-footer-chip">DB: MARIADB 10.4</span>
            <span class="tech-footer-chip">PRECISION: TABULAR NUMS</span>
        </div>
    </footer>
</main>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
