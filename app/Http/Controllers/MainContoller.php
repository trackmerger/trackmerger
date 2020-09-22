<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GpsMerger;

class MainContoller extends Controller
{
    public function showForm() {
        return view('start');
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function check(Request $request) {
        if (!$request->hasFile('files')) {
            dd('Dateien fehlen');
        }

        $merger = new GpsMerger();
        $fileInfos = $merger->extractData($request->file('files'), $request->get('type'));

        return view('check', compact('fileInfos'));
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function output(Request $request) {
        $merger = new GpsMerger();
        $data = $merger->merge($request->get('entries'));
        $xml = $merger->generateResultXML($data);

        return response()->streamDownload(function () use ($xml) {
            echo $xml;
        }, 'merged.gpx', ['Content-Type' => 'text/xml']);
    }
}
