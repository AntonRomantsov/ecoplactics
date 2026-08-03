<?php
/**
 * One-off diagnostic to see exactly why the Level 4 README panel in the
 * "check instructions" modal renders empty. Replicates the same file-read
 * logic the OCMOD-patched home.php controller is supposed to run, plus
 * reports whether the OCMOD modification is actually the currently
 * compiled one. Safe to delete once the modal works end-to-end.
 */

header('Content-Type: text/plain; charset=utf-8');

require __DIR__ . '/config.php';

echo "DIR_APPLICATION: " . DIR_APPLICATION . "\n";
echo "Project root (dirname(DIR_APPLICATION)): " . dirname(DIR_APPLICATION) . "\n\n";

$files = array(
	'check-instructions.txt',
	'check-instructions-level4-readme.txt',
);

foreach ($files as $name) {
	$path = dirname(DIR_APPLICATION) . '/' . $name;
	if (!is_file($path)) {
		echo "MISSING: $name ($path)\n";
		continue;
	}
	$content = file_get_contents($path);
	echo "OK: $name (size=" . strlen($content) . " bytes)\n";
	echo "--- first 200 chars ---\n";
	echo substr($content, 0, 200) . "\n\n";
}

// Check whether the OCMOD modification has actually been compiled and what
// it currently contains for home.php / home.twig.
$modCachePhp  = DIR_MODIFICATION . 'catalog/controller/common/home.php';
$modCacheTwig = DIR_MODIFICATION . 'catalog/view/theme/default/template/common/home.twig';

echo "=== Compiled OCMOD cache ===\n";
foreach (array('home.php' => $modCachePhp, 'home.twig' => $modCacheTwig) as $label => $path) {
	if (!is_file($path)) {
		echo "$label: NOT COMPILED (file does not exist at $path)\n";
		continue;
	}
	$content = file_get_contents($path);
	echo "$label: compiled, size=" . strlen($content) . " bytes, modified=" . date('Y-m-d H:i:s', filemtime($path)) . "\n";
	echo "  contains 'check_readme_level4': " . (strpos($content, 'check_readme_level4') !== false ? 'YES' : 'NO') . "\n";
	echo "  contains 'check_instructions': " . (strpos($content, 'check_instructions') !== false ? 'YES' : 'NO') . "\n";
	echo "  contains 'ecp-check-readme4': " . (strpos($content, 'ecp-check-readme4') !== false ? 'YES' : 'NO') . "\n";
}
