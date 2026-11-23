<?php

namespace dokuwiki\plugin\h5p;

use dokuwiki\HTTP\DokuHTTPClient;

/**
 * Minimal client for the official H5P Content Type Hub (api.h5p.org)
 *
 * The Hub only ever delivers the latest version of a content type together
 * with all libraries it depends on. Requests are only made on explicit admin
 * action; the site UUID and the content type list are cached in hub.json.
 */
class Hub
{
    public const SITES_ENDPOINT = 'https://api.h5p.org/v1/sites';
    public const CONTENT_TYPES_ENDPOINT = 'https://api.h5p.org/v1/content-types/';
    public const CORE_API_VERSION = '1.27';

    protected Store $store;
    protected string $platformVersion;
    protected string $tmpDir;
    protected int $maxBytes;

    public function __construct(Store $store, string $platformVersion, string $tmpDir, int $maxBytes)
    {
        $this->store = $store;
        $this->platformVersion = $platformVersion;
        $this->tmpDir = $tmpDir;
        $this->maxBytes = $maxBytes;
    }

    /**
     * Cached list of content types [machineName => info], empty if never fetched
     */
    public function getCachedContentTypes(): array
    {
      return $this->readState()['contentTypes'] ?? [];
    }

    public function getCacheTime(): int
    {
        return (int)($this->readState()['fetched'] ?? 0);
    }

    /**
     * Fetch the list of available content types from the Hub and cache it
     *
     * @throws \RuntimeException
     */
    public function refreshContentTypes(): array
    {
        $body = $this->post(self::CONTENT_TYPES_ENDPOINT, $this->registrationData($this->getUuid()));
        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['contentTypes']) || !is_array($data['contentTypes'])) {
            throw new H5PException('hub_answer');
        }

        $types = [];
        foreach ($data['contentTypes'] as $ct) {
            $name = $ct['machineName'] ?? ($ct['id'] ?? '');
            if (!is_string($name) || !preg_match('/^[\w.\-]{1,255}$/', $name)) continue;
            $types[$name] = [
                'title' => (string)($ct['title'] ?? $name),
                'version' => sprintf(
                    '%d.%d.%d',
                    $ct['version']['major'] ?? 0,
                    $ct['version']['minor'] ?? 0,
                    $ct['version']['patch'] ?? 0
                ),
                'majorMinor' => sprintf('%d.%d', $ct['version']['major'] ?? 0, $ct['version']['minor'] ?? 0),
                'coreApi' => sprintf(
                    '%d.%d',
                    $ct['coreApiVersionNeeded']['major'] ?? 1,
                    $ct['coreApiVersionNeeded']['minor'] ?? 0
                ),
            ];
        }
        uasort($types, static fn($a, $b) => strcasecmp($a['title'], $b['title']));

        $state = $this->readState();
        $state['contentTypes'] = $types;
        $state['fetched'] = time();
        $this->writeState($state);
        return $types;
    }

    /**
     * Download a content type (with all its libraries) from the Hub and install it
     *
     * @return array install report of Store::installLibraries()
     * @throws \RuntimeException
     */
    public function install(string $machineName): array
    {
        if (!preg_match('/^[\w.\-]{1,255}$/', $machineName)) {
            throw new H5PException('badname');
        }

        $body = $this->post(self::CONTENT_TYPES_ENDPOINT . $machineName, ['uuid' => $this->getUuid()]);
        if (strncmp($body, "PK", 2) !== 0) {
            throw new H5PException(
                'hub_nopackage',
                $machineName,
                mb_substr(trim(strip_tags($body)), 0, 120)
            );
        }

        if (!is_dir($this->tmpDir)) @mkdir($this->tmpDir, 0775, true);
        $file = tempnam($this->tmpDir, 'h5phub');
        if ($file === false || file_put_contents($file, $body) === false) {
            throw new H5PException('hub_save');
        }
        try {
            return $this->store->installLibraries($file);
        } finally {
            @unlink($file);
        }
    }

    /**
     * UUID of this site at the Hub, registers the site on first use
     *
     * @throws \RuntimeException
     */
    public function getUuid(): string
    {
        $state = $this->readState();
        if (!empty($state['uuid'])) return $state['uuid'];

        $body = $this->post(self::SITES_ENDPOINT, $this->registrationData(''));
        $data = json_decode($body, true);
        if (!is_array($data) || empty($data['uuid']) || !is_string($data['uuid'])) {
            throw new H5PException('hub_register');
        }
        $state['uuid'] = $data['uuid'];
        $this->writeState($state);
        return $state['uuid'];
    }

    protected function registrationData(string $uuid): array
    {
        return [
            'uuid' => $uuid,
            'platform_name' => 'DokuWiki',
            'platform_version' => $this->platformVersion,
            'h5p_version' => self::CORE_API_VERSION,
            'disabled' => 0,
            'local_id' => crc32(__DIR__),
            'type' => 'internet',
            'core_api_version' => self::CORE_API_VERSION,
        ];
    }

    /**
     * @throws \RuntimeException
     */
    protected function post(string $url, array $data): string
    {
        $http = new DokuHTTPClient();
        $http->timeout = 60;
        $http->max_bodysize = $this->maxBytes;
        $http->keep_alive = false;

        $body = $http->post($url, $data);
        if ($body === false || $http->status < 200 || $http->status >= 300) {
            $detail = $http->error ?: ('HTTP ' . $http->status);
            throw new H5PException('hub_unreachable', $detail);
        }
        return (string)$body;
    }

    protected function stateFile(): string
    {
        return $this->store->getBaseDir() . '/hub.json';
    }

    protected function readState(): array
    {
        $file = $this->stateFile();
        if (!is_file($file)) return [];
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    protected function writeState(array $state): void
    {
        $dir = $this->store->getBaseDir();
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        file_put_contents($this->stateFile(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
