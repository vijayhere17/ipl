<h2>My Teams</h2>

@foreach($teams as $t)
    <div style="border:1px solid #000; padding:10px;">
        {{ $t['team_name'] }}
        <br>
        Captain: {{ $t['captain'] }}
        <br>
        <a href="/team-preview/{{ $t['team_id'] }}">Preview</a>
    </div>
@endforeach