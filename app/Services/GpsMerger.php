<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use SimpleXMLElement;

class GpsMerger {

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $data;

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $fileInfos;

    public function __construct() {
        $this->data = collect();
        $this->fileInfos = collect();
    }

    /**
     * @param $entries
     * @return string
     */
    public function merge($entries) {
        $this->data = session('gpsdata');
        $this->fileInfos = session('fileinfos');

        $keys1 = $this->data[0]->keys();
        $keys2 = $this->data[1]->keys();
        $keys = $keys1->merge($keys2)->unique()->sort();

        return $keys->map(function ($timestamp) use ($entries) {
            $newItem = [];

            foreach($this->data as $index => $data) {
                if ($data->has($timestamp)) {
                    $dataItem = $data->get($timestamp);
                    if (!array_key_exists('time', $newItem)) $newItem['time'] = $dataItem['time'];

                    if (in_array('cadence', $entries[$index])) $newItem['cadence'] = $dataItem['cadence'];
                    if (in_array('power', $entries[$index])) $newItem['power'] = $dataItem['power'];
                    if (in_array('speed', $entries[$index])) $newItem['speed'] = $dataItem['speed'];
                    if (in_array('altitude', $entries[$index])) $newItem['altitude'] = $dataItem['altitude'];
                    if (in_array('distance', $entries[$index])) $newItem['distance'] = $dataItem['distance'];
                    if (in_array('lat', $entries[$index])) $newItem['lat'] = $dataItem['lat'];
                    if (in_array('long', $entries[$index])) $newItem['long'] = $dataItem['long'];
                    if (in_array('hr', $entries[$index])) $newItem['hr'] = $dataItem['hr'];
                }
            }

            return $newItem;
        });
    }

    /**
     * @param array $files
     * @param int $type
     * @return Collection
     */
    public function extractData($files, $type) {
        $hiddenFields = ['time', 'ele'];

        foreach($files as $file) {
            /** @var UploadedFile $file */
            $xml = simplexml_load_file($file->path());

            if ($file->getClientOriginalExtension() == 'gpx') {
                $data = $this->extractDataFromGpx($xml);
                $this->data->push($data);

                $entries = collect(array_keys($data->first()))->filter(function ($value) use ($hiddenFields) {
                    return !in_array($value, $hiddenFields);
                });

                $this->fileInfos->push([
                    'filename' => $file->getClientOriginalName(),
                    'type' => 'GPX',
                    'entries' => $entries
                ]);
            } elseif ($file->getClientOriginalExtension() == 'tcx') {
                $data = $this->extractDataFromTcx($xml);
                $this->data->push($data);

                $entries = collect(array_keys($data->first()))->filter(function ($value) use ($hiddenFields) {
                    return !in_array($value, $hiddenFields);
                });

                $this->fileInfos->push([
                    'filename' => $file->getClientOriginalName(),
                    'type' => 'TCX',
                    'entries' => $entries
                ]);
            }
        }

        session([
            'fileinfos' => $this->fileInfos,
            'gpsdata' => $this->data,
            'type' => $type
        ]);

        return $this->fileInfos;
    }

    /**
     * @param $data
     * @return string
     */
    public function generateResultXML($data) {
        $type = session('type');

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

        foreach ($data as $entry) {
            $trkpt = $trkseg->addChild('trkpt');

            if (isset($entry['long']) && isset($entry['lat'])) {
                $trkpt->addAttribute('lon', $entry['long']);
                $trkpt->addAttribute('lat', $entry['lat']);
            }

            $trkpt->addChild('ele', $entry['altitude'] ?? '');
            $trkpt->addChild('time', $entry['time'] ?? '');

            $extensions = $trkpt->addChild('extensions');

            if ($type == 1) {
                $extensions->addChild('hr', $entry['hr'] ?? '', $gpx_schema);
                $extensions->addChild('cadence', $entry['cadence'] ?? '', $gpx_schema);
                $extensions->addChild('power', $entry['power'] ?? '');
                $extensions->addChild('distance', $entry['distance'] ?? '');
            } elseif ($type == 2) {
                $trackPointExtension = $extensions->addChild('TrackPointExtension', null, $ns3_schema);
                $trackPointExtension->addChild('hr', $entry['hr'] ?? '', $ns3_schema);
                $trackPointExtension->addChild('cad', $entry['cadence'] ?? '', $ns3_schema);

                $extensions->addChild('power', $entry['power'] ?? '');
            }
        }

        return $xml->asXML();
    }

    /**
     * @param SimpleXMLElement $xml
     * @return Collection
     */
    protected function extractDataFromGpx(SimpleXMLElement $xml) {
        $xml->registerXPathNamespace('gpxtpx', 'http://www.garmin.com/xmlschemas/TrackPointExtension/v1');

        $data = collect();
        $ns = $xml->getNamespaces(true);
        foreach($xml->trk->trkseg->trkpt as $trackpoint) {
            $data->put(strtotime($trackpoint->time), [
                'time' => (string) $trackpoint->time,
                'altitude' => (float) $trackpoint->ele,
                'lat' => (float) $trackpoint['lat'],
                'long' => (float) $trackpoint['lon'],
                'hr' => (isset($trackpoint->extensions)) ? (int) $trackpoint->extensions->children($ns['gpxtpx'])->TrackPointExtension->hr : 0
            ]);
        }

        return $data;
    }

    /**
     * @param SimpleXMLElement $xml
     * @return Collection
     */
    protected function extractDataFromTcx(SimpleXMLElement $xml) {
        $data = collect();

        foreach($xml->Activities->Activity->Lap->Track->Trackpoint as $trackpoint) {
            $data->put(strtotime($trackpoint->Time), [
                'time' => (string) $trackpoint->Time,
                'cadence' => (int) $trackpoint->Cadence,
                'distance' => (int) $trackpoint->DistanceMeters,
                'altitude' => (int) $trackpoint->AltitudeMeters,
                'power' => (int) $trackpoint->Extensions->TPX->Watts,
//                'speed' => (float) $trackpoint->Extensions->TPX->Speed
            ]);
        }

        return $data;
    }
}
