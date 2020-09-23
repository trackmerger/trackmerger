@extends('layouts.app')

@section('content')
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title">Bitte Daten zum mergen auswählen</h3>
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
                                    @foreach($info['entries'] as $entry)
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

                <br/>
                <button type="submit" class="btn btn-primary">Merge</button>
            </form>
        </div>
    </div>
@endsection
