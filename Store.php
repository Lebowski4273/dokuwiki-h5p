<?php

namespace dokuwiki\plugin\h5p;

/**
 * Central storage for H5P libraries and extracted H5P content.
 *
 * Layout below the base directory:
 *   libraries/<MachineName>-<major>.<minor>/   one folder per library version
 *   libraries.updated                           touched whenever libraries change
 *   content/<md5 of media id>/                  h5p.json + content/ of one .h5p file
 */
class Store
{
    /** File extensions allowed in content folders (based on the H5P core whitelist) */
    public const CONTENT_EXT = [
        'json', 'png', 'jpg', 'jpeg', 'gif', 'bmp', 'tif', 'tiff', 'svg', 'webp',
        'eot', 'ttf', 'woff', 'woff2', 'otf',
        'webm', 'mp4', 'ogg', 'oga', 'ogv', 'mp3', 'm4a', 'wav',
        'txt', 'pdf', 'rtf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
        'xml', 'csv', 'md', 'vtt', 'webvtt', 'gltf', 'glb',
    ];

    /** Additional extensions allowed in library folders */
    public const LIBRARY_EXTRA_EXT = ['js', 'css'];

    /** MIME types used when delivering files */
    public const MIME = [
        'json' => 'application/json', 'js' => 'text/javascript', 'css' => 'text/css',
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
        'bmp' => 'image/bmp', 'tif' => 'image/tiff', 'tiff' => 'image/tiff', 'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'eot' => 'application/vnd.ms-fontobject', 'ttf' => 'font/ttf', 'woff' => 'font/woff',
        'woff2' => 'font/woff2', 'otf' => 'font/otf',
        'webm' => 'video/webm', 'mp4' => 'video/mp4', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg',
        'ogv' => 'video/ogg', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav',
        'txt' => 'text/plain', 'pdf' => 'application/pdf', 'rtf' => 'application/rtf',
        'xml' => 'application/xml', 'csv' => 'text/csv', 'md' => 'text/plain',
        'vtt' => 'text/vtt', 'webvtt' => 'text/vtt',
        'gltf' => 'model/gltf+json', 'glb' => 'model/gltf-binary',
    ];

    /** increase when extracted content has to be regenerated */
    public const EXTRACT_VERSION = 'v2';

    protected string $base;
    protected int $maxBytes;
    protected int $maxFiles;
    protected bool $minorFallback = false;

    /**
     * @param string $base     base directory (created on demand)
     * @param int    $maxBytes maximum uncompressed size of one extraction
     * @param int    $maxFiles maximum number of entries in a package
     */
    public function __construct(string $base, int $maxBytes = 209715200, int $maxFiles = 20000)
    {
        $this->base = rtrim($base, '/');
        $this->maxBytes = $maxBytes;
        $this->maxFiles = $maxFiles;
    }

    /**
     * Allow using a higher minor version when the required one is not installed
     */
    public function setMinorFallback(bool $enabled): void
    {
        $this->minorFallback = $enabled;
    }

    public function getBaseDir(): string
    {
        return $this->base;
    }

    public function getLibraryDir(): string
    {
        return $this->base . '/libraries';
    }

    public function getContentBaseDir(): string
    {
        return $this->base . '/content';
    }

    public function getIndexFile(): string
    {
        return $this->base . '/libraries.updated';
    }

    public static function libraryFolder(string $machineName, int $major, int $minor): string
    {
        return $machineName . '-' . $major . '.' . $minor;
    }

    public static function contentExtensions(): array
    {
        return self::CONTENT_EXT;
    }

    public static function libraryExtensions(): array
    {
        return array_merge(self::CONTENT_EXT, self::LIBRARY_EXTRA_EXT);
    }

    public static function mimeType(string $file): string
    {
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        return self::MIME[$ext] ?? 'application/octet-stream';
    }

    // ------------------------------------------------------------------ packages

    /**
     * Read and validate the structure of an .h5p package without extracting it
     *
     * @return array ['h5p' => h5p.json data, 'libraries' => [top folder => normalized library.json]]
     * @throws \RuntimeException
     */
    public function readPackage(string $zipFile): array
    {
        $zip = $this->openZip($zipFile);
        try {
            $h5p = $this->validateH5pJson($this->decodeJson($zip->getFromName('h5p.json'), 'h5p.json'));
            return ['h5p' => $h5p, 'libraries' => $this->findLibrariesInZip($zip)];
        } finally {
            $zip->close();
        }
    }

    /**
     * Find all libraries in any zip archive (.h5p package, Hub download or a zip of library folders)
     *
     * @return array [folder prefix inside the zip ('' = root) => normalized library.json]
     * @throws \RuntimeException
     */
    public function readArchiveLibraries(string $zipFile): array
    {
        $zip = $this->openZip($zipFile);
        try {
            return $this->findLibrariesInZip($zip);
        } finally {
            $zip->close();
        }
    }

    /**
     * Every folder containing a library.json is a library (except inside content/)
     */
    protected function findLibrariesInZip(\ZipArchive $zip): array
    {
        $libraries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $raw = (string)$zip->getNameIndex($i);
            $name = $this->normalizeEntry($raw);
            if ($name === null) continue;
            if ($name !== 'library.json' && substr($name, -13) !== '/library.json') continue;
            $prefix = $name === 'library.json' ? '' : substr($name, 0, -13);
            if ($prefix === 'content' || strpos($prefix, 'content/') === 0) continue;
            $libraries[$prefix] = $this->validateLibraryJson($this->decodeJson($zip->getFromIndex($i), $name));
        }
        return $libraries;
    }

    /**
     * Libraries contained in a package, keyed by their central folder name
     *
     * @param array $package result of readPackage()
     */
    public function packageLibraries(array $package): array
    {
        $result = [];
        foreach ($package['libraries'] as $lib) {
            $folder = self::libraryFolder($lib['machineName'], $lib['majorVersion'], $lib['minorVersion']);
            $result[$folder] = $lib;
        }
        return $result;
    }

    /**
     * Extract h5p.json and content/ of a package into the content store
     *
     * Re-extracts only when the packaage changed (mtime/size).
     *
     * @param string $key     32 hex chars identifying the content (md5 of the media id)
     * @return string the content directory
     * @throws \RuntimeException
     */
    public function prepareContent(string $key, string $zipFile): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $key)) {
            throw new H5PException('badkey');
        }
        if (!is_file($zipFile)) {
            throw new H5PException('notfound');
        }

        $dir = $this->getContentBaseDir() . '/' . $key;
        clearstatcache(true, $zipFile);
        // the format version forces re-extraction when the extraction logic changes
        $stamp = self::EXTRACT_VERSION . ':' . filemtime($zipFile) . ':' . filesize($zipFile);
        if (
            is_file($dir . '/.source') &&
            trim((string)file_get_contents($dir . '/.source')) === $stamp &&
            is_file($dir . '/h5p.json')
        ) {
            return $dir;
        }

        $zip = $this->openZip($zipFile);
        $tmp = null;
        try {
            $this->validateH5pJson($this->decodeJson($zip->getFromName('h5p.json'), 'h5p.json'));
            $tmp = $this->makeTempDir($this->getContentBaseDir());
            $this->extract($zip, $tmp, self::contentExtensions(), static function (string $name): ?string {
                if ($name === 'h5p.json') return $name;
                if (strpos($name, 'content/') === 0) return $name;
                return null;
            });
            if (!is_file($tmp . '/content/content.json')) {
                throw new H5PException('nocontent');
            }
            $this->completeDependencies($tmp);
            file_put_contents($tmp . '/.source', $stamp);
            $this->replaceDir($tmp, $dir);
            $tmp = null;
        } finally {
            $zip->close();
            if ($tmp !== null) $this->removeDir($tmp);
        }

        return $dir;
    }

    /**
     * Install all libraries contained in a package into the central library store
     *
     * Existing libraries are replaced only by a higher patch version.
     *
     * @return array ['installed' => [...], 'updated' => [...], 'skipped' => [...]] (labels)
     * @throws \RuntimeException
     */
    public function installLibraries(string $zipFile): array
    {
        $libraries = $this->readArchiveLibraries($zipFile);
        $report = ['installed' => [], 'updated' => [], 'skipped' => []];
        if (!$libraries) return $report;
        $prefixes = array_keys($libraries);

        $libDir = $this->getLibraryDir();
        $zip = $this->openZip($zipFile);
        try {
            foreach ($libraries as $top => $lib) {
                $folder = self::libraryFolder($lib['machineName'], $lib['majorVersion'], $lib['minorVersion']);
                $label = $lib['machineName'] . ' ' . $lib['majorVersion'] . '.' .
                    $lib['minorVersion'] . '.' . $lib['patchVersion'];

                $existing = $this->getInstalledLibrary($folder);
                if ($existing !== null && $existing['patchVersion'] >= $lib['patchVersion']) {
                    $report['skipped'][] = $label;
                    continue;
                }

                $tmp = $this->makeTempDir($libDir);
                try {
                    $prefix = $top === '' ? '' : $top . '/';
                    // other libraries nested below this one must not end up in it
                    $nested = [];
                    foreach ($prefixes as $other) {
						//if ($other !== $top && $prefix === '' ) {
                        if ($other !== $top && ($prefix === '' || strpos($other . '/', $prefix) === 0)) {
                            $nested[] = $other . '/';
                        }
                    }
                    $this->extract($zip, $tmp, self::libraryExtensions(), static function (string $name) use ($prefix, $nested): ?string {
                        if ($prefix !== '' && strpos($name, $prefix) !== 0) return null;
                        foreach ($nested as $n) {
                            if (strpos($name, $n) === 0) return null;
                        }
                        if ($prefix === '' && (strpos($name, 'content/') === 0 || $name === 'h5p.json')) return null;
                        return substr($name, strlen($prefix));
                    });
                    if (!is_file($tmp . '/library.json')) {
                        throw new H5PException('nolibjson', $folder);
                    }
                    $this->replaceDir($tmp, $libDir . '/' . $folder);
                    $tmp = null;
                } finally {
                    if ($tmp !== null) $this->removeDir($tmp);
                }

                $report[$existing === null ? 'installed' : 'updated'][] = $label;
            }
        } finally {
            $zip->close();
        }

        if ($report['installed'] || $report['updated']) $this->touchIndex();
        return $report;
    }

    /**
     * Remove everything without h5p.json and content/ from a package (in place)
     *
     * @return int number of removed entries
     * @throws \RuntimeException
     */
    public function stripPackage(string $zipFile): int
    {
        $zip = $this->openZip($zipFile, true);
        $removed = 0;
        try {
            $delete = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $raw = str_replace('\\', '/', (string)$zip->getNameIndex($i));
                if (substr($raw, -1) === '/') {
                    // directory entry: keep only content/ and its subfolders
                    if (strpos($raw, 'content/') === 0 && strpos($raw, '..') === false) continue;
                    $delete[] = $i;
                    continue;
                }
                $name = $this->normalizeEntry($raw);
                if ($name !== null && ($name === 'h5p.json' || strpos($name, 'content/') === 0)) continue;
                $delete[] = $i;
            }
            foreach ($delete as $i) {
                if ($zip->deleteIndex($i)) $removed++;
            }
        } finally {
            if (!$zip->close()) {
                throw new H5PException('writepackage');
            }
        }
        clearstatcache(true, $zipFile);
        return $removed;
    }

    /**
     * Remove the extracted content of one H5P file
     */
    public function deleteContent(string $key): bool
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $key)) return false;
        $dir = $this->getContentBaseDir() . '/' . $key;
        if (!is_dir($dir)) return false;
        $this->removeDir($dir);
        return !is_dir($dir);
    }

    /**
     * Remove extracted content whose key is not in the given list,
     * plus leftovers of interrupted extractions older than one hour
     *
     * @param string[] $keepKeys content keys that are still in use
     * @return int number of removed content directories
     */
    public function cleanupContent(array $keepKeys): int
    {
        $base = $this->getContentBaseDir();
        if (!is_dir($base)) return 0;
        $keep = array_flip($keepKeys);
        $removed = 0;
        foreach (scandir($base) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $base . '/' . $entry;
            if (!is_dir($path)) continue;
            if (strpos($entry, '.tmp-') === 0 || strpos($entry, '.old-') === 0) {
                if (filemtime($path) < time() - 3600) $this->removeDir($path);
                continue;
            }
            if (preg_match('/^[a-f0-9]{32}$/', $entry) && !isset($keep[$entry])) {
                $this->removeDir($path);
                $removed++;
            }
        }
        return $removed;
    }

    // ------------------------------------------------------------------ libraries

    /**
     * @return array|null normalized library.json of an installed library
     */
    public function getInstalledLibrary(string $folder): ?array
    {
        if (!$this->isValidLibraryFolder($folder)) return null;
        $file = $this->getLibraryDir() . '/' . $folder . '/library.json';
        if (!is_file($file)) return null;
        try {
            return $this->validateLibraryJson($this->decodeJson(file_get_contents($file), $file));
        } catch (\RuntimeException $e) {
            return null;
        }
    }

    /**
     * @return array [folder => normalized library.json], sorted by folder
     */
    public function getLibraries(): array
    {
        $result = [];
        $dir = $this->getLibraryDir();
        if (!is_dir($dir)) return $result;
        foreach (scandir($dir) as $folder) {
            if ($folder[0] === '.') continue;
            $lib = $this->getInstalledLibrary($folder);
            if ($lib !== null) $result[$folder] = $lib;
        }
        ksort($result, SORT_NATURAL | SORT_FLAG_CASE);
        return $result;
    }

    /**
     * @throws \RuntimeException
     */
    public function deleteLibrary(string $folder): void
    {
        if (!$this->isValidLibraryFolder($folder) || !is_dir($this->getLibraryDir() . '/' . $folder)) {
            throw new H5PException('unknownlib', $folder);
        }
        $this->removeDir($this->getLibraryDir() . '/' . $folder);
        $this->touchIndex();
    }

    /**
     * Folder of the installed library to use for a required version
     *
     * Exact match first. With minor fallback enabled, the highest installed
     * higher minor version of the same major version is used instead.
     */
    public function findLibraryFolder(string $machineName, int $major, int $minor): ?string
    {
        $exact = self::libraryFolder($machineName, $major, $minor);
        if ($this->getInstalledLibrary($exact) !== null) return $exact;
        if (!$this->minorFallback) return null;

        $best = null;
        $bestMinor = $minor;
        $prefix = $machineName . '-' . $major . '.';
        $dir = $this->getLibraryDir();
        if (!is_dir($dir)) return null;
        foreach (scandir($dir) as $folder) {
            if (strpos($folder, $prefix) !== 0) continue;
            $folderMinor = substr($folder, strlen($prefix));
            if (!ctype_digit($folderMinor) || (int)$folderMinor <= $bestMinor) continue;
            if ($this->getInstalledLibrary($folder) === null) continue;
            $best = $folder;
            $bestMinor = (int)$folderMinor;
        }
        return $best;
    }

    /**
     * Resolve a requested library file, applying the minor fallback to the first path segment
     */
    public function resolveLibraryFile(string $relative): ?string
    {
        $relative = str_replace('\\', '/', $relative);
        $parts = explode('/', $relative, 2);
        if (count($parts) === 2 && preg_match('/^([\w.\-]{1,255})-(\d+)\.(\d+)$/', $parts[0], $m)) {
            $folder = $this->findLibraryFolder($m[1], (int)$m[2], (int)$m[3]);
            if ($folder === null) return null;
            $relative = $folder . '/' . $parts[1];
        }
        return $this->resolveFile($this->getLibraryDir(), $relative, self::libraryExtensions());
    }

    /**
     * Folders of all installed libraries that depend on the given one (directly)
     */
    public function getDependents(string $folder): array
    {
        $result = [];
        foreach ($this->getLibraries() as $other => $lib) {
            foreach ($lib['preloadedDependencies'] as $dep) {
                if (self::libraryFolder($dep['machineName'], $dep['majorVersion'], $dep['minorVersion']) === $folder) {
                    $result[] = $other;
                    break;
                }
            }
        }
        return $result;
    }

    /**
     * Find all (recursively) required libraries that are not available
     *
     * @param array $dependencies preloadedDependencies list
     * @param array $extra        additional libraries treated as available [folder => library]
     * @return string[] labels like "H5P.MultiChoice 1.16"
     */
    public function missingDependencies(array $dependencies, array $extra = []): array
    {
        $missing = [];
        $seen = [];
        $queue = $dependencies;
        while ($queue) {
            $dep = array_shift($queue);
            $dep = $this->normalizeDependency($dep);
            if ($dep === null) continue;
            $folder = self::libraryFolder($dep['machineName'], $dep['majorVersion'], $dep['minorVersion']);
            if (isset($seen[$folder])) continue;
            $seen[$folder] = true;

            $lib = $extra[$folder] ?? null;
            if ($lib === null) {
                $found = $this->findLibraryFolder($dep['machineName'], $dep['majorVersion'], $dep['minorVersion']);
                if ($found !== null) $lib = $this->getInstalledLibrary($found);
            }
            if ($lib === null) {
                $missing[] = $dep['machineName'] . ' ' . $dep['majorVersion'] . '.' . $dep['minorVersion'];
                continue;
            }
            foreach ($lib['preloadedDependencies'] as $sub) $queue[] = $sub;
        }
        return $missing;
    }

    /**
     * preloadedDependencies of extracted content
     *
     * @throws \RuntimeException
     */
    /**
     * All dependencies of a package: h5p.json plus sub-content libraries used in content.json
     *
     * @throws \RuntimeException
     */
    public function packageDependencies(string $zipFile): array
    {
        $zip = $this->openZip($zipFile);
        try {
            $h5p = $this->validateH5pJson($this->decodeJson($zip->getFromName('h5p.json'), 'h5p.json'));
            $content = $zip->getFromName('content/content.json');
        } finally {
            $zip->close();
        }
        $sub = [];
        if (is_string($content)) {
            $data = json_decode($content, true);
            if (is_array($data)) $sub = $this->findSubContentLibraries($data);
        }
        return $this->mergeDependencies($h5p['preloadedDependencies'], $sub);
    }

    /**
     * Libraries referenced as sub-content ("library": "H5P.Text 1.1") anywhere in content.json
     */
    public function findSubContentLibraries(array $data): array
    {
        $found = [];
        array_walk_recursive($data, static function ($value, $key) use (&$found) {
            if ($key !== 'library' || !is_string($value)) return;
            if (!preg_match('/^([\w.\-]{1,255}) (\d+)\.(\d+)$/', $value, $m)) return;
            $found[$m[1] . '-' . $m[2] . '.' . $m[3]] = [
                'machineName' => $m[1],
                'majorVersion' => (int)$m[2],
                'minorVersion' => (int)$m[3],
            ];
        });
        return array_values($found);
    }

    /**
     * Append dependencies from $extra that are not yet in $deps
     */
    protected function mergeDependencies(array $deps, array $extra): array
    {
        $have = [];
        foreach ($deps as $dep) {
            $have[self::libraryFolder($dep['machineName'], $dep['majorVersion'], $dep['minorVersion'])] = true;
        }
        foreach ($extra as $dep) {
            $folder = self::libraryFolder($dep['machineName'], $dep['majorVersion'], $dep['minorVersion']);
            if (isset($have[$folder])) continue;
            $deps[] = $dep;
            $have[$folder] = true;
        }
        return $deps;
    }

    /**
     * Add sub-content libraries missing in an extracted h5p.json
     *
     * Some exports list only part of the libraries used by nested content. Full H5P
     * platforms load those from their database, the standalone player only loads
     * what h5p.json lists.
     */
    protected function completeDependencies(string $dir): void
    {
        $raw = (string)file_get_contents($dir . '/h5p.json');
        $h5p = $this->decodeJson($raw, 'h5p.json');
        $content = json_decode((string)file_get_contents($dir . '/content/content.json'), true);
        if (!is_array($content)) return;

        $current = $this->validateH5pJson($h5p)['preloadedDependencies'];
        $merged = $this->mergeDependencies($current, $this->findSubContentLibraries($content));
        if (count($merged) === count($current)) return;

        $h5p['preloadedDependencies'] = array_merge(
            (array)($h5p['preloadedDependencies'] ?? []),
            array_slice($merged, count($current))
        );
        file_put_contents($dir . '/h5p.json', json_encode($h5p, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function contentDependencies(string $contentDir): array
    {
        $h5p = $this->validateH5pJson(
            $this->decodeJson(@file_get_contents($contentDir . '/h5p.json'), 'h5p.json')
        );
        return $h5p['preloadedDependencies'];
    }

    // ------------------------------------------------------------------ delivery

    /**
     * Resolve a requested relative path below a base directory safely
     *
     * @param string   $baseDir    directory the file must be inside of
     * @param string   $relative   requested path (slash separated)
     * @param string[] $extensions allowed file extensions
     * @return string|null absolute path or null if not allowed / not existing
     */
    public function resolveFile(string $baseDir, string $relative, array $extensions): ?string
    {
        $relative = str_replace('\\', '/', $relative);
        if ($relative === '' || strpos($relative, "\0") !== false) return null;
        foreach (explode('/', $relative) as $part) {
            if ($part === '' || $part[0] === '.') return null; // also blocks "." and ".."
        }
        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if (!in_array($ext, $extensions, true)) return null;

        $realBase = realpath($baseDir);
        $real = realpath($baseDir . '/' . $relative);
        if ($realBase === false || $real === false || !is_file($real)) return null;
        if (strpos($real, $realBase . DIRECTORY_SEPARATOR) !== 0) return null;
        return $real;
    }

    // ------------------------------------------------------------------ internals

    protected function openZip(string $file, bool $write = false): \ZipArchive
    {
        if (!is_file($file)) throw new H5PException('notfound');
        $zip = new \ZipArchive();
        $flags = 0;
        if (!$write && defined('ZipArchive::RDONLY')) $flags = \ZipArchive::RDONLY;
        $result = $zip->open($file, $flags);
        if ($result !== true) {
            throw new H5PException('notzip');
        }
        if ($zip->numFiles > $this->maxFiles) {
            $zip->close();
            throw new H5PException('toomany');
        }
        return $zip;
    }

    /**
     * Normalize a zip entry name
     *
     * @return string|null null for directories and hidden/system files that should be ignored
     * @throws \RuntimeException for unsafe names (path traversal, absolute paths)
     */
    protected function normalizeEntry(string $name): ?string
    {
        $name = str_replace('\\', '/', $name);
        if ($name === '' || substr($name, -1) === '/') return null;
        if ($name[0] === '/' || preg_match('/^[a-zA-Z]:/', $name) || strpos($name, "\0") !== false) {
            throw new H5PException('unsafe', $name);
        }
        $parts = explode('/', $name);
        foreach ($parts as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                throw new H5PException('unsafe', $name);
            }
        }
        foreach ($parts as $part) {
            if ($part[0] === '.' || $part === '__MACOSX') return null;
        }
        return $name;
    }

    /**
     * Extract selected entries of a zip into a directory
     *
     * @param callable $map maps a normalized entry name to a relative target path or null (skip)
     */
    protected function extract(\ZipArchive $zip, string $dest, array $extensions, callable $map): void
    {
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $raw = (string)$zip->getNameIndex($i);
            $name = $this->normalizeEntry($raw);
            if ($name === null) continue;
            $target = $map($name);
            if ($target === null || $target === '') continue;

            $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
            if (!in_array($ext, $extensions, true)) continue; // silently skip disallowed files

            $stat = $zip->statIndex($i);
            if ($stat !== false && $total + (int)$stat['size'] > $this->maxBytes) {
                throw new H5PException('toolarge');
            }

            $path = $dest . '/' . $target;
            if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0775, true) && !is_dir(dirname($path))) {
                throw new H5PException('storage', dirname($path));
            }
            $in = $zip->getStream($raw);
            $out = fopen($path, 'wb');
            if ($in === false || $out === false) {
                throw new H5PException('extract', $name);
            }
            try {
                while (!feof($in)) {
                    $buffer = fread($in, 65536);
                    if ($buffer === false) break;
                    $total += strlen($buffer);
                    if ($total > $this->maxBytes) {
                        throw new H5PException('toolarge');
                    }
                    fwrite($out, $buffer);
                }
            } finally {
                fclose($in);
                fclose($out);
            }
        }
    }

    protected function decodeJson($json, string $label): array
    {
        if (!is_string($json) || $json === '') {
            throw new H5PException('jsonmissing', $label);
        }
        // strip UTF-8 BOM
        if (strpos($json, "\xEF\xBB\xBF") === 0) $json = substr($json, 3);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new H5PException('jsoninvalid', $label);
        }
        return $data;
    }

    protected function validateH5pJson(array $h5p): array
    {
        if (empty($h5p['mainLibrary']) || !is_string($h5p['mainLibrary'])) {
            throw new H5PException('nomainlib');
        }
        $deps = [];
        foreach ((array)($h5p['preloadedDependencies'] ?? []) as $dep) {
            $dep = $this->normalizeDependency($dep);
            if ($dep !== null) $deps[] = $dep;
        }
        $h5p['preloadedDependencies'] = $deps;
        return $h5p;
    }

    protected function validateLibraryJson(array $lib): array
    {
        $name = $lib['machineName'] ?? '';
        if (!is_string($name) || !preg_match('/^[\w.\-]{1,255}$/', $name)) {
            throw new H5PException('badlibjson', 'library.json', 'machineName');
        }
        foreach (['majorVersion', 'minorVersion', 'patchVersion'] as $key) {
            if (!isset($lib[$key]) || !is_numeric($lib[$key])) {
                throw new H5PException('badlibjson', $name, $key);
            }
            $lib[$key] = (int)$lib[$key];
        }
        $deps = [];
        foreach ((array)($lib['preloadedDependencies'] ?? []) as $dep) {
            $dep = $this->normalizeDependency($dep);
            if ($dep !== null) $deps[] = $dep;
        }
        $lib['preloadedDependencies'] = $deps;
        $lib['title'] = (string)($lib['title'] ?? $name);
        $lib['runnable'] = !empty($lib['runnable']);
        return $lib;
    }

    protected function normalizeDependency($dep): ?array
    {
        if (!is_array($dep)) return null;
        $name = $dep['machineName'] ?? '';
        if (!is_string($name) || !preg_match('/^[\w.\-]{1,255}$/', $name)) return null;
        if (!isset($dep['majorVersion'], $dep['minorVersion'])) return null;
        if (!is_numeric($dep['majorVersion']) || !is_numeric($dep['minorVersion'])) return null;
        return [
            'machineName' => $name,
            'majorVersion' => (int)$dep['majorVersion'],
            'minorVersion' => (int)$dep['minorVersion'],
        ];
    }

    protected function isValidLibraryFolder(string $folder): bool
    {
        return (bool)preg_match('/^[\w.\-]{1,255}-\d+\.\d+$/', $folder) && strpos($folder, '..') === false;
    }

    protected function makeTempDir(string $parent): string
    {
        if (!is_dir($parent) && !mkdir($parent, 0775, true) && !is_dir($parent)) {
            throw new H5PException('storage', $parent);
        }
        $dir = $parent . '/.tmp-' . bin2hex(random_bytes(8));
        if (!mkdir($dir, 0775)) {
            throw new H5PException('storage', $parent);
        }
        return $dir;
    }

    /**
     * Move $src to $dst, replacing an existing $dst as atomically as possible
     */
    protected function replaceDir(string $src, string $dst): void
    {
        $old = null;
        if (is_dir($dst)) {
            $old = dirname($dst) . '/.old-' . bin2hex(random_bytes(8));
            if (!@rename($dst, $old)) $old = null;
        }
        if (!@rename($src, $dst)) {
            // someone else was faster or rename failed - keep the existing version
            $this->removeDir($src);
            if ($old !== null && !is_dir($dst)) @rename($old, $dst);
            if (!is_dir($dst)) throw new H5PException('storage', $dst);
        }
        if ($old !== null && is_dir($old)) $this->removeDir($old);
    }

    protected function removeDir(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            if (is_link($dir) || is_file($dir)) @unlink($dir);
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path) && !is_link($path)) {
                $this->removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    protected function touchIndex(): void
    {
        if (!is_dir($this->base)) @mkdir($this->base, 0775, true);
        @touch($this->getIndexFile());
        clearstatcache(true, $this->getIndexFile());
    }
}
