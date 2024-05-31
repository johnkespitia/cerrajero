<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>email</title>
    <style>
        .container{
            display: flex;
            width: 100%;
            font-family: arial, helvetica, sans-serif;
        }
        .container .right-section{
            width: 20%;
            background-color:#eaf0f6;
        }
        .container .center-section{
            width: 60%;
            margin: 0px auto;
            background-color:#ffffff;
        }
        .container .left-section{
            width: 20%;
            background-color:#eaf0f6;
        }
        .container .center-section header{
            background: #ffffff url('{{ $bg }}') center top / cover repeat;
            background-position: center top;
            background-repeat: repeat;
            background-size: cover;
            margin: 0px auto;
            max-width: 600px;
            min-height:850px;
            color: #FFFFFF;
        }
        .container .center-section header .logo{
            border: 0;
            display: block;
            outline: none;
            text-decoration: none;
            height: 129.57px;
            margin: auto;
            font-size: 13px;
        }
        .container .center-section header h1{
            border: 0;
            display: block;
            outline: none;
            text-decoration: none;
            max-width: 450px;
            margin: auto;
            margin-top: 350px;
            font-size: 48px;
            color: rgb(236, 240, 241);

        }
        .container .center-section header h2{
            border: 0;
            display: block;
            outline: none;
            text-decoration: none;
            max-width: 450px;
            margin: auto;
            font-size: 30px;
            color: rgb(236, 240, 241);

        }
        .container .center-section .content{
            margin: 0px auto;
            max-width: 600px;
            direction: ltr;
            padding: 20px 0;
            padding-bottom: 40px;
            padding-left: 20px;
            padding-right: 20px;
            padding-top: 20px;
        }
        .container .center-section footer, .container .center-section footer p {
            background: #00a4bd;
            background-color: #00a4bd;
            margin: 0px auto;
            max-width: 600px;
            font-size: 14px;
            font-family: arial, helvetica, sans-serif;
            color: #ced4d9;
        }
        .container .center-section footer p {
            background: #00a4bd;
            background-color: #00a4bd;
            margin: 0px auto;
            max-width: 600px;
            font-size: 14px;
            font-family: arial, helvetica, sans-serif;
            color: #ced4d9;
            padding: 10px 25px;
            padding-top: 20px;
            padding-right: 20px;
            padding-bottom: 20px;
            padding-left: 20px;
        }
        .container .center-section footer p {
            color: inherit !important;
        }
        .custom-button{
            display:inline-block;
            margin-top:20px;
            background:#9c27b0;
            color:#ffffff !important;
            font-family:arial,helvetica,sans-serif;
            font-size:16px;
            font-weight:bold;
            line-height:1.25;
            text-decoration:none;
            text-transform:none;
            padding:10px 25px;
            border-radius:25px
        }
        .google{
            background-color: #4285f4;
        }
        .yahoo{
            background-color: #720e9e;
        }
        .office{
            background-color: #003366;
        }
        .hotmail{
            background-color: #003366;
        }
        .container-details{
            display:flex;
            justify-content: space-between;
        }
        .container-plan ul{
            list-style: none;
            padding: 0;
        }
        @media (max-width: 600px) {
            .container .right-section{
                width: 5%;
                background-color:#eaf0f6;
            }
            .container .center-section{
                width: 90%;
                margin: 0px auto;
                background-color:#ffffff;
            }
            .container .left-section{
                width: 5%;
                background-color:#eaf0f6;
            }
        }

        .container-details{
        display:flex;
        justify-content: space-between;
    }
    .container-plan{

    }
    .container-plan ul{
        list-style: none;
	    padding: 0;
    }
    .container-plan ul li + li {
        margin-top: 1rem;
    }
    .container-plan ul li {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: aliceblue;
        padding: 1.5rem;
        border-radius: 1rem;
        width: calc(100% - 2rem);
        box-shadow: 0.25rem 0.25rem 0.75rem rgb(0 0 0 / 0.1);
    }
    .container-plan ul li:nth-child(even) {
        flex-direction: row-reverse;
        background: honeydew;
        margin-right: -2rem;
        margin-left: 2rem;
    }
    .container-professor{
        display:flex;
        justify-content: space-between;
    }
    .professor-image{
        aspect-ratio: 1 / 1;
        max-width: 20%;
        border-radius: 10px;
    }
    .professor-details{
        min-width: 70%;
        margin-left: 20px;
        text-align:center;
    }
    </style>
</head>
<body>
    <div class="container">
        <div class="right-section"></div>
        <div class="center-section">
        <header>
            <img class="logo" src={{ asset('storage/mail_assets/mail-logo1.png') }}></img>
            <h1>{{$main_title ?? ''}}</h1>
            <h2>{{$subtitle ?? ''}}</h2>
            <a href="{{$main_btn_url ?? ''}}"
                style="display:inline-block;
                margin-top:20px;
                background:#9c27b0;
                color:#ffffff;
                font-family:arial,helvetica,sans-serif;
                font-size:16px;font-weight:bold;line-height:1.25;margin:20px 0px 0 70px;text-decoration:none;text-transform:none;padding:10px 25px;border-radius:25px"
                target="_blank" >
                {{$main_btn_title ?? ''}}
            </a>
        </header>
            <div class="content">
                @yield('contenido')
            </div>
            <footer>
                <p>Copyright © 2024  PLG Educaion, All rights reserved.</p>

                <p><strong>Our mailing address is:</strong><br/><a href="mailto:academico@plgeducation.com">academico@plgeducation.com</a></p>
            </footer>
        </div>
        <div class="left-section"></div>
    </div>
</body>
</html>
