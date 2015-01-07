<?php


/**
 * Dummy class for Behavior Test
 */
class Importer extends CakeTestModel {

	public $actsAs = array('Yacsv.Importer');
	public $importFilterArgs = array(
		array('name' => 'csv'),
	);
	public $importFields = array(
		'name',
		'country',
	);

}


/**
 * TestSuite for Yacsv.importerBehavior
 */
class ImporterBehaviorTest extends CakeTestCase {

	public $fixtures = array('plugin.Yacsv.importer');
	private $csvFile;

	public function setUp() {
		parent::setUp();
		$this->Importer = new Importer();
	}

	public function tearDown() {

	}

	/**
	 * make CSV file for test
	 *
	 */
	private function _makeDummyCsv($data, $csvEncoding = 'UTF-8') {
		$this->csvFile = TMP . uniqid('importer_') . '.csv';

		$fp = fopen($this->csvFile, 'w');
		foreach ($data as $d) {
			fwrite($fp, mb_convert_encoding($d . "\n", $csvEncoding));
		}
		fclose($fp);

		return $this->csvFile;
	}

	/**
	 * make CSV file use CR (carriage return) for test
	 *
	 */
	private function _makeDummyCRCsv($data, $csvEncoding = 'UTF-8') {
		$this->csvFile = TMP . uniqid('importer_') . '.csv';

		$fp = fopen($this->csvFile, 'w');
		foreach ($data as $d) {
			fwrite($fp, mb_convert_encoding($d . "\r", $csvEncoding));
		}
		fclose($fp);

		return $this->csvFile;
	}

	/**
	 * @dataProvider csvDataProvider
	 *
	 */
	public function testImportCsvFromFile($data, $csvEncoding, $options, $expected) {

		$csvFile = $this->_makeDummyCsv($data, $csvEncoding);

		$result = $this->Importer->importCsvFromFile($csvFile, $options);
		$this->assertTrue($result);

		$results = $this->Importer->find('all');
		$this->assertTrue((count($results) > 0));

		for($i = 0; $i < count($results); ++$i) {
			$name = $results[$i]['Importer']['name'];
			$country = $results[$i]['Importer']['country'];

			$this->assertSame($expected[$i]['name'], $name);
			$this->assertSame($expected[$i]['country'], $country);
		}

		$result = $this->Importer->importCsvFromFile($csvFile, $options);
		$this->assertTrue($result);
		$results2 = $this->Importer->find('all');

		// preDeleteAll check
		if (!empty($options['preDeleteAll'])) {
			$this->assertTrue((count($results) === count($results2)));
		} else {
			$this->assertTrue((count($results) * 2 === count($results2)));
		}
	}

	/**
	 * @dataProvider csvDataProvider
	 *
	 * jpn: CRを改行コードとして認識させたいときはauto_detect_line_endingsを有効にすればいい
	 */
	public function testImportCsvCRFromFile($data, $csvEncoding, $options, $expected) {

		ini_set('auto_detect_line_endings', true);

		$csvFile = $this->_makeDummyCRCsv($data, $csvEncoding);
		$result = $this->Importer->importCsvFromFile($csvFile, $options);
		$this->assertTrue($result);

		$results = $this->Importer->find('all');

		$this->assertTrue((count($results) > 0));

		for($i = 0; $i < count($results); ++$i) {
			$name = $results[$i]['Importer']['name'];
			$country = $results[$i]['Importer']['country'];

			$this->assertSame($expected[$i]['name'], $name);
			$this->assertSame($expected[$i]['country'], $country);
		}
	}

	/**
	 * dataProvider for testImportCsvFromFile
	 *
	 */
	public function csvDataProvider() {
		$inputs[] = array(
			'"Oyama","Japan"',
			'"Suzuki","Antarctica"',
		);
		$csvEncoding[] = 'UTF-8';
		$options[] = array(
			'csvEncoding' => 'UTF-8',
			'hasHeader' => false,
			'delimiter' => ',',
			'enclosure' => '"',
		);
		$expected[] = array(
			array(
				'name' => 'Oyama',
				'country' => 'Japan',
			),
			array(
				'name' => 'Suzuki',
				'country' => 'Antarctica',
			),
		);

		// enclosure = false
		$inputs[] = array(
			'"Oyama","Japan"',
			'"Suzuki","Antarctica"',
		);
		$csvEncoding[] = 'UTF-8';
		$options[] = array(
			'csvEncoding' => 'UTF-8',
			'hasHeader' => false,
			'delimiter' => ',',
			'enclosure' => false,
		);
		$expected[] = array(
			array(
				'name' => '"Oyama"',
				'country' => '"Japan"',
			),
			array(
				'name' => '"Suzuki"',
				'country' => '"Antarctica"',
			),
		);

		// TSV
		$inputs[] = array(
			"Oyama\tJapan",
			"Suzuki\tAntarctica",
		);
		$csvEncoding[] = 'UTF-8';
		$options[] = array(
			'csvEncoding' => 'UTF-8',
			'hasHeader' => false,
			'delimiter' => "\t",
			'enclosure' => false,
		);
		$expected[] = array(
			array(
				'name' => 'Oyama',
				'country' => 'Japan',
			),
			array(
				'name' => 'Suzuki',
				'country' => 'Antarctica',
			),
		);

		// has HEADER
		$inputs[] = array(
			'"NAME","COUNTRY"', // HEADER LINE
			'"Oyama","Japan"',
			'"Suzuki","Antarctica"',
		);
		$csvEncoding[] = 'UTF-8';
		$options[] = array(
			'csvEncoding' => 'UTF-8',
			'hasHeader' => true,
			'delimiter' => ',',
			'enclosure' => '"',
		);
		$expected[] = array(
			array(
				'name' => 'Oyama',
				'country' => 'Japan',
			),
			array(
				'name' => 'Suzuki',
				'country' => 'Antarctica',
			),
		);

		// NO HEADER data, but hasHeader true
		$inputs[] = array(
			'"Oyama","Japan"', // NO HEADER, but skip this line
			'"Suzuki","Antarctica"',
			'Ando,Nomad',
		);
		$csvEncoding[] = 'UTF-8';
		$options[] = array(
			'csvEncoding' => 'UTF-8',
			'hasHeader' => true,
			'delimiter' => ',',
			'enclosure' => '"',
		);
		$expected[] = array(
			array(
				'name' => 'Suzuki',
				'country' => 'Antarctica',
			),
			array(
				'name' => 'Ando',
				'country' => 'Nomad',
			),
		);

		// has two HEADER , one skip
		$inputs[] = array(
			'"NAME","COUNTRY"', // HEADER
			'"NAME2","COUNTRY2"', // HEADER2
			'"Oyama","Japan"',
			'"Suzuki","Antarctica"',
			'"Ando",Nomad',
		);
		$csvEncoding[] = 'UTF-8';
		$options[] = array(
			'csvEncoding' => 'UTF-8',
			'hasHeader' => true,
			'delimiter' => ',',
			'enclosure' => '"',
			'skipHeaderCount' => 1,
		);
		$expected[] = array(
			array(
				'name' => 'NAME2',
				'country' => 'COUNTRY2',
			),
			array(
				'name' => 'Oyama',
				'country' => 'Japan',
			),
			array(
				'name' => 'Suzuki',
				'country' => 'Antarctica',
			),
			array(
				'name' => 'Ando',
				'country' => 'Nomad'
			),
		);

		// has two HEADER , two skip
		$inputs[] = array(
			'"NAME","COUNTRY"', // HEADER
			'"NAME2","COUNTRY2"', // HEADER2
			'"Oyama","Japan"',
			'"Suzuki","Antarctica"',
			'"Ando",Nomad',
		);
		$csvEncoding[] = 'UTF-8';
		$options[] = array(
			'csvEncoding' => 'UTF-8',
			'hasHeader' => true,
			'delimiter' => ',',
			'enclosure' => '"',
			'skipHeaderCount' => 2,
		);
		$expected[] = array(
			array(
				'name' => 'Oyama',
				'country' => 'Japan',
			),
			array(
				'name' => 'Suzuki',
				'country' => 'Antarctica',
			),
			array(
				'name' => 'Ando',
				'country' => 'Nomad'
			),
		);

		// csv encoding
		$inputs[] = array(
			'"Oyama","日本"',
			'"Suzuki","南極大陸"',
		);
		$csvEncoding[] = 'SJIS-win';
		$options[] = array(
			'csvEncoding' => 'SJIS-win',
			'hasHeader' => false,
			'delimiter' => ',',
			'enclosure' => '"',
		);
		$expected[] = array(
			array(
				'name' => 'Oyama',
				'country' => '日本',
			),
			array(
				'name' => 'Suzuki',
				'country' => '南極大陸',
			),
		);

		// csv auto detect encoding
		$inputs[] = array(
			'"NAME","COUNTRY"', // HEADER
			'"Oyama","日本"',
			'"Suzuki","南極大陸"',
		);
		$csvEncoding[] = 'SJIS-win';
		$options[] = array(
			'csvEncoding' => 'auto',
			'hasHeader' => true,
			'delimiter' => ',',
			'enclosure' => '"',
			'skipHeaderCount' => 1,
		);
		$expected[] = array(
			array(
				'name' => 'Oyama',
				'country' => '日本',
			),
			array(
				'name' => 'Suzuki',
				'country' => '南極大陸',
			),
		);

		// csv auto detect delimiter
		$inputs[] = array(
			'"NAME","COUNTRY"', // HEADER
			'"Oyama","日本"',
			'"Suzuki","南極大陸"',
		);
		$csvEncoding[] = 'SJIS-win';
		$options[] = array(
			'csvEncoding' => 'auto',
			'hasHeader' => true,
			'delimiter' => 'auto',
			'enclosure' => '"',
			'skipHeaderCount' => 1,
		);
		$expected[] = array(
			array(
				'name' => 'Oyama',
				'country' => '日本',
			),
			array(
				'name' => 'Suzuki',
				'country' => '南極大陸',
			),
		);

		// csv auto detect enclosure
		$inputs[] = array(
			'NAME,COUNTRY', // HEADER
			'"Oyama","日本"',
			'"Suzuki",南極大陸',
		);
		$csvEncoding[] = 'SJIS-win';
		$options[] = array(
			'csvEncoding' => 'auto',
			'hasHeader' => true,
			'delimiter' => ',',
			'enclosure' => 'auto',
			'skipHeaderCount' => 1,
		);
		$expected[] = array(
			array(
				'name' => 'Oyama',
				'country' => '日本',
			),
			array(
				'name' => 'Suzuki',
				'country' => '南極大陸',
			),
		);

		$inputs[] = array(
			'NAME,COUNTRY', // HEADER
			'Oyama,日本',
			'Suzuki,南極大陸',
		);
		$csvEncoding[] = 'SJIS-win';
		$options[] = array(
			'csvEncoding' => 'auto',
			'hasHeader' => true,
			'delimiter' => 'auto',
			'enclosure' => 'auto',
			'skipHeaderCount' => 1,
		);
		$expected[] = array(
			array(
				'name' => 'Oyama',
				'country' => '日本',
			),
			array(
				'name' => 'Suzuki',
				'country' => '南極大陸',
			),
		);

		// preDeleteAll
		$inputs[] = array(
			'"Oyama","Japan"',
			'"Suzuki","Antarctica"',
		);
		$csvEncoding[] = 'UTF-8';
		$options[] = array(
			'csvEncoding' => 'UTF-8',
			'hasHeader' => false,
			'delimiter' => ',',
			'enclosure' => '"',
			'preDeleteAll' => true,
		);
		$expected[] = array(
			array(
				'name' => 'Oyama',
				'country' => 'Japan',
			),
			array(
				'name' => 'Suzuki',
				'country' => 'Antarctica',
			),
		);
		
		$data = array();
		for($i = 0; $i < count($inputs); ++$i) {
			$data[] = array($inputs[$i], $csvEncoding[$i], $options[$i], $expected[$i]);
		}

		return $data;
	}

	/**
	 * @dataProvider importedCountDataProvider
	 */
	public function testGetImportedCount($data, $expected) {
		$csvFile = $this->_makeDummyCsv($data);

		$options = array(
			'csvEncoding' => 'UTF-8',
			'hasHeader' => false,
			'delimiter' => "\t",
			'enclosure' => '"',
			'forceImport' => true,
		);

		$result = $this->Importer->importCsvFromFile($csvFile, $options);
		$this->assertTrue($result);

		$result = $this->Importer->getImportedCount();
		$this->assertSame($expected, $result);
	}

	/**
	 * dataProvider for testGetImportedCount
	 */
	public function importedCountDataProvider() {
		$inputs[] = array(
			"Oyama\tJapan",
			"Suzuki\tAntarctica",
		);
		$expected[] = 2;

		// include invalid line
		$inputs[] = array(
			"Oyama\tJapan",
			"hoge", // invalid line
			"Suzuki\tAntarctica",
		);
		$expected[] = 2;

		// no data
		$inputs[] = array();
		$expected[] = 0;

		$data = array();
		for($i = 0; $i < count($inputs); ++$i) {
			$data[] = array($inputs[$i], $expected[$i]);
		}

		return $data;
	}

}
