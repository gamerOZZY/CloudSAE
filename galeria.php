<?php
// Configuración
$storage_account = 'cuentalol';
$container = 'contenedorlol';
$clave = 'clavelol';
$blob_url_base = "https://$storage_account.blob.core.windows.net/$container";


// SUBIDA de imagen
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
    $archivo = $_FILES['archivo']['tmp_name'];
    $nombre = $_FILES['archivo']['name'];

    $url = "$blob_url_base/$nombre";

    $fp = fopen($archivo, "r");
    $contenido = fread($fp, filesize($archivo));
    fclose($fp);

    $date = gmdate("D, d M Y H:i:s T");
    $length = strlen($contenido);
    $headers = array(
        "x-ms-blob-type: BlockBlob",
        "x-ms-date: $date",
        "x-ms-version: 2020-10-02",
        "Content-Length: $length"
    );

    $resource = "/$storage_account/$container/$nombre";
    $stringToSign = "PUT\n\n\n$length\n\nimage/jpeg\n\n\n\n\n\n\nx-ms-blob-type:BlockBlob\nx-ms-date:$date\nx-ms-version:2020-10-02\n$resource";
    $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($clave), true));
    $authorization = "SharedKey $storage_account:$signature";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($headers, array("Authorization: $authorization", "Content-Type: image/jpeg")));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $contenido);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $mensaje = ($code == 201) ? "Ara! Ara! Imagen subida con éxito 🥰" : "Upsi, hubo un error al subir: Código HTTP $code";
    
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subida y Galería de Imágenes</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4; }
        h2 { color: #2c3e50; }
        form { margin-bottom: 30px; background: #fff; padding: 20px; border-radius: 8px; }
        .galeria { display: flex; flex-wrap: wrap; gap: 15px; }
        .img-card { text-align: center; width: 150px; }
        .img-card img { width: 150px; height: 150px; object-fit: cover; border: 1px solid #ccc; border-radius: 4px; }
        .mensaje { color: green; margin-bottom: 10px; }
    </style>
</head>
<body>
    <h2>📤 Subir Imagen a Azure Blob Storage</h2>
    
    <?php if ($mensaje): ?>
        <p class="mensaje"><?php echo $mensaje; ?></p>
    <?php endif; ?>

    <form action="galeria.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="archivo" required>
        <input type="submit" value="Subir Imagen">
    </form>

    <h2>🖼️ Galería de Imágenes</h2>
    <div class="galeria">
        <?php
        // Mostrar imágenes
        $date = gmdate("D, d M Y H:i:s T");
        $headers = [
            "x-ms-date: $date",
            "x-ms-version: 2020-10-02"
        ];

        $resource = "/$storage_account/$container\ncomp:list\nrestype:container";
        $stringToSign = "GET\n\n\n\n\n\n\n\n\n\n\n\nx-ms-date:$date\nx-ms-version:2020-10-02\n$resource";
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($clave), true));
        $authorization = "SharedKey $storage_account:$signature";

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "$blob_url_base?restype=container&comp=list",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_merge($headers, ["Authorization: $authorization"])
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        if ($response) {
            
            $xml = simplexml_load_string($response);            
            if ($xml && isset($xml->Blobs->Blob)) {
            
                foreach ($xml->Blobs->Blob as $blob) {
                    $nombreBlob = (string)$blob->Name;
                    $url = "$blob_url_base/$nombreBlob";
                    echo "<div class='img-card'>
                            <a href='$url' target='_blank'>
                                <img src='$url' alt='$nombreBlob'>
                            </a>
                            <p style='font-size:12px;'>$nombreBlob</p>
                          </div>";
                }
            } else {
                echo "Ara! Ara! No hay imágenes aún, cielito 💙";
            }
        } else {
            echo "Upsi, hubo un error al cargar galería 😢";
        }
        ?>
    </div>
</body>
</html>
