@echo off
echo Iniciando API Python para procesamiento Excel...
cd /d C:\xampp\htdocs\Portal-Facturacion

REM Activar entorno virtual
call venv\Scripts\activate

REM Iniciar API
python api_python/app.py

pause