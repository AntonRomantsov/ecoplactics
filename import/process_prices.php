<?php
/**
 * Level 2, part 1 — updates prices in Import.xlsx from price.xlsx (a messy
 * multi-sheet vendor price list), computes old_price = price + 10%, and
 * highlights every touched row: one color if the price actually changed,
 * a different color if the matched price is identical to what was already
 * there. Ported from an earlier attempt at this same assignment, which
 * matched products correctly but painted every matched row the same single
 * color regardless of whether the price had changed - that gap is fixed
 * here by reading the existing price before it gets overwritten.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

if (php_sapi_name() !== 'cli') {
	header('Content-Type: text/plain; charset=utf-8');
}

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

ini_set('memory_limit', '512M');
set_time_limit(600);

// Colors: green = price changed, amber = price already up to date.
const COLOR_CHANGED  = 'FF90EE90';
const COLOR_IDENTICAL = 'FFFFE699';

$importPath = __DIR__ . '/../Import.xlsx';
$updatedPath = __DIR__ . '/Import_updated.xlsx';

// The price file's name has Cyrillic characters, which FTP clients can mangle
// in transit (Windows/Linux disagree on Unicode normalization for filenames),
// so a hardcoded exact name is fragile. Auto-detect it instead: any .xlsx in
// the project root that isn't Import.xlsx itself.
$pricePath = null;
foreach (glob(__DIR__ . '/../*.xlsx') as $candidate) {
	$name = basename($candidate);
	if ($name !== 'Import.xlsx' && strpos($name, '~$') !== 0) {
		$pricePath = $candidate;
		break;
	}
}
if ($pricePath === null) {
	throw new \RuntimeException('Price .xlsx file not found in the project root (looked for any .xlsx next to Import.xlsx).');
}

// ── 1. Load Import.xlsx, detect columns by header text ──────────────────────
$importBook  = IOFactory::load($importPath);
$importSheet = $importBook->getActiveSheet();
$maxRow      = $importSheet->getHighestRow();
$maxCol      = $importSheet->getHighestColumn();

$articleCol  = 'B';
$priceCol    = 'G';
$oldPriceCol = 'H';
$nameCol     = null;

$headerRow = $importSheet->rangeToArray("A1:{$maxCol}1", null, true, true, true)[1];
foreach ($headerRow as $letter => $val) {
	switch (mb_strtolower(trim((string)$val))) {
		case 'артикул':                                             $articleCol  = $letter; break;
		case 'цена': case 'ціна':                                  $priceCol    = $letter; break;
		case 'старая цена': case 'стара ціна':                     $oldPriceCol = $letter; break;
		case 'название': case 'наименование': case 'назва':
		case 'найменування': case 'name': case 'номенклатура':
		case 'товар': case 'наименование товара':                  $nameCol     = $letter; break;
	}
}

// ── 2. Build lookup maps: article => row, normalized article => row, name => article ──
$articles     = array(); // article (original) => row
$normArticles = array(); // normalized article => ['art' => original, 'row' => int]
$artsByLength = array(); // length => [normArt, ...] (for the Levenshtein filter)
$nameMap      = array(); // normalized name => ['art' => str, 'row' => int]

for ($row = 2; $row <= $maxRow; $row++) {
	$art = trim((string)$importSheet->getCell($articleCol . $row)->getValue());
	if ($art !== '') {
		$articles[$art] = $row;
		$norm = normalizeArticle($art);
		if (!isset($normArticles[$norm])) {
			$normArticles[$norm] = array('art' => $art, 'row' => $row);
			$artsByLength[mb_strlen($norm)][] = $norm;
		}
	}

	if ($nameCol !== null) {
		$name = trim((string)$importSheet->getCell($nameCol . $row)->getValue());
		if (mb_strlen($name) >= 4) {
			$normName = normalizeName($name);
			if (!isset($nameMap[$normName])) {
				$nameMap[$normName] = array('art' => $art, 'row' => $row);
			}
		}
	}
}

// Word index for fast fuzzy-match candidate filtering: word (>=4 chars) => [normName, ...]
$wordIndex = array();
foreach ($nameMap as $normName => $_) {
	foreach (array_unique(explode(' ', $normName)) as $word) {
		if (mb_strlen($word) >= 4) {
			$wordIndex[$word][] = $normName;
		}
	}
}

// ── 3. Scan every sheet of the price file ────────────────────────────────────
$priceBook = IOFactory::load($pricePath);
$priceMap  = array(); // art => matched price
$matchedBy = array(); // art => 'article' | 'article_norm' | 'article_fuzzy' | 'name_exact' | 'name_fuzzy'

foreach ($priceBook->getSheetNames() as $sheetName) {
	$sheet   = $priceBook->getSheetByName($sheetName);
	$lastRow = $sheet->getHighestRow();
	$lastCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

	for ($r = 1; $r <= $lastRow; $r++) {
		$cells = array();
		for ($c = 1; $c <= $lastCol; $c++) {
			$cells[$c] = $sheet->getCell(Coordinate::stringFromColumnIndex($c) . $r)->getValue();
		}

		$foundArt = null;
		$foundCol = null;
		$foundHow = null;

		// Pass 1: match by article (exact -> normalized -> Levenshtein <= 1)
		foreach ($cells as $c => $val) {
			$str = trim((string)$val);
			if ($str === '') continue;

			if (isset($articles[$str]) && !isset($priceMap[$str])) {
				$foundArt = $str; $foundCol = $c; $foundHow = 'article';
				break;
			}

			$normStr = normalizeArticle($str);
			if (isset($normArticles[$normStr])) {
				$info = $normArticles[$normStr];
				if (!isset($priceMap[$info['art']])) {
					$foundArt = $info['art']; $foundCol = $c; $foundHow = 'article_norm';
					break;
				}
			}

			$normLen = mb_strlen($normStr);
			if ($normLen >= 5) {
				$levBest = null; $levBestDist = PHP_INT_MAX;
				for ($dl = -1; $dl <= 1; $dl++) {
					$checkLen = $normLen + $dl;
					if ($checkLen < 1 || !isset($artsByLength[$checkLen])) continue;
					foreach ($artsByLength[$checkLen] as $candidate) {
						$dist = levenshtein($normStr, $candidate);
						if ($dist <= 1 && $dist < $levBestDist) {
							$levBestDist = $dist; $levBest = $candidate;
						}
					}
				}
				if ($levBest !== null) {
					$info = $normArticles[$levBest];
					if (!isset($priceMap[$info['art']])) {
						$foundArt = $info['art']; $foundCol = $c; $foundHow = 'article_fuzzy';
						break;
					}
				}
			}
		}

		// Pass 2: match by name (only if no article matched)
		if ($foundArt === null && $nameCol !== null) {
			$bestArt = null; $bestScore = 0.0; $bestCol = null; $bestHow = null;

			foreach ($cells as $c => $val) {
				$str = trim((string)$val);
				if (mb_strlen($str) < 4 || isNumericLike($str)) continue;

				$norm = normalizeName($str);
				if ($norm === '') continue;

				if (isset($nameMap[$norm])) {
					$info = $nameMap[$norm];
					if (!isset($priceMap[$info['art']])) {
						$bestArt = $info['art']; $bestScore = 1.0; $bestCol = $c; $bestHow = 'name_exact';
					}
					break;
				}

				$candidates = array();
				foreach (array_unique(explode(' ', $norm)) as $word) {
					if (mb_strlen($word) >= 4 && isset($wordIndex[$word])) {
						foreach ($wordIndex[$word] as $cName) {
							$candidates[$cName] = (isset($candidates[$cName]) ? $candidates[$cName] : 0) + 1;
						}
					}
				}
				arsort($candidates);

				foreach ($candidates as $cName => $_) {
					similar_text($norm, $cName, $pct);
					if ($pct > $bestScore && $pct >= 75.0) {
						$info = $nameMap[$cName];
						if (!isset($priceMap[$info['art']])) {
							$bestScore = $pct; $bestArt = $info['art']; $bestCol = $c; $bestHow = 'name_fuzzy';
						}
					}
				}
			}

			if ($bestArt !== null) {
				$foundArt = $bestArt; $foundCol = $bestCol; $foundHow = $bestHow;
			}
		}

		if ($foundArt === null) continue;

		// Price = the last plausible number in the row, excluding the matched cell.
		$price = null;
		foreach ($cells as $c => $val) {
			if ($c === $foundCol) continue;
			$n = toFloat($val);
			if ($n !== null && $n > 10 && $n < 1000000) {
				$price = $n;
			}
		}

		if ($price !== null && !isset($priceMap[$foundArt])) {
			$priceMap[$foundArt] = $price;
			$matchedBy[$foundArt] = $foundHow;
		}
	}
}

// ── 4. Update Import.xlsx rows, highlighting changed vs identical prices ────
$changed = 0;
$identical = 0;
$log = array();

foreach ($priceMap as $art => $newPrice) {
	$row = $articles[$art];
	$newPrice = (int)round($newPrice);
	$oldPrice = (int)round($newPrice * 1.10);

	$existingPrice = $importSheet->getCell($priceCol . $row)->getValue();
	$existingPrice = is_numeric($existingPrice) ? (int)round((float)$existingPrice) : null;
	$isChanged = ($existingPrice !== $newPrice);

	$importSheet->setCellValue($priceCol . $row, $newPrice);
	$importSheet->setCellValue($oldPriceCol . $row, $oldPrice);

	$fillColor = $isChanged ? COLOR_CHANGED : COLOR_IDENTICAL;
	$importSheet->getStyle("A{$row}:{$maxCol}{$row}")
		->getFill()
		->setFillType(Fill::FILL_SOLID)
		->getStartColor()->setARGB($fillColor);

	if ($isChanged) {
		$changed++;
	} else {
		$identical++;
	}

	$log[] = array(
		'article'       => $art,
		'existing_price' => $existingPrice,
		'new_price'     => $newPrice,
		'old_price'     => $oldPrice,
		'changed'       => $isChanged,
		'matched_by'    => isset($matchedBy[$art]) ? $matchedBy[$art] : 'article'
	);
}

// ── 5. Save ───────────────────────────────────────────────────────────────
IOFactory::createWriter($importBook, 'Xlsx')->save($updatedPath);

echo "Готово.\n";
echo "Оброблено рядків з ціною в прайсі: " . count($priceMap) . "\n";
echo "  - Змінено ціну (" . COLOR_CHANGED . "): $changed\n";
echo "  - Ціна без змін (" . COLOR_IDENTICAL . "): $identical\n";
echo "Не знайдено відповідності в прайсі: " . (count($articles) - count($priceMap)) . " товарів (лишились без кольору)\n";
echo "Результат: $updatedPath\n";

file_put_contents(__DIR__ . '/process_prices.log.json', json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// ── Helpers ───────────────────────────────────────────────────────────────

function normalizeArticle($s) {
	$s = mb_strtolower(trim($s));
	$s = preg_replace('/[\s\-_.\/()\[\]]+/', '', $s);
	return $s;
}

function normalizeName($s) {
	$s = mb_strtolower($s);
	$s = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $s);
	return trim(preg_replace('/\s+/', ' ', $s));
}

function isNumericLike($s) {
	return (bool)preg_match('/^[\d\s.,\/\-]+$/', $s);
}

function toFloat($val) {
	if ($val === null || $val === '') return null;
	if (is_int($val) || is_float($val)) return abs((float)$val);

	$s = preg_replace('/[^\d.,]/', '', trim((string)$val));
	if ($s === '') return null;

	if (strpos($s, ',') !== false && strpos($s, '.') !== false) {
		$s = strrpos($s, '.') > strrpos($s, ',')
			? str_replace(',', '', $s)
			: str_replace(array('.', ','), array('', '.'), $s);
	} elseif (strpos($s, ',') !== false) {
		$parts = explode(',', $s);
		$s = (count($parts) === 2 && strlen(end($parts)) === 3)
			? str_replace(',', '', $s)
			: str_replace(',', '.', $s);
	}

	return is_numeric($s) ? abs((float)$s) : null;
}
