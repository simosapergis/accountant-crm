<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** OPS-04: PDF rendering cannot read application files or remote resources. */
final class W08PdfSecurityTest extends TestCase
{
    public function testPdfResourcesAreLimitedToBundledDompdfResources(): void
    {
        $bootstrap = CRM_TEST_APP_ROOT . '/includes/pdf.php';
        self::assertFileExists($bootstrap);
        require_once $bootstrap;
        $options = crm_pdf_renderer()->getOptions();
        self::assertFalse($options->validateLocalUri('file://' . CRM_TEST_APP_ROOT . '/includes/db.php')[0]);
        self::assertFalse($options->validateRemoteUri('https://example.com/image.png')[0]);
        self::assertFalse($options->getIsPhpEnabled());
        self::assertFalse($options->getIsJavascriptEnabled());
        self::assertTrue($options->validateLocalUri('file://' . CRM_TEST_APP_ROOT . '/vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf')[0]);
    }
}
