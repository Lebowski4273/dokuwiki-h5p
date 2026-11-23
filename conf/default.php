<?php
/**
 * Default settings for the h5p plugin
 */

$conf['libmanagers']     = '';  // users/@groups allowed to install libraries (admin always alloed to install)
$conf['strip_on_upload'] = 1;   // remove libraries from uploaded .h5p files
$conf['frame']           = 0;   // show the H5P action bar below the content
$conf['copyright']       = 0;   // show the copyright button (needs frame)
$conf['fullscreen']      = 1;   // allow fullscreen for content types supporting it
$conf['icon']            = 0;   // show the H5P icon (needs frame)
$conf['export']          = 0;   // show the "Reuse" button to download the .h5p file (needs frame)
$conf['maxsize']         = 200; // maximum uncompressed size of a package in MB
$conf['storagedir']      = '';  // empty: <savedir>/h5p
$conf['replace']         = 1;   // show a "replace" button below H5P content for users allowed to overwrite the file
$conf['hub']             = 1;   // allow installing content types from the H5P Hub (admin page)
$conf['minor_fallback']  = 0;   // use a higher minor version if the required one is missing
