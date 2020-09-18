# GPX File Merger

This tool merges GPX files from Apple Watch and TCX files from Bosch E-Bike Connect (Kiox) into one file to upload to Strava, Garmin etc.

This tool uses the power and cadence from TCX and add it to gpx tracked with your watch.

---

## How to use


#### Bosch e-bike Connect (export tcx from your bike ride)

1. Go to [Bosch e-Bike Connect](https://www.ebike-connect.com/login?lang=de-de) and Login.
2. Go to "Activity" and select the activity you want to export.
3. Click on options and select export as „TCX“
4. Save this file on your computer.

![bosch](images/bosch.jpeg)


#### Apple Watch (export gpx file from your training)

1. You need a app installed on your iphone which makes it possible to export a apple watch training as GPX.
You can use the app "Rungap".
2. Export your training and save the file.

#### Merge both files
1. Upload both files and select type of export.
2. Merge it
![merger](images/merger.jpeg)

---

## How to run in docker.

1. Clone repository
2. Rename docker-compose.yml.sample into docker-compose.yml
3. Change parameters like port if you want (default port is 80)
4. Start container with docker-compose 

            docker-compose up -d
            
5. Open your browser and navigate to http://localhost:80
        



