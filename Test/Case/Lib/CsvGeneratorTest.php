<?php
App::uses('CsvGenerator', 'Yacsv.Lib');

/**
 * CsvGeneratorTest
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

        // デフォルト出力
        $data[] = array(
            array('Post' => array(
                'id' => 1,
                'title' => 'Title',
                'body' => 'Hello World',
                'created' => '2014-11-28 00:00:00',
            )),
            array('Post' => array(
                'id' => 2,
                'title' => 'タイトル',
                'body' => 'こんにちは世界',
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
"2","タイトル","こんにちは世界","2014-11-29 00:00:00"

EOF;
        $expected[] = $csv;

        // $fieldsで順番入れ替えが可能
        $data[] = array(
            array('Post' => array(
                'id' => 1,
                'title' => 'Title',
                'body' => 'Hello World',
                'created' => '2014-11-28 00:00:00',
            )),
            array('Post' => array(
                'id' => 2,
                'title' => 'タイトル',
                'body' => 'こんにちは世界',
                'created' => '2014-11-29 00:00:00',
            )),
        );
        $fields[] = array(
            'Post.body',
            'Post.title',
            'Post.created',
        );
        $options[] = array();
        $csv = <<< EOF
"Hello World","Title","2014-11-28 00:00:00"
"こんにちは世界","タイトル","2014-11-29 00:00:00"

EOF;
        $expected[] = $csv;

        // $fieldsでヘッダ設定が可能
        $data[] = array(
            array('Post' => array(
                'id' => 1,
                'title' => 'Title',
                'body' => 'Hello World',
                'created' => '2014-11-28 00:00:00',
            )),
            array('Post' => array(
                'id' => 2,
                'title' => 'タイトル',
                'body' => 'こんにちは世界',
                'created' => '2014-11-29 00:00:00',
            )),
        );
        $fields[] = array(
            'タイトル' => 'Post.title',
            '内容' => 'Post.body',
            '投稿日時' => 'Post.created',
        );
        $options[] = array();
        $csv = <<< EOF
"タイトル","内容","投稿日時"
"Title","Hello World","2014-11-28 00:00:00"
"タイトル","こんにちは世界","2014-11-29 00:00:00"

EOF;
        $expected[] = $csv;

        // Shift-JISでも出力可能
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
        $options[] = array('csvEncoding' => 'Shift-JIS');
        $csv = <<< EOF
"1","Title","Hello World","2014-11-28 00:00:00"
"2","Title2","Hello World2","2014-11-29 00:00:00"

EOF;
        $expected[] = mb_convert_encoding($csv, 'Shift-JIS');

        // 対象フィールドがなかったら空値
        $data[] = array(
            array('Post' => array(
                'id' => 1,
                'title' => 'Title',
                'body' => 'Hello World',
                'created' => '2014-11-28 00:00:00',
            )),
            array('Post' => array(
                'id' => 2,
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
"2","","Hello World2","2014-11-29 00:00:00"

EOF;
        $expected[] = $csv;

        $d = array();
        for($i = 0; $i < count($data); ++$i) {
            $d[] = array($data[$i], $fields[$i], $options[$i], $expected[$i]);
        }
        return $d;
    }
}
