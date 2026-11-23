<?php

use dokuwiki\Extension\AdminPlugin;

/**
 * DokuWiki Plugin h5p (Admin Component)
 *
 * - shows libraries missing for H5P files in the media manager
 * - installs content types from the H5P Hub
 * - installs libraries from an uploaded .h5p file or a zip of library folders
 * - lists and deletes installed libraries
 */
class admin_plugin_h5p extends AdminPlugin
{
    public function forAdminOnly()
    {
        return false; // managers may use it, too
    }

    public function getMenuSort()
    {
        return 610;
    }

    public function getMenuIcon()
    {
        return DOKU_PLUGIN . 'h5p/admin.svg';
    }

    public function handle()
    {
        global $INPUT;
        if (!$INPUT->post->has('h5p_action') || !checkSecurityToken()) return;

        /** @var helper_plugin_h5p $helper */
        $helper = $this->loadHelper('h5p');
        $store = $helper->getStore();
        $action = $INPUT->post->str('h5p_action');

        if (strpos($action, 'hub_') === 0 && !$this->getConf('hub')) return;
        @set_time_limit(300);

        try {
            switch ($action) {
                case 'install':
                    $upload = $_FILES['h5pfile'] ?? null;
                    if (!$upload || $upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
                        msg($this->getLang('admin_nofile'), -1);
                        return;
                    }
                    $this->report($store->installLibraries($upload['tmp_name']));
                    break;

                case 'delete':
                    $folder = $INPUT->post->str('library');
                    $store->deleteLibrary($folder);
                    msg(hsc(sprintf($this->getLang('admin_deleted'), $folder)), 1);
                    break;

                case 'cleanup':
                    msg(hsc(sprintf($this->getLang('admin_cleaned'), $helper->cleanupContent())), 1);
                    break;

                case 'hub_refresh':
                    $types = $helper->getHub()->refreshContentTypes();
                    msg(hsc(sprintf($this->getLang('hub_refreshed'), count($types))), 1);
                    break;

                case 'hub_install':
                    $this->hubInstall($helper, [$INPUT->post->str('machinename')]);
                    break;

                case 'hub_install_missing':
                    $names = [];
                    foreach (array_keys($helper->scanMedia()['missing']) as $label) {
                        $names[] = $this->machineName($label);
                    }
                    $this->hubInstall($helper, array_unique($names));
                    break;
            }
        } catch (\Exception $e) {
            msg(hsc(sprintf($this->getLang('admin_error'), $helper->errorText($e))), -1);
        }
    }

    /**
     * Install several content types from the Hub, continue on errors
     */
    protected function hubInstall(helper_plugin_h5p $helper, array $names): void
    {
        foreach ($names as $name) {
            if ($name === '') continue;
            try {
                $this->report($helper->getHub()->install($name));
            } catch (\Exception $e) {
                msg(hsc(sprintf($this->getLang('hub_failed'), $name, $helper->errorText($e))), -1);
            }
        }
    }

    protected function report(array $report): void
    {
        foreach (['installed', 'updated', 'skipped'] as $key) {
            if ($report[$key]) {
                msg(hsc(sprintf($this->getLang('admin_' . $key), implode(', ', $report[$key]))),
                    $key === 'skipped' ? 0 : 1);
            }
        }
        if (!$report['installed'] && !$report['updated'] && !$report['skipped']) {
            msg($this->getLang('admin_nolibs'), 0);
        }
    }

    /** "H5P.MultiChoice 1.16" -> "H5P.MultiChoice" */
    protected function machineName(string $label): string
    {
        $pos = strrpos($label, ' ');
        return $pos === false ? $label : substr($label, 0, $pos);
    }

    public function html()
    {
        /** @var helper_plugin_h5p $helper */
        $helper = $this->loadHelper('h5p');
        $store = $helper->getStore();
        $hubEnabled = (bool)$this->getConf('hub');
        $hubTypes = $hubEnabled ? $helper->getHub()->getCachedContentTypes() : [];

        echo '<div class="plugin_h5p_admin">';
        echo '<h1>' . hsc($this->getLang('menu')) . '</h1>';
        echo $this->locale_xhtml('intro');

        // status
        echo '<ul>';
        echo '<li>' . hsc($this->getLang($helper->playerAvailable() ? 'admin_player_ok' : 'noplayer')) . '</li>';
        echo '<li>' . hsc(sprintf($this->getLang('admin_storage'), $store->getBaseDir())) . '</li>';
        if ($this->getConf('minor_fallback')) echo '<li>' . hsc($this->getLang('admin_fallback_on')) . '</li>';
        echo '</ul>';

        $this->htmlMissing($helper, $hubEnabled, $hubTypes);
        if ($hubEnabled) $this->htmlHub($helper, $hubTypes);
        $this->htmlUpload();
        $this->htmlLibraries($helper);
        $this->htmlCleanup();

        echo '</div>';
    }

    protected function htmlMissing(helper_plugin_h5p $helper, bool $hubEnabled, array $hubTypes): void
    {
        $scan = $helper->scanMedia();
        echo '<h2>' . hsc($this->getLang('admin_missing')) . '</h2>';

        $broken = array_filter($scan['files'], static fn($f) => $f['error'] !== null);
        if (!$scan['missing'] && !$broken) {
            echo '<p>' . hsc(sprintf($this->getLang('admin_missing_none'), count($scan['files']))) . '</p>';
            return;
        }

        if ($scan['missing']) {
            echo '<table class="inline"><thead><tr>'
                . '<th>' . hsc($this->getLang('admin_col_library')) . '</th>'
                . '<th>' . hsc($this->getLang('admin_col_files')) . '</th>';
            if ($hubEnabled) echo '<th>' . hsc($this->getLang('admin_col_hub')) . '</th><th></th>';
            echo '</tr></thead><tbody>';

            foreach ($scan['missing'] as $label => $ids) {
                $name = $this->machineName($label);
                $required = substr($label, strlen($name) + 1);
                echo '<tr><td><code>' . hsc($label) . '</code></td><td>';
                echo implode(', ', array_map(static fn($id) => hsc($id), $ids));
                echo '</td>';
                if ($hubEnabled) {
                    echo '<td>';
                    if (isset($hubTypes[$name])) {
                        echo hsc($hubTypes[$name]['version']);
                        if ($hubTypes[$name]['majorMinor'] !== $required) {
                            echo '<br><small>' . hsc(sprintf($this->getLang('admin_version_differs'), $required)) . '</small>';
                        }
                    } elseif ($hubTypes) {
                        echo '<small>' . hsc($this->getLang('admin_not_on_hub')) . '</small>';
                    }
                    echo '</td><td>';
                    $this->button('hub_install', $this->getLang('hub_install_btn'), ['machinename' => $name]);
                    echo '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
            if ($hubEnabled && count($scan['missing']) > 1) {
                $this->button('hub_install_missing', $this->getLang('hub_install_all_btn'));
            }
        }

        if ($broken) {
            echo '<p>' . hsc($this->getLang('admin_broken')) . '</p><ul>';
            foreach ($broken as $id => $info) {
                echo '<li><code>' . hsc($id) . '</code>: ' . hsc($info['error']) . '</li>';
            }
            echo '</ul>';
        }
    }

    protected function htmlHub(helper_plugin_h5p $helper, array $hubTypes): void
    {
        echo '<h2>' . hsc($this->getLang('hub_title')) . '</h2>';
        echo '<p>' . hsc($this->getLang('hub_intro')) . '</p>';

        $fetched = $helper->getHub()->getCacheTime();
        if ($hubTypes) {
            echo '<form action="' . hsc($this->actionUrl()) . '" method="post">';
            formSecurityToken();
            echo '<input type="hidden" name="h5p_action" value="hub_install" />';
            echo '<select name="machinename">';
            $installed = [];
            foreach ($helper->getStore()->getLibraries() as $lib) {
                $installed[$lib['machineName']] = $lib['majorVersion'] . '.' . $lib['minorVersion'] . '.' . $lib['patchVersion'];
            }
            foreach ($hubTypes as $name => $type) {
                $text = $type['title'] . ' – ' . $type['version'];
                if (isset($installed[$name])) $text .= ' (' . sprintf($this->getLang('hub_installed'), $installed[$name]) . ')';
                echo '<option value="' . hsc($name) . '">' . hsc($text) . '</option>';
            }
            echo '</select> ';
            echo '<button type="submit">' . hsc($this->getLang('hub_install_btn')) . '</button>';
            echo '</form>';
            echo '<p><small>' . hsc(sprintf($this->getLang('hub_cached'), dformat($fetched))) . '</small></p>';
        }
        $this->button('hub_refresh', $this->getLang($hubTypes ? 'hub_refresh_btn' : 'hub_load_btn'));
    }

    protected function htmlUpload(): void
    {
        echo '<h2>' . hsc($this->getLang('admin_install')) . '</h2>';
        echo '<p>' . hsc($this->getLang('admin_install_intro')) . '</p>';
        echo '<form action="' . hsc($this->actionUrl()) . '" method="post" enctype="multipart/form-data">';
        formSecurityToken();
        echo '<input type="hidden" name="h5p_action" value="install" />';
        echo '<input type="file" name="h5pfile" accept=".h5p,.zip" required /> ';
        echo '<button type="submit">' . hsc($this->getLang('admin_install_btn')) . '</button>';
        echo '</form>';
    }

    protected function htmlLibraries(helper_plugin_h5p $helper): void
    {
        $store = $helper->getStore();
        $libraries = $store->getLibraries();
        echo '<h2>' . hsc(sprintf($this->getLang('admin_list'), count($libraries))) . '</h2>';
        if (!$libraries) {
            echo '<p>' . hsc($this->getLang('admin_empty')) . '</p>';
            return;
        }

        echo '<table class="inline"><thead><tr>'
            . '<th>' . hsc($this->getLang('admin_col_title')) . '</th>'
            . '<th>' . hsc($this->getLang('admin_col_name')) . '</th>'
            . '<th>' . hsc($this->getLang('admin_col_version')) . '</th>'
            . '<th>' . hsc($this->getLang('admin_col_usedby')) . '</th>'
            . '<th></th>'
            . '</tr></thead><tbody>';
        foreach ($libraries as $folder => $lib) {
            echo '<tr>';
            echo '<td>' . hsc($lib['title']) . ($lib['runnable'] ? ' <strong>★</strong>' : '') . '</td>';
            echo '<td><code>' . hsc($lib['machineName']) . '</code></td>';
            echo '<td>' . hsc($lib['majorVersion'] . '.' . $lib['minorVersion'] . '.' . $lib['patchVersion']) . '</td>';
            echo '<td>' . hsc(implode(', ', $store->getDependents($folder))) . '</td>';
            echo '<td>';
            $this->button('delete', $this->getLang('admin_delete'), ['library' => $folder], 'plugin_h5p_delete');
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p>★ ' . hsc($this->getLang('admin_runnable')) . '</p>';
    }

    protected function htmlCleanup(): void
    {
        echo '<h2>' . hsc($this->getLang('admin_cleanup')) . '</h2>';
        echo '<p>' . hsc($this->getLang('admin_cleanup_intro')) . '</p>';
        $this->button('cleanup', $this->getLang('admin_cleanup_btn'));
    }

    protected function actionUrl(): string
    {
        return wl('', ['do' => 'admin', 'page' => 'h5p'], false, '&');
    }

    /**
     * Small POST form with a single button
     */
    protected function button(string $action, string $label, array $fields = [], string $class = ''): void
    {
        echo '<form action="' . hsc($this->actionUrl()) . '" method="post"' . ($class ? ' class="' . $class . '"' : '') . '>';
        formSecurityToken();
        echo '<input type="hidden" name="h5p_action" value="' . hsc($action) . '" />';
        foreach ($fields as $name => $value) {
            echo '<input type="hidden" name="' . hsc($name) . '" value="' . hsc($value) . '" />';
        }
        echo '<button type="submit">' . hsc($label) . '</button>';
        echo '</form>';
    }
}
