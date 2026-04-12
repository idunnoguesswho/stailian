@echo off
xcopy /E /Y /I "C:\Users\katie\OneDrive\Documents\GitHub\stailian\*.php" "F:\LoganUSB\root\"
xcopy /E /Y /I "C:\Users\katie\OneDrive\Documents\GitHub\stailian\*.css" "F:\LoganUSB\root\"
xcopy /E /Y /I "C:\Users\katie\OneDrive\Documents\GitHub\stailian\*.htaccess" "F:\LoganUSB\root\"

REM Remove config.php from local server — it contains production credentials
REM and must never run locally (db.php falls back to localhost automatically)
if exist "F:\LoganUSB\root\config.php" del "F:\LoganUSB\root\config.php"

echo Done! Open http://localhost:8080/index.php
pause
