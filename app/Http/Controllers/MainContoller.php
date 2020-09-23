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
            dd('no files - choose your files and press upload-button');
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
        $xml = $merger->merge($request->get('entries'));

        if ($merger->fileInfos[0] == 'GPX' || $merger->fileInfos[1] == 'GPX') {
            $filename = 'merged.gpx';
        } else {
            $filename = 'merged.tcx';
        }

        return response()->streamDownload(function () use ($xml) {
            echo $xml;
        }, $filename, ['Content-Type' => 'text/xml']);
    }
}
