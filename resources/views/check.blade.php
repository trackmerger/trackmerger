@extends('layouts.app')

@section('content')
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">Select data to merge and output format</h3>
        </div>
        <div class="panel-body">
            <form action="{{ route('output') }}" method="post" enctype="multipart/form-data">
                @csrf

                <table class="table table-condensed">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Type</th>
                            <th>Streams</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fileInfos as $key => $info)
                            <tr>
                                <td>{{ $info['filename'] }}</td>
                                <td>{{ $info['type'] }}</td>
                                <td>
                                    @foreach($info['fields'] as $entry)
                                        <div class="checkbox">
                                            <label>
                                                <input type="checkbox" name="entries[{{ $key }}][]" value="{{ $entry }}"> {{ $entry }}
                                            </label>
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="form-group">
                    <label>Output</label>

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
                <button type="submit" class="btn btn-primary">Merge</button>
            </form>
        </div>
    </div>
@endsection
