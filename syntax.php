<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 * DokuWiki Plugin h5p (Syntax Component)
 *
 * Syntax:
 *   {{h5p>namespace:datei.h5p}}
 *   {{h5p>namespace:datei.h5p?width=800&frame=1&copyright=1&fullscreen=0&export=1}}
 * 
 * TODO: Einbetten
 */
class syntax_plugin_h5p extends SyntaxPlugin
{
    public function getType()
    {
        return 'substition';
    }

    public function getPType()
    {
        return 'block';
    }

    public function getSort()
    {
        return 300; // before the core media syntax (320)
    }

    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('\{\{h5p>[^}\n]+\}\}', $mode, 'plugin_h5p');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        global $ID;

        $inner = trim(substr($match, 6, -2));
        [$src, $query] = array_pad(explode('?', $inner, 2), 2, '');
        $id = $this->resolveMediaId(trim($src), (string)$ID);

        $params = [];
        parse_str($query, $params);

        $opts = [];
        if (isset($params['width']) && preg_match('/^(\d{1,4})(px|%|em|rem)?$/', (string)$params['width'], $m)) {
            $opts['width'] = $m[1] . (($m[2] ?? '') !== '' ? $m[2] : 'px');
        }
        foreach (['frame', 'copyright', 'fullscreen', 'icon', 'export'] as $key) {
            if (isset($params[$key])) $opts[$key] = $params[$key] ? 1 : 0;
        }

        return ['id' => $id, 'opts' => $opts];
    }

    public function render($format, Doku_Renderer $renderer, $data)
    {
        $id = $data['id'];

        if ($format === 'metadata') {
            /** @var Doku_Renderer_metadata $renderer */
            $renderer->meta['relation']['media'][$id] = file_exists(mediaFN($id));
            if (!isset($renderer->meta['plugin_h5p']) || !in_array($id, $renderer->meta['plugin_h5p'], true)) {
                $renderer->meta['plugin_h5p'][] = $id;
            }
            return true;
        }

        if ($format !== 'xhtml') return false;

        // Note: the XHTML output is cached for all users, so no ACL checks here.
        // Access control happens in file.php when the player loads the content.

        /** @var helper_plugin_h5p $helper */
        $helper = $this->loadHelper('h5p');

        if (!$helper->playerAvailable()) {
            $this->error($renderer, $id, $this->getLang('noplayer'));
            return true;
        }

        try {
            $dir = $helper->prepare($id);
            $store = $helper->getStore();
            $missing = $store->missingDependencies($store->contentDependencies($dir));
        } catch (\Exception $e) {
            $this->error($renderer, $id, sprintf($this->getLang('broken'), $id, $helper->errorText($e)));
            return true;
        }

        if ($missing) {
            $this->error($renderer, $id, sprintf($this->getLang('missing'), implode(', ', $missing)));
            return true;
        }

        $opts = $data['opts'] + [
                'frame' => (int)$this->getConf('frame'),
                'copyright' => (int)$this->getConf('copyright'),
                'fullscreen' => (int)$this->getConf('fullscreen'),
                'icon' => (int)$this->getConf('icon'),
                'export' => (int)$this->getConf('export'),
            ];

        $attrs = [
            'class' => 'plugin_h5p',
            'data-h5p-id' => $id,
            'data-h5p-content' => $helper->contentUrl($id),
            'data-h5p-libraries' => $helper->librariesUrl(),
            'data-h5p-player' => $helper->playerUrl(),
            'data-h5p-frame' => $opts['frame'],
            'data-h5p-copyright' => $opts['copyright'],
            'data-h5p-fullscreen' => $opts['fullscreen'],
            'data-h5p-icon' => $opts['icon'],
            'data-h5p-export' => $opts['export'],
        ];
        if ($opts['export']) {
            // download of the media file itself, DokuWiki's fetch.php checks the read permission
            $attrs['data-h5p-download'] = ml($id, '', true, '&');
        }
        if (!empty($opts['width'])) $attrs['style'] = 'max-width:' . $opts['width'];

        $renderer->doc .= '<div ' . buildAttributes($attrs) . '>'
            . '<noscript><p>' . hsc($this->getLang('noscript')) . '</p></noscript>'
            . '</div>' . DOKU_LF;
        return true;
    }

    protected function error(Doku_Renderer $renderer, string $id, string $message): void
    {
        // data-h5p-id lets permitted users replace a broken file directly from here
        $renderer->doc .= '<div class="plugin_h5p_error" data-h5p-id="' . hsc($id) . '">'
            . hsc($message) . '</div>' . DOKU_LF;
    }

    /**
     * Resolve a (possibly relative) media id against the current page
     */
    protected function resolveMediaId(string $src, string $page): string
    {
        if (class_exists('\dokuwiki\File\MediaResolver')) {
            return (new \dokuwiki\File\MediaResolver($page))->resolveId($src);
        }
        $exists = false;
        resolve_mediaid(getNS($page), $src, $exists); // older DokuWiki releases
        return $src;
    }
}
