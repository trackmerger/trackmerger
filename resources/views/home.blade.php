@extends('layouts.app')

@section('content')
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">Upload</h3>
        </div>
        <div class="panel-body">
            <form action="" method="post" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label for="tcxfile">TCX-Datei:</label>
                    <input type="file" id="tcxfile" name="tcxfile" accept=".tcx">
                </div>
                <div class="form-group">
                    <label for="gpxfile">GPX-Datei:</label>
                    <input type="file" id="gpxfile" name="gpxfile" accept=".gpx">
                </div>

                <div class="radio">
                    <label>
                        <input type="radio" name="type" id="type1" value="1" checked>
                        Für Strava
                    </label>
                </div>
                <div class="radio">
                    <label>
                        <input type="radio" name="type" id="type2" value="2">
                        Für Garmin
                    </label>
                </div>

                <br/>
                <button type="submit" class="btn btn-primary">Hochladen und Mergen</button>
            </form>
        </div>
    </div>
@endsection
