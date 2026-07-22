<?php
/**
 * geo.php — Proxy para datos geográficos de España.
 * Provincias: lista estática (52 + Ceuta + Melilla).
 * Municipios: proxy a GeoAPI.es (apiv1.geoapi.es).
 *
 * Uso:
 *   geo.php?tipo=provincias
 *   geo.php?tipo=municipios&cpro=18
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=86400'); // 1 día de caché

$tipo = $_GET['tipo'] ?? '';

// ──────────────────────────────────────────────────────────
// PROVINCIAS — lista estática 
// ──────────────────────────────────────────────────────────
if ($tipo === 'provincias') {
    $provincias = [
        ['cpro' => '01', 'nombre' => 'Araba/Álava'],
        ['cpro' => '02', 'nombre' => 'Albacete'],
        ['cpro' => '03', 'nombre' => 'Alicante/Alacant'],
        ['cpro' => '04', 'nombre' => 'Almería'],
        ['cpro' => '05', 'nombre' => 'Ávila'],
        ['cpro' => '06', 'nombre' => 'Badajoz'],
        ['cpro' => '07', 'nombre' => 'Balears (Illes)'],
        ['cpro' => '08', 'nombre' => 'Barcelona'],
        ['cpro' => '09', 'nombre' => 'Burgos'],
        ['cpro' => '10', 'nombre' => 'Cáceres'],
        ['cpro' => '11', 'nombre' => 'Cádiz'],
        ['cpro' => '12', 'nombre' => 'Castellón/Castelló'],
        ['cpro' => '13', 'nombre' => 'Ciudad Real'],
        ['cpro' => '14', 'nombre' => 'Córdoba'],
        ['cpro' => '15', 'nombre' => 'Coruña (A)'],
        ['cpro' => '16', 'nombre' => 'Cuenca'],
        ['cpro' => '17', 'nombre' => 'Girona'],
        ['cpro' => '18', 'nombre' => 'Granada'],
        ['cpro' => '19', 'nombre' => 'Guadalajara'],
        ['cpro' => '20', 'nombre' => 'Gipuzkoa'],
        ['cpro' => '21', 'nombre' => 'Huelva'],
        ['cpro' => '22', 'nombre' => 'Huesca'],
        ['cpro' => '23', 'nombre' => 'Jaén'],
        ['cpro' => '24', 'nombre' => 'León'],
        ['cpro' => '25', 'nombre' => 'Lleida'],
        ['cpro' => '26', 'nombre' => 'Rioja (La)'],
        ['cpro' => '27', 'nombre' => 'Lugo'],
        ['cpro' => '28', 'nombre' => 'Madrid'],
        ['cpro' => '29', 'nombre' => 'Málaga'],
        ['cpro' => '30', 'nombre' => 'Murcia'],
        ['cpro' => '31', 'nombre' => 'Navarra'],
        ['cpro' => '32', 'nombre' => 'Ourense'],
        ['cpro' => '33', 'nombre' => 'Asturias'],
        ['cpro' => '34', 'nombre' => 'Palencia'],
        ['cpro' => '35', 'nombre' => 'Palmas (Las)'],
        ['cpro' => '36', 'nombre' => 'Pontevedra'],
        ['cpro' => '37', 'nombre' => 'Salamanca'],
        ['cpro' => '38', 'nombre' => 'Santa Cruz de Tenerife'],
        ['cpro' => '39', 'nombre' => 'Cantabria'],
        ['cpro' => '40', 'nombre' => 'Segovia'],
        ['cpro' => '41', 'nombre' => 'Sevilla'],
        ['cpro' => '42', 'nombre' => 'Soria'],
        ['cpro' => '43', 'nombre' => 'Tarragona'],
        ['cpro' => '44', 'nombre' => 'Teruel'],
        ['cpro' => '45', 'nombre' => 'Toledo'],
        ['cpro' => '46', 'nombre' => 'Valencia/València'],
        ['cpro' => '47', 'nombre' => 'Valladolid'],
        ['cpro' => '48', 'nombre' => 'Bizkaia'],
        ['cpro' => '49', 'nombre' => 'Zamora'],
        ['cpro' => '50', 'nombre' => 'Zaragoza'],
        ['cpro' => '51', 'nombre' => 'Ceuta'],
        ['cpro' => '52', 'nombre' => 'Melilla'],
    ];
    echo json_encode($provincias, JSON_UNESCAPED_UNICODE);
    exit;
}

// ──────────────────────────────────────────────────────────
// MUNICIPIOS — OpenDataSoft con caché local en disco
// ──────────────────────────────────────────────────────────
if ($tipo === 'municipios') {
    $cpro = preg_replace('/[^0-9]/', '', $_GET['cpro'] ?? '');
    if ($cpro === '' || strlen($cpro) > 2) {
        http_response_code(400);
        echo json_encode(['error' => 'Parámetro cpro inválido.']);
        exit;
    }
    $cpro = str_pad($cpro, 2, '0', STR_PAD_LEFT);

    // Caché local: data/municipios_{cpro}.json (TTL 30 días)
    $cacheDir  = __DIR__ . '/data/';
    $cacheFile = $cacheDir . 'municipios_' . $cpro . '.json';
    $cacheTTL  = 30 * 24 * 3600;

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTTL) {
        echo file_get_contents($cacheFile);
        exit;
    }

    $baseUrl = 'https://public.opendatasoft.com/api/explore/v2.1/catalog/datasets/georef-spain-municipio/records'
             . '?limit=100'
             . '&select=mun_name'
             . '&where=prov_code%3D%22' . rawurlencode($cpro) . '%22'
             . '&order_by=mun_name';

    $municipios = [];
    $offset     = 0;
    $totalCount = null;
    $apiError   = false;

    do {
        $apiUrl = $baseUrl . '&offset=' . $offset;
        $ch = curl_init($apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_USERAGENT      => 'BorrascasWeb/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $err) {
            $apiError = true;
            break;
        }

        $decoded = json_decode($raw, true);
        if (!isset($decoded['results']) || !is_array($decoded['results'])) {
            $apiError = true;
            break;
        }

        if ($totalCount === null) {
            $totalCount = (int)($decoded['total_count'] ?? 0);
        }

        foreach ($decoded['results'] as $m) {
            $municipios[] = ['nombre' => $m['mun_name']];
        }

        $offset += 100;
    } while ($offset < $totalCount);

    if ($apiError || empty($municipios)) {
        // Si la API falla, devolver caché antigua si existe, o lista vacía
        if (file_exists($cacheFile)) {
            echo file_get_contents($cacheFile);
        } else {
            echo json_encode([]);
        }
        exit;
    }

    $json = json_encode($municipios, JSON_UNESCAPED_UNICODE);
    // Guardar caché si el directorio es escribible
    if (is_dir($cacheDir) && is_writable($cacheDir)) {
        file_put_contents($cacheFile, $json);
    }
    echo $json;
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Parámetro tipo no válido.']);
