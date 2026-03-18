<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Table as TableModel;
use Illuminate\Support\Facades\Http;

class TableQrController extends Controller
{
    public function image(string $uuid)
    {
        $table = TableModel::where('uuid', $uuid)->firstOrFail();

        $url = route('table.show', $table->uuid);

        $response = Http::timeout(10)->get('https://api.qrserver.com/v1/create-qr-code/', [
            'size' => '400x400',
            'data' => $url,
        ]);

        abort_unless($response->successful(), 502);

        return response($response->body(), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'max-age=3600',
        ]);
    }
}
