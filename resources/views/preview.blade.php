<h2>{{ $team['team_name'] }}</h2>

@foreach($team['players'] as $p)
    <div>
        {{ $p['name'] }} ({{ $p['role'] }})
        @if($p['is_captain']) - C @endif
        @if($p['is_vice_captain']) - VC @endif
    </div>
@endforeach