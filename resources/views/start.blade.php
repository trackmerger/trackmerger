@extends('layouts.app')

@section('content')
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">Upload</h3>
        </div>
        <div class="panel-body">
            <form action="{{ route('check') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label for="files">Files:</label>
                    <input type="file" id="files" name="files[]" accept=".tcx,.gpx" multiple>
                </div>

                <div class="form-group">
                    <label>Download</label>

                    <div class="radio">
                        <label>
                            <input type="radio" name="type" id="type1" value="1" checked>
                            Strava
                        </label>
                    </div>
                    <div class="radio">
                        <label>
                            <input type="radio" name="type" id="type2" value="2">
                            Garmin
                        </label>
                    </div>
                </div>

                <br/>
                <button type="submit" class="btn btn-primary">Upload</button>
            </form>
        </div>
    </div>
@endsection
