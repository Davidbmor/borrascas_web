<?php
// ============================================================
//  VALORES GLOBALES DE CAMPAÑA (modificables cada temporada)
// ============================================================
define('PREVISION_GRANADA_TM',   124000);  // Tm
define('CIERRE_GRANADA_TM',       98000);  // Tm
// Bajada = (100 - CIERRE*100/PREVISION) / 100  →  expresada como % para mostrar
// (100 - 98000*100/124000) / 100 * 100 = 20.97 %
define('BAJADA_PORCENTAJE',        20.97); // %
define('RENDIMIENTO_MEDIO',        0.2077); // 20.77 %
define('PRECIO_KG_AOVE',            4.40); // €/kg aceite
define('PRECIO_CALIDAD_ACEITE',     1.50); // €/kg aceite
define('SOBRECOSTE_RECOLECCION',    0.25); // €/kg aceituna
define('SOBRECOSTE_PRODUCCION',     0.04); // €/kg aceite

// Titulo campaña (aparece en el PDF)
define('TITULO_CAMPANA', 'Campaña Oleícola 2024/2025');
define('FECHA_RECOLECCION', '10/11/2025');

// ============================================================
//  COOPERATIVAS
// ============================================================
define('COOPERATIVAS', [
    'ACEITES ALGARINEJO, S. COOP. AND.',
    'ACEITES FUENTES DE CESNA, S. COOP. AND.',
    'AGROPECUARIA DE ALTURA, S. COOP. AND.',
    'AGRÍCOLA LOS TAJOS, S. COOP. AND.',
    'AGRÍCOLA SAN FRANCISCO (COSAFRA), S. COOP. AND.',
    'AGRÍCOLA SAN ROGELIO DE ÍLLORA, S. COOP. AND.',
    'AGRÍCOLA SANTA BÁRBARA DE BAZA, S. COOP. AND.',
    'ALBA GANADEROS, S. COOP. AND.',
    'ALMAZARA DE MONTILLANA, S. COOP. AND.',
    'ALMAZARA NUESTRA SEÑORA DE LOS REMEDIOS, S. COOP. AND.',
    'ALMENDRAS ALHAMBRA, S. COOP. AND.',
    'ALMENDRAS DEL NORTE DE GRANADA, S. COOP. AND.',
    'ALMENDRAS GRANADA (ALGRA), S. COOP. AND.',
    'BARRANCO DEL CIGARRAL, S. COOP. AND.',
    'CAMPO-AGRO OLIVARERA, S. COOP. AND.',
    'CENTRO SUR (CESURCA), S. COOP. AND.',
    'COMERCIALIZADORA CRIADORES DE OVINO ECOLOGICO LOJEÑO DE SIERRA (COVECOL), S. COOP. AND.',
    'COMERCIALIZADORA SEGUREÑA, S. COOP. AND.',
    'CONDE DE BENALÚA, S. COOP. AND.',
    'COUAGA VEGAS DE GRANADA, S. COOP. AND.',
    'EL GRUPO, S. COOP. AND.',
    'EL LLANETE, S. COOP. AND.',
    'ESPAFRÓN, S. COOP. AND.',
    'ESPALORQUIANA, S. COOP. AND.',
    'ESPÁRRAGO DE CARTUJA, S. COOP. AND.',
    'ESPÁRRAGO DE GRANADA, S. COOP. AND. DE 2º GRADO',
    'GRANADA LA PALMA, S. COOP. AND.',
    'GRANÁ GENIL, S. COOP. AND.',
    'HERCO-FRUT, S. COOP. AND.',
    'HERMANOS GARCÍA GUTIÉRREZ, S. COOP. AND.',
    'HORTOVILLA, S. COOP. AND.',
    'HÁBITAT AOVE, S. COOP. AND.',
    'LA ESPERANZA DEL CAMPO, S. COOP. AND.',
    'LA FLOR DE LA ALPUJARRA, S. COOP. AND.',
    'LA SANTA CRUZ, S. COOP. AND.',
    'LA UNIÓN DE ANDALUCÍA, S. A. T.',
    'LOS FRESNOS, S. COOP. AND.',
    'LOS GALLOMBARES, S. COOP. AND.',
    'LOS PALMARES, S. COOP. AND.',
    'MAITENA DEL GENIL, S. COOP. AND.',
    'NUESTRA SEÑORA DE LA CABEZA DE CÚLLAR, S. COOP. AND.',
    'NUESTRA SEÑORA DE LA CABEZA DE ZÚJAR, S. COOP. AND.',
    'NUESTRA SEÑORA DE LOS DOLORES DE FREILA, S. COOP. AND.',
    'NUESTRA SEÑORA DE LOS DOLORES, S. COOP. AND.',
    'NUESTRA SEÑORA DE LOS REMEDIOS DE CAMPOTÉJAR, S. COOP. AND.',
    'NUESTRA SEÑORA DE LOS REMEDIOS DE IZNALLOZ, S. COOP. AND.',
    'NUESTRA SEÑORA DEL PILAR DE COLOMERA, S. COOP. AND.',
    'NUESTRA SEÑORA DEL ROSARIO DE CASTRIL, S. COOP. AND.',
    'NUESTRA SEÑORA DEL ROSARIO DE DEHESAS VIEJAS, S. COOP. AND.',
    'NUESTRO SEÑOR DE LAS TRES MARÍAS, S. COOP. AND.',
    'OLEOMONTES, S. COOP. AND.',
    'OLEOTROPIC, S. COOP. AND.',
    'OLIJAYENA, S. COOP. AND.',
    'PROCAM, S. COOP. AND.',
    'PRODUCTORES AGRARIOS DE BENALÚA DE GUADIX (BENAFRU), S. COOP. AND.',
    'PRODUCTORES DE CAÑA DE AZÚCAR Y REMOLACHA DEL LITORAL DE GRANADA, S. COOP. AND.',
    'PUERTO LOPE, S. COOP. AND.',
    'S. A. T. ALMENCASTRIL',
    'S. A. T. HORTOVENTAS',
    'S. A. T. LODAISA',
    'S. A. T. NUESTRA SEÑORA DEL PERPETUO SOCORRO',
    'S. A. T. SOL DEL FARDES',
    'S. A. T. TABACOS GRANADA ASOCIACIÓN',
    'S. A. T. TRAMA Y AZAHAR',
    'SAN ANTONIO DE COGOLLOS, S. COOP. AND.',
    'SAN FRANCISCO DE ASÍS, S. COOP. AND.',
    'SAN FRANCISCO SERRANO, S. COOP. AND.',
    'SAN ILDEFONSO DE PELIGROS, S. COOP. AND.',
    'SAN ISIDRO DE DEIFONTES, S. COOP. AND.',
    'SAN ISIDRO DE LOJA, S. COOP. AND.',
    'SAN LORENZO DE ZAGRA, S. COOP. AND.',
    'SAN ROQUE DE PINOS DEL VALLE, S. COOP. AND.',
    'SAN SEBASTIÁN DE ALFACAR, S. COOP. AND.',
    'SANTA ANA DE SALAR, S. COOP. AND.',
    'SANTA ISABEL DE CAMPOTÉJAR, S. COOP. AND.',
    'SANTA MÓNICA DE PÍÑAR, S. COOP. AND.',
    'SANTIAGO APÓSTOL, S. COOP. AND.',
    'SOTO DE FUENTE VAQUEROS, S. COOP. AND.',
    'TEMPLEOLIVA, S. COOP. AND.',
    'UNION AGRÍCOLA SAN JOSÉ, S. COOP. AND.',
    'VARAILA DE DOMINGO PÉREZ, S. COOP. AND.',
    'VEGACHAUCHINA, S. COOP. AND.',
    'VINÍCOLA ALHAMEÑA SIERRA DE TEJEDA, S. COOP. AND.',
    'VIRGEN DE LA CABEZA, S. COOP. AND.',
    'No socio',
]);

// ============================================================
//  RUTAS
// ============================================================
define('EXCEL_PATH',   __DIR__ . '/data/autorizados.xlsx');

// ============================================================
//  GOOGLE SHEETS (verificación de DNI autorizados)
// ============================================================
define('GSHEETS_CREDENTIALS', __DIR__ . '/excel-borrascas-6912c3abd273.json');
define('GSHEETS_SPREADSHEET_ID', '1yah7ukkHs31dLZqDLG_jfmHbEYquGcoYCok9lCSO9Js');
define('GSHEETS_RANGE', 'A:Z');  // columnas a leer
define('UPLOADS_DIR',  __DIR__ . '/uploads/');
define('INFORMES_DIR', __DIR__ . '/informes/');
define('REGISTRO_JSON', INFORMES_DIR . 'registro.json');
define('TRAMITES_DIR', __DIR__ . '/tramites_altas_bajas/');
define('TRAMITES_PLANTILLAS_DIR', TRAMITES_DIR . 'plantillas/');
define('TRAMITES_EXPEDIENTES_DIR', TRAMITES_DIR . 'expedientes/');

// ============================================================
//  PANEL DE ADMINISTRACIÓN
//  Cambiar la contraseña con: echo password_hash('TuPassword', PASSWORD_DEFAULT);
// ============================================================
define('ADMIN_USER',          'admin');
define('ADMIN_PASSWORD_HASH', '$2y$10$dfWkkAKorUuPPmr1ZJinVOaZmPnTf9C3pCZxqBTz2UEX7NeG3Za82');

// ============================================================
//  LÍMITES DE SUBIDA DE IMÁGENES
// ============================================================
define('MAX_IMAGENES',       10);
define('MAX_TAMANO_IMG', 8388608); // 8 MB por imagen
define('TIPOS_IMAGEN',  ['image/jpeg', 'image/png', 'image/webp']);

// ============================================================
//  LÍMITES DE DOCUMENTOS ADJUNTOS
// ============================================================
define('MAX_ADJUNTOS',        5);
define('MAX_TAMANO_ADJUNTO', 8388608); // 8 MB por archivo
define('TIPOS_ADJUNTO', [
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/webp'      => 'webp',
    'application/pdf' => 'pdf',
]);
