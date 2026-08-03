<?php
/**
 * Level 2, part 2 — generates a YML (Rozetka/Prom-style) marketplace feed
 * from Import_updated.xlsx (the output of process_prices.php).
 *
 * Reads the workbook in row windows via a custom IReadFilter instead of
 * loading it whole, so memory stays flat regardless of catalog size: only
 * $xmlChunk rows are ever in memory at once, and each chunk's <offer>
 * fragments are streamed straight to a temp file with fwrite(). The temp
 * file is then block-copied into the final XML and removed. This is what
 * keeps the script viable for a 10 000+ product catalog on shared hosting.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(0);

require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

ini_set('memory_limit', '512M');
set_time_limit(600);

$isCli = (php_sapi_name() === 'cli');

set_exception_handler(function (\Throwable $e) use ($isCli) {
	if ($isCli) {
		fwrite(STDERR, 'Error: ' . $e->getMessage() . "\nFile: " . $e->getFile() . ':' . $e->getLine() . "\n");
		exit(1);
	}
	if (!headers_sent()) {
		header('Content-Type: text/plain; charset=utf-8');
	}
	echo 'Error: ' . $e->getMessage() . "\nFile: " . $e->getFile() . ':' . $e->getLine();
	exit(1);
});

class RangeFilter implements IReadFilter
{
	private $startRow;
	private $endRow;
	private $cols;

	public function __construct(int $startRow, int $endRow, ?array $cols = null)
	{
		$this->startRow = $startRow;
		$this->endRow   = $endRow;
		$this->cols     = $cols;
	}

	// No type hints here: PhpSpreadsheet 1.x's IReadFilter::readCell() declares
	// untyped parameters, and PHP requires an exact signature match for interfaces.
	public function readCell($columnAddress, $row, $worksheetName = '')
	{
		if ($this->cols !== null && !in_array($columnAddress, $this->cols, true)) {
			return false;
		}
		return $row === 1 || ($row >= $this->startRow && $row <= $this->endRow);
	}
}

$updatedPath = __DIR__ . '/Import_updated.xlsx';
$xmlPath     = __DIR__ . '/marketplace.xml';
$xmlChunk    = 200;

if (!is_file($updatedPath)) {
	throw new \RuntimeException("Import_updated.xlsx not found - run process_prices.php first.");
}

$shopName    = 'Ecoplastics';
$companyName = 'Ecoplastics';
$shopUrl     = 'https://ecoplastics.telbot-test.space';

$categoryNames     = array();
$manufacturerNames = array();

// Column headers (raw, as exported from OpenCart) => human-readable Ukrainian attribute labels.
$attrLabels = array(
	'Страна'                       => 'Країна виробника',
	'robochatemperatura'           => 'Робоча температура',
	'potuzhnistimetr'              => 'Питома потужність, Вт/м',
	'typnahrivalnohokabeliu'       => 'Тип нагрівального кабелю',
	'dovzhyna'                     => 'Довжина',
	'typustanovky'                 => 'Тип установки',
	'pryznachennia'                => 'Призначення',
	'harantii'                     => 'Гарантія',
	'typ'                          => 'Тип',
	'typmontazhu'                  => 'Тип монтажу',
	'robochanapruha'               => 'Робоча напруга',
	'vnutrishniaizoliatsiia'       => 'Внутрішня ізоляція',
	'zovnishniaizoliatsiia'        => 'Зовнішня ізоляція',
	'minimalnatemperaturamontazha' => 'Мінімальна температура монтажу',
	'tovshchynamata'               => 'Товщина мата',
	'diametrkabeliu'               => 'Діаметр кабелю',
	'dovginakabelu'                => 'Довжина кабелю',
	'ploshcha'                     => 'Площа обігріву',
	'zahalnapotuzhnist'            => 'Загальна потужність',
	'krokvidrizu'                  => 'Крок відрізу',
	'typtermostata'                => 'Тип термостата',
	'potuzhnistnavantazhennia'     => 'Потужність навантаження',
	'materialstrichky'             => 'Матеріал стрічки',
	'pokryttia'                    => 'Покриття',
	'sposibvyhotovlennia'          => 'Спосіб виготовлення',
	'shyrynastrichky'              => 'Ширина стрічки',
	'moschnost'                    => 'Потужність',
	'vaga'                         => 'Вага',
	'material'                     => 'Матеріал',
	'sezon'                        => 'Сезон',
	'temperaturakomforta'          => 'Температура комфорту',
	'tehnologia'                   => 'Технологія',
	'kolportov'                    => 'Кількість портів',
	'porty'                        => 'Порти',
	'kilkistfaz'                   => 'Кількість фаз',
	'invertornyi'                  => 'Інверторний',
	'obmotka'                      => 'Обмотка',
	'chystyisynus'                 => 'Чистий синус',
	'nominalnapotuzhnist'          => 'Номінальна потужність',
	'maksymalnapotuzhnist'         => 'Максимальна потужність',
	'materialopory'                => 'Матеріал опори',
	'materialstilnytsi'            => 'Матеріал стільниці',
	'reguluvannyavysoti'           => 'Регулювання висоти',
	'sposibreguluvannya'           => 'Спосіб регулювання',
	'rozmirstilnutsi'              => 'Розмір стільниці',
	'rozmirramy'                   => 'Розмір рами',
	'kolirstolnytsi'               => 'Колір стільниці',
	'koliropory'                   => 'Колір опори',
	'visota'                       => 'Висота',
	'komplektatsiia'               => 'Комплектація',
	'obiem'                        => "Об'єм",
	'vh_tok'                       => 'Вхідний струм',
	'vsh-tok'                      => 'Вихідний струм',
	'emkost'                       => 'Ємність',
	'uteplitel'                    => 'Утеплювач',
	'materialnaruzhnohosloya'      => 'Матеріал зовнішнього шару',
	'koltsiklow'                   => 'Кількість циклів',
	'zaryadka'                     => 'Зарядка',
);

// Columns that describe the product itself rather than a filterable spec - never emitted as <param>.
$skipAsAttr = array_flip(array(
	'ID', 'Артикул', 'Название', 'tv.availability', 'Родитель', 'Описание',
	'Цена', 'Старая цена', 'Галерея', 'Производитель',
	'Новый', 'Популярный', 'Особый', 'Опубликован', 'Шаблон',
	'tv.product_description', 'tv.full_product_description', 'uri', 'Размер',
	'krainaregestracii', 'krainavyrobnyk',
	'filter_dovginakabelu', 'filter_dovginakabelu_2', 'filter_ploshcha',
	'pryznachennia_1', 'antyiskrystapasta',
	'vneshniy_blok', 'vnutrenniy_blok', 'ves_vnut_bloka', 'ves_naruzh_bloka',
	'ploshchaobsl', 'obiembaka', 'temperaturaplavlenniatermoplivky',
));

// ── Phase 0: read the header row to resolve column indexes by name ─────────
$reader = IOFactory::createReaderForFile($updatedPath);
$reader->setReadDataOnly(true);
$reader->setReadFilter(new RangeFilter(1, 1));
$ssTmp = $reader->load($updatedPath);
$shTmp = $ssTmp->getSheet(0);

$maxHCol      = Coordinate::columnIndexFromString($shTmp->getHighestDataColumn());
$headers      = array();
$headerByName = array();
for ($c = 1; $c <= $maxHCol; $c++) {
	$h = trim((string)$shTmp->getCell(Coordinate::stringFromColumnIndex($c) . '1')->getValue());
	if ($h !== '') { $headers[$c] = $h; $headerByName[$h] = $c; }
}
unset($ssTmp, $shTmp);
gc_collect_cycles();

$colId        = $headerByName['ID']                          ?? 1;
$colArticle   = $headerByName['Артикул']                     ?? 2;
$colName      = $headerByName['Название']                    ?? 3;
$colAvail     = $headerByName['tv.availability']              ?? 4;
$colParent    = $headerByName['Родитель']                    ?? 5;
$colPrice     = $headerByName['Цена']                         ?? 7;
$colOldPrice  = $headerByName['Старая цена']                 ?? 8;
$colGallery   = $headerByName['Галерея']                     ?? 9;
$colManuf     = $headerByName['Производитель']                ?? 10;
$colCountry   = $headerByName['Страна']                       ?? 11;
$colPublished = $headerByName['Опубликован']                 ?? 17;
$colShortDesc = $headerByName['tv.product_description']       ?? 20;
$colFullDesc  = $headerByName['tv.full_product_description']  ?? null;
$colUri       = $headerByName['uri']                          ?? null;
$totalCols    = $maxHCol;

// ── Phase 0b: lightweight pass over just a few columns to collect totalRows + categories ──
$colNameLetter = Coordinate::stringFromColumnIndex($colName);
$reader->setReadFilter(new RangeFilter(2, 99999, array('A', $colNameLetter, 'E', 'J', 'Q')));
$ss0 = $reader->load($updatedPath);
$sh0 = $ss0->getSheet(0);

$totalRows         = $sh0->getHighestDataRow();
$foundCategories    = array();
$foundManufacturers = array();
$categoryAutoNames  = array();

for ($r = 2; $r <= $totalRows; $r++) {
	if (cellStr($sh0, $colPublished, $r) === '0') continue;

	$pid   = cellStr($sh0, $colParent, $r);
	$mid   = cellStr($sh0, $colManuf,  $r);
	$pname = cellStr($sh0, $colName,   $r);

	if ($pid !== '') {
		$foundCategories[$pid] = true;
		if (!isset($categoryAutoNames[$pid]) && $pname !== '') {
			$cn = extractCategoryName($pname);
			if ($cn !== '') $categoryAutoNames[$pid] = $cn;
		}
	}
	if ($mid !== '') $foundManufacturers[$mid] = true;
}
unset($ss0, $sh0);
gc_collect_cycles();

// ── Phase 1: write <offer> fragments chunk by chunk to a temp file ─────────
$tempFile = $xmlPath . '.tmp';
$fpOffers = fopen($tempFile, 'w');
$offersWritten = 0;
$offersSkipped = 0;

for ($startRow = 2; $startRow <= $totalRows; $startRow += $xmlChunk) {
	$endRow = min($startRow + $xmlChunk - 1, $totalRows);
	$reader->setReadFilter(new RangeFilter($startRow, $endRow));
	$ss    = $reader->load($updatedPath);
	$sheet = $ss->getSheet(0);

	for ($r = $startRow; $r <= $endRow; $r++) {
		$id        = cellStr($sheet, $colId,        $r);
		$published = cellStr($sheet, $colPublished, $r);
		$price     = cellStr($sheet, $colPrice,     $r);
		if ($id === '' || $published === '0' || !is_numeric($price) || (float)$price <= 0) {
			$offersSkipped++;
			continue;
		}

		$article   = cellStr($sheet, $colArticle,  $r);
		$name      = cellStr($sheet, $colName,     $r);
		$avail     = cellStr($sheet, $colAvail,     $r);
		$parentId  = cellStr($sheet, $colParent,    $r);
		$oldPrice  = cellStr($sheet, $colOldPrice, $r);
		$gallery   = cellStr($sheet, $colGallery,   $r);
		$manufId   = cellStr($sheet, $colManuf,     $r);
		$country   = cellStr($sheet, $colCountry,   $r);
		$shortDesc = cellStr($sheet, $colShortDesc, $r);
		$fullDesc  = $colFullDesc ? cellStr($sheet, $colFullDesc, $r) : '';
		$uri       = $colUri ? cellStr($sheet, $colUri, $r) : '';

		$productUrl = $uri !== ''
			? rtrim($shopUrl, '/') . '/' . ltrim($uri, '/')
			: rtrim($shopUrl, '/') . '/product/' . $id;

		$vendor  = $manufacturerNames[$manufId] ?? '';
		$images  = splitImages($gallery);
		$descRaw = $fullDesc !== '' ? $fullDesc : $shortDesc;

		$x  = "      <offer id=\"" . xesc($id) . "\" available=\"" . ($avail === '1' ? 'true' : 'false') . "\">\n";
		$x .= "        <url>"   . xesc($productUrl) . "</url>\n";
		$x .= "        <price>" . (int)round((float)$price) . "</price>\n";
		if (is_numeric($oldPrice) && (float)$oldPrice > (float)$price) {
			$x .= "        <oldprice>" . (int)round((float)$oldPrice) . "</oldprice>\n";
		}
		$x .= "        <currencyId>UAH</currencyId>\n";
		if ($parentId !== '') $x .= "        <categoryId>" . xesc($parentId) . "</categoryId>\n";
		foreach (array_slice($images, 0, 10) as $img) {
			$x .= "        <picture>" . xesc($img) . "</picture>\n";
		}
		$x .= "        <name>" . xesc($name !== '' ? $name : "Товар $id") . "</name>\n";
		if ($vendor  !== '') $x .= "        <vendor>"            . xesc($vendor)  . "</vendor>\n";
		if ($article !== '') $x .= "        <vendorCode>"        . xesc($article) . "</vendorCode>\n";
		if ($country !== '') $x .= "        <country_of_origin>" . xesc($country) . "</country_of_origin>\n";
		if ($descRaw !== '') $x .= "        <description><![CDATA[" . str_replace(']]>', ']]]]><![CDATA[>', $descRaw) . "]]></description>\n";

		for ($c = 1; $c <= $totalCols; $c++) {
			$hdr = $headers[$c] ?? '';
			if ($hdr === '' || isset($skipAsAttr[$hdr])) continue;

			$val = cellStr($sheet, $c, $r);
			if ($val === '') continue;

			$label  = xesc($attrLabels[$hdr] ?? $hdr);
			$values = (strpos($val, '|') !== false)
				? array_filter(array_map('trim', explode('|', $val)))
				: array($val);

			foreach ($values as $pv) {
				$x .= "        <param name=\"$label\">" . xesc($pv) . "</param>\n";
			}
		}
		$x .= "      </offer>\n";
		fwrite($fpOffers, $x);
		$offersWritten++;
	}

	unset($ss, $sheet);
	gc_collect_cycles();
}
fclose($fpOffers);

// ── Phase 2: assemble the final XML by streaming the temp file in ──────────
$fpOut = fopen($xmlPath, 'w');
fwrite($fpOut, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
fwrite($fpOut, "<yml_catalog date=\"" . date('Y-m-d H:i') . "\">\n");
fwrite($fpOut, "  <shop>\n");
fwrite($fpOut, "    <name>"    . xesc($shopName)    . "</name>\n");
fwrite($fpOut, "    <company>" . xesc($companyName) . "</company>\n");
fwrite($fpOut, "    <url>"     . xesc($shopUrl)     . "</url>\n");
fwrite($fpOut, "    <currencies>\n      <currency id=\"UAH\" rate=\"1\"/>\n    </currencies>\n");
fwrite($fpOut, "    <categories>\n");
foreach (array_keys($foundCategories) as $catId) {
	$catName = $categoryNames[(string)$catId]
		?? $categoryAutoNames[(string)$catId]
		?? "Категорія $catId";
	fwrite($fpOut, "      <category id=\"" . xesc((string)$catId) . "\">" . xesc($catName) . "</category>\n");
}
fwrite($fpOut, "    </categories>\n");
fwrite($fpOut, "    <offers>\n");
$fpTmp = fopen($tempFile, 'r');
while (!feof($fpTmp)) fwrite($fpOut, fread($fpTmp, 65536));
fclose($fpTmp);
fwrite($fpOut, "    </offers>\n  </shop>\n</yml_catalog>\n");
fclose($fpOut);
unlink($tempFile);

// ── Output ──────────────────────────────────────────────────────────────
if ($isCli) {
	echo "Готово.\n";
	echo "Товарів у фіді: $offersWritten\n";
	echo "Пропущено (не опубліковано / без ціни): $offersSkipped\n";
	echo "Категорій: " . count($foundCategories) . "\n";
	echo "Результат: $xmlPath\n";
} else {
	header('Content-Type: application/xml; charset=utf-8');
	header('Content-Disposition: attachment; filename="marketplace.xml"');
	header('Content-Length: ' . filesize($xmlPath));
	header('Cache-Control: no-store');
	readfile($xmlPath);
}

// ── Helpers ─────────────────────────────────────────────────────────────

function cellStr(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $col, int $row): string
{
	return trim((string)$sheet->getCell(Coordinate::stringFromColumnIndex($col) . $row)->getValue());
}

function splitImages(string $raw): array
{
	$urls = preg_split('/[\|\n\r]+/', $raw);
	return array_values(array_filter(
		array_map('trim', $urls),
		static function ($u) { return $u !== '' && strpos($u, 'http') === 0; }
	));
}

function xesc(string $s): string
{
	return htmlspecialchars($s, ENT_XML1);
}

function extractCategoryName(string $name, int $maxWords = 3): string
{
	$words  = preg_split('/\s+/', trim($name));
	$result = array();
	foreach ($words as $word) {
		$letters = preg_replace('/[^\p{L}]/u', '', $word);
		if ($letters === '' || !preg_match('/^\p{Cyrillic}+$/u', $letters)) break;
		$result[] = $letters;
		if (count($result) >= $maxWords) break;
	}
	return implode(' ', $result);
}
