<!DOCTYPE html>
<html>
<head>
    <title>Live Match</title>

    <style>
        body {
            background: #0f172a;
            color: white;
            font-family: Arial;
            margin: 0;
        }

        .header {
            background: #020617;
            padding: 15px;
            text-align: center;
        }

        .score-row {
            display: flex;
            justify-content: space-between;
            padding: 20px;
            background: #111827;
        }

        .team-box {
            text-align: center;
        }

        .team-box h2 {
            font-size: 28px;
            margin: 5px 0;
        }

        .section {
            background: #111827;
            margin: 10px;
            padding: 15px;
            border-radius: 10px;
        }

        .ball {
            padding: 10px;
            border-radius: 50%;
            margin: 5px;
            display: inline-block;
            min-width: 35px;
            text-align: center;
        }

        .four { background: #3b82f6; }
        .six { background: #22c55e; }
        .wicket { background: #ef4444; }
        .dot { background: #6b7280; }

        .row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }

    </style>
</head>

<body>

<div class="header">
    <h3 id="match_name"></h3>
    <p id="status" style="color:red;"></p>
</div>

<div class="score-row">
    <div class="team-box">
        <h4 id="team1"></h4>
        <h2 id="score1"></h2>
        <p id="over1"></p>
    </div>

    <div class="team-box">
        <h4 id="team2"></h4>
        <h2 id="score2"></h2>
        <p id="over2"></p>
    </div>
</div>

<!-- LAST OVER -->
<div class="section">
    <h4>Last Over</h4>
    <div id="last_over"></div>
</div>

<!-- BATSMEN -->
<div class="section">
    <h4>Batsmen</h4>
    <div id="batsmen"></div>
</div>

<!-- BOWLER -->
<div class="section">
    <h4>Bowler</h4>
    <p id="bowler"></p>
</div>

<!-- SQUADS -->
<div class="section">
    <h4>Squads</h4>
    <p><b>Team 1:</b> <span id="team1_squad"></span></p>
    <p><b>Team 2:</b> <span id="team2_squad"></span></p>
</div>

<script>
    const matchId = {{ $id }};

    function getBallClass(ball) {
        if (ball == 4) return 'four';
        if (ball == 6) return 'six';
        if (ball == 'W') return 'wicket';
        return 'dot';
    }

    function fetchLive() {
        fetch(`/api/v1/match/${matchId}/live`)
        .then(res => res.json())
        .then(res => {
            const data = res.data;

            // HEADER
            document.getElementById('match_name').innerText = data.match_name;
            document.getElementById('status').innerText = data.match_status;

            // SCORE
            document.getElementById('team1').innerText = data.score.team1.name;
            document.getElementById('score1').innerText =
                data.score.team1.runs + "/" + data.score.team1.wickets;
            document.getElementById('over1').innerText =
                data.score.team1.overs + " ov";

            document.getElementById('team2').innerText = data.score.team2.name;
            document.getElementById('score2').innerText =
                data.score.team2.runs + "/" + data.score.team2.wickets;
            document.getElementById('over2').innerText =
                data.score.team2.overs + " ov";

            // LAST OVER (FIXED 🔥)
            let ballsHTML = "";
            data.last_over.forEach(ball => {

                let value = ball.score ?? ball; // fix both cases
                let cls = getBallClass(value);

                ballsHTML += `<span class="ball ${cls}">${value}</span>`;
            });

            document.getElementById('last_over').innerHTML = ballsHTML;

            // BATSMEN
            let batsmenHTML = "";
            data.batsmen.forEach(b => {
                batsmenHTML += `
                    <div class="row">
                        <span>${b.name}</span>
                    </div>
                `;
            });

            document.getElementById('batsmen').innerHTML = batsmenHTML;

            // BOWLER
            document.getElementById('bowler').innerText = data.bowler;

            // SQUADS
            document.getElementById('team1_squad').innerText =
                data.team1_squad.join(', ');

            document.getElementById('team2_squad').innerText =
                data.team2_squad.join(', ');
        });
    }

    // AUTO REFRESH
    fetchLive();
    setInterval(fetchLive, 5000);
</script>

</body>
</html>