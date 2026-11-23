<?php

use dokuwiki\Extension\Plugin;
use dokuwiki\plugin\h5p\H5PException;
use dokuwiki\plugin\h5p\Hub;
use dokuwiki\plugin\h5p\Store;

/**
 * DokuWiki Plugin h5p (Helper Component)
 *
 *
 */
class helper_plugin_h5p extends Plugin
{
    protected ?Store $store = null;
    protected ?Hub $hub = null;

    public function getStore(): Store
    {
        if ($this->store === null) {
            $maxMb = max(1, (int)$this->getConf('maxsize'));
            $this->store = new Store($this->getStorageDir(), $maxMb * 1024 * 1024);
            $this->store->setMinorFallback((bool)$this->getConf('minor_fallback'));
        }
        return $this->store;
    }

    public function getHub(): Hub
    {
        global $conf;
        if ($this->hub === null) {
            $this->hub = new Hub(
                $this->getStore(),
                getVersion(),
                $conf['tmpdir'] ?? sys_get_temp_dir(),
                max(1, (int)$this->getConf('maxsize')) * 1024 * 1024
            );
        }
        return $this->hub;
    }

    /**
     * Check all .h5p files in the media directory for missing libraries
     *
     * @return array [
     *   'files'   => [media id => ['missing' => [labels], 'error' => string|null]],
     *   'missing' => [label => [media ids]]   aggregated over all files
     * ]
     */
    public function scanMedia(): array
    {
        global $conf;
        $result = ['files' => [], 'missing' => []];
        $base = rtrim($conf['mediadir'], '/');
        if (!is_dir($base)) return $result;

        $store = $this->getStore();
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'h5p') continue;
            $id = pathID(substr($file->getPathname(), strlen($base) + 1), true);
            try {
                $missing = $store->missingDependencies($store->packageDependencies($file->getPathname()));
                $result['files'][$id] = ['missing' => $missing, 'error' => null];
                foreach ($missing as $label) $result['missing'][$label][] = $id;
            } catch (\Exception $e) {
                $result['files'][$id] = ['missing' => [], 'error' => $e->getMessage()];
            }
        }
        ksort($result['files']);
        ksort($result['missing'], SORT_NATURAL | SORT_FLAG_CASE);
        return $result;
    }

    /**
     * Directory for libraries and extracted content (default: <savedir>/h5p)
     */
    public function getStorageDir(): string
    {
        global $conf;
        $dir = trim((string)$this->getConf('storagedir'));
        if ($dir === '') $dir = rtrim($conf['savedir'], '/') . '/h5p';
        if (!preg_match('#^(/|[a-zA-Z]:[\\\\/])#', $dir)) $dir = DOKU_INC . $dir;
        return rtrim($dir, '/');
    }

    /**
     * Error message of an exception in the user's language
     */
    public function errorText(\Throwable $e): string
    {
        if ($e instanceof H5PException) {
            $template = $this->getLang('err_' . $e->getErrorCode());
            if ($template !== '' && $template !== null) {
                $args = $e->getArgs();
                return vsprintf($template, array_pad($args, substr_count($template, '%s'), ''));
            }
        }
        return $e->getMessage();
    }

    public function isH5PFile(string $name): bool
    {
        return strtolower(substr($name, -4)) === '.h5p';
    }

    /**
     * Make sure the content of a media file is extracted, return its directory
     *
     * @throws \RuntimeException
     */
    public function prepare(string $mediaId): string
    {
        $file = mediaFN($mediaId);
        if (!file_exists($file)) {
            throw new H5PException('notfound');
        }
        return $this->getStore()->prepareContent(md5($mediaId), $file);
    }

    /**
     * Remove the extracted content of a (deleted) media file
     */
    public function removeContent(string $mediaId): bool
    {
        return $this->getStore()->deleteContent(md5($mediaId));
    }

    /**
     * Remove extracted content of H5P files that no longer exist in the media manager
     *
     * @return int number of removed content directories
     */
    public function cleanupContent(): int
    {
        $keep = [];
        foreach (array_keys($this->scanMedia()['files']) as $id) {
            $keep[] = md5($id);
        }
        return $this->getStore()->cleanupContent($keep);
    }

    public function encodeId(string $mediaId): string
    {
        return rtrim(strtr(base64_encode($mediaId), '+/', '-_'), '=');
    }

    public function decodeId(string $encoded): ?string
    {
        if (!preg_match('/^[A-Za-z0-9\-_]+$/', $encoded)) return null;
        $id = base64_decode(strtr($encoded, '-_', '+/'), true);
        if ($id === false || $id === '' || $id !== cleanID($id)) return null;
        return $id;
    }

    protected function endpoint(): string
    {
        return DOKU_BASE . 'lib/plugins/h5p/file.php';
    }

    public function contentUrl(string $mediaId): string
    {
        return $this->endpoint() . '/c/' . $this->encodeId($mediaId);
    }

    public function librariesUrl(): string
    {
        return $this->endpoint() . '/l';
    }

    public function playerUrl(): string
    {
        return DOKU_BASE . 'lib/plugins/h5p/vendor/h5p-standalone/';
    }

    public function playerAvailable(): bool
    {
        $dir = DOKU_PLUGIN . 'h5p/vendor/h5p-standalone/';
        return is_file($dir . 'main.bundle.js') && is_file($dir . 'frame.bundle.js') && is_file($dir . 'styles/h5p.css');
    }

    /**
     * May the current user add new libraries to the central store?
     */
    public function canInstallLibraries(): bool
    {
        global $INPUT, $USERINFO;
        if (auth_ismanager()) return true;
        $allowed = trim((string)$this->getConf('libmanagers'));
        $user = $INPUT->server->str('REMOTE_USER');
        if ($allowed === '' || $user === '') return false;
        return auth_isMember($allowed, $user, (array)($USERINFO['grps'] ?? []));
    }
}
