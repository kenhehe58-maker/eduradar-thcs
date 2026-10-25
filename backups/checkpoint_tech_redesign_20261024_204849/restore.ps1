# =============================================================================
# EduRadar THCS - 1-Click Rollback Script to Checkpoint: 20261024_204849
# =============================================================================
$ErrorActionPreference = "Stop"
$ckpt = "D:\xam\htdocs\my-web-project\backups\checkpoint_tech_redesign_20261024_204849"
$webDir = "D:\xam\htdocs\my-web-project"

Write-Host "🔄 Đang khôi phục toàn bộ mã nguồn PHP, CSS, JS từ Checkpoint 20261024_204849..." -ForegroundColor Cyan
Copy-Item "$ckpt\*.php" -Destination $webDir -Force
Copy-Item "$ckpt\includes\*.php" -Destination "$webDir\includes" -Force
Copy-Item "$ckpt\assets\css\*.css" -Destination "$webDir\assets\css" -Force
Copy-Item "$ckpt\assets\js\*.js" -Destination "$webDir\assets\js" -Force

Write-Host "🗄️ Đang khôi phục cơ sở dữ liệu MariaDB (edu_radar)..." -ForegroundColor Cyan
cmd /c "D:\xam\mysql\bin\mysql.exe -u root edu_radar < "$ckpt\edu_radar_checkpoint.sql""

Write-Host "✅ ĐÃ QUAY LẠI THÀNH CÔNG CHECKPOINT: 20261024_204849!" -ForegroundColor Green
