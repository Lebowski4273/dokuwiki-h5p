<?php
/**
 * Standalone self test for the Store class (no DokuWiki needed)
 *
 * Run: php _test/selftest.php
 */

require_once __DIR__ . '/../H5PException.php';
require_once __DIR__ . '/../Store.php';

use dokuwiki\plugin\h5p\Store;

$failures = 0;
function check(string $label, bool $ok): void
{
    global $failures;
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . "\n";
    if (!$ok) $failures++;
}

function makeZip(string $file, array $entries): void
{
    $zip = new ZipArchive();
    $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($entries as $name => $content) {
        if (substr($name, -1) === '/') {
            $zip->addEmptyDir(rtrim($name, '/'));
        } else {
            $zip->addFromString($name, $content);
        }
    }
    $zip->close();
}

function lib(string $name, int $maj, int $min, int $patch, array $deps = []): string
{
    return json_encode([
        'title' => $name, 'machineName' => $name, 'majorVersion' => $maj, 'minorVersion' => $min,
        'patchVersion' => $patch, 'runnable' => 1, 'preloadedDependencies' => $deps,
    ]);
}

$work = sys_get_temp_dir() . '/h5p-selftest-' . bin2hex(random_bytes(4));
mkdir($work);
$store = new Store($work . '/store');

$h5pJson = json_encode([
    'title' => 'Test', 'mainLibrary' => 'H5P.Foo',
    'preloadedDependencies' => [['machineName' => 'H5P.Foo', 'majorVersion' => '1', 'minorVersion' => '2']],
]);
$full = $work . '/full.h5p';
makeZip($full, [
    'h5p.json' => $h5pJson,
    'content/' => '',
    'content/content.json' => '{"question":"?"}',
    'content/images/a.png' => 'PNG',
    'content/evil.php' => '<?php echo 1;',
    'H5P.Foo-1.2/library.json' => lib('H5P.Foo', 1, 2, 3, [['machineName' => 'H5P.Bar', 'majorVersion' => 1, 'minorVersion' => 0]]),
    'H5P.Foo-1.2/foo.js' => 'var foo;',
    'H5P.Foo-1.2/evil.php' => '<?php',
    'H5P.Bar-1.0/library.json' => lib('H5P.Bar', 1, 0, 5),
    'H5P.Bar-1.0/bar.css' => '.bar{}',
    '__MACOSX/._foo' => 'x',
]);

echo "readPackage\n";
$pkg = $store->readPackage($full);
check('two libraries found', count($pkg['libraries']) === 2);
check('dependency normalized to int', $pkg['h5p']['preloadedDependencies'][0]['majorVersion'] === 1);

echo "missingDependencies\n";
$missing = $store->missingDependencies($pkg['h5p']['preloadedDependencies']);
check('Foo missing before install', $missing === ['H5P.Foo 1.2']);
$missing = $store->missingDependencies($pkg['h5p']['preloadedDependencies'], $store->packageLibraries($pkg));
check('nothing missing when package libs count', $missing === []);

echo "installLibraries\n";
$report = $store->installLibraries($full);
check('two installed', count($report['installed']) === 2);
check('library js extracted', is_file($store->getLibraryDir() . '/H5P.Foo-1.2/foo.js'));
check('php not extracted', !file_exists($store->getLibraryDir() . '/H5P.Foo-1.2/evil.php'));
check('index file touched', is_file($store->getIndexFile()));
check('no deps missing after install', $store->missingDependencies($pkg['h5p']['preloadedDependencies']) === []);
$report = $store->installLibraries($full);
check('second install skips', count($report['skipped']) === 2 && !$report['installed']);
check('getDependents', $store->getDependents('H5P.Bar-1.0') === ['H5P.Foo-1.2']);

echo "patch update\n";
$newer = $work . '/newer.h5p';
makeZip($newer, [
    'h5p.json' => $h5pJson,
    'content/content.json' => '{}',
    'H5P.Bar-1.0/library.json' => lib('H5P.Bar', 1, 0, 7),
    'H5P.Bar-1.0/bar2.css' => '.bar2{}',
]);
$report = $store->installLibraries($newer);
check('higher patch updates', $report['updated'] === ['H5P.Bar 1.0.7']);
check('old files replaced', !file_exists($store->getLibraryDir() . '/H5P.Bar-1.0/bar.css'));
check('library list', array_keys($store->getLibraries()) === ['H5P.Bar-1.0', 'H5P.Foo-1.2']);

echo "prepareContent\n";
$key = md5('ns:test.h5p');
$dir = $store->prepareContent($key, $full);
check('h5p.json extracted', is_file($dir . '/h5p.json'));
check('content.json extracted', is_file($dir . '/content/content.json'));
check('image extracted', is_file($dir . '/content/images/a.png'));
check('php in content skipped', !file_exists($dir . '/content/evil.php'));
check('libraries not in content dir', !file_exists($dir . '/H5P.Foo-1.2'));
$mtime = filemtime($dir . '/.source');
sleep(1);
$store->prepareContent($key, $full);
check('unchanged package not re-extracted', filemtime($dir . '/.source') === $mtime);
check('contentDependencies', $store->contentDependencies($dir)[0]['machineName'] === 'H5P.Foo');

echo "stripPackage\n";
$copy = $work . '/copy.h5p';
copy($full, $copy);
$removed = $store->stripPackage($copy);
$z = new ZipArchive();
$z->open($copy);
$names = [];
for ($i = 0; $i < $z->numFiles; $i++) $names[] = $z->getNameIndex($i);
$z->close();
sort($names);
check('removed entries > 0', $removed > 0);
check('only h5p.json and content left',
    $names === ['content/', 'content/content.json', 'content/evil.php', 'content/images/a.png', 'h5p.json']);
$pkg2 = $store->readPackage($copy);
check('stripped package still valid, no libs', $pkg2['libraries'] === []);
touch($copy, time() + 5);
$dir2 = $store->prepareContent(md5('copy'), $copy);
check('stripped package renders content', is_file($dir2 . '/content/content.json'));

echo "resolveFile\n";
$libExt = Store::libraryExtensions();
check('library js resolves', $store->resolveFile($store->getLibraryDir(), 'H5P.Foo-1.2/foo.js', $libExt) !== null);
check('traversal blocked', $store->resolveFile($store->getLibraryDir(), '../content/' . $key . '/h5p.json', $libExt) === null);
check('hidden blocked', $store->resolveFile($dir, '.source', ['source']) === null);
check('wrong extension blocked', $store->resolveFile($dir, 'content/content.json', ['png']) === null);
check('content json resolves', $store->resolveFile($dir, 'content/content.json', Store::contentExtensions()) !== null);

echo "malicious packages\n";
$evil = $work . '/evil.h5p';
makeZip($evil, ['h5p.json' => $h5pJson, 'content/content.json' => '{}', '../../escape.txt' => 'x']);
try {
    $store->readPackage($evil);
    check('zip slip rejected', false);
} catch (RuntimeException $e) {
    check('zip slip rejected', true);
}
check('nothing escaped', !file_exists(dirname($work) . '/escape.txt'));

$nojson = $work . '/nojson.h5p';
makeZip($nojson, ['content/content.json' => '{}']);
try {
    $store->readPackage($nojson);
    check('missing h5p.json rejected', false);
} catch (RuntimeException $e) {
    check('missing h5p.json rejected', true);
}

$notzip = $work . '/notzip.h5p';
file_put_contents($notzip, 'hello');
try {
    $store->readPackage($notzip);
    check('non-zip rejected', false);
} catch (RuntimeException $e) {
    check('non-zip rejected', true);
}

$small = new Store($work . '/small', 10);
try {
    $small->prepareContent($key, $full);
    check('size limit enforced', false);
} catch (RuntimeException $e) {
    check('size limit enforced', true);
}
check('no temp dirs left', count(glob($work . '/small/content/.tmp-*')) === 0);

echo "deleteLibrary\n";
$store->deleteLibrary('H5P.Bar-1.0');
check('library deleted', !is_dir($store->getLibraryDir() . '/H5P.Bar-1.0'));
check('now missing again', $store->missingDependencies($pkg['h5p']['preloadedDependencies']) === ['H5P.Bar 1.0']);
try {
    $store->deleteLibrary('../content');
    check('invalid delete rejected', false);
} catch (RuntimeException $e) {
    check('invalid delete rejected', true);
}

echo "content-only package (like downloads from h5p.org)\n";
$aq = $work . '/aq.h5p';
makeZip($aq, [
    'content/content.json' => '{"arithmeticType":"multiplication"}',
    'h5p.json' => json_encode(['title' => 'Arithmetic Quiz', 'mainLibrary' => 'H5P.ArithmeticQuiz',
        'preloadedDependencies' => [['machineName' => 'H5P.ArithmeticQuiz', 'majorVersion' => '1', 'minorVersion' => '1']]]),
]);
$pkgAq = $store->readPackage($aq);
check('readable without libraries', $pkgAq['libraries'] === []);
check('reports missing main library', $store->missingDependencies($pkgAq['h5p']['preloadedDependencies']) === ['H5P.ArithmeticQuiz 1.1']);
$report = $store->installLibraries($aq);
check('installing from it is a no-op', !$report['installed'] && !$report['updated'] && !$report['skipped']);
check('content can still be prepared', is_file($store->prepareContent(md5('aq'), $aq) . '/content/content.json'));

echo "zip of library folders (e.g. from Lumi), nested in a parent folder\n";
$libzip = $work . '/libs.zip';
makeZip($libzip, [
    'libraries/H5P.ArithmeticQuiz-1.1/library.json' => lib('H5P.ArithmeticQuiz', 1, 1, 4,
        [['machineName' => 'H5P.JoubelUI', 'majorVersion' => 1, 'minorVersion' => 3]]),
    'libraries/H5P.ArithmeticQuiz-1.1/dist/aq.js' => 'aq',
    'libraries/H5P.JoubelUI-1.3/library.json' => lib('H5P.JoubelUI', 1, 3, 9),
    'libraries/H5P.JoubelUI-1.3/js/ui.js' => 'ui',
    'libraries/readme.txt' => 'not a library',
]);
$report = $store->installLibraries($libzip);
check('both libraries installed from plain zip', count($report['installed']) === 2);
check('subfolder files kept', is_file($store->getLibraryDir() . '/H5P.ArithmeticQuiz-1.1/dist/aq.js'));
check('content-only package now complete', $store->missingDependencies($pkgAq['h5p']['preloadedDependencies']) === []);

echo "single library at zip root (e.g. GitHub repository zip)\n";
$rootzip = $work . '/root.zip';
makeZip($rootzip, [
    'library.json' => lib('H5P.Root', 2, 0, 1),
    'root.js' => 'root',
    'semantics.json' => '[]',
]);
$report = $store->installLibraries($rootzip);
check('root library installed', $report['installed'] === ['H5P.Root 2.0.1']);
check('root library files', is_file($store->getLibraryDir() . '/H5P.Root-2.0/root.js'));

echo "library zip with a library nested inside another folder\n";
$nestzip = $work . '/nest.zip';
makeZip($nestzip, [
    'library.json' => lib('H5P.Outer', 1, 0, 0),
    'outer.js' => 'o',
    'H5P.Inner-1.0/library.json' => lib('H5P.Inner', 1, 0, 0),
    'H5P.Inner-1.0/inner.js' => 'i',
]);
$store->installLibraries($nestzip);
check('nested library separate', is_file($store->getLibraryDir() . '/H5P.Inner-1.0/inner.js'));
check('nested library not copied into outer', !file_exists($store->getLibraryDir() . '/H5P.Outer-1.0/H5P.Inner-1.0'));

echo "minor fallback\n";
$needOld = [['machineName' => 'H5P.JoubelUI', 'majorVersion' => 1, 'minorVersion' => 2]];
check('older minor missing without fallback', $store->missingDependencies($needOld) === ['H5P.JoubelUI 1.2']);
check('library file not served without fallback', $store->resolveLibraryFile('H5P.JoubelUI-1.2/js/ui.js') === null);
$store->setMinorFallback(true);
check('higher minor used with fallback', $store->missingDependencies($needOld) === []);
check('library file served from 1.3', strpos((string)$store->resolveLibraryFile('H5P.JoubelUI-1.2/js/ui.js'), 'H5P.JoubelUI-1.3/js/ui.js') !== false);
check('newer minor never replaced by older', $store->findLibraryFolder('H5P.JoubelUI', 1, 4) === null);
check('other major not used', $store->findLibraryFolder('H5P.JoubelUI', 2, 0) === null);
check('traversal still blocked with fallback', $store->resolveLibraryFile('H5P.JoubelUI-1.2/../../content/x.json') === null);
$store->setMinorFallback(false);

echo "content cleanup\n";
$keyA = md5('ns:a.h5p');
$keyB = md5('ns:b.h5p');
$store->prepareContent($keyA, $aq);
$store->prepareContent($keyB, $aq);
mkdir($store->getContentBaseDir() . '/.tmp-stale');
touch($store->getContentBaseDir() . '/.tmp-stale', time() - 7200);
mkdir($store->getContentBaseDir() . '/.tmp-fresh');
check('deleteContent removes one', $store->deleteContent($keyA) && !is_dir($store->getContentBaseDir() . '/' . $keyA));
check('deleteContent rejects bad key', $store->deleteContent('../libraries') === false);
check('libraries untouched', is_dir($store->getLibraryDir() . '/H5P.JoubelUI-1.3'));
$removed = $store->cleanupContent([$keyB]);
check('kept content survives cleanup', is_dir($store->getContentBaseDir() . '/' . $keyB));
check('orphans removed', $removed > 0 && !is_dir($store->getContentBaseDir() . '/' . md5('aq')));
check('stale temp dir removed', !is_dir($store->getContentBaseDir() . '/.tmp-stale'));
check('fresh temp dir kept (extraction may be running)', is_dir($store->getContentBaseDir() . '/.tmp-fresh'));

echo "sub-content libraries missing in h5p.json (Interactive Video case)\n";
$iv = $work . '/iv.h5p';
makeZip($iv, [
    'h5p.json' => json_encode(['title' => 'Video', 'mainLibrary' => 'H5P.JoubelUI',
        'preloadedDependencies' => [['machineName' => 'H5P.JoubelUI', 'majorVersion' => 1, 'minorVersion' => 3]]]),
    'content/content.json' => json_encode(['interactiveVideo' => ['assets' => ['interactions' => [
        ['action' => ['library' => 'H5P.Text 1.1', 'params' => ['text' => 'hi']]],
        ['action' => ['library' => 'H5P.JoubelUI 1.3', 'params' => []]],
        ['action' => ['library' => 'H5P.Text 1.1', 'params' => []]],
        ['label' => ['library' => 'not a library']],
    ]]]]),
]);
check('package dependencies include sub-content', $store->missingDependencies($store->packageDependencies($iv)) === ['H5P.Text 1.1']);
$ivDir = $store->prepareContent(md5('iv'), $iv);
$ivJson = json_decode(file_get_contents($ivDir . '/h5p.json'), true);
$names = array_map(static fn($d) => $d['machineName'] . ' ' . $d['majorVersion'] . '.' . $d['minorVersion'], $ivJson['preloadedDependencies']);
check('h5p.json completed once, order kept', $names === ['H5P.JoubelUI 1.3', 'H5P.Text 1.1']);
check('other h5p.json fields kept', $ivJson['title'] === 'Video');
check('render check sees missing sub-content', $store->missingDependencies($store->contentDependencies($ivDir)) === ['H5P.Text 1.1']);
$z = new ZipArchive();
$z->open($iv);
check('original package unchanged', strpos($z->getFromName('h5p.json'), 'H5P.Text') === false);
$z->close();
file_put_contents($ivDir . '/.source', '1:2:3'); // old stamp format
$store->prepareContent(md5('iv'), $iv);
check('old extractions are regenerated', strpos(file_get_contents($ivDir . '/.source'), Store::EXTRACT_VERSION . ':') === 0);

// cleanup
exec('rm -rf ' . escapeshellarg($work));

echo $failures ? "\n$failures test(s) FAILED\n" : "\nall tests passed\n";
exit($failures ? 1 : 0);
