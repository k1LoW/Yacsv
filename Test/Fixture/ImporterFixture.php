<?php

/**
 * Fixture for Importer Model used by ImporterBehaviorTest
 *
 */
class ImporterFixture extends CakeTestFixture {

	public $fields = array(
		'id' => array('type' => 'integer', 'key' => 'primary'),
		'name' => array('type' => 'text'),
		'country' => array('type' => 'text'),
		'created' => array('type' => 'timestamp'),
	);

	public $records = array(
	);

}