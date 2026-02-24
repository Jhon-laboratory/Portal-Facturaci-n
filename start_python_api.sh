#!/bin/bash
echo "Iniciando API Python para procesamiento Excel..."
cd /var/www/html/Portal-Facturacion

# Activar entorno virtual
source venv/bin/activate

# Iniciar API
python api_python/app.py