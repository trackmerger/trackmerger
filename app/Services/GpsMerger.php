<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use SimpleXMLElement;
use Waddle\Activity;
use Waddle\Lap;
use Waddle\Parsers\GPXParser;
use Waddle\Parsers\TCXParser;
use Waddle\TrackPoint;

class GpsMerger {

    const DATE_FORMAT = 'Y-m-d\TH:i:s\Z';

    /**
     * @var \Illuminate\Support\Collection
     */
    protected $activities;

    /**
     * @var \Illuminate\Support\Collection
     */
    public $fileInfos;

    public function __construct() {
        $this->activities = collect();
        $this->fileInfos = collect();
    }

    /**
    * @param array $files
    * @return Collection
    */
    public function extractData($files) {
        foreach($files as $file) {
            $type = null;

            /** @var UploadedFile $file */
            if ($file->getClientOriginalExtension() == 'gpx') {
                $parser = new GPXParser();
                $type = 'GPX';
            } elseif ($file->getClientOriginalExtension() == 'tcx') {
                $parser = new TCXParser();
                $type = 'TCX';
            }

            $activity = $parser->parse($file->path());
            $this->activities->push($activity);

            $this->fileInfos->push([
                'filename' => $file->getClientOriginalName(),
                'type' => $type,
                'fields' => $this->getAvailableFields($activity)
            ]);
        }

        session([
            'fileinfos' => $this->fileInfos,
            'activities' => $this->activities
        ]);

        return $this->fileInfos;
    }

    protected function getAvailableFields(Activity $activity) {
        $availableFields = collect();

        foreach ($activity->getLaps() as $lap) {
            foreach ($lap->getTrackPoints() as $index => $trackPoint) {
                $currentAvailableFields = [];

                if (!empty($lap->getTotalCalories())) $currentAvailableFields[] = 'Calories';
                if (!empty($trackPoint->getPosition())) $currentAvailableFields[] = 'Position';
                if (!is_null($trackPoint->getAltitude())) $currentAvailableFields[] = 'Altitude';
                if (!is_null($trackPoint->getDistance())) $currentAvailableFields[] = 'Distance';
                if (!is_null($trackPoint->getSpeed())) $currentAvailableFields[] = 'Speed';
                if (!is_null($trackPoint->getWatts())) $currentAvailableFields[] = 'Watts';
                if (!is_null($trackPoint->getCadence())) $currentAvailableFields[] = 'Cadence';
                if (!is_null($trackPoint->getHeartRate())) $currentAvailableFields[] = 'HeartRate';
                if (!is_null($trackPoint->getCalories())) $currentAvailableFields[] = 'Calories';

                $availableFields = $availableFields->merge($currentAvailableFields)->unique();
            }
        }

        return $availableFields;
    }

    /**
     * @param $entries
     * @return string
     */
    public function merge($entries) {
        $this->activities = session('activities');
        $this->fileInfos = session('fileinfos');

        $resultActivity = new Activity();
        $resultLap = new Lap();

        $trackpoints = collect();
        $this->activities->each(function ($activity, $index) use ($resultLap, $trackpoints, $entries) {
            /** @var Activity $activity */
            foreach($activity->getLaps() as $lap) {
                if (in_array('Calories', $entries[$index])) {
                    $resultLap->setTotalCalories($resultLap->getTotalCalories() + $lap->getTotalCalories());
                }

                if (in_array('Distance', $entries[$index])) {
                    $resultLap->setTotalDistance($resultLap->getTotalDistance() + $lap->getTotalDistance());
                }

                if (in_array('Speed', $entries[$index])) {
                    $resultLap->setMaxSpeed(max($resultLap->getMaxSpeed(), $lap->getMaxSpeed()));
                }

                foreach($lap->getTrackPoints() as $trackPoint) {
                    // ignore trackpoints without position
                    if (in_array('Position', $entries[$index]) && $trackPoint->getPosition('lat') == 0 && $trackPoint->getPosition('lon') == 0) {
                        continue;
                    }

                    if (!$trackpoints->has($trackPoint->getTime('U'))) {
                        $currentPoint = new TrackPoint();
                        $currentPoint->setTime($trackPoint->getTime());
                    } else {
                        $currentPoint = $trackpoints->get($trackPoint->getTime('U'));
                    }

                    foreach($entries[$index] as $field) {
                        if (!in_array($field, ['Calories'])) {
                            $currentPoint->{'set'.$field}($trackPoint->{'get'.$field}());
                        }
                    }

                    $trackpoints->put($trackPoint->getTime('U'), $currentPoint);
                }
            }
        });

        $trackpoints = $trackpoints->sortKeys();
        $resultLap->setTrackPoints($trackpoints->toArray());
        $resultLap->setTotalTime($trackpoints->count());
        $resultActivity->setType($this->activities->first()->getType());
        $resultActivity->setStartTime($trackpoints->first()->getTime());
        $resultActivity->setLaps([$resultLap]);

        if ($this->fileInfos[0] == 'GPX' || $this->fileInfos[1] == 'GPX') {
            return $this->generateResultXMLAsGPX($resultActivity);
        } else {
            return $this->generateResultXMLAsTCX($resultActivity);
        }
    }


    /**
     * @param Activity $resultActivity
     * @return string
     */
    public function generateResultXMLAsTCX(Activity $resultActivity) {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>
            <TrainingCenterDatabase
                xmlns="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2"
                xmlns:ns2="http://www.garmin.com/xmlschemas/ActivityExtension/v2"
                xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                xsi:schemaLocation="http://www.garmin.com/xmlschemas/TrainingCenterDatabase/v2 http://www.garmin.com/xmlschemas/TrainingCenterDatabasev2.xsd"
            ></TrainingCenterDatabase>');

        $startTime = $resultActivity->getStartTime(self::DATE_FORMAT);

        $activities = $xml->addChild('Activities');
        $activity = $activities->addChild('Activity');
        $activity->addAttribute('Sport', $resultActivity->getType());
        $activity->addChild('Id', $startTime);
        $activity->addChild('Notes', 'TrackMerger Export');

        /** @var Lap $resultLap */
        $resultLap = $resultActivity->getLap(0);

        $lap = $activity->addChild('Lap');
        $lap->addAttribute('StartTime', $startTime);
        $lap->addChild('TotalTimeSeconds', $resultLap->getTotalTime());
        $lap->addChild('DistanceMeters', $resultLap->getTotalDistance());
        $lap->addChild('MaximumSpeed', $resultLap->getMaxSpeed());
        $lap->addChild('Calories', $resultLap->getTotalCalories());
        $lap->addChild('TriggerMethod', 'Manual'); // @todo hardcoded!

        $track = $lap->addChild('Track');

        foreach ($resultLap->getTrackPoints() as $resultTrackPoint) {
            /** @var TrackPoint $resultTrackPoint */
            $trackPoint = $track->addChild('Trackpoint');
            $trackPoint->addChild('Time', $resultTrackPoint->getTime(self::DATE_FORMAT));

            if (!empty($resultTrackPoint->getPosition())) {
                $position = $trackPoint->addChild('Position');
                $position->addChild('LongitudeDegrees', $resultTrackPoint->getPosition('lon'));
                $position->addChild('LatitudeDegrees', $resultTrackPoint->getPosition('lat'));
            }

            $trackPoint->addChild('AltitudeMeters', $resultTrackPoint->getAltitude());
            if (!is_null($resultTrackPoint->getHeartRate())) {
                $heartRateBpm = $trackPoint->addChild('HeartRateBpm');
                $heartRateBpm->addChild('Value', $resultTrackPoint->getHeartRate());
            }

            if (!is_null($resultTrackPoint->getCadence())) {
                $trackPoint->addChild('Cadence', $resultTrackPoint->getCadence());
            }

            if (!is_null($resultTrackPoint->getWatts()) || !is_null($resultTrackPoint->getSpeed())) {
                $extensions = $trackPoint->addChild('Extensions');
                $tpx = $extensions->addChild('TPX', null);
                $tpx->addAttribute('xmlns', 'http://www.garmin.com/xmlschemas/ActivityExtension/v2');

                if (!is_null($resultTrackPoint->getWatts())) {
                    $tpx->addChild('Watts', $resultTrackPoint->getWatts());
                }

                if (!is_null($resultTrackPoint->getSpeed())) {
                    $tpx->addChild('Speed', $resultTrackPoint->getSpeed());
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
