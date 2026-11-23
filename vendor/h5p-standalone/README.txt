Hierher gehört der Inhalt des dist-Ordners von h5p-standalone
(https://github.com/tunapanda/h5p-standalone, MIT-Lizenz).

Benötigt werden mindestens:
  main.bundle.js
  frame.bundle.js
  styles/h5p.css
  (sowie die übrigen Dateien aus dist, z. B. fonts/)

Beispiel:
  npm pack h5p-standalone
  tar xzf h5p-standalone-*.tgz
  cp -r package/dist/* lib/plugins/h5p/vendor/h5p-standalone/
