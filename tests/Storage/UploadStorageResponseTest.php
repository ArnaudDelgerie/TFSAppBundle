<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Storage;

use ArnaudDelgerie\TFSAppBundle\Storage\Exception\PathOutsideStorageException;
use ArnaudDelgerie\TFSAppBundle\Storage\Exception\StorageException;
use ArnaudDelgerie\TFSAppBundle\Storage\UploadStorage;
use PHPUnit\Framework\TestCase;

final class UploadStorageResponseTest extends TestCase
{
    private string $baseDir;

    private UploadStorage $storage;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/tfs-app-bundle-storage-response-tests/' . uniqid('t-', true);
        mkdir($this->baseDir, 0777, true);

        $this->storage = new UploadStorage($this->baseDir . '/uploads');
        $this->storage->write('invoices/42.pdf', 'pdf bytes');
    }

    protected function tearDown(): void
    {
        @unlink($this->baseDir . '/uploads/invoices/42.pdf');
        @rmdir($this->baseDir . '/uploads/invoices');
        @rmdir($this->baseDir . '/uploads');
        @rmdir($this->baseDir);
    }

    public function testDownloadHeadersVerbatim(): void
    {
        $response = $this->storage->download('invoices/42.pdf');

        self::assertSame($this->baseDir . '/uploads/invoices/42.pdf', $response->getFile()->getPathname());
        self::assertSame('attachment; filename=42.pdf', $response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function testInlineHeadersVerbatim(): void
    {
        $response = $this->storage->inline('invoices/42.pdf', 'invoice.pdf');

        self::assertSame('inline; filename=invoice.pdf', $response->headers->get('Content-Disposition'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame("default-src 'none'; sandbox", $response->headers->get('Content-Security-Policy'));
    }

    public function testFilenameWithAQuoteIsQuotedAndEscaped(): void
    {
        $response = $this->storage->download('invoices/42.pdf', 'my "best" invoice.pdf');

        self::assertSame(
            'attachment; filename="my \\"best\\" invoice.pdf"',
            $response->headers->get('Content-Disposition'),
        );
    }

    public function testNonAsciiFilenameGetsAnAsciiFallbackAndAnEncodedFilenameStar(): void
    {
        $response = $this->storage->download('invoices/42.pdf', 'facture-é.pdf');

        self::assertSame(
            "attachment; filename=facture-_.pdf; filename*=utf-8''facture-%C3%A9.pdf",
            $response->headers->get('Content-Disposition'),
        );
    }

    public function testMissingKeyThrowsInsteadOfResponding(): void
    {
        $this->expectException(StorageException::class);

        $this->storage->download('invoices/43.pdf');
    }

    public function testConfinementIsTheSameOnBothResponses(): void
    {
        foreach (['download', 'inline'] as $method) {
            try {
                $this->storage->{$method}('../uploads/invoices/42.pdf');
                self::fail(sprintf('%s() accepted a key it must refuse.', $method));
            } catch (PathOutsideStorageException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
