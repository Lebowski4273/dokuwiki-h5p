<?php
/**
 * English settings for the h5p plugin
 */

$lang['libmanagers']     = 'Users and @groups (comma separated) allowed to install H5P libraries in addition to managers';
$lang['strip_on_upload'] = 'Remove libraries from uploaded H5P files (only the content stays in the media manager)';
$lang['frame']           = 'Show the action bar below the content';
$lang['copyright']       = 'Show the copyright button (requires action bar)';
$lang['fullscreen']      = 'Allow fullscreen (for content types supporting it)';
$lang['icon']            = 'Show the H5P logo (requires action bar)';
$lang['export']          = 'Show the "Reuse" button to download the H5P file (requires action bar; the file contains no libraries)';
$lang['maxsize']         = 'Maximum uncompressed size of an H5P file in MB';
$lang['replace']         = 'Show a "Replace H5P file" button below the content (only for users allowed to overwrite the file)';
$lang['hub']             = 'Allow installing content types from the official H5P Hub (api.h5p.org) on the admin page';
$lang['minor_fallback']  = 'If the required version of a library is missing, use a higher minor version of the same major version (usually compatible, not guaranteed)';
$lang['storagedir']      = 'Storage directory for libraries and extracted content (empty = &lt;savedir&gt;/h5p)';
