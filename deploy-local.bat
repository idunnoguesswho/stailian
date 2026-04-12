@echo off
xcopy /E /Y /I "C:\Users\katie\OneDrive\Documents\GitHub\stailian\*.php" "F:\LoganUSB\usbwebserver\root\"
xcopy /E /Y /I "C:\Users\katie\OneDrive\Documents\GitHub\stailian\*.css" "F:\LoganUSB\usbwebserver\root\"
xcopy /E /Y /I "C:\Users\katie\OneDrive\Documents\GitHub\stailian\*.htaccess" "F:\LoganUSB\usbwebserver\root\"
echo Done! Open http://localhost:8080/index.php
pause
