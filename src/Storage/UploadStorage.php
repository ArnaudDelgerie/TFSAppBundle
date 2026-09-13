<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Storage;

use ArnaudDelgerie\TFSAppBundle\Storage\Exception\PathOutsideStorageException;
use ArnaudDelgerie\TFSAppBundle\Storage\Exception\StorageException;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class UploadStorage implements UploadStorageInterface
{
    private readonly string $root;

    public function __construct(string $root)
    {
        $this->root = '/' === $root ? $root : rtrim($root, '/');
    }

    public function root(): string
    {
        return $this->root;
    }

    public function has(string $key): bool
    {
        return is_file($this->resolve($key));
    }

    public function path(string $key): string
    {
        return $this->resolve($key);
    }

    public function write(string $key, string $contents): void
    {
        $path = $this->resolve($key);
        $this->ensureDirectory(\dirname($path));

        if (false === @file_put_contents($path, $contents, \LOCK_EX)) {
            throw new StorageException(sprintf('Could not write %s.', $path));
        }
    }

    public function store(string $key, UploadedFile $file): void
    {
        $path = $this->resolve($key);
        $this->ensureDirectory(\dirname($path));

        try {
            $file->move(\dirname($path), basename($path));
        } catch (FileException $e) {
            throw new StorageException(sprintf('Could not move the uploaded file to %s.', $path), 0, $e);
        }
    }

    public function delete(string $key): void
    {
        $path = $this->resolve($key);

        if (!file_exists($path) && !is_link($path)) {
            return;
        }

        if (is_dir($path) && !is_link($path)) {
            throw new StorageException(sprintf('%s is a directory, not a stored file.', $path));
        }

        if (!@unlink($path)) {
            throw new StorageException(sprintf('Could not delete %s.', $path));
        }
    }

    /**
     * Lexical checks first, then the rule that actually holds: the deepest
     * ancestor of the target that exists on disk must realpath to somewhere
     * inside the root's own realpath. That sees a symlink planted inside the
     * root, which no string check can, and works for a file not yet created.
     */
    private function resolve(string $key): string
    {
        if ('' === $key || str_contains($key, "\0")) {
            throw new PathOutsideStorageException('A storage key must be a non-empty string without null bytes.');
        }

        if (str_starts_with($key, '/') || str_starts_with($key, '\\') || 1 === preg_match('/^[A-Za-z]:/', $key)) {
            throw new PathOutsideStorageException('A storage key must be relative to the storage root.');
        }

        $segments = preg_split('#[/\\\\]#', $key);

        foreach ($segments as $segment) {
            if ('' === $segment || '.' === $segment || '..' === $segment) {
                throw new PathOutsideStorageException('A storage key must not contain empty, "." or ".." segments.');
            }
        }

        $realRoot = $this->realRoot();
        $target = $realRoot . '/' . implode('/', $segments);

        // realpath() answers from a per-process cache; a symlink swapped since
        // the last lookup must not be judged on its old destination.
        clearstatcache(true);

        $ancestor = $target;

        while (!file_exists($ancestor) && !is_link($ancestor)) {
            $ancestor = \dirname($ancestor);
        }

        $realAncestor = realpath($ancestor);

        if (false === $realAncestor || ($realAncestor !== $realRoot && !str_starts_with($realAncestor, $realRoot . '/'))) {
            throw new PathOutsideStorageException('A storage key must not resolve outside the storage root.');
        }

        return $target;
    }

    private function realRoot(): string
    {
        $this->ensureDirectory($this->root);

        $realRoot = realpath($this->root);

        if (false === $realRoot) {
            throw new StorageException(sprintf('Could not resolve the storage root %s.', $this->root));
        }

        return '/' === $realRoot ? '' : $realRoot;
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new StorageException(sprintf('Could not create the directory %s.', $directory));
        }
    }
}
