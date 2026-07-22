<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function pa_render_pdf_field(\setasign\Fpdi\Fpdi $pdf, array $field, string $value): void
{
    $page = (int)($field['page'] ?? 1);
    $x = (float)($field['x'] ?? 0);
    $y = (float)($field['y'] ?? 0);
    $w = (float)($field['w'] ?? 60);
    $h = (float)($field['h'] ?? 6);
    $fontSize = (int)($field['font_size'] ?? 10);
    $align = strtoupper((string)($field['align'] ?? 'L'));
    $style = (string)($field['style'] ?? '');
    $value = trim($value);

    if ($value === '') {
        return;
    }

    if (!empty($field['upper'])) {
        $value = mb_strtoupper($value, 'UTF-8');
    }

    $pdf->SetFont(DEFAULT_TEXT_FONT, $style, $fontSize);
    $pdf->SetTextColor(20, 20, 20);
    $pdf->SetXY($x, $y);

    if (($field['type'] ?? 'text') === 'textarea' || !empty($field['multiline'])) {
        $pdf->MultiCell($w, $h, $value, 0, $align, false);
        return;
    }

    $pdf->Cell($w, $h, $value, 0, 0, $align, false);
}

function pa_generate_document_pdf(array $template, array $values, string $outputPdf, ?string $signaturePath = null): void
{
    $templatePdf = (string)($template['pdf_path'] ?? '');
    if ($templatePdf === '' || !is_file($templatePdf)) {
        throw new RuntimeException('No se encontró el PDF plantilla del trámite.');
    }

    $pdf = new \setasign\Fpdi\Fpdi();
    $pageCount = $pdf->setSourceFile($templatePdf);
    $signature = is_array($template['signature'] ?? null) ? $template['signature'] : [];

    for ($page = 1; $page <= $pageCount; $page++) {
        $templateId = $pdf->importPage($page);
        $size = $pdf->getTemplateSize($templateId);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($templateId, 0, 0, $size['width'], $size['height']);

        foreach (($template['fields'] ?? []) as $field) {
            if ((int)($field['page'] ?? 1) !== $page) {
                continue;
            }

            $name = (string)($field['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $rawValue = (string)($values[$name] ?? '');
            pa_render_pdf_field($pdf, $field, $rawValue);
        }

        if ($signaturePath !== null && $signature !== [] && (int)($signature['page'] ?? 1) === $page) {
            $sigX = (float)($signature['x'] ?? 0);
            $sigY = (float)($signature['y'] ?? 0);
            $sigW = (float)($signature['w'] ?? 60);
            $sigH = (float)($signature['h'] ?? 0);
            $pdf->Image($signaturePath, $sigX, $sigY, $sigW, $sigH, 'PNG');
        }
    }

    $pdf->Output($outputPdf, 'F');
}
