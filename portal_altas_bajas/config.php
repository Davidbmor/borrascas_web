<?php
declare(strict_types=1);

const APP_NAME = 'Portal de Altas y Bajas';
const APP_SUBTITLE = 'Solicitud online, firma y archivo';

const ROOT_DIR = __DIR__;
const VENDOR_AUTOLOAD = ROOT_DIR . '/../vendor/autoload.php';

const STORAGE_DIR = ROOT_DIR . '/storage';
const TEMPLATES_DIR = ROOT_DIR . '/plantillas';
const DEFINITIONS_DIR = STORAGE_DIR . '/definiciones';
const EXPEDIENTES_DIR = STORAGE_DIR . '/expedientes';
const TMP_DIR = STORAGE_DIR . '/tmp';
const REGISTRY_FILE = STORAGE_DIR . '/registro.json';

const ADMIN_USER = 'admin';
const ADMIN_PASSWORD = 'cambiar-esta-clave';

const MAX_ATTACHMENTS = 5;
const MAX_ATTACHMENT_BYTES = 8388608; // 8 MB
const ALLOWED_ATTACHMENT_MIME = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

const ALLOWED_SIGNATURE_MIME = ['image/png'];
const DEFAULT_TEXT_FONT = 'Helvetica';
