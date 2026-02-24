@echo off
echo ========================================
echo Iniciando API Python para procesamiento Excel
echo ========================================
echo.

cd /d C:\xampp\htdocs\Portal-Facturacion

REM Verificar si Python está instalado
python --version >nul 2>&1
if errorlevel 1 (
    echo ERROR: Python no está instalado o no está en el PATH
    echo Por favor instala Python desde python.org
    pause
    exit /b 1
)

REM Activar entorno virtual
echo Activando entorno virtual...
call venv\Scripts\activate.bat

if errorlevel 1 (
    echo ERROR: No se pudo activar el entorno virtual
    echo Ejecuta primero: python -m venv venv
    pause
    exit /b 1
)

REM Verificar dependencias
echo Verificando dependencias...
python -c "import pandas" >nul 2>&1
if errorlevel 1 (
    echo Instalando dependencias...
    pip install -r requirements.txt
)

REM Iniciar API
echo.
echo Iniciando API en http://127.0.0.1:5000
echo Presiona Ctrl+C para detener
echo.
python api_python/app.py

pause