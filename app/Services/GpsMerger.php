<?php

namespace App\Services;

use SimpleXMLElement;

class GpsMerger {

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $tcx;

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $gpx;

    public function __construct() {
        $this->tcx = collect();
        $this->gpx = collect();
    }

    public function merge($tcxFilePath, $gpxFilePath, $type) {
        $this->extractData($tcxFilePath, $gpxFilePath);

        $tcxKeys = $this->tcx->keys();
        $gpxKeys = $this->gpx->keys();
        $keys = $tcxKeys->merge($gpxKeys)->unique()->sort();

        $result = $keys->map(function ($key) {
            $newItem = [];

            if ($this->tcx->has($key)) {
                $tcx = $this->tcx->get($key);
                $newItem['time'] = $tcx['time'];
                $newItem['cadence'] = $tcx['cadence'];
                $newItem['power'] = $tcx['power'];
                $newItem['speed'] = $tcx['speed'];
            }

            if ($this->gpx->has($key)) {
                $gpx = $this->gpx->get($key);
                $newItem['time'] = $gpx['time'];
                $newItem['ele'] = $gpx['ele'];
                $newItem['lat'] = $gpx['lat'];
                $newItem['long'] = $gpx['long'];
                $newItem['hr'] = $gpx['hr'];
            }

            return $newItem;
        });

        return $this->generateResultXML($result, $type);
    }

    /**
     * @param $tcxFilePath
     * @param $gpxFilePath
     */
    protected function extractData($tcxFilePath, $gpxFilePath) {
        $tcxXML = simplexml_load_file($tcxFilePath);
        $gpxXML = simplexml_load_file($gpxFilePath);

        $gpxXML->registerXPathNamespace('gpxtpx', 'http://www.garmin.com/xmlschemas/TrackPointExtension/v1');

        foreach($tcxXML->Activities->Activity->Lap->Track->Trackpoint as $trackpoint) {
            $this->tcx->put(strtotime($trackpoint->Time), [
                'time' => (string) $trackpoint->Time,
                'cadence' => (int) $trackpoint->Cadence,
                'power' => (int) $trackpoint->Extensions->TPX->Watts,
                'speed' => (float) $trackpoint->Extensions->TPX->Speed
            ]);
        }

        $ns = $gpxXML->getNamespaces(true);
        foreach($gpxXML->trk->trkseg->trkpt as $trackpoint) {
            $this->gpx->put(strtotime($trackpoint->time), [
                'time' => (string) $trackpoint->time,
                'ele' => (float) $trackpoint->ele,
                'lat' => (float) $trackpoint['lat'],
                'long' => (float) $trackpoint['lon'],
                'hr' => (isset($trackpoint->extensions)) ? (int) $trackpoint->extensions->children($ns['gpxtpx'])->TrackPointExtension->hr : 0
            ]);
        }
    }

    /**
     * @param $entries
     * @param $type
     * @return string
     */
    protected function generateResultXML($entries, $type) {
        $gpx_schema = 'http://www.cluetrust.com/XML/GPXDATA/1/0';
        $ns3_schema = 'http://www.garmin.com/xmlschemas/TrackPointExtension/v1';

        if ($type == 1) {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><gpx xmlns="http://www.topografix.com/GPX/1/1" xmlns:gpxdata="' . $gpx_schema . '" creator="Apple Watch" version="8.2"></gpx>');
        } elseif ($type == 2) {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><gpx xmlns="http://www.topografix.com/GPX/1/1" xmlns:ns3="'.$ns3_schema.'" creator="Apple Watch" version="8.2"></gpx>');
        }

        $metadata = $xml->addChild('metadata');

        $link = $metadata->addChild('link');
        $link->addAttribute('href', 'foo.bar.com');
        $link->addChild('text', 'awesome TCX and GPX merger');

        $trk = $xml->addChild('trk');
        $trk->addChild('name', 'foo bar');
        $trk->addChild('type', 'Biking');

        $trkseg = $trk->addChild('trkseg');

        foreach ($entries as $entry) {
            $trkpt = $trkseg->addChild('trkpt');

            if (isset($entry['long']) && isset($entry['lat'])) {
                $trkpt->addAttribute('lon', $entry['long']);
                $trkpt->addAttribute('lat', $entry['lat']);
            }

            $trkpt->addChild('ele', $entry['ele'] ?? '');
            $trkpt->addChild('time', $entry['time'] ?? '');

            $extensions = $trkpt->addChild('extensions');

            if ($type == 1) {
                $extensions->addChild('hr', $entry['hr'] ?? '', $gpx_schema);
                $extensions->addChild('cadence', $entry['cadence'] ?? '', $gpx_schema);
                $extensions->addChild('power', $entry['power'] ?? '');
            } elseif ($type == 2) {
                $trackPointExtension = $extensions->addChild('TrackPointExtension');
                $trackPointExtension->addChild('hr', $entry['hr'] ?? '', $ns3_schema);
                $trackPointExtension->addChild('cad', $entry['cadence'] ?? '', $ns3_schema);

                $extensions->addChild('power', $entry['power'] ?? '');
            }
        }

        return $xml->asXML();
    }
}
