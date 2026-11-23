<?php

namespace App\Services;

use App\Models\Room;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class RoomQrCode
{
    /**
     * Where a scan lands: the PWA request form with this room pre-selected.
     */
    public function url(Room $room): string
    {
        return rtrim((string) config('helpdesk.frontend_url'), '/').'/r/'.$room->qr_token;
    }

    public function svg(Room $room, int $size = 220): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, margin: 1), new SvgImageBackEnd));

        // Medium correction survives a scratched or partly covered sticker.
        $svg = $writer->writeString($this->url($room), 'UTF-8', ErrorCorrectionLevel::M());

        return preg_replace('/^<\?xml[^>]*>\s*/', '', $svg);
    }
}
