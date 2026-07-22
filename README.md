# Informe de Daños por Borrasca – ACGranada
Aplicación PHP para generar informes PDF de daños agrícolas por borrasca,
con verificación de DNI autorizado y cálculo automático de pérdidas.

---

## Estructura de archivos

```
borrascas_web/
├── .htaccess               ← Cabeceras de seguridad
├── composer.json           ← Dependencias PHP (Dompdf + PhpSpreadsheet)
├── config.php              ← Valores de campaña y constantes
├── index.php               ← Formulario principal
├── verificar_dni.php       ← AJAX: comprueba si el DNI está autorizado
├── procesar.php            ← Procesa el formulario y genera el PDF
├── assets/
│   ├── css/style.css
│   ├── js/formulario.js
│   └── img/logo.png        ← Pon aquí el logo de ACGranada
├── data/
│   ├── .htaccess           ← Prohíbe acceso web directo a esta carpeta
│   └── autorizados.xlsx    ← TU EXCEL con los DNIs autorizados (ver más abajo)
└── uploads/
    └── .htaccess           ← Prohíbe acceso web directo a esta carpeta
```

---

## Instalación en el hosting (Piensa Solutions)

### 1. Subir los archivos
Sube **todo el contenido** de esta carpeta a:
```
public_html/borrascas/
```
(o la ruta equivalente en tu hosting para que quede en `acgranada.com/borrascas`)

### 2. Instalar dependencias con Composer

Conéctate por **SSH** al hosting (Piensa Solutions lo permite) y ejecuta:
```bash
cd ~/public_html/borrascas
composer install --no-dev --optimize-autoloader
```

Si **no tienes SSH**, descarga las dependencias desde tu PC:
1. Instala Composer en Windows: https://getcomposer.org/Composer-Setup.exe
2. En PowerShell, dentro de esta carpeta:
   ```
   composer install --no-dev --optimize-autoloader
   ```
3. Sube la carpeta `vendor/` que se genera al hosting.

### 3. Subir el Excel de DNIs autorizados
- Pon tu archivo Excel en `data/autorizados.xlsx`
- La fila 1 se toma como **cabecera** (se salta automáticamente)
- El script busca el DNI en **cualquier columna**, así que el orden no importa
- El nombre se extrae de la primera celda de texto que no sea el propio DNI

**Formato recomendado:**

| Nº | Nombre         | DNI       | ... |
|----|----------------|-----------|-----|
| 1  | Juan García    | 12345678A |     |
| 2  | María Pérez    | 87654321B |     |

### 4. (Opcional) Añadir el logo
Pon el logo de ACGranada en `assets/img/logo.png` (cualquier tamaño, se escala a 56 px de alto).

---

## Configuración de la campaña (`config.php`)

Edita `config.php` para actualizar los valores de cada temporada:

```php
define('PREVISION_GRANADA_TM',   124000);  // Tm
define('CIERRE_GRANADA_TM',       98000);  // Tm
define('BAJADA_PORCENTAJE',           21); // %
define('RENDIMIENTO_MEDIO',         0.21); // 21 %
define('PRECIO_KG_AOVE',            4.40); // €/kg aceite
define('PRECIO_CALIDAD_ACEITE',     1.50); // €/kg aceite
define('SOBRECOSTE_RECOLECCION',    0.25); // €/kg aceituna
define('SOBRECOSTE_PRODUCCION',     0.04); // €/kg aceite
define('TITULO_CAMPANA', 'Campaña Oleícola 2024/2025');
```

También puedes añadir/quitar cooperativas en el array `COOPERATIVAS`.

---

## Cómo funciona el formulario

1. El usuario introduce su **DNI** → se verifica en tiempo real contra el Excel.
2. Si está autorizado, rellena el resto de datos personales y elige **Modelo 1**.
3. Introduce los Kgs de producción; la tabla de cálculos se actualiza al instante.
4. Adjunta imágenes como prueba (JPG/PNG/WebP, máx. 8 MB cada una, hasta 10).
5. Pulsa **Generar informe PDF** → el PDF se descarga directamente.

### Fórmulas del Modelo 1

| Concepto | Fórmula |
|----------|---------|
| Kgs. aceite (de cualquier campo) | Kgs. aceituna × Rendimiento medio (21 %) |
| Valor en € (producción) | Kgs. aceite × Precio AOVE (4,40 €) |
| Pérdidas en producción | Previsión inicial (€) − Producción real (€) |
| Calidad de aceite | Kgs. aceite recolección × 1,50 €/Kg |
| Sobrecoste recolección | Kgs. aceituna recolección × 0,25 €/Kg |
| Sobrecoste producción | Kgs. aceite recolección × 0,04 €/Kg |
| **TOTAL** | Pérdidas + Calidad + Sobrecoste rec. + Sobrecoste prod. + Varios |

---

## Requisitos del servidor
- PHP 8.0 o superior
- Extensiones: `mbstring`, `gd` o `imagick`, `zip` (para PhpSpreadsheet)
- Permisos de escritura en la carpeta `uploads/` (chmod 755)

---

## Añadir más modelos en el futuro

1. Añade la opción en el `<select id="tipo_informe">` de `index.php` (quita el `disabled`).
2. Crea la sección HTML correspondiente en `index.php` (igual que `#modelo1`).
3. Añade la lógica de cálculo en `procesar.php` dentro del bloque `if ($tipoInforme === 2)`.
4. Añade la preview de cálculos en `formulario.js`.
