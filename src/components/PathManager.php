<?php

namespace framework\components;

use framework\Component;

/**
 * Class PathManager
 *
 * Responsible for managing and generating framework paths
 * based on the application's root directory.
 *
 * Example usage:
 *
 * $paths = new PathManager();
 * $paths->app();               // /var/www/myapp/app
 * $paths->config('app.php');   // /var/www/myapp/config/app.php
 */
class PathManager extends Component
{
    /**
     * The root directory of the application.
     *
     * @var string
     */
    protected string $rootPath;

    public function init(): void
    {
        $this->rootPath = config('paths.base_dir');
    }

    /**
     * Resolves a path based on its prefix or relative structure.
     * 
     * Handles the following cases:
     * <ul>
     * <li>path/to -> Normalizes and returns as is</li>
     * <li>/path/to (with a trailing slash) -> Returns the absolute path from application's root</li>
     * <li>@assets/path/to -> Returns the aboslute path calculated from assets folder</li>
     * </ul>
     * 
     * Notes:
     * <ol>
     *  <li>It removes any trailing slashes that the path might have</li>
     *  <li>It replaces a blank "/" with the root path (without trailing slash)</li>
     * </ol>
     * 
     * @param string $path The path to resolve.
     * @return string The fully resolved and normalized path.
     */
    public function resolve(string $path): string
    {
        // 1. Handle "tagged" paths (e.g., @assets/css/style.css)
        if (str_starts_with($path, '@')) {
            // Find the first slash to separate the tag from the rest of the path
            $slashPos = strpos($path, '/');

            if (empty($slashPos)) {
                $dirKey = substr($path, 1);
                return $this->normalize(config("paths.{$dirKey}"));
            }

            // Extract the directory key (e.g., "assets") and the remaining path
            $dirKey = substr($path, 1, $slashPos - 1);
            $remainingPath = substr($path, $slashPos + 1);


            // Fetch the base directory for this tag from config
            $dirBase = config("paths.{$dirKey}") ?? '';

            return $this->normalize($this->join($dirBase, $remainingPath));
        }

        // 2. Handle relative paths starting with a slash (e.g., /assets/css/style.css)
        if (str_starts_with($path, '/')) {
            return $this->normalize($this->join($this->rootPath, $path));
        }

        // 3. Fallback: Return normalized path directly if no rules match
        return $this->normalize($path);
    }

    /**
     * Resolves a path, applying a default tag prefix if none is present.
     * * @param string $path The path to resolve.
     * @param string|null $defaultTag The default tag (e.g., "@views") to apply if no tag exists.
     * @return string The fully resolved and normalized path.
     */
    public function resolveWithDefault(string $path, ?string $defaultTag = null): string
    {
        // Check if the path already starts with a tag '@'
        if (!str_starts_with($path, '@') && $defaultTag !== null) {

            // Ensure the default tag is formatted correctly with the '@' prefix
            $prefix = str_starts_with($defaultTag, '@') ? $defaultTag : "@{$defaultTag}";

            // Trim leading slashes from the path to avoid "@@tag//path" issues 
            // and join with the prefix
            $path = $this->join($prefix, ltrim($path, '/'));
        }

        // Pass the modified (or original) path to the main resolve logic
        return $this->resolve($path);
    }

    public function replaceDots(string $path): string
    {
        // Remove escaped dots
        $path = str_replace('\.', '--*--', $path);

        // Convert dots to spaces
        $path = str_replace('.', '/', $path);

        // Convert escaped dots
        return str_replace('--*--', '.', $path);
    }

    /**
     * Get the root path of the application.
     *
     * @param string|null $path Optional sub-path to append.
     * @return string
     */
    public function root(?string $path = null): string
    {
        return $this->normalize($this->join($this->rootPath, $path));
    }

    /**
     * Get the application directory path.
     *
     * @param string|null $path Optional sub-path to append.
     * @return string
     */
    public function app(?string $path = null): string
    {
        return $this->normalize($this->join($this->rootPath, 'app', $path));
    }

    /**
     * Get the config directory path.
     *
     * @param string|null $path Optional config file to append.
     * @return string
     */
    public function config(?string $path = null): string
    {
        return $this->normalize($this->join($this->rootPath, 'app', 'config', $path));
    }

    /**
     * Get the public directory path.
     *
     * @param string|null $path Optional sub-path to append.
     * @return string
     */
    public function public(?string $path = null): string
    {
        return $this->normalize($this->join($this->rootPath, 'public', $path));
    }

    /**
     * Get the storage directory path.
     *
     * @param string|null $path Optional sub-path to append.
     * @return string
     */
    public function storage(?string $path = null): string
    {
        return $this->normalize($this->join($this->rootPath, 'app', 'storage', $path));
    }

    /**
     * Get the resources directory path.
     *
     * @param string|null $path Optional sub-path to append.
     * @return string
     */
    public function resources(?string $path = null): string
    {
        return $this->resolve("@assets/$path");
    }

    /**
     * Determine whether a given path exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool
    {
        return file_exists($this->resolve($path));
    }

    /**
     * Normalize a filesystem path.
     *
     * - Converts all directory separators to the system separator
     * - Removes duplicate slashes
     * - Resolves "." and ".." segments
     * - Preserves absolute paths and Windows drive prefixes
     *
     * @param string $path
     * @return string
     */
    public function normalize(string $path): string
    {
        if ($path === '') {
            return '';
        }

        // Unify directory separators
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // Detect if path is absolute (Unix or Windows)
        $isAbsolute = false;
        $prefix = '';

        // Windows drive letter support (e.g. C:\)
        if (preg_match('#^[A-Za-z]:#', $path, $matches)) {
            $prefix = $matches[0];
            $path = substr($path, strlen($prefix));
            $isAbsolute = true;
        } elseif (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $isAbsolute = true;
        }

        $segments = explode(DIRECTORY_SEPARATOR, $path);
        $resolved = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                if (!empty($resolved) && end($resolved) !== '..') {
                    array_pop($resolved);
                } else {
                    // Prevent breaking absolute root
                    if (!$isAbsolute) {
                        $resolved[] = '..';
                    }
                }
            } else {
                $resolved[] = $segment;
            }
        }

        $normalized = implode(DIRECTORY_SEPARATOR, $resolved);

        if ($isAbsolute) {
            $normalized = DIRECTORY_SEPARATOR . $normalized;
        }

        if ($prefix) {
            $normalized = $prefix . $normalized;
        }

        return $normalized !== '' ? $normalized : ($isAbsolute ? DIRECTORY_SEPARATOR : '.');
    }

    /**
     * Joins directories using specified directory
     * separator
     * 
     * @param string[] $paths The paths to join
     * @return string The complete path generated by
     * joining given paths. The path will not
     * contain a trailing slash
     */
    protected function join(...$paths)
    {
        $result = '';

        foreach ($paths as $path) {
            if (empty($path))
                continue;
            $result .= rtrim($path, '/') . '/';
        }

        return substr($result, 0, strlen($result) - 1);
    }
}
