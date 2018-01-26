<?php

namespace Directus\Tests\Api\Io;

use Directus\Database\Exception\ItemNotFoundException;

class FilesTest extends \PHPUnit_Framework_TestCase
{
    protected $queryParams = [
        'access_token' => 'token'
    ];

    /**
     * @var string
     */
    protected static $fileName = 'green.jpg';

    /**
     * @var string
     */
    protected static $uploadPath;

    /**
     * @var string
     */
    protected static $thumbsPath;

    public static function resetDatabase()
    {
        static::$uploadPath = realpath(__DIR__ . '/../../storage/uploads');
        static::$thumbsPath = static::$uploadPath . '/thumbs';

        reset_table_id('directus_files', 2);
        $uploadPath = static::$uploadPath;

        $uploadsOmit = ['.gitignore', '.htaccess', '00000000001.jpg'];
        foreach (glob($uploadPath . "/*.*") as $filename) {
            $name = basename($filename);
            if (is_dir($filename) || in_array($name, $uploadsOmit)) {
                continue;
            }

            unlink($filename);
        }

        $thumbsOmit = ['.gitignore', '1.jpg'];
        $thumbsPath = static::$thumbsPath;
        foreach (glob($thumbsPath . "/*.*") as $filename) {
            $name = basename($filename);
            if (is_dir($filename) || in_array($name, $thumbsOmit)) {
                continue;
            }

            unlink($filename);
        }
    }

    public static function setUpBeforeClass()
    {
        static::resetDatabase();
    }

    public static function tearDownAfterClass()
    {
        static::resetDatabase();
    }

    public function testCreate()
    {
        $name = static::$fileName;
        $data = [
            'name' => $name,
            'data' => $this->getImageBase64()
        ];

        $response = request_post('files', $data, ['query' => $this->queryParams]);
        assert_response($this, $response);
        assert_response_data_contains($this, $response, ['name' => $name]);

        $this->assertTrue(file_exists(static::$uploadPath . '/' . $name));
        $this->assertTrue(file_exists(static::$thumbsPath . '/2.jpg'));
    }

    public function testUpdate()
    {
        $data = [
            'title' => 'Green background'
        ];

        $response = request_patch('files/2', $data, ['query' => $this->queryParams]);
        assert_response($this, $response);
        assert_response_data_contains($this, $response, array_merge(['id' => 2], $data));
    }

    public function testGetOne()
    {
        $data = [
            'id' => 2,
            'title' => 'Green background'
        ];

        $response = request_get('files/2', $this->queryParams);
        assert_response($this, $response);
        assert_response_data_contains($this, $response, $data);
    }

    public function testList()
    {
        $response = request_get('files', $this->queryParams);
        assert_response($this, $response, [
            'data' => 'array',
            'count' => 2
        ]);
    }

    public function testDelete()
    {
        $response = request_delete('files/2', ['query' => $this->queryParams]);
        assert_response_empty($this, $response);

        $response = request_error_get('files/2', $this->queryParams);
        assert_response_error($this, $response, [
            'code' => ItemNotFoundException::ERROR_CODE,
            'status' => 404
        ]);

        $this->assertFalse(file_exists(static::$uploadPath . '/' . static::$fileName));
        $this->assertFalse(file_exists(static::$thumbsPath . '/2.jpg'));
    }

    protected function getImageBase64()
    {
        // TODO: Allow the data alone
        // TODO: Guess the data type
        // TODO: Confirm is base64
        return 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAUDBAQEAwUEBAQFBQUGBwwIBwcHBw8LCwkMEQ8SEhEPERETFhwXExQaFRERGCEYGh0dHx8fExciJCIeJBweHx7/2wBDAQUFBQcGBw4ICA4eFBEUHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh4eHh7/wAARCAB4AKADASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFgEBAQEAAAAAAAAAAAAAAAAAAAUH/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8AugILDAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAH/9k=';
    }
}