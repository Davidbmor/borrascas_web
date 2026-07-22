<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once VENDOR_AUTOLOAD;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function pa_bootstrap(): void
{
    foreach ([STORAGE_DIR, DEFINITIONS_DIR, EXPEDIENTES_DIR, TMP_DIR] as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    if (!file_exists(REGISTRY_FILE)) {
        file_put_contents(REGISTRY_FILE, "[]");
    }
}

pa_bootstrap();

function pa_h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pa_flash(string $key, ?string $value = null): ?string
{
    $sessionKey = 'flash_' . $key;

    if ($value !== null) {
        $_SESSION[$sessionKey] = $value;
        return null;
    }

    if (!array_key_exists($sessionKey, $_SESSION)) {
        return null;
    }

    $message = (string)$_SESSION[$sessionKey];
    unset($_SESSION[$sessionKey]);
    return $message;
}

function pa_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_token'];
}

function pa_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . pa_h(pa_csrf_token()) . '">';
}

function pa_verify_csrf(): void
{
    $token = (string)($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
        throw new RuntimeException('Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');
    }
}

function pa_is_admin(): bool
{
    return !empty($_SESSION['admin_ok']);
}

function pa_require_admin(): void
{
    if (!pa_is_admin()) {
        header('Location: admin.php');
        exit;
    }
}

function pa_slugify(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'tramite';
    }

    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($ascii !== false) {
        $value = $ascii;
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
    $value = trim($value, '-');

    return $value !== '' ? $value : 'tramite';
}

function pa_normalize_dni(string $dni): string
{
    return strtoupper(preg_replace('/[^0-9A-Z]/', '', $dni) ?? '');
}

function pa_safe_filename(string $name): string
{
    $name = basename($name);
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?? $name;
    return $name !== '' ? $name : 'archivo';
}

function pa_load_json(string $path, array $fallback = []): array
{
    if (!file_exists($path)) {
        return $fallback;
    }

    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function pa_save_json(string $path, array $data): void
{
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function pa_template_catalog(): array
{
    $templates = [];
    foreach (glob(DEFINITIONS_DIR . '/*.json') ?: [] as $definitionFile) {
        $definition = pa_load_json($definitionFile, []);
        if ($definition === []) {
            continue;
        }

        $definition['slug'] = (string)($definition['slug'] ?? pathinfo($definitionFile, PATHINFO_FILENAME));
        $definition['definition_file'] = $definitionFile;
        $definition['pdf'] = (string)($definition['pdf'] ?? '');
        $definition['pdf_path'] = TEMPLATES_DIR . '/' . basename($definition['pdf']);
        $definition['pdf_exists'] = is_file($definition['pdf_path']);
        $definition['fields'] = is_array($definition['fields'] ?? null) ? $definition['fields'] : [];
        $definition['signature'] = is_array($definition['signature'] ?? null) ? $definition['signature'] : [];
        $definition['person_key'] = (string)($definition['person_key'] ?? 'dni');
        $templates[] = $definition;
    }

    usort($templates, static fn(array $a, array $b): int => strcmp((string)$a['slug'], (string)$b['slug']));
    return $templates;
}

function pa_get_template(string $slug): ?array
{
    foreach (pa_template_catalog() as $template) {
        if (($template['slug'] ?? '') === $slug) {
            return $template;
        }
    }

    return null;
}

function pa_registry(): array
{
    return pa_load_json(REGISTRY_FILE, []);
}

function pa_store_registry(array $registry): void
{
    pa_save_json(REGISTRY_FILE, $registry);
}

function pa_append_registry(array $item): void
{
    $registry = pa_registry();
    array_unshift($registry, $item);
    pa_store_registry($registry);
}

function pa_find_registry(string $id): ?array
{
    foreach (pa_registry() as $item) {
        if (($item['id'] ?? '') === $id) {
            return $item;
        }
    }

    return null;
}

function pa_grouped_registry(): array
{
    $grouped = [];
    foreach (pa_registry() as $item) {
        $dni = (string)($item['dni'] ?? 'sin-dni');
        $grouped[$dni][] = $item;
    }

    ksort($grouped);
    return $grouped;
}

function pa_expediente_path(string $dni, string $id): string
{
    return EXPEDIENTES_DIR . '/' . pa_normalize_dni($dni) . '/' . $id . '/';
}

function pa_format_bytes(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $size = $bytes;
    $index = 0;
    while ($size >= 1024 && $index < count($units) - 1) {
        $size /= 1024;
        $index++;
    }

    return rtrim(rtrim(number_format($size, 2, ',', '.'), '0'), ',') . ' ' . $units[$index];
}

function pa_signature_from_data_uri(string $dataUri): string
{
    $prefix = 'data:image/png;base64,';
    if (!str_starts_with($dataUri, $prefix)) {
        throw new RuntimeException('La firma debe venir en formato PNG.');
    }

    $binary = base64_decode(substr($dataUri, strlen($prefix)), true);
    if ($binary === false) {
        throw new RuntimeException('No se pudo leer la firma.');
    }

    $tmp = TMP_DIR . '/firma_' . bin2hex(random_bytes(8)) . '.png';
    file_put_contents($tmp, $binary);
    return $tmp;
}
