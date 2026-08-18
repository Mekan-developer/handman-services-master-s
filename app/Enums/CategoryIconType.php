<?php

namespace App\Enums;

enum CategoryIconType: string
{
    /** Icon picked from the curated preset set in config/service_icons.php. */
    case Preset = 'preset';

    /**
     * Legacy custom SVG uploaded by an admin. Read-only: uploading SVGs was
     * replaced by `Image`, existing categories keep rendering their old file.
     */
    case Custom = 'custom';

    /**
     * Raster image uploaded by the admin (PNG/JPG/…), converted to WebP and
     * squeezed under 50 KB, stored on the public disk under category-icons/.
     */
    case Image = 'image';
}
