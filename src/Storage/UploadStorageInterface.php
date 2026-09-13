<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Storage;

use ArnaudDelgerie\TFSAppBundle\Storage\Exception\PathOutsideStorageException;
use ArnaudDelgerie\TFSAppBundle\Storage\Exception\StorageException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The app's durable files under APP_UPLOAD_DIR. A key is a relative path the
 * caller chooses ("invoices/2026/42.pdf"); every method refuses one that would
 * reach outside the root, lexically or through a symlink, with
 * PathOutsideStorageException. Naming, uniqueness and authorization stay the
 * app's.
 */
interface UploadStorageInterface
{
    /**
     * The configured root, as given — APP_UPLOAD_DIR, or the project's
     * var/uploads when that variable is absent.
     */
    public function root(): string;

    /**
     * @throws PathOutsideStorageException
     */
    public function has(string $key): bool;

    /**
     * The absolute path the key resolves to, whether or not a file exists there.
     *
     * @throws PathOutsideStorageException
     */
    public function path(string $key): string;

    /**
     * Creates intermediate directories; replaces an existing file.
     *
     * @throws PathOutsideStorageException
     * @throws StorageException
     */
    public function write(string $key, string $contents): void;

    /**
     * Moves the uploaded file to the key, creating intermediate directories.
     *
     * @throws PathOutsideStorageException
     * @throws StorageException
     */
    public function store(string $key, UploadedFile $file): void;

    /**
     * A no-op when nothing exists at the key.
     *
     * @throws PathOutsideStorageException
     * @throws StorageException
     */
    public function delete(string $key): void;
}
