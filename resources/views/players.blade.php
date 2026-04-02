<form method="GET" action="/create-team/{{ $match_id }}">
    <input type="hidden" name="cricket_match_id" value="{{ $match_id }}">

    @foreach($players as $p)
        <div>
            <input type="checkbox" name="players[]" value="{{ $p['player_id'] }}">
            {{ $p['name'] }} ({{ $p['role'] }}) - {{ $p['team'] ?? '' }}
        </div>
    @endforeach

    <button type="submit">Next</button>
</form>