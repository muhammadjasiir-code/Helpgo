<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Passenger Service - HelpGo</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Poppins',sans-serif;
}

body{
    background:linear-gradient(135deg,#062b22,#0b4d3b,#0a2d24);
    color:#fff;
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    overflow:hidden;
}

.bg{
    position:absolute;
    width:500px;
    height:500px;
    background:#FFD54F20;
    filter:blur(100px);
    border-radius:50%;
}

.bg:nth-child(1){
    top:-150px;
    left:-120px;
}

.bg:nth-child(2){
    bottom:-150px;
    right:-120px;
}

.card{
    position:relative;
    z-index:2;
    width:90%;
    max-width:420px;
    background:rgba(255,255,255,.08);
    backdrop-filter:blur(20px);
    border:1px solid rgba(255,255,255,.15);
    border-radius:28px;
    padding:40px 30px;
    text-align:center;
    box-shadow:0 20px 60px rgba(0,0,0,.35);
}

.icon{
    width:110px;
    height:110px;
    margin:auto;
    border-radius:50%;
    background:linear-gradient(135deg,#FFD54F,#FFC107);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:55px;
    box-shadow:0 15px 35px rgba(255,193,7,.35);
}

h1{
    margin-top:25px;
    font-size:32px;
    color:#FFD54F;
}

h3{
    margin-top:10px;
    font-weight:400;
    color:#ddd;
}

p{
    margin:25px 0;
    line-height:1.7;
    color:#eee;
    font-size:15px;
}

.badge{
    display:inline-block;
    padding:10px 18px;
    border-radius:50px;
    background:#FFD54F;
    color:#1a1a1a;
    font-weight:700;
    margin-bottom:25px;
}

.btn{
    display:inline-block;
    width:100%;
    padding:15px;
    background:linear-gradient(135deg,#FFD54F,#FFC107);
    color:#111;
    text-decoration:none;
    font-weight:700;
    border-radius:14px;
    transition:.3s;
}

.btn:hover{
    transform:translateY(-3px);
}

.footer{
    margin-top:25px;
    font-size:13px;
    color:#bbb;
}
</style>

</head>
<body>

<div class="bg"></div>
<div class="bg"></div>

<div class="card">

<div class="icon">
🚕
</div>

<h1>Passenger Service</h1>

<h3>Coming Soon</h3>

<p>
We're working hard to launch our Passenger Booking service.
Soon you'll be able to book rides quickly and safely with HelpGo.
</p>

<div class="badge">
🚀 Launching Soon
</div>

<a href="home.php" class="btn">
← Back to Home
</a>

<div class="footer">
© HelpGo • Thrikkaripur
</div>

</div>

</body>
</html>