<?php
/**
 * includes/header.php
 * Cabecera unificada compartida con el diseño exacto del componente React.
 */
$pageTitle     = $pageTitle     ?? 'Informe de Daños – ACGranada';
$modelLabel    = $modelLabel    ?? '';
$backUrl       = $backUrl       ?? null;
$assetBase     = $assetBase     ?? '';
$numExpediente = $numExpediente ?? null;

// Datos de sesión/cuenta
$userEmail   = !empty($_SESSION['admin_ok']) ? 'admin@faeca.es' : ($_SESSION['user_email'] ?? 'usuario@faeca.es');
$userRole    = !empty($_SESSION['admin_ok']) ? 'admin' : ($_SESSION['user_role'] ?? 'user');
$accountName = $_SESSION['account_name'] ?? 'Cuenta Principal';
$userInitial = strtoupper(substr($userEmail, 0, 1));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <?php include __DIR__ . '/css.php'; ?>
    <style>
      .header-main {
        position: relative;
        margin-bottom: 2.25rem !important;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-shrink: 0;
        padding: 0 1.5rem;
        height: 80px; /* h-20 */
        border-bottom: 1px solid rgba(255, 255, 255, 0.7);
        background: linear-gradient(90deg, #203b2b 0%, #2f5335 48%, #6f8f4c 100%);
        color: #ffffff;
        box-shadow: 0 14px 35px rgba(22, 42, 28, 0.15);
        overflow: hidden;
      }
      @media (min-width: 640px) {
        .header-main { padding: 0 2rem; }
      }
      .header-bg-effects {
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top left, rgba(255, 255, 255, 0.18), transparent 28%),
                    radial-gradient(circle at bottom right, rgba(255, 255, 255, 0.08), transparent 30%);
        pointer-events: none;
      }
      .header-left {
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        min-width: 0;
      }
      .logo-container {
        display: flex;
        align-items: center;
        gap: 1rem;
      }
      .img-rosco {
        height: 3.5rem; /* h-14 */
        width: 3.5rem;  /* w-14 */
        object-fit: contain;
      }
      .img-faeca-agro {
        height: 2.5rem; /* h-10 */
        width: auto;
        object-fit: contain;
      }
      @media (min-width: 640px) {
        .img-faeca-agro {
          height: 3rem; /* sm:h-12 */
        }
      }
      .account-card {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        border-radius: 9999px;
        background: rgba(255, 255, 255, 0.1);
        padding: 0.375rem 0.75rem;
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border: 1px solid rgba(255, 255, 255, 0.15);
      }
      .badge-admin {
        font-size: 10px;
        background-color: #d8a84a;
        color: #1d241c;
        padding: 0.125rem 0.375rem;
        border-radius: 9999px;
        font-weight: 800;
        margin-left: 0.25rem;
      }
      .btn-icon {
        color: rgba(255, 255, 255, 0.85);
        padding: 0.5rem;
        border-radius: 0.75rem;
        transition: background-color 0.2s, color 0.2s;
        border: 1px solid rgba(255, 255, 255, 0.1);
        background: transparent;
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .btn-icon:hover {
        color: #ffffff;
        background-color: rgba(255, 255, 255, 0.1);
      }
    </style>
</head>
<body class="bg-[#f4f1e8] text-[#1f2b21] font-sans">

<header class="header-main">
    <div class="header-bg-effects"></div>

    <!-- LOGOS -->
    <div class="header-left">
        <a href="<?= htmlspecialchars($assetBase ?: './') ?>landing.php" class="logo-container text-decoration-none">
            <img src="<?= htmlspecialchars($assetBase) ?>assets/img/RoscoTransparente.gif" alt="Rosco" class="img-rosco" />
            <img src="<?= htmlspecialchars($assetBase) ?>assets/img/FaecaAGRO360Transparente.png" alt="Faeca Agro 360" class="img-faeca-agro" />
        </a>
    </div>

    <!-- USUARIO / CUENTA DERECHA -->
    <div class="position-relative d-flex align-items-center gap-2">
        <?php if ($numExpediente): ?>
            <span class="badge bg-white text-dark px-3 py-2 me-1 font-monospace" style="font-size:0.85rem; border-radius:8px;">
                <i class="bi bi-hash text-success"></i> Exp. <?= htmlspecialchars($numExpediente) ?>
            </span>
        <?php endif; ?>

        <div class="account-card d-none d-md-flex">
            <span class="text-xs font-medium text-white max-w-[180px] truncate" style="font-size:0.75rem;">
                <?= htmlspecialchars($userEmail) ?>
            </span>
            <?php if ($userRole === 'admin'): ?>
                <span class="badge-admin">ADMIN</span>
            <?php endif; ?>
        </div>

        <a href="<?= htmlspecialchars($assetBase) ?>admin.php" title="Cerrar sesión / Administración" class="btn-icon text-decoration-none">
            <svg class="h-4 w-4" style="width:1rem;height:1rem;" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
        </a>
    </div>
</header>
