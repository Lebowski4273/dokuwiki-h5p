<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;

/**
 * DokuWiki Plugin h5p (Action Component)
 *
 * - invalidates the page cache when an embedded .h5p file or the libraries change
 * - checks uploaded .h5p files, installs their libraries (if allowed) and strips them
 * - Removes extracted content, if original h5p-file is deleted.
 */
class action_plugin_h5p extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('PARSER_CACHE_USE', 'BEFORE', $this, 'handleCacheUse');
        $controller->register_hook('MEDIA_UPLOAD_FINISH', 'BEFORE', $this, 'handleUploadBefore');
        $controller->register_hook('MEDIA_UPLOAD_FINISH', 'AFTER', $this, 'handleUploadAfter');
        $controller->register_hook('MEDIA_DELETE_FILE', 'AFTER', $this, 'handleMediaDelete');
        $controller->register_hook('DOKUWIKI_STARTED', 'AFTER', $this, 'handleJsInfo');
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handleAjax');
    }

    /**
     * Tell the JavaScript which H5P files on this page the current user may replace
     *
     * The page HTML is cached for all users, JSINFO is created per request.
     */
    public function handleJsInfo(Event $event)
    {
        global $JSINFO, $ID, $ACT;
        if (!$this->getConf('replace') || act_clean($ACT) !== 'show' || !$ID) return;

        $ids = p_get_metadata($ID, 'plugin_h5p', METADATA_DONT_RENDER);
        if (!is_array($ids) || !$ids) return;

        $allowed = [];
        foreach ($ids as $id) {
            if (file_exists(mediaFN($id)) && auth_quickaclcheck($id) >= AUTH_DELETE) $allowed[] = $id;
        }
        if (!$allowed) return;

        $JSINFO['plugin_h5p'] = [
            'replace' => $allowed,
            'sectok' => getSecurityToken(),
        ];
    }

    /**
     * AJAX endpoint lib/exe/ajax.php?call=plugin_h5p_replace
     */
    public function handleAjax(Event $event)
    {
        if ($event->data !== 'plugin_h5p_replace') return;
        $event->preventDefault();
        $event->stopPropagation();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->replaceFile(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Replace an existing .h5p file with an uploaded one
     *
     * Uses DokuWiki's own media_save(): ACL, media revisions, change log and the
     * MEDIA_UPLOAD_FINISH handlers of this plugin (validation, libraries, stripping) all apply.
     *
     * @return array ['ok' => bool, 'messages' => [['level' => int, 'text' => string]]]
     */
    protected function replaceFile(): array
    {
        global $INPUT, $MSG;

        $fail = static fn(string $text) => ['ok' => false, 'messages' => [['level' => -1, 'text' => $text]]];

        if (!$this->getConf('replace') || $INPUT->server->str('REQUEST_METHOD') !== 'POST') {
            return $fail($this->getLang('replace_denied'));
        }
        if (!checkSecurityToken()) {
            $MSG = [];
            return $fail($this->getLang('replace_denied'));
        }

        /** @var helper_plugin_h5p $helper */
        $helper = plugin_load('helper', 'h5p');
        $id = cleanID($INPUT->post->str('id'));
        if ($id === '' || !$helper->isH5PFile($id) || !file_exists(mediaFN($id))) {
            return $fail($this->getLang('replace_notfound'));
        }
        $auth = auth_quickaclcheck($id);
        if ($auth < AUTH_DELETE) {
            return $fail($this->getLang('replace_denied'));
        }

        $upload = $_FILES['h5pfile'] ?? null;
        if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            return $fail($this->getLang('replace_nofile'));
        }
        if (!$helper->isH5PFile((string)$upload['name'])) {
            return $fail($this->getLang('replace_wrongtype'));
        }

        [$ext, $mime] = mimetype($id);
        $MSG = [];
        $result = media_save(
            ['name' => $upload['tmp_name'], 'mime' => $mime, 'ext' => $ext],
            $id,
            true,
            $auth,
            'move_uploaded_file'
        );

        // collect messages created by media_save() and the upload handlers
        $messages = [];
        foreach ((array)$MSG as $m) {
            $messages[] = ['level' => $this->msgLevel($m['lvl'] ?? 0), 'text' => $this->plain((string)($m['msg'] ?? ''))];
        }
        $MSG = [];

        if (is_array($result)) {
            $messages[] = ['level' => -1, 'text' => $this->plain($result[0])];
            return ['ok' => false, 'messages' => $messages];
        }
        return ['ok' => true, 'messages' => $messages];
    }

    /** DokuWiki message levels may be numbers or names */
    protected function msgLevel($lvl): int
    {
        $map = ['error' => -1, 'info' => 0, 'success' => 1, 'notify' => 2];
        return is_numeric($lvl) ? (int)$lvl : ($map[$lvl] ?? 0);
    }

    protected function plain(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    }

    /**
     * Remove the extracted content when an .h5p file is deleted in the media manager
     */
    public function handleMediaDelete(Event $event)
    {
        $id = (string)($event->data['id'] ?? '');
        if (empty($event->data['unl'])) return; // file was not deleted

        /** @var helper_plugin_h5p $helper */
        $helper = plugin_load('helper', 'h5p');
        if (!$helper->isH5PFile($id)) return;
        $helper->removeContent($id);
    }

    /**
     * Add embedded H5P files and the library index as cache dependencies
     */
    public function handleCacheUse(Event $event)
    {
        $cache = $event->data;
        if (($cache->mode ?? '') !== 'xhtml' || empty($cache->page)) return;

        $ids = p_get_metadata($cache->page, 'plugin_h5p', METADATA_DONT_RENDER);
        if (!is_array($ids) || !$ids) return;

        /** @var helper_plugin_h5p $helper */
        $helper = plugin_load('helper', 'h5p');
        foreach ($ids as $id) {
            $file = mediaFN($id);
            if (file_exists($file)) $cache->depends['files'][] = $file;
        }
        $index = $helper->getStore()->getIndexFile();
        if (file_exists($index)) $cache->depends['files'][] = $index;
        $cache->depends['files'][] = DOKU_PLUGIN . 'h5p/syntax.php';
    }

    /**
     * Validate an uploaded .h5p file before it is stored - only broken or unsafe packages are rejected
     */
    public function handleUploadBefore(Event $event)
    {
        [$tmpFile, , $id] = $event->data;

        /** @var helper_plugin_h5p $helper */
        $helper = plugin_load('helper', 'h5p');
        if (!$helper->isH5PFile((string)$id)) return;

        try {
            $helper->getStore()->readPackage($tmpFile);
        } catch (\Exception $e) {
            $this->reject($event, sprintf($this->getLang('upload_invalid'), $helper->errorText($e)));
        }
    }

    /**
     * After a successful upload: install libraries (if allowed), strip them from the file,
     * pre-extract the content and warn about libraries that are still missing
     */
    public function handleUploadAfter(Event $event)
    {
        if (is_array($event->result) || !$event->result) return; // upload failed

        [, $file, $id] = $event->data;

        /** @var helper_plugin_h5p $helper */
        $helper = plugin_load('helper', 'h5p');
        if (!$helper->isH5PFile((string)$id) || !file_exists($file)) return;

        $store = $helper->getStore();
        try {
            if ($helper->canInstallLibraries()) {
                $report = $store->installLibraries($file);
                if ($report['installed']) {
                    msg(hsc(sprintf($this->getLang('upload_installed'), implode(', ', $report['installed']))), 1);
                }
                if ($report['updated']) {
                    msg(hsc(sprintf($this->getLang('upload_updated'), implode(', ', $report['updated']))), 1);
                }
            }
            if ($this->getConf('strip_on_upload')) {
                $store->stripPackage($file);
            }
            $dir = $helper->prepare($id);
            $missing = $store->missingDependencies($store->contentDependencies($dir));
            if ($missing) {
                msg(hsc(sprintf($this->getLang('upload_missing'), implode(', ', $missing))), 2);
            }
        } catch (\Exception $e) {
            msg(hsc(sprintf($this->getLang('upload_postprocess'), $helper->errorText($e))), -1);
        }
    }

    protected function reject(Event $event, string $message): void
    {
        $event->preventDefault();
        $event->stopPropagation();
        $event->result = [hsc($message), -1];
    }
}
