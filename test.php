<!DOCTYPE html>
<html>
<head>
    <title>Test Simple</title>
</head>
<body>
    <h2>Test de Subida</h2>
    
    <input type="file" id="archivo" accept=".xls,.xlsx,.csv">
    <button onclick="probar()">Probar</button>
    
    <div id="resultado" style="margin-top:20px; padding:10px; border:1px solid #ccc;"></div>
    
    <script>
    function probar() {
        const fileInput = document.getElementById('archivo');
        const resultado = document.getElementById('resultado');
        
        if (!fileInput.files[0]) {
            resultado.innerHTML = 'Selecciona un archivo';
            return;
        }
        
        const formData = new FormData();
        formData.append('archivo', fileInput.files[0]);
        
        resultado.innerHTML = 'Enviando...';
        
        fetch('/Portal-Facturacion/controller/arcor/recep.arcor.scz.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            resultado.innerHTML = '<h3>Respuesta RAW:</h3><pre>' + text + '</pre>';
            
            try {
                const json = JSON.parse(text);
                resultado.innerHTML += '<h3 style="color:green">✓ JSON válido</h3>';
                resultado.innerHTML += '<pre>' + JSON.stringify(json, null, 2) + '</pre>';
            } catch(e) {
                resultado.innerHTML += '<h3 style="color:red">✗ Error parseando JSON: ' + e.message + '</h3>';
            }
        })
        .catch(error => {
            resultado.innerHTML = 'Error: ' + error;
        });
    }
    </script>
</body>
</html>