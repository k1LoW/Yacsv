<?php
App::uses('CsvGenerator', 'Yacsv.Lib');

/**
 *
 *
 *
 */
class CsvGeneratorTest extends CakeTestCase {

    public function setUp() {
        parent::setUp();
    }

    public function tearDown() {

    }

    /**
     * testGenrateCSV
     * @dataProvider findDataProvider
     *
     */
    public function testGenrateCSV($data, $fields, $options, $expected){
        $result = CsvGenerator::generate($data, $fields, $options);

        $this->assertEquals($result, $expected);
    }

    /**
     * testGenrateCSVToOutput
     * @dataProvider findDataProvider
     *
     */
    public function testGenrateCSVToOutput($data, $fields, $options, $expected){
        $options = array_merge($options, array('forceOutput' => true));

        ob_start();
        CsvGenerator::generate($data, $fields, $options);
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertEquals($result, $expected);
    }

    /**
     * findDataProvider
     *
     */
    public function findDataProvider(){
        $data[] = array(
            array('Post' => array(
                'id' => 1,
                'title' => 'Title',
                'body' => 'Hello World',
                'created' => '2014-11-28 00:00:00',
            )),
            array('Post' => array(
                'id' => 2,
                'title' => 'Title2',
                'body' => 'Hello World2',
                'created' => '2014-11-29 00:00:00',
            )),
        );
        $fields[] = array(
            'Post.id',
            'Post.title',
            'Post.body',
            'Post.created',
        );
        $options[] = array();
        $csv = <<< EOF
"1","Title","Hello World","2014-11-28 00:00:00"
"2","Title2","Hello World2","2014-11-29 00:00:00"

EOF;

        $expected[] = mb_convert_encoding($csv, 'Shift-JIS');

        $d = array();
        for($i = 0; $i < count($data); ++$i) {
            $d[] = array($data[$i], $fields[$i], $options[$i], $expected[$i]);
        }
        return $d;
    }
}
