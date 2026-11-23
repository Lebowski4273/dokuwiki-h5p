<?php
/**
 * German language file for the h5p plugin (formal, "Sie")
 */

$lang['menu'] = 'H5P-Bibliotheken';

// page output
$lang['noplayer'] = 'Der H5P-Player fehlt. Die Dateien von h5p-standalone müssen nach lib/plugins/h5p/vendor/h5p-standalone/ kopiert werden (siehe README).';
$lang['broken']   = 'Der H5P-Inhalt %s kann nicht angezeigt werden: %s';
$lang['missing']  = 'Für diesen H5P-Inhalt fehlen Bibliotheken: %s. Ein Manager kann sie unter Administration → H5P-Bibliotheken installieren.';
$lang['noscript'] = 'Für H5P-Inhalte wird JavaScript benötigt.';

// upload
$lang['upload_invalid']     = 'Die H5P-Datei ist ungültig: %s';
$lang['upload_missing']     = 'Hinweis: Für diese H5P-Datei fehlen noch Bibliotheken: %s. Ein Manager kann sie unter Administration → H5P-Bibliotheken installieren.';
$lang['upload_installed']   = 'H5P-Bibliotheken installiert: %s';
$lang['upload_updated']     = 'H5P-Bibliotheken aktualisiert: %s';
$lang['upload_postprocess'] = 'Die H5P-Datei wurde hochgeladen, die Nachbearbeitung ist aber fehlgeschlagen: %s';

// admin
$lang['admin_player_ok']   = 'Der H5P-Player ist installiert.';
$lang['admin_storage']     = 'Speicherort: %s';
$lang['admin_install']     = 'Bibliotheken aus einer Datei installieren';
$lang['admin_install_intro'] = 'Möglich sind H5P-Dateien mit Bibliotheken sowie ZIP-Dateien mit Bibliotheksordnern (jeder Ordner mit einer library.json), z. B. aus dem Bibliotheksordner von Lumi oder Moodle.';
$lang['admin_install_btn'] = 'Installieren';
$lang['admin_nofile']      = 'Bitte eine H5P-Datei auswählen.';
$lang['admin_nolibs']      = 'Die Datei enthält keine Bibliotheken.';
$lang['admin_installed']   = 'Installiert: %s';
$lang['admin_updated']     = 'Aktualisiert: %s';
$lang['admin_skipped']     = 'Bereits aktuell: %s';
$lang['admin_deleted']     = 'Bibliothek %s gelöscht.';
$lang['admin_error']       = 'Fehler: %s';
$lang['admin_list']        = 'Installierte Bibliotheken (%d)';
$lang['admin_empty']       = 'Es sind noch keine Bibliotheken installiert.';
$lang['admin_col_title']   = 'Titel';
$lang['admin_col_name']    = 'Maschinenname';
$lang['admin_col_version'] = 'Version';
$lang['admin_col_usedby']  = 'Benötigt von';
$lang['admin_delete']      = 'Löschen';
$lang['admin_fallback_on']     = 'Minor-Fallback ist aktiv: Fehlende Versionen werden durch höhere Minor-Versionen ersetzt.';
$lang['admin_missing']         = 'Fehlende Bibliotheken';
$lang['admin_missing_none']    = 'Alle %d H5P-Dateien im Medienmanager haben ihre Bibliotheken.';
$lang['admin_col_library']     = 'Bibliothek (verlangte Version)';
$lang['admin_col_files']       = 'Benötigt von';
$lang['admin_col_hub']         = 'Version im Hub';
$lang['admin_version_differs'] = 'abweichend von %s: Inhalt in Lumi aktualisieren oder Minor-Fallback einschalten';
$lang['admin_not_on_hub']      = 'nicht im Hub';
$lang['admin_broken']          = 'Diese H5P-Dateien sind fehlerhaft:';
$lang['hub_title']             = 'Inhaltstyp vom H5P-Hub installieren';
$lang['hub_intro']             = 'Lädt die neueste Version eines Inhaltstyps mit allen benötigten Bibliotheken von api.h5p.org. Der Server verbindet sich nur, wenn Sie hier einen Knopf drücken.';
$lang['hub_install_btn']       = 'Vom Hub installieren';
$lang['hub_install_all_btn']   = 'Alle fehlenden vom Hub installieren';
$lang['hub_load_btn']          = 'Liste der Inhaltstypen vom Hub laden';
$lang['hub_refresh_btn']       = 'Liste aktualisieren';
$lang['hub_refreshed']         = '%d Inhaltstypen vom Hub geladen.';
$lang['hub_installed']         = 'installiert: %s';
$lang['hub_cached']            = 'Stand der Liste: %s';
$lang['hub_failed']            = '%s konnte nicht vom Hub installiert werden: %s';
$lang['admin_cleanup']         = 'Aufräumen';
$lang['admin_cleanup_intro']   = 'Entpackte Inhalte gelöschter H5P-Dateien werden beim Löschen im Medienmanager automatisch entfernt. Dateien, die auf anderem Weg verschwunden sind (z. B. per FTP oder durch Verschieben), räumt dieser Knopf auf.';
$lang['admin_cleanup_btn']     = 'Verwaiste Inhalte löschen';
$lang['admin_cleaned']         = '%d verwaiste Inhalte gelöscht.';
$lang['admin_runnable']    = '= Inhaltstyp (kann direkt als H5P-Inhalt verwendet werden)';

// error messages (inserted into the messages above)
$lang['err_badkey']          = 'ungültige Inhaltskennung';
$lang['err_notfound']        = 'Datei nicht gefunden';
$lang['err_nocontent']       = 'content/content.json fehlt';
$lang['err_writepackage']    = 'die Datei konnte nicht geschrieben werden';
$lang['err_unknownlib']      = 'unbekannte Bibliothek %s';
$lang['err_notzip']          = 'keine gültige H5P-Datei (ZIP)';
$lang['err_toomany']         = 'die Datei enthält zu viele Einzeldateien';
$lang['err_unsafe']          = 'unsicherer Dateiname in der Datei: %s';
$lang['err_toolarge']        = 'die Datei ist entpackt zu groß (siehe Einstellung maxsize)';
$lang['err_extract']         = '%s konnte nicht entpackt werden';
$lang['err_storage']         = 'in das Speicherverzeichnis kann nicht geschrieben werden (%s)';
$lang['err_nomainlib']       = 'in h5p.json fehlt die Angabe mainLibrary';
$lang['err_badlibjson']      = '%s: ungültige Angabe %s';
$lang['err_nolibjson']       = '%s: library.json fehlt';
$lang['err_jsonmissing']     = '%s fehlt';
$lang['err_jsoninvalid']     = '%s ist kein gültiges JSON';
$lang['err_badname']         = 'ungültiger Maschinenname';
$lang['err_hub_answer']      = 'unerwartete Antwort vom H5P-Hub';
$lang['err_hub_save']        = 'der Download konnte nicht gespeichert werden';
$lang['err_hub_register']    = 'die Registrierung beim H5P-Hub ist fehlgeschlagen';
$lang['err_hub_unreachable'] = 'H5P-Hub nicht erreichbar: %s';
$lang['err_hub_nopackage']   = 'der H5P-Hub hat für %s kein Paket geliefert (%s)';

// replace button (server side)
$lang['replace_denied']    = 'Sie haben keine Berechtigung, diese Datei zu ersetzen.';
$lang['replace_notfound']  = 'Die zu ersetzende H5P-Datei wurde nicht gefunden.';
$lang['replace_nofile']    = 'Es wurde keine Datei empfangen. Möglicherweise ist sie größer, als der Server erlaubt (upload_max_filesize).';
$lang['replace_wrongtype'] = 'Bitte eine Datei mit der Endung .h5p auswählen.';

// JavaScript
$lang['js']['loaderror']     = 'Der H5P-Player konnte nicht geladen werden.';
$lang['js']['initerror']     = 'Der H5P-Inhalt konnte nicht angezeigt werden.';
$lang['js']['noaccess']      = 'Sie haben keine Berechtigung, diesen H5P-Inhalt anzusehen.';
$lang['js']['replace_btn']     = 'H5P-Datei ersetzen';
$lang['js']['replace_confirm'] = '%s durch die ausgewählte Datei ersetzen? Die bisherige Version bleibt in der Versionsgeschichte des Medienmanagers erhalten.';
$lang['js']['replace_working'] = 'Datei wird hochgeladen …';
$lang['js']['replace_done']    = 'Die Datei wurde ersetzt.';
$lang['js']['replace_reload']  = 'Seite neu laden';
$lang['js']['replace_failed']  = 'Das Ersetzen ist fehlgeschlagen.';
$lang['js']['confirmdelete'] = 'Bibliothek wirklich löschen? Inhalte, die sie benötigen, funktionieren danach nicht mehr.';
