<form method="POST" action="/store-team">
    @csrf

    <input type="hidden" name="cricket_match_id" value="{{ $match_id }}">
    <input type="text" name="team_name" placeholder="Team Name">

    @foreach($players as $p)
        <div>
            <input type="hidden" name="players[{{ $loop->index }}][player_id]" value="{{ $p }}">
            
            Player ID: {{ $p }}

            C <input type="radio" name="captain" value="{{ $p }}">
            VC <input type="radio" name="vice_captain" value="{{ $p }}">
        </div>
    @endforeach

    <button type="submit">Create Team</button>
</form>