<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/pdf.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Método no permitido.');
    }

    pa_verify_csrf();

    $slug = (string)($_POST['template'] ?? '');
    $template = $slug !== '' ? pa_get_template($slug) : null;
    if ($template === null) {
        throw new RuntimeException('No se encontró la plantilla solicitada.');
    }
    if (empty($template['pdf_exists'])) {
        throw new RuntimeException('La plantilla seleccionada no tiene PDF asociado en la carpeta de plantillas.');
    }

    $values = [];
    $errors = [];
    foreach (($template['fields'] ?? []) as $field) {
        if (!is_array($field)) {
            continue;
        }

        $name = (string)($field['name'] ?? '');
        if ($name === '') {
            continue;
        }

        $value = trim((string)($_POST[$name] ?? ''));
        $values[$name] = $value;

        if (!empty($field['required']) && $value === '') {
            $errors[] = 'El campo "' . ($field['label'] ?? $name) . '" es obligatorio.';
        }

        if ($value !== '') {
            if (($field['type'] ?? '') === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'El campo "' . ($field['label'] ?? $name) . '" no es un correo válido.';
            }

            if (($field['type'] ?? '') === 'number' && !is_numeric($value)) {
                $errors[] = 'El campo "' . ($field['label'] ?? $name) . '" debe ser numérico.';
            }
        }
    }

    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $personKey = strtolower(trim((string)($template['person_key'] ?? 'dni')));

    // Búsqueda insensible a mayúsculas/minúsculas en los valores enviados
    $dniRaw = '';
    foreach ($values as $k => $v) {
        if (strtolower($k) === $personKey && $v !== '') {
            $dniRaw = $v;
            break;
        }
    }
    // Fallback: buscar cualquier campo con nombre parecido a dni/nif
    if ($dniRaw === '') {
        foreach ($values as $k => $v) {
            if (preg_match('/^(dni|nif|nif_?dni|nie)$/i', $k) && $v !== '') {
                $dniRaw = $v;
                break;
            }
        }
    }
    // Último fallback: carpeta anónima con ID generado
    $dni = $dniRaw !== '' ? pa_normalize_dni($dniRaw) : ('anonimo_' . date('Ymd'));

    $signatureData = trim((string)($_POST['firma_data'] ?? ''));
    if ($signatureData === '') {
        throw new RuntimeException('Debes firmar el documento antes de enviarlo.');
    }

    $signatureTmp = pa_signature_from_data_uri($signatureData);
    $expedienteId = date('Ymd_His') . '_' . bin2hex(random_bytes(3));
    $expedienteDir = pa_expediente_path($dni, $expedienteId);
    $adjuntos = [];
    $adjuntosDir = '';

    if (!is_dir($expedienteDir)) {
        mkdir($expedienteDir, 0755, true);
    }

    if (!empty($_FILES['adjuntos']['name'][0])) {
        $files = $_FILES['adjuntos'];
        $count = count((array)$files['name']);
        if ($count > MAX_ATTACHMENTS) {
            throw new RuntimeException('Se permiten como máximo ' . MAX_ATTACHMENTS . ' adjuntos.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $adjuntosDir = $expedienteDir . 'adjuntos/';
        if (!is_dir($adjuntosDir)) {
            mkdir($adjuntosDir, 0755, true);
        }

        for ($i = 0; $i < $count; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $size = (int)($files['size'][$i] ?? 0);
            if ($size > MAX_ATTACHMENT_BYTES) {
                throw new RuntimeException('Uno de los adjuntos supera el tamaño máximo permitido.');
            }

            $tmpName = (string)($files['tmp_name'][$i] ?? '');
            $mime = $finfo->file($tmpName) ?: '';
            if (!array_key_exists($mime, ALLOWED_ATTACHMENT_MIME)) {
                throw new RuntimeException('Uno de los adjuntos tiene un formato no permitido.');
            }

            $originalName = pa_safe_filename((string)($files['name'][$i] ?? 'archivo'));
            $storedName = 'adj_' . bin2hex(random_bytes(8)) . '.' . ALLOWED_ATTACHMENT_MIME[$mime];
            $destination = $adjuntosDir . $storedName;

            if (!move_uploaded_file($tmpName, $destination)) {
                throw new RuntimeException('No se pudo guardar uno de los adjuntos.');
            }

            $adjuntos[] = [
                'original' => $originalName,
                'archivo' => $storedName,
                'mime' => $mime,
                'peso' => $size,
            ];
        }
    }

    $pdfName = 'documento_firmado.pdf';
    $pdfPath = $expedienteDir . $pdfName;
    pa_generate_document_pdf($template, $values, $pdfPath, $signatureTmp);

    $displayName = trim((string)($values['nombre'] ?? $values['nombre_completo'] ?? $values['razon_social'] ?? $dni));
    $record = [
        'id' => $expedienteId,
        'dni' => $dni,
        'nombre' => $displayName,
        'template' => [
            'slug' => (string)$template['slug'],
            'title' => (string)($template['title'] ?? $template['slug']),
        ],
        'pdf' => $pdfName,
        'adjuntos' => $adjuntos,
        'folder' => $expedienteDir,
        'created_at' => date('c'),
        'signed_at' => date('c'),
        'fields' => $values,
    ];

    pa_save_json($expedienteDir . 'datos.json', $record);
    pa_append_registry($record);

    if (file_exists($signatureTmp)) {
        unlink($signatureTmp);
    }

    pa_flash('ok', 'Trámite guardado y firmado correctamente.');
    header('Location: index.php?ref=' . rawurlencode($expedienteId));
    exit;
} catch (Throwable $e) {
    if (isset($signatureTmp) && is_string($signatureTmp) && file_exists($signatureTmp)) {
        unlink($signatureTmp);
    }

    pa_flash('error', $e->getMessage());
    $back = 'index.php';
    if (!empty($_POST['template'])) {
        $back = 'solicitud.php?template=' . rawurlencode((string)$_POST['template']);
    }
    header('Location: ' . $back);
    exit;
}
