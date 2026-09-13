<?php

declare(strict_types=1);

namespace ArnaudDelgerie\TFSAppBundle\Storage\Exception;

/**
 * The key was refused before any byte was read or written: it is not a
 * relative path confined to the storage root, lexically or through a symlink.
 * The key itself is never echoed back, since it is caller-supplied input.
 */
final class PathOutsideStorageException extends StorageException
{
}
