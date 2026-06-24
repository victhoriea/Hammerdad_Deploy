<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Log In | Hammerdad</title>
    
    <link rel="stylesheet" href="loader.css">

    <style>
        :root {
            --header-height: 80px;
        }

        html, body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        body {
            font-family: Tahoma, sans-serif; 
            background-color: #d9d9d9;
            display: flex;
            flex-direction: column;
            color: #212328;

            background-image: url('images/bg.png');
            background-attachment: fixed;
            background-size: cover;
            background-position: center;
        } 

        .header {
            width: 100%;
            height: var(--header-height);
            padding: 15px 35px;
            background-color: #545454;
            display: flex;
            flex-direction: row;
            box-shadow: 0px 1px 10px 1px#52525286;
            justify-content: left;
            align-items: center;
            position: fixed;
            gap: 10px;
            top: 0;
            left: 0;
            right: 0;
            box-sizing: border-box;
            color: #fff;
        }

        .main-panel {
            background: #F5F5F5;
            width: 360px;
            height: 450px;
            margin-top: calc(var(--header-height) + 130px);
            margin-bottom: 40px;
            padding: 20px 30px;
            display: flex;
            flex-direction: column;
            align-self: center;
            align-items: center;
            justify-content: flex-end;
            position: relative;
            border-radius: 5px;
            box-shadow: 0px 1px 10px 1px #52525286; 
            box-sizing: border-box;
        }

        .input-div {
            width: 100%;
            padding: 15px 10px;
            background-color: #fff;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 5px;
            display: flex;
            flex-direction: column;
        }

        label {
            align-self: left;
            font-size: 18px;
        }

        input {
            width: 100%;
            height: 35px;
            padding: 0 10px;
            border: 1px #DDDDDD;
            border-style: solid;
            border-radius: 7px;
            margin-top: 7px;
            margin-bottom: 10px;
        }

        .log-in-btn {
            width: 50%;
            height: 35px;
            margin-top: -10px;
            background-color: #b71513;
            color: #fff;
            border-style: none;
            border-radius: 5px;
            align-self: center;
        }

        .log-in-btn:hover {
            cursor: pointer;
        }

        .log-in-btn:active {
            background-color: #920e0c;
        }
        
    </style>

</head>

<body>

    <div class="loader-wrapper">
        <div class="loader"></div>
    </div>

    <div class="header">
        <img src="images/Hammerdad-Text-Logo.png" style="height: 50px;">
        <p style="font-family: Segoe UI; font-size: 20px; font-weight: bold;">HAMMERDAD SERVICE CENTER</p>
    </div>
    
    <div class="header-outline"></div>

    <div class="main-panel">
        <img src="images/Hammerdad-Logo.png" style="width: 220px; top: -100px; position: absolute;">
        <p style="font-family: Segoe UI; font-size: 20px; font-weight: bold; color:#b71513; margin: 0;">HAMMERDAD SERVICE CENTER</p>
        <p style="font-size: 18px; padding-bottom: 10px; margin: 0;">Management System</p>

        <div class="input-div">
            <form class="log-in-form" method="POST" action="user-login.php">
                <label for="username">Username</label><br>
                <input type="text" id="username" name="username"><br>
                <label for="password">Password</label><br>
                <input type="password" id="password" name="password">
            </form>

            <button class="log-in-btn" onclick="submitLogin()">Log In</button>

            <div style="height: 20px; margin-top: 8px; text-align: center;">
                <?php if (isset($_GET['error'])): ?>
                    <p style="color: #b71513; font-size: 13px; margin: 0;">Invalid username or password.</p>
                <?php endif; ?>
            </div>
                
        </div>

    </div>

<script>
    function submitLogin() {
        const wrapper = document.querySelector('.loader-wrapper');
        wrapper.classList.add('show');
        document.querySelector('.log-in-form').submit();
    }

</script>

</body>

</html>
