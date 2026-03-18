<?php

namespace App\Http\Controllers;

use App\Models\Table as TableModel;
use chillerlan\QRCode\QRCode;
use Illuminate\Http\Response;

class QrCodeController extends Controller
{
    public function table(string $uuid): Response
    {
        $table = TableModel::where('uuid', $uuid)->firstOrFail();

        $url = route('table.show', $table->uuid);
        $svg = (new QRCode)->render($url);

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
