<?php
// includes/footer.php - Stitch UI Unified Footer Shell
?>
    <!-- BOTTOM CLEARANCE SPACER -->
    <div style="height: 30px; width: 100%; pointer-events: none;" aria-hidden="true"></div>
    <footer style="margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; font-size: 12.5px; color: var(--text-muted); flex-wrap: wrap; gap: 12px;">
        <p>© <?= date('Y') ?> <b>EduRadar THCS</b> – Hệ Thống Quản Lý Điểm & Cảnh Báo Sớm Học Sinh (Thông Tư 22/2021/TT-BGDĐT).</p>
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981; display: inline-block;"></span>
            <span>Cơ sở dữ liệu MariaDB (MySQL 3306)</span>
        </div>
    </footer>
</main>
<?php if (!empty($extraScripts)) echo $extraScripts; ?>
</body>
</html>
