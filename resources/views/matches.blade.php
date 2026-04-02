<h2>Matches</h2>

@foreach($matches as $m)
    <div style="border:1px solid #ccc; padding:10px; margin:10px;">
        {{ $m['team_1'] }} vs {{ $m['team_2'] }}
        <br>
        <a href="/players/{{ $m['api_match_id'] }}">Select Players</a>
    </div>
@endforeach