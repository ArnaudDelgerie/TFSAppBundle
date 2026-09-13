<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Tests\Storage;

use ArnaudDelgerie\TFSAppBundle\Storage\Exception\PathOutsideStorageException;
use ArnaudDelgerie\TFSAppBundle\Storage\Exception\StorageException;
use ArnaudDelgerie\TFSAppBundle\Storage\UploadStorage;
use ArnaudDelgerie\TFSAppBundle\Storage\UploadStorageInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadStorageTest extends TestCase
{
    private string $baseDir;

    private string $root;

    private ?UploadStorageTestKernel $kernel = null;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/tfs-app-bundle-storage-tests/' . uniqid('t-', true);
        $this->root = $this->baseDir . '/uploads';
        mkdir($this->baseDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        restore_exception_handler();

        putenv('APP_UPLOAD_DIR');
        unset($_SERVER['APP_UPLOAD_DIR'], $_ENV['APP_UPLOAD_DIR']);

        self::removeDir($this->baseDir);
    }

    public function testNestedKeyRoundTrips(): void
    {
        $storage = new UploadStorage($this->root);

        $storage->write('invoices/2026/42.pdf', 'pdf bytes');

        self::assertTrue($storage->has('invoices/2026/42.pdf'));
        self::assertSame($this->root . '/invoices/2026/42.pdf', $storage->path('invoices/2026/42.pdf'));
        self::assertSame('pdf bytes', file_get_contents($this->root . '/invoices/2026/42.pdf'));

        $storage->delete('invoices/2026/42.pdf');

        self::assertFalse($storage->has('invoices/2026/42.pdf'));
    }

    public function testRootIsCreatedOnFirstUse(): void
    {
        $storage = new UploadStorage($this->root);

        self::assertDirectoryDoesNotExist($this->root);

        $storage->write('first.txt', 'x');

        self::assertFileExists($this->root . '/first.txt');
    }

    public function testKeyForAFileNotYetCreatedStillResolves(): void
    {
        $storage = new UploadStorage($this->root);

        self::assertSame($this->root . '/a/b/new.txt', $storage->path('a/b/new.txt'));
        self::assertFalse($storage->has('a/b/new.txt'));
    }

    public function testDeletingAnAbsentKeyIsANoOp(): void
    {
        $storage = new UploadStorage($this->root);

        $storage->delete('never/written.txt');

        self::assertFalse($storage->has('never/written.txt'));
    }

    public function testDeletingADirectoryIsRefused(): void
    {
        $storage = new UploadStorage($this->root);
        $storage->write('dir/file.txt', 'x');

        $this->expectException(StorageException::class);

        $storage->delete('dir');
    }

    public function testStoreMovesTheUploadedFileUnderTheRoot(): void
    {
        $storage = new UploadStorage($this->root);
        $source = $this->baseDir . '/php-upload-tmp';
        file_put_contents($source, 'avatar bytes');

        $storage->store('avatars/7.png', new UploadedFile($source, 'me.png', null, null, true));

        self::assertFileDoesNotExist($source);
        self::assertSame('avatar bytes', file_get_contents($this->root . '/avatars/7.png'));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function refusedKeys(): iterable
    {
        yield 'parent first' => ['../escape.txt'];
        yield 'parent middle' => ['a/../../escape.txt'];
        yield 'parent last' => ['a/..'];
        yield 'parent only' => ['..'];
        yield 'deep traversal' => ['../../etc/passwd'];
        yield 'backslash traversal' => ['a\\..\\..\\escape.txt'];
        yield 'absolute' => ['/etc/passwd'];
        yield 'absolute backslash' => ['\\etc\\passwd'];
        yield 'drive letter' => ['C:/Windows/win.ini'];
        yield 'empty' => [''];
        yield 'empty segment' => ['a//b.txt'];
        yield 'trailing slash' => ['a/'];
        yield 'dot segment' => ['./a.txt'];
        yield 'null byte' => ["a.txt\0.png"];
    }

    #[DataProvider('refusedKeys')]
    public function testRefusedKeyThrowsOnEveryMethod(string $key): void
    {
        $storage = new UploadStorage($this->root);
        $source = $this->baseDir . '/php-upload-tmp';
        file_put_contents($source, 'x');

        $calls = [
            'has' => static fn () => $storage->has($key),
            'path' => static fn () => $storage->path($key),
            'write' => static fn () => $storage->write($key, 'x'),
            'store' => static fn () => $storage->store($key, new UploadedFile($source, 'x.txt', null, null, true)),
            'delete' => static fn () => $storage->delete($key),
        ];

        foreach ($calls as $method => $call) {
            try {
                $call();
                self::fail(sprintf('%s() accepted a key it must refuse.', $method));
            } catch (PathOutsideStorageException) {
                $this->addToAssertionCount(1);
            }
        }

        self::assertFileExists($source);
        self::assertFileDoesNotExist($this->baseDir . '/escape.txt');
    }

    public function testKeyWhoseParentIsASymlinkOutsideTheRootThrows(): void
    {
        $outside = $this->baseDir . '/outside';
        mkdir($outside);
        mkdir($this->root);
        symlink($outside, $this->root . '/linked');

        $storage = new UploadStorage($this->root);

        try {
            $storage->write('linked/planted.txt', 'x');
            self::fail('write() followed a symlink out of the root.');
        } catch (PathOutsideStorageException) {
        }

        self::assertFileDoesNotExist($outside . '/planted.txt');

        $this->expectException(PathOutsideStorageException::class);

        $storage->path('linked/deeper/not-yet.txt');
    }

    public function testKeyNamingASymlinkToAFileOutsideTheRootThrows(): void
    {
        file_put_contents($this->baseDir . '/secret.txt', 'secret');
        mkdir($this->root);
        symlink($this->baseDir . '/secret.txt', $this->root . '/innocent.txt');

        $storage = new UploadStorage($this->root);

        $this->expectException(PathOutsideStorageException::class);

        $storage->has('innocent.txt');
    }

    public function testDanglingSymlinkThrows(): void
    {
        mkdir($this->root);
        symlink($this->baseDir . '/does-not-exist', $this->root . '/dangling');

        $storage = new UploadStorage($this->root);

        $this->expectException(PathOutsideStorageException::class);

        $storage->write('dangling', 'x');
    }

    public function testSymlinkStayingInsideTheRootIsAllowed(): void
    {
        $storage = new UploadStorage($this->root);
        $storage->write('real/file.txt', 'x');
        symlink($this->root . '/real', $this->root . '/alias');

        self::assertTrue($storage->has('alias/file.txt'));
    }

    public function testRootThatIsItselfASymlinkIsAllowed(): void
    {
        mkdir($this->baseDir . '/real-uploads');
        symlink($this->baseDir . '/real-uploads', $this->root);

        $storage = new UploadStorage($this->root);
        $storage->write('file.txt', 'x');

        self::assertFileExists($this->baseDir . '/real-uploads/file.txt');
    }

    public function testKernelAutowiresTheStorageOnAppUploadDir(): void
    {
        putenv('APP_UPLOAD_DIR=' . $this->root);

        $storage = $this->bootStorage();

        self::assertSame($this->root, $storage->root());

        $source = $this->baseDir . '/php-upload-tmp';
        file_put_contents($source, 'bytes');
        $storage->store('docs/a.txt', new UploadedFile($source, 'a.txt', null, null, true));

        self::assertSame('bytes', file_get_contents($this->root . '/docs/a.txt'));
        self::assertFileDoesNotExist($this->baseDir . '/project/var/uploads/docs/a.txt');
    }

    public function testRootFallsBackToProjectVarUploadsWhenTheVariableIsUnset(): void
    {
        self::assertSame($this->baseDir . '/project/var/uploads', $this->bootStorage()->root());
    }

    public function testRootFallsBackWhenTheVariableIsEmpty(): void
    {
        putenv('APP_UPLOAD_DIR=');

        self::assertSame($this->baseDir . '/project/var/uploads', $this->bootStorage()->root());
    }

    private function bootStorage(): UploadStorageInterface
    {
        $projectDir = $this->baseDir . '/project';

        if (!is_dir($projectDir)) {
            mkdir($projectDir, 0777, true);
        }

        $this->kernel = new UploadStorageTestKernel($projectDir);
        $this->kernel->boot();

        return $this->kernel->getContainer()->get('test.upload_storage');
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }

        foreach (new \FilesystemIterator($dir) as $item) {
            if ($item->isDir() && !$item->isLink()) {
                self::removeDir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
