# =============================================================================
# EduRadar THCS - 1-Click Safe Rollback Script (Khôi phục toàn bộ Code + Database)
# Cách chạy: powershell -ExecutionPolicy Bypass -File "D:\xam\htdocs\my-web-project\backups\restore_checkpoint.ps1"
# =============================================================================
$ErrorActionPreference = "Stop"
$ckptDir = "D:\xam\htdocs\my-web-project\backups\checkpoint_full_code_and_db"
$webDir  = "D:\xam\htdocs\my-web-project"

Write-Host "🔄 Đang khôi phục toàn bộ mã nguồn PHP & CSS từ Checkpoint..." -ForegroundColor Cyan
Copy-Item "$ckptDir\*.php" -Destination $webDir -Force
Copy-Item "$ckptDir\includes\*.php" -Destination "$webDir\includes" -Force
Copy-Item "$ckptDir\assets\css\*.css" -Destination "$webDir\assets\css" -Force

Write-Host "🗄️ Đang khôi phục cơ sở dữ liệu MariaDB (edu_radar)..." -ForegroundColor Cyan
cmd /c "D:\xam\mysql\bin\mysql.exe -u root edu_radar < `"$ckptDir\edu_radar_checkpoint.sql`""

Write-Host "✅ KHÔI PHỤC HOÀN TẤT 100%! Dự án đã quay về trạng thái an toàn ban đầu." -ForegroundColor Green
