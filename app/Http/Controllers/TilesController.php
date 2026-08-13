<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use PDO;

class TilesController extends Controller
{
    /**
     * Serve a single vector tile from the self-hosted MBTiles archive.
     *
     * The archive lives at `storage/maps/tiles.mbtiles` (configurable through
     * `MBTILES_PATH`) and backs both the admin map and the mobile apps.
     */
    public function vectorTile(int $z, int $x, int $y): Response
    {
        $tile = $this->readTile($z, $x, $y);

        // An empty tile is normal for sea and unmapped areas — not an error.
        if ($tile === false) {
            return response('', 204);
        }

        return response($tile)
            ->header('Content-Type', 'application/x-protobuf')
            // Tiles are already gzip-compressed inside the MBTiles archive.
            ->header('Content-Encoding', 'gzip')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * @return string|false Raw tile bytes, or false when the tile is absent.
     */
    private function readTile(int $z, int $x, int $y): string|false
    {
        $db = new PDO('sqlite:'.config('services.mbtiles.path'), null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // MBTiles stores Y in the TMS scheme, i.e. with a flipped axis.
        $tmsY = (1 << $z) - 1 - $y;

        $statement = $db->prepare(
            'SELECT tile_data FROM tiles
             WHERE zoom_level = ? AND tile_column = ? AND tile_row = ?'
        );
        $statement->execute([$z, $x, $tmsY]);

        return $statement->fetchColumn();
    }
}
