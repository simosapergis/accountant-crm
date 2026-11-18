<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

/** Render trusted report templates with escaped data and bundled resources only. */
function crm_pdf_renderer(): \Dompdf\Dompdf
{
    return new \Dompdf\Dompdf([
        'defaultFont' => 'DejaVu Sans',
        'isRemoteEnabled' => false,
        'isPhpEnabled' => false,
        'isJavascriptEnabled' => false,
        'allowedProtocols' => ['file://'],
        'chroot' => dirname(__DIR__) . '/vendor/dompdf/dompdf/lib/fonts',
    ]);
}
