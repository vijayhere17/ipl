<h2>Leaderboard</h2>

@foreach($data as $d)
    <div>
        Rank {{ $d['rank'] }} - {{ $d['user']['name'] }} - {{ $d['total_points'] }}
    </div>
@endforeach