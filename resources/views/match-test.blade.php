<!DOCTYPE html>
<html>
<head>

<title>Fantasy Match UI</title>

<style>

body{
font-family:Arial;
background:#f4f4f4;
padding:40px;
}

.card{
width:420px;
background:white;
padding:25px;
border-radius:10px;
box-shadow:0 3px 10px rgba(0,0,0,0.1);
}

.match-title{
font-size:18px;
font-weight:bold;
margin-bottom:10px;
}

.teams{
font-size:22px;
margin:10px 0;
}

.score{
font-size:18px;
margin:5px 0;
}

.status{
color:red;
font-weight:bold;
margin-top:10px;
}

.section{
margin-top:15px;
}

</style>

</head>

<body>

<div class="card">

<div class="match-title" id="matchName">Loading...</div>

<div class="teams">
<span id="team1"></span> vs <span id="team2"></span>
</div>

<div class="section">
<b>Venue:</b> <span id="venue"></span>
</div>

<div class="section">
<b>Date:</b> <span id="date"></span>
</div>

<div class="section">
<b>Toss:</b> <span id="toss"></span>
</div>

<div class="section">
<b>Winner:</b> <span id="winner"></span>
</div>

<div class="section">
<h3>Score</h3>

<div class="score" id="score1"></div>
<div class="score" id="score2"></div>

</div>

<div class="status" id="status"></div>

</div>


<script>

fetch("http://127.0.0.1:8000/api/v1/match/d44ae827-46a1-4b55-8b6e-13af118f9421/info")

.then(res => res.json())

.then(data => {

let match = data.data;

document.getElementById("matchName").innerText = match.name;

document.getElementById("team1").innerText = match.teams[0];

document.getElementById("team2").innerText = match.teams[1];

document.getElementById("venue").innerText = match.venue;

document.getElementById("date").innerText = match.date;

document.getElementById("status").innerText = match.status;

document.getElementById("toss").innerText =
match.tossWinner + " chose to " + match.tossChoice;

document.getElementById("winner").innerText = match.matchWinner;

if(match.score){

document.getElementById("score1").innerText =
match.score[0].inning + " : " +
match.score[0].r + "/" +
match.score[0].w + " (" +
match.score[0].o + " overs)";

document.getElementById("score2").innerText =
match.score[1].inning + " : " +
match.score[1].r + "/" +
match.score[1].w + " (" +
match.score[1].o + " overs)";

}

});

</script>

</body>
</html>