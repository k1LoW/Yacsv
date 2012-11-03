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
	private function _makeDummyCsv($data) {
		$this->csvFile = TMP . 'importer_' . uniqid() . '.csv';

		$fp = fopen($this->csvFile, 'w');
		foreach ($data as $d) {
			fwrite($fp, $d . "\n");
		}
		fclose($fp);

		return $this->csvFile;
	}

	/**
	 * @dataProvider csvDataProvider
	 *
	 */
	public function testImportCsvFromFile($data, $expected) {

		$csvFile = $this->_makeDummyCsv($data);

		$options = array(
			'csvEncoding' => 'UTF-8',
			'hasHeader' => false,
			'delimiter' => ',',
			'enclosure' => '"',
		);

		$result = $this->Importer->importCsvFromFile($csvFile, $options);
		$this->assertTrue($result);

		$results = $this->Importer->find('all');

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

		$inputs[] = array(
			'"NAME","COUNTRY"', // HEADER LINE
			'"Oyama","Japan"',
			'"Suzuki","Antarctica"',
		);
		$expected[] = array(
			array(
				'name' => 'NAME',
				'country' => 'COUNTRY',
			),
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
			$data[] = array($inputs[$i], $expected[$i]);
		}

		return $data;
	}



	/**
	 * test for importCsbFromFile() with hasHeader option
	 * @dataProvider csvDataProviderWithHeader
	 */
	public function testImportCsvFromFileWithHeader($data, $expected, $skipcount) {
		$csvFile = $this->_makeDummyCsv($data);

		$options = array(
			'csvEncoding' => 'UTF-8',
			'hasHeader' => true,
			'skipHeaderCount' => $skipcount,
			'delimiter' => ',',
			'enclosure' => '"',
		);

		$result = $this->Importer->importCsvFromFile($csvFile, $options);
		$this->assertTrue($result);

		$results = $this->Importer->find('all');

		for($i = 0; $i < count($results); ++$i) {
			$name = $results[$i]['Importer']['name'];
			$country = $results[$i]['Importer']['country'];

			$this->assertSame($expected[$i]['name'], $name);
			$this->assertSame($expected[$i]['country'], $country);
		}
	}

	public function csvDataProviderWithHeader() {
		// has a HEADER
		$inputs[] = array(
			'"NAME","COUNTRY"', // HEADER
			'"Oyama","Japan"',
			'"Suzuki","Antarctica"',
			'"Ando",Nomad',
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
		$skipcount[] = 1;

		// NO HEADER data, but hasHeader true
		$inputs[] = array(
			'"Oyama","Japan"', // NO HEADER, but skip this line
			'"Suzuki","Antarctica"',
			'Ando,Nomad',
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
		$skipcount[] = 1;

		// has two HEADER , one skip
		$inputs[] = array(
			'"NAME","COUNTRY"', // HEADER
			'"NAME2","COUNTRY2"', // HEADER2
			'"Oyama","Japan"',
			'"Suzuki","Antarctica"',
			'"Ando",Nomad',
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
		$skipcount[] = 1;

		// has two HEADER , two skip
		$inputs[] = array(
			'"NAME","COUNTRY"', // HEADER
			'"NAME2","COUNTRY2"', // HEADER2
			'"Oyama","Japan"',
			'"Suzuki","Antarctica"',
			'"Ando",Nomad',
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
		$skipcount[] = 2;


		$data = array();
		for($i = 0; $i < count($inputs); ++$i) {
			$data[] = array($inputs[$i], $expected[$i], $skipcount[$i]);
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
