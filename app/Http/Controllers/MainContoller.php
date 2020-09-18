<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GpsMerger;

class MainContoller extends Controller
{
    public function showForm() {
        return view('home');
    }

    public function process(Request $request) {
        if (!$request->hasFile('tcxfile') || !$request->hasFile('gpxfile')) {
            dd('Dateien fehlen');
        }

        $merger = new GpsMerger();
        $xml = $merger->merge($request->tcxfile->path(), $request->gpxfile->path(), $request->get('type'));

        return response()->streamDownload(function () use ($xml) {
            echo $xml;
        }, 'merged.gpx', ['Content-Type' => 'text/xml']);
    }
}
