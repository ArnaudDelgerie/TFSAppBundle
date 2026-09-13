<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Storage;

use ArnaudDelgerie\TFSAppBundle\Storage\Exception\PathOutsideStorageException;
use ArnaudDelgerie\TFSAppBundle\Storage\Exception\StorageException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The app's durable files under APP_UPLOAD_DIR. A key is a relative path the
 * caller chooses ("invoices/2026/42.pdf"); every method refuses one that would
 * reach outside the root, lexically or through a symlink, with
 * PathOutsideStorageException. Naming, uniqueness and authorization stay the
 * app's.
 *
 * download() is the default way to hand a file back. An uploaded file served
 * inline without the sandboxing CSP inline() adds is same-origin script on the
 * app's own origin: the hub's §4 policy is 'self', which permits exactly what
 * the app itself serves.
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

    /**
     * Content-Disposition: attachment, X-Content-Type-Options: nosniff. The
     * filename defaults to the key's basename.
     *
     * @throws PathOutsideStorageException
     * @throws StorageException when nothing is stored at the key
     * @throws \InvalidArgumentException when the filename contains "/" or "\"
     */
    public function download(string $key, ?string $filename = null): BinaryFileResponse;

    /**
     * Content-Disposition: inline, nosniff, and a sandboxing CSP — see the class
     * docblock for why that CSP is not optional.
     *
     * @throws PathOutsideStorageException
     * @throws StorageException when nothing is stored at the key
     * @throws \InvalidArgumentException when the filename contains "/" or "\"
     */
    public function inline(string $key, ?string $filename = null): BinaryFileResponse;
}
