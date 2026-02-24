@echo off
echo ========================================
echo INSTALADOR DE PYTHON PARA PORTAL FACTURACION
echo ========================================
echo.

cd /d C:\xampp\htdocs\Portal-Facturacion

REM Verificar Python
python --version >nul 2>&1
if errorlevel 1 (
    echo Python NO está instalado.
    echo.
    echo Por favor descarga e instala Python desde:
    echo https://www.python.org/downloads/
    echo.
    echo IMPORTANTE: Marca "Add Python to PATH" durante la instalacion
    echo.
    pause
    start https://www.python.org/downloads/
    exit /b 1
)

echo Python detectado correctamente
python --version
echo.

REM Crear entorno virtual
echo Creando entorno virtual...
python -m venv venv
if errorlevel 1 (
    echo Error creando entorno virtual
    pause
    exit /b 1
)
echo OK
echo.

REM Activar entorno virtual
echo Activando entorno virtual...
call venv\Scripts\activate.bat
echo OK
echo.

REM Instalar dependencias
echo Instalando dependencias...
pip install --upgrade pip
pip install pandas openpyxl flask flask-cors python-dotenv
echo OK
echo.

REM Crear estructura de directorios
echo Creando estructura de directorios...
if not exist api_python mkdir api_python
if not exist api_python\procesadores mkdir api_python\procesadores
if not exist api_python\utils mkdir api_python\utils
if not exist api_python\logs mkdir api_python\logs
if not exist controller mkdir controller
echo OK
echo.

REM Crear archivo de requerimientos
echo Creando requirements.txt...
(
echo pandas==2.0.3
echo openpyxl==3.1.2
echo flask==2.3.2
echo flask-cors==4.0.0
echo python-dotenv==1.0.0
) > requirements.txt
echo OK
echo.

echo ========================================
echo INSTALACION COMPLETADA EXITOSAMENTE
echo ========================================
echo.
echo Para iniciar la API:
echo   1. Ejecuta: iniciar_python_api.bat
echo.
echo Para probar:
echo   2. Abre http://127.0.0.1:5000/health en tu navegador
echo.
echo Deberias ver: {"status":"ok"}
echo.

pause