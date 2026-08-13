<?php

namespace Tests\Feature;

use PDO;
use Tests\TestCase;

class TilesTest extends TestCase
{
    private string $archivePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Build a throwaway MBTiles archive so the test never depends on the
        // real 118 MB storage/maps/tiles.mbtiles being present.
        $this->archivePath = storage_path('framework/testing/tiles-test.mbtiles');

        if (! is_dir(dirname($this->archivePath))) {
            mkdir(dirname($this->archivePath), 0755, true);
        }

        @unlink($this->archivePath);

        $db = new PDO('sqlite:'.$this->archivePath);
        $db->exec('CREATE TABLE tiles (zoom_level INTEGER, tile_column INTEGER, tile_row INTEGER, tile_data BLOB)');
        // z=2, x=1, y=1 → TMS row = (1 << 2) - 1 - 1 = 2
        $db->exec("INSERT INTO tiles VALUES (2, 1, 2, 'tile-bytes')");

        config(['services.mbtiles.path' => $this->archivePath]);
    }

    protected function tearDown(): void
    {
        @unlink($this->archivePath);

        parent::tearDown();
    }

    public function test_existing_tile_is_served_with_vector_tile_headers(): void
    {
        $response = $this->get('/tiles/2/1/1.pbf');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/x-protobuf')
            ->assertHeader('Content-Encoding', 'gzip')
            ->assertHeader('Cache-Control', 'max-age=86400, public');

        $this->assertSame('tile-bytes', $response->getContent());
    }

    public function test_missing_tile_returns_204_instead_of_an_error(): void
    {
        $this->get('/tiles/2/9/9.pbf')->assertNoContent();
    }

    public function test_tms_row_is_flipped_so_the_raw_y_does_not_match(): void
    {
        // The stored row is 2; requesting y=2 must resolve to TMS row 1 → empty.
        $this->get('/tiles/2/1/2.pbf')->assertNoContent();
    }

    public function test_non_numeric_coordinates_do_not_match_the_route(): void
    {
        $this->get('/tiles/abc/1/1.pbf')->assertNotFound();
    }
}
