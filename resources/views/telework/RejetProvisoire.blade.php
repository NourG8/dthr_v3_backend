<html lang="en-US">

<head>
    <meta content="text/html; charset=utf-8" http-equiv="Content-Type" />
    <title>Response following the request for telework</title>
    <meta name="description" content="Reset Password Email Template.">
    <style type="text/css">
       a {
  outline: none;
  text-decoration: none;
  display: inline-block;
  text-align: center;
  /* line-height: 3; */
  color: white;
  height:25px;
  /* margin:10px; */
}
    </style>
</head>

<body marginheight="0" topmargin="0" marginwidth="0" style="margin: 0px; background-color: #f2f3f8;" leftmargin="0">
    <!--100% body table-->
    <table cellspacing="0" border="0" cellpadding="0" width="100%" bgcolor="#f2f3f8"
       >
        <tr>
            <td>
                <table style="background-color: #f2f3f8; max-width:670px;  margin:0 auto;" width="100%" border="0"
                    align="center" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="height:50px;">&nbsp;</td>
                    </tr>

                    <tr>
                        <td style="height:20px;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td>
                            <table width="95%" border="0" align="center" cellpadding="0" cellspacing="0"
                                style="max-width:670px;background:#fff; border-radius:3px; text-align:center;-webkit-box-shadow:0 6px 18px 0 rgba(0,0,0,.06);-moz-box-shadow:0 6px 18px 0 rgba(0,0,0,.06);box-shadow:0 6px 18px 0 rgba(0,0,0,.06);">
                                <tr>
                                    <td style="height:80px;">&nbsp;</td>
                                </tr>

  <tr>
                        <td style="text-align:center;">
                        <img width="60" src="{{asset('assets/logo.png')}}" title="logo" alt="logo">
                        </td>
                    </tr>
                                <tr>
                                    <td style="height:40px;">&nbsp;</td>
                                </tr>
                                <tr>
                                    <td style="padding:0 35px;">
                                        <h1 style="color:#1e1e2d; font-weight:500; margin:0;font-size:32px;font-family:'Rubik',sans-serif;">You have an answer on your telework request</h1>
                                        <span
                                            style="display:inline-block; vertical-align:middle; margin:29px 0 26px; border-bottom:1px solid #cecece; width:100px;"></span>
                                        <p style="color:#455056; font-size:15px;line-height:24px; margin:0;">
                                            Hey <b> {{ $user['last_name']}} {{ $user['first_name']}} </b>  <br>
                                            You wish to benefit from telework at the frequency of
                                            @foreach($dates as $index=>$date)
                                                {{ $date }}
                                                @if($index < count($dates)-1 )
                                                     ,
                                                @endif
                                            @endforeach .
                                            We have studied your request and regret to inform you that we have <b>provisionally rejected</b> your request.<br>
                                            here is the reason for refusal : " {{$result}} ". <br>
                                            You can modify your request according to the reason for rejection. <br>
                                            We remain available to answer any questions you may have.
                                        </p>
                                       </td>
                                </tr>
                                <tr>
                                    <td style="height:40px;">&nbsp;</td>
                                </tr>
                                <tr >
                                <td class="esd-block-button" align="center" >
                                    <span class="es-button-border">
                                    </span>
                                 </td>
                                </tr>
                                <tr>
                                    <td style="height:40px;">&nbsp;</td>
                                </tr>
                            </table>
                        </td>
                </table>
            </td>
        </tr>
        <tr>
            <td style="height:80px;">&nbsp;</td>
        </tr>

    </table>
    <!--/100% body table-->
</body>

</html>

<style>

</style>
