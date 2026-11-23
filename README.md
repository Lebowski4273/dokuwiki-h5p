# DokuWiki H5P Plugin

This plugin can display H5P content on DokuWiki pages. However, the `.h5p` files must be created externally (Lumi, Moodle, h5p.org, etc.). They are then uploaded via the Media Manager and can be embedded on wiki pages. There is a small convenience feature: a **Replace H5P file** button can be shown below H5P content, saving a trip through the Media Manager when you have modified an existing file in an external tool. H5P libraries (content types) are **not** stored inside every H5P file, but rather once centrally on the server.

The admin page lets you install H5P libraries. It can also remove orphaned content (unpacked H5P files whose source file has been deleted). In the configuration, you can specify which users or user groups are allowed to install libraries. By default, admin users can always do this.

Playback uses the [h5p-standalone](https://github.com/tunapanda/h5p-standalone) player (MIT license). It runs completely in the browser, so the plugin only needs to unpack and serve files. I use the files already packaged with npm from [https://www.jsdelivr.com/package/npm/h5p-standalone?tab=files&path=dist](https://www.jsdelivr.com/package/npm/h5p-standalone?tab=files&path=dist); these live in `vendor/h5p-standalone`.

## Installation

1. Copy the `h5p` folder to `lib/plugins/h5p/` or trigger the installation via the admin page.
2. The files already packaged with npm from [https://www.jsdelivr.com/package/npm/h5p-standalone?tab=files&path=dist](https://www.jsdelivr.com/package/npm/h5p-standalone?tab=files&path=dist) are located under `lib/plugins/h5p/vendor/h5p-standalone/`.
If you get a different version, download the `.zip` from [h5p-standalone](https://github.com/tunapanda/h5p-standalone) and copy the contents of the `dist` folder to `lib/plugins/h5p/vendor/h5p-standalone/`. For example:
```
npm pack h5p-standalone
tar xzf h5p-standalone-*.tgz
cp -r package/dist/* lib/plugins/h5p/vendor/h5p-standalone/

```


Afterward, at least `main.bundle.js`, `frame.bundle.js`, and `styles/h5p.css` must be present there. The admin page shows whether the player was found.
3. **Allow the `.h5p` file type:** Add this line to `conf/mime.local.conf`:
```
h5p   application/zip

```


4. **Web server:** Files are served over paths like `lib/plugins/h5p/file.php/c/…/h5p.json`.
* Apache: usually works without changes.
* nginx: The PHP location must also pass paths *after* `.php`, for example:
```
location ~ \.php(/|$) {
    fastcgi_split_path_info ^(.+?\.php)(/.*)$;
    fastcgi_param PATH_INFO $fastcgi_path_info;
    # … remaining fastcgi settings
}

```




5. The data directory must be writable by the web server. By default, `data/h5p/` is created.
6. Under **Admin → H5P Libraries**, install the missing libraries from the Hub. For the Hub, the server needs outgoing HTTPS connections to `api.h5p.org`.

## Usage

```
{{h5p>unterricht:informatik:variablen.h5p}}
{{h5p>variablen.h5p?width=800&frame=1&copyright=1}}

```

Relative paths are resolved to the page's namespace, just like regular media files.

### Updating content ("Replace H5P file")

Editing happens outside the wiki, for example in Lumi. Below each H5P item, users who are allowed to overwrite the file (ACL *Delete* permission on the media file) see the **Replace H5P file** button. Workflow:

1. Download with "Reuse" (or use your own Lumi file) and edit it in Lumi.
2. Click **Replace H5P file** below the content and choose the new file.
3. The page reloads and displays the new version.

Replacing runs through DokuWiki's normal upload function: the old version stays in the Media Manager's revision history, and libraries are handled just like on upload. The button also appears below an error message so broken files can be swapped out directly. Can be disabled with `replace`.

| Parameter | Meaning |
| --- | --- |
| `width` | maximum width, e.g. `800`, `800px`, `80%` |
| `frame` | show action bar (`1`/`0`) |
| `copyright` | copyright button (`1`/`0`, requires `frame`) |
| `fullscreen` | fullscreen button (`1`/`0`) |
| `icon` | H5P logo (`1`/`0`, requires `frame`) |
| `export` | "Reuse" download (`1`/`0`, requires `frame`) |

The player automatically adjusts its height to fit the content.

## How libraries are managed

Many H5P files **no longer contain libraries**. Downloads from h5p.org, for example, have contained only `h5p.json` and `content/` since early 2025. The plugin is designed for this: content and libraries are stored separately.

```
data/h5p/
├── libraries/                  central libraries
│   ├── H5P.ArithmeticQuiz-1.1/
│   ├── H5P.JoubelUI-1.3/
│   └── …
├── libraries.updated           timestamp; clears page cache on changes
├── hub.json                    Hub registration and list of content types
└── content/<md5>/              unpacked content per H5P file (h5p.json + content/)

```

**Upload to the Media Manager:** Every valid H5P file is accepted. If libraries are missing, there is a notice, and a message listing the missing libraries appears on the page instead of the player. When a manager (or a member of `libmanagers`) uploads a file *with* libraries, they are installed centrally and stripped from the file (can be disabled with `strip_on_upload`). Library code from other users is never adopted.

**Admin page** (**Admin → H5P Libraries**), available only to managers and admins:

1. *Missing libraries:* scans all H5P files in the Media Manager and shows what is missing and which file needs it. One button lets you install them individually or all together from the Hub.
2. *Install content type from H5P Hub:* downloads the latest version of a content type with all dependencies from `api.h5p.org`. The first time, the wiki registers with the Hub (like WordPress or Moodle) and saves the returned UUID in `hub.json`. The server only opens the connection on button click. Can be disabled with `hub`.
3. *Install libraries from a file:* H5P files containing libraries or ZIP files containing library folders (any folder with a `library.json`, including nested ones), e.g., the library folder from Lumi or Moodle. This works without the Hub.
4. *Installed libraries:* overview with dependencies, deletion.

A library is only replaced by a higher patch version. Different minor versions (e.g., `H5P.Question-1.4` and `-1.5`) coexist side by side.

### Version issues and minor fallback

The Hub always delivers only the **latest** version. If content requires an older minor version (e.g., 1.1, while the Hub has 1.2), it remains marked as "missing." The admin page points this out. Solutions:

* open the content in Lumi, update it, and re-export it, or
* enable the `minor_fallback` setting: the highest installed minor version of the same major version is then used. This usually works, though H5P does not guarantee it.

## Security

* Only allowed file types are unpacked (based on the H5P whitelist), with no `.js`/`.css` inside content. Paths containing `..` or absolute paths cause the file to be rejected.
* Content is served only if the user has read permission on the H5P file. Libraries are accessible to everyone, as they contain only program code.
* H5P libraries are JavaScript that runs in the wiki's context (just as in WordPress or Moodle). Therefore, only trustworthy people should be allowed to install libraries.

## Settings

In the Configuration Manager under "h5p": `libmanagers`, `strip_on_upload`, `frame`, `copyright`, `fullscreen`, `icon`, `export`, `replace`, `maxsize` (MB, unpacked), `storagedir`, `hub`, `minor_fallback`.

## Known limitations

* "Reuse" downloads the file from the Media Manager (checking read permissions). It contains no libraries; Moodle, WordPress, and Lumi fetch them themselves from the H5P Hub on import. "Embed" is not supported.
* Display only: results (xAPI) are not saved, and there is no editor.
* If an H5P file is deleted in the Media Manager, the plugin also removes its unpacked content. If a file disappears another way (FTP, Move plugin), the **Delete orphaned content** button on the admin page helps. Unpacked content is recreated automatically when needed.
* When libraries are stripped, the file is modified after upload. The media changelog may therefore show the original file size.

## Testing core logic

```
php _test/selftest.php

```