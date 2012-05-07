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

		$data = array();
		for($i = 0; $i < count($inputs); ++$i) {
			$data[] = array($inputs[$i], $expected[$i]);
		}

		return $data;
	}


}
