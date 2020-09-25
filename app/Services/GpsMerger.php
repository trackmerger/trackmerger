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
    public $fileInfos;

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

        $data =  $keys->map(function ($timestamp) use ($entries) {
            $newItem = [];

            foreach($this->data as $index => $data) {
                if ($data->has($timestamp)) {
                    $dataItem = $data->get($timestamp);
                    if (!array_key_exists('time', $newItem)) $newItem['time'] = $dataItem['time'];
                    if (!array_key_exists('sport', $newItem)) $newItem['sport'] = $dataItem['sport'] ?? 'Biking';   // @todo fallback oder null?

                    if (in_array('cadence', $entries[$index])) $newItem['cadence'] = $dataItem['cadence'] ?? null;
                    if (in_array('power', $entries[$index])) $newItem['power'] = $dataItem['power'] ?? null;
                    if (in_array('speed', $entries[$index])) $newItem['speed'] = $dataItem['speed'] ?? null;
                    if (in_array('altitude', $entries[$index])) $newItem['altitude'] = $dataItem['altitude'] ?? null;
                    if (in_array('distance', $entries[$index])) $newItem['distance'] = $dataItem['distance'] ?? null;
                    if (in_array('lat', $entries[$index])) $newItem['lat'] = $dataItem['lat'] ?? null;
                    if (in_array('long', $entries[$index])) $newItem['long'] = $dataItem['long'] ?? null;
                    if (in_array('hr', $entries[$index])) $newItem['hr'] = $dataItem['hr'] ?? null;
                    if (in_array('calories', $entries[$index])) $newItem['calories'] = $dataItem['calories'] ?? null;
                }
            }

            return $newItem;
        });

        if ($this->fileInfos[0] == 'GPX' || $this->fileInfos[1] == 'GPX') {
            return $this->generateResultXMLAsGPX($data);
        } else {
            return $this->generateResultXMLAsTCX($data);
        }
    }

    /**
     * @param array $files
     * @param int $type
     * @return Collection
     */
    public function extractData($files, $type) {
        $hiddenFields = ['time', 'ele', 'sport'];

        foreach($files as $file) {
            /** @var UploadedFile $file */
            $xml = simplexml_load_file($file->path());

            if ($file->getClientOriginalExtension() == 'gpx') {
                $result = $this->extractDataFromGpx($xml);
                $data = $result['data'];
                $this->data->push($data);

                $entries = collect($result['keys'])->filter(function ($value) use ($hiddenFields) {
                    return !in_array($value, $hiddenFields);
                });

                $this->fileInfos->push([
                    'filename' => $file->getClientOriginalName(),
                    'type' => 'GPX',
                    'entries' => $entries
                ]);
            } elseif ($file->getClientOriginalExtension() == 'tcx') {
                $result = $this->extractDataFromTcx($xml);
                $data = $result['data'];

                $this->data->push($data);

                $entries = collect($result['keys'])->filter(function ($value) use ($hiddenFields) {
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
     * @param Collection $data
     * @return string
     */
    public function generateResultXMLAsTCX($data) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>
            <TrainingCenterDatabase
                xmlns="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2"
                xmlns:ns2="http://www.garmin.com/xmlschemas/ActivityExtension/v2"
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                xsi:schemaLocation="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2 http://www.garmin.com/xmlschemas/TrainingCenterDatabasev2.xsd"
            ></TrainingCenterDatabase>');

        $activities = $xml->addChild('Activities');
        $activity = $activities->addChild('Activity');
        $activity->addAttribute('Sport', $data->first()['sport'] ?? null);
        $activity->addChild('Id', gmdate('Y-m-d').'T'.gmdate('H:i:s').'Z');
        $activity->addChild('Notes', 'TrackMerger Export');

        $lap = $activity->addChild('Lap');
        $lap->addAttribute('StartTime', $data->first()['time']);
        $lap->addChild('TotalTimeSeconds', $data->count());
        $lap->addChild('Calories', $data->first()['calories'] ?? null);
        $lap->addChild('TriggerMethod', 'Manual'); // @todo hardcoded!

        $track = $lap->addChild('Track');

        foreach ($data as $entry) {
            $trackPoint = $track->addChild('Trackpoint');
            $trackPoint->addChild('Time', $entry['time'] ?? '');

            if (!empty($entry['long']) && !empty($entry['lat'])) {
                $position = $trackPoint->addChild('Position');
                $position->addChild('LongitudeDegrees', $entry['long']);
                $position->addChild('LatitudeDegrees', $entry['lat']);
            }

            $trackPoint->addChild('AltitudeMeters', $entry['altitude'] ?? '');
            if (array_key_exists('hr', $entry)) {
                $heartRateBpm = $trackPoint->addChild('HeartRateBpm');
                $heartRateBpm->addChild('Value', $entry['hr']);
            }

            if (array_key_exists('cadence', $entry)) {
                $trackPoint->addChild('Cadence', $entry['cadence']);
            }

            if (array_key_exists('power', $entry) || array_key_exists('speed', $entry)) {
                $extensions = $trackPoint->addChild('Extensions');
                $tpx = $extensions->addChild('TPX', null);
                $tpx->addAttribute('xmlns', 'http://www.garmin.com/xmlschemas/ActivityExtension/v2');

                if (array_key_exists('power', $entry)) {
                    $tpx->addChild('Watts', $entry['power']);
                }

                if (array_key_exists('speed', $entry)) {
                    $tpx->addChild('Speed', $entry['speed']);
                }
            }
        }

//        $this->validateTCXForGamin($xml->asXML());

        return $xml->asXML();
    }

    /**
     * @param $data
     * @return string
     */
    public function generateResultXMLAsGPX($data) {
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
     * @return array
     */
    protected function extractDataFromGpx(SimpleXMLElement $xml) {
        $xml->registerXPathNamespace('gpxtpx', 'http://www.garmin.com/xmlschemas/TrackPointExtension/v1');

        $data = collect();
        $keys = collect();
        $ns = $xml->getNamespaces(true);

        foreach($xml->trk->trkseg->trkpt as $trackpoint) {
            $newItem = [
                'time' => (string) $trackpoint->time,
                'altitude' => (float) $trackpoint->ele,
                'lat' => (float) $trackpoint['lat'],
                'long' => (float) $trackpoint['lon'],
                'hr' => (isset($trackpoint->extensions)) ? (int) $trackpoint->extensions->children($ns['gpxtpx'])->TrackPointExtension->hr : 0
            ];
            $keys = $keys->merge(array_keys($newItem))->unique();

            $data->put(strtotime($trackpoint->time), $newItem);
        }

        return [
            'keys' => $keys,
            'data' => $data
        ];
    }

    /**
     * @param SimpleXMLElement $xml
     * @return array
     */
    protected function extractDataFromTcx(SimpleXMLElement $xml) {
        $data = collect();
        $keys = collect();

        foreach($xml->Activities->Activity->Lap as $lap) {
            foreach($lap->Track->Trackpoint as $trackpoint) {
                $newItem = [
                    'time' => (string) $trackpoint->Time
                ];

                if (!empty($xml->Activities->Activity->Lap->Calories)) {
                    $newItem['calories'] = (int) $xml->Activities->Activity->Lap->Calories;
                }

                if (!empty($xml->Activities->Activity['Sport'])) {
                    $newItem['sport'] = (string) $xml->Activities->Activity['Sport'];
                }

                if (isset($trackpoint->Cadence)) {
                    $newItem['cadence'] = (int) $trackpoint->Cadence;
                }

                if (isset($trackpoint->DistanceMeters)) {
                    $newItem['distance'] = (float) $trackpoint->DistanceMeters;
                }

                if (isset($trackpoint->AltitudeMeters)) {
                    $newItem['altitude'] = (float) $trackpoint->AltitudeMeters;
                }

                if (isset($trackpoint->Extensions) && isset($trackpoint->Extensions->TPX)) {
                    if (isset($trackpoint->Extensions->TPX->Watts)) {
                        $newItem['power'] = (int) $trackpoint->Extensions->TPX->Watts;
                    }

                    if (isset($trackpoint->Extensions->TPX->Speed)) {
                        $newItem['speed'] = (float) $trackpoint->Extensions->TPX->Speed;
                    }
                }

                if (isset($trackpoint->HeartRateBpm)) {
                    $newItem['hr'] = (int) $trackpoint->HeartRateBpm->Value;
                }

                if (isset($trackpoint->Position)) {
                    $newItem['lat'] = (float) $trackpoint->Position->LatitudeDegrees;
                    $newItem['long'] = (float) $trackpoint->Position->LongitudeDegrees;
                }

                $keys = $keys->merge(array_keys($newItem))->unique();

                $data->put(strtotime($trackpoint->Time), $newItem);
            }
        }

        return [
            'keys' => $keys,
            'data' => $data
        ];
    }

    protected function validateTCXForGamin($xml) {
        $xsd = 'https://www8.garmin.com/xmlschemas/TrainingCenterDatabasev2.xsd';
        // needed for getting errors
        libxml_use_internal_errors(true);

        $domDocument= new \DOMDocument();
        $domDocument->loadXML($xml);
        if (!$domDocument->schemaValidate($xsd)) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                dump($error);
            }
            libxml_clear_errors();
        }
    }
}
