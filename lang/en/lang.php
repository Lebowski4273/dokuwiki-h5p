<?php
/**
 * English language file for the h5p plugin
 */

$lang['menu'] = 'H5P libraries';

// page output
$lang['noplayer'] = 'The H5P player is missing. Copy the h5p-standalone files to lib/plugins/h5p/vendor/h5p-standalone/ (see README).';
$lang['broken']   = 'The H5P content %s cannot be displayed: %s';
$lang['missing']  = 'Libraries required by this H5P content are missing: %s. A manager can install them under Administration → H5P libraries.';
$lang['noscript'] = 'H5P content requires JavaScript.';

// upload
$lang['upload_invalid']     = 'The H5P file is invalid: %s';
$lang['upload_missing']     = 'Note: libraries required by this H5P file are still missing: %s. A manager can install them under Administration → H5P libraries.';
$lang['upload_installed']   = 'H5P libraries installed: %s';
$lang['upload_updated']     = 'H5P libraries updated: %s';
$lang['upload_postprocess'] = 'The H5P file was uploaded but post-processing failed: %s';

// admin
$lang['admin_player_ok']   = 'The H5P player is installed.';
$lang['admin_storage']     = 'Storage directory: %s';
$lang['admin_install']     = 'Install libraries from a file';
$lang['admin_install_intro'] = 'Accepted are H5P files containing libraries and zip files with library folders (each folder containing a library.json), e.g. from the library folder of Lumi or Moodle.';
$lang['admin_install_btn'] = 'Install';
$lang['admin_nofile']      = 'Please select an H5P file.';
$lang['admin_nolibs']      = 'The file does not contain any libraries.';
$lang['admin_installed']   = 'Installed: %s';
$lang['admin_updated']     = 'Updated: %s';
$lang['admin_skipped']     = 'Already up to date: %s';
$lang['admin_deleted']     = 'Library %s deleted.';
$lang['admin_error']       = 'Error: %s';
$lang['admin_list']        = 'Installed libraries (%d)';
$lang['admin_empty']       = 'No libraries installed yet.';
$lang['admin_col_title']   = 'Title';
$lang['admin_col_name']    = 'Machine name';
$lang['admin_col_version'] = 'Version';
$lang['admin_col_usedby']  = 'Required by';
$lang['admin_delete']      = 'Delete';
$lang['admin_fallback_on']     = 'Minor fallback is active: missing versions are replaced by higher minor versions.';
$lang['admin_missing']         = 'Missing libraries';
$lang['admin_missing_none']    = 'All %d H5P files in the media manager have their libraries.';
$lang['admin_col_library']     = 'Library (required version)';
$lang['admin_col_files']       = 'Required by';
$lang['admin_col_hub']         = 'Version on the Hub';
$lang['admin_version_differs'] = 'differs from %s: update the content in Lumi or enable the minor fallback';
$lang['admin_not_on_hub']      = 'not on the Hub';
$lang['admin_broken']          = 'These H5P files are broken:';
$lang['hub_title']             = 'Install a content type from the H5P Hub';
$lang['hub_intro']             = 'Downloads the latest version of a content type with all required libraries from api.h5p.org. The server only connects when you press a button here.';
$lang['hub_install_btn']       = 'Install from Hub';
$lang['hub_install_all_btn']   = 'Install all missing from Hub';
$lang['hub_load_btn']          = 'Load content type list from the Hub';
$lang['hub_refresh_btn']       = 'Refresh list';
$lang['hub_refreshed']         = '%d content types loaded from the Hub.';
$lang['hub_installed']         = 'installed: %s';
$lang['hub_cached']            = 'List as of: %s';
$lang['hub_failed']            = '%s could not be installed from the Hub: %s';
$lang['admin_cleanup']         = 'Clean up';
$lang['admin_cleanup_intro']   = 'Extracted content of H5P files is removed automatically when the file is deleted in the media manager. This button removes content of files that disappeared in other ways (e.g. FTP or moving).';
$lang['admin_cleanup_btn']     = 'Delete orphaned content';
$lang['admin_cleaned']         = '%d orphaned contents deleted.';
$lang['admin_runnable']    = '= content type (can be used directly as H5P content)';

// error messages (inserted into the messages above)
$lang['err_badkey']          = 'invalid content key';
$lang['err_notfound']        = 'file not found';
$lang['err_nocontent']       = 'content/content.json is missing';
$lang['err_writepackage']    = 'the file could not be written';
$lang['err_unknownlib']      = 'unknown library %s';
$lang['err_notzip']          = 'not a valid H5P (zip) file';
$lang['err_toomany']         = 'the file contains too many entries';
$lang['err_unsafe']          = 'unsafe file name in the file: %s';
$lang['err_toolarge']        = 'the file is too large when extracted (see setting maxsize)';
$lang['err_extract']         = '%s could not be extracted';
$lang['err_storage']         = 'cannot write to the storage directory (%s)';
$lang['err_nomainlib']       = 'h5p.json does not specify mainLibrary';
$lang['err_badlibjson']      = '%s: invalid %s';
$lang['err_nolibjson']       = '%s: library.json is missing';
$lang['err_jsonmissing']     = '%s is missing';
$lang['err_jsoninvalid']     = '%s is not valid JSON';
$lang['err_badname']         = 'invalid machine name';
$lang['err_hub_answer']      = 'unexpected answer from the H5P Hub';
$lang['err_hub_save']        = 'the download could not be saved';
$lang['err_hub_register']    = 'registration at the H5P Hub failed';
$lang['err_hub_unreachable'] = 'H5P Hub not reachable: %s';
$lang['err_hub_nopackage']   = 'the H5P Hub did not send a package for %s (%s)';

// replace button (server side)
$lang['replace_denied']    = 'You are not allowed to replace this file.';
$lang['replace_notfound']  = 'The H5P file to replace was not found.';
$lang['replace_nofile']    = 'No file was received. It may be larger than the server allows (upload_max_filesize).';
$lang['replace_wrongtype'] = 'Please select a file with the extension .h5p.';

// JavaScript
$lang['js']['loaderror']     = 'The H5P player could not be loaded.';
$lang['js']['initerror']     = 'The H5P content could not be displayed.';
$lang['js']['noaccess']      = 'You are not allowed to view this H5P content.';
$lang['js']['replace_btn']     = 'Replace H5P file';
$lang['js']['replace_confirm'] = 'Replace %s with the selected file? The previous version stays in the revision history of the media manager.';
$lang['js']['replace_working'] = 'Uploading file …';
$lang['js']['replace_done']    = 'The file has been replaced.';
$lang['js']['replace_reload']  = 'Reload page';
$lang['js']['replace_failed']  = 'Replacing failed.';
$lang['js']['confirmdelete'] = 'Really delete this library? Content requiring it will stop working.';
