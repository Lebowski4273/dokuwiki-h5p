/**
 * DokuWiki Plugin h5p - initializes the h5p-standalone player
 *
 * The (large) player bundle is only loaded on pages that contain H5P content.
 */
(function () {
    'use strict';

    function lang(key, fallback) {
        try {
            return LANG.plugins.h5p[key] || fallback;
        } catch (e) {
            return fallback;
        }
    }

    function showError(el, message) {
        var div = document.createElement('div');
        div.className = 'plugin_h5p_error';
        div.textContent = message;
        el.replaceChildren(div);
    }

    /** the player constructor, wherever the bundle put it */
    function getPlayerClass() {
        var lib = window.H5PStandalone;
        if (!lib) return null;
        if (typeof lib.H5P === 'function') return lib.H5P;
        if (lib.default && typeof lib.default.H5P === 'function') return lib.default.H5P;
        if (typeof lib.default === 'function') return lib.default;
        return null;
    }

    function loadPlayer(baseUrl) {
        return new Promise(function (resolve, reject) {
            if (getPlayerClass()) {
                resolve();
                return;
            }

            // Hide AMD/CommonJS loaders of other scripts while the bundle runs. Otherwise a UMD
            // bundle registers itself there instead of creating the global H5PStandalone.
            var saved = {define: window.define, module: window.module, exports: window.exports};
            var restore = function () {
                window.define = saved.define;
                window.module = saved.module;
                window.exports = saved.exports;
            };
            window.define = undefined;
            window.module = undefined;
            window.exports = undefined;

            var script = document.createElement('script');
            script.src = baseUrl + 'main.bundle.js';
            script.charset = 'UTF-8';
            script.onload = function () {
                restore();
                if (getPlayerClass()) {
                    resolve();
                } else {
                    if (window.console) {
                        console.error('H5P: main.bundle.js was loaded but provides no H5PStandalone.H5P. ' +
                            'Found: window.H5PStandalone = ', window.H5PStandalone);
                    }
                    reject(new Error('player'));
                }
            };
            script.onerror = function () {
                restore();
                if (window.console) console.error('H5P: could not load ' + script.src);
                reject(new Error('player'));
            };
            document.head.appendChild(script);
        });
    }

    /** check access before starting the player to show a clear message */
    function checkContent(url) {
        return fetch(url + '/h5p.json', {credentials: 'same-origin', method: 'HEAD'})
            .then(function (response) {
                if (!response.ok) throw new Error(String(response.status));
            });
    }

    // ------------------------------------------------------------------ replace button

    function replaceInfo() {
        try {
            return JSINFO.plugin_h5p || null;
        } catch (e) {
            return null;
        }
    }

    function showMessages(box, messages) {
        box.replaceChildren();
        messages.forEach(function (m) {
            var p = document.createElement('div');
            p.className = 'plugin_h5p_msg plugin_h5p_msg_' +
                (m.level < 0 ? 'error' : (m.level === 1 ? 'success' : (m.level === 2 ? 'notify' : 'info')));
            p.textContent = m.text;
            box.appendChild(p);
        });
    }

    function uploadReplacement(id, file, info, box, button) {
        var data = new FormData();
        data.append('call', 'plugin_h5p_replace');
        data.append('id', id);
        data.append('sectok', info.sectok);
        data.append('h5pfile', file);

        button.disabled = true;
        showMessages(box, [{level: 0, text: lang('replace_working', 'Uploading file …')}]);

        fetch(DOKU_BASE + 'lib/exe/ajax.php', {method: 'POST', body: data, credentials: 'same-origin'})
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function (result) {
                var messages = result.messages || [];
                if (!result.ok) {
                    showMessages(box, messages.length ? messages : [{level: -1, text: lang('replace_failed', 'Replacing failed.')}]);
                    button.disabled = false;
                    return;
                }
                var warnings = messages.some(function (m) {
                    return m.level < 0 || m.level === 2;
                });
                messages.push({level: 1, text: lang('replace_done', 'The file has been replaced.')});
                showMessages(box, messages);
                if (warnings) {
                    // keep the warnings readable, reload on request
                    var reload = document.createElement('button');
                    reload.type = 'button';
                    reload.textContent = lang('replace_reload', 'Reload page');
                    reload.addEventListener('click', function () {
                        window.location.reload();
                    });
                    box.appendChild(reload);
                } else {
                    window.setTimeout(function () {
                        window.location.reload();
                    }, 1200);
                }
            })
            .catch(function (e) {
                showMessages(box, [{level: -1, text: lang('replace_failed', 'Replacing failed.') + ' (' + e.message + ')'}]);
                button.disabled = false;
            });
    }

    function addReplaceTools() {
        var info = replaceInfo();
        if (!info || !Array.isArray(info.replace) || !info.replace.length) return;

        document.querySelectorAll('[data-h5p-id]').forEach(function (el) {
            var id = el.dataset.h5pId;
            if (info.replace.indexOf(id) === -1) return;

            var tools = document.createElement('div');
            tools.className = 'plugin_h5p_tools';

            var input = document.createElement('input');
            input.type = 'file';
            input.accept = '.h5p';
            input.hidden = true;

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'plugin_h5p_replace';
            button.textContent = lang('replace_btn', 'Replace H5P file');
            button.title = id;

            var box = document.createElement('div');
            box.className = 'plugin_h5p_msgs';

            button.addEventListener('click', function () {
                input.value = '';
                input.click();
            });
            input.addEventListener('change', function () {
                var file = input.files && input.files[0];
                if (!file) return;
                var question = lang('replace_confirm', 'Replace %s with the selected file?').replace('%s', id);
                if (!window.confirm(question)) return;
                uploadReplacement(id, file, info, box, button);
            });

            tools.appendChild(button);
            tools.appendChild(input);
            tools.appendChild(box);
            el.insertAdjacentElement('afterend', tools);
        });
    }

    // ------------------------------------------------------------------ player

    async function init() {
        addReplaceTools();

        var elements = document.querySelectorAll('div.plugin_h5p[data-h5p-content]');
        if (!elements.length) return;

        var playerUrl = elements[0].dataset.h5pPlayer;
        try {
            await loadPlayer(playerUrl);
        } catch (e) {
            elements.forEach(function (el) {
                showError(el, lang('loaderror', 'The H5P player could not be loaded.'));
            });
            return;
        }

        // Remember the constructor now: starting a player may overwrite window.H5PStandalone,
        // so looking it up again for the second content on a page can fail.
        var Player = getPlayerClass();
        if (typeof Player !== 'function') {
            if (window.console) console.error('H5P: no player constructor found', window.H5PStandalone);
            elements.forEach(function (el) {
                showError(el, lang('loaderror', 'The H5P player could not be loaded.'));
            });
            return;
        }

        // initialize one after another - parallel initialization can confuse the player
        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            var d = el.dataset;
            try {
                await checkContent(d.h5pContent);
            } catch (e) {
                if (window.console) {
                    console.error('H5P: checking ' + d.h5pContent + '/h5p.json failed, status ' + e.message);
                }
                showError(el, e.message === '403'
                    ? lang('noaccess', 'You are not allowed to view this H5P content.')
                    : lang('initerror', 'The H5P content could not be displayed.'));
                continue;
            }
            el.replaceChildren();
            try {
                if (window.console) console.log('H5P: starting ' + d.h5pContent);
                await new Player(el, {
                    h5pJsonPath: d.h5pContent,
                    librariesPath: d.h5pLibraries,
                    frameJs: d.h5pPlayer + 'frame.bundle.js',
                    frameCss: d.h5pPlayer + 'styles/h5p.css',
                    frame: d.h5pFrame === '1',
                    copyright: d.h5pCopyright === '1',
                    fullScreen: d.h5pFullscreen === '1',
                    icon: d.h5pIcon === '1',
                    export: d.h5pExport === '1' && !!d.h5pDownload,
                    downloadUrl: d.h5pDownload || undefined,
                    embed: false
                });
            } catch (e) {
                if (window.console) console.error('H5P:', e);
                showError(el, lang('initerror', 'The H5P content could not be displayed.'));
            }
        }
    }

    // confirmation for deleting libraries on the admin page
    document.addEventListener('submit', function (e) {
        if (e.target.matches && e.target.matches('form.plugin_h5p_delete')) {
            if (!window.confirm(lang('confirmdelete', 'Really delete this library?'))) {
                e.preventDefault();
            }
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
