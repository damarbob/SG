<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width" />
    <style type="text/css">
        body {
            height: 100% !important;
            margin: 0;
            padding: 0;
            width: 100% !important;
            background-color: #f4f6f9;
            color: #333;
            font-family: Helvetica, Arial, sans-serif;
            text-align: center;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: left;
        }

        h1 {
            font-size: 24px;
            margin-bottom: 20px;
        }

        p {
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            margin-top: 20px;
        }

        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #777;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1><?= lang('StarGate.loginRequest') ?></h1>
        <p><?= lang('StarGate.emailHello') ?></p>
        <p><?= lang('StarGate.emailLoginRequestBody', [$ipAddress]) ?></p>
        <p><?= lang('StarGate.emailLoginRequestCta') ?></p>

        <?php
        // Append token to callback URL
        $separator = (parse_url($callback_url, PHP_URL_QUERY) == NULL) ? '?' : '&';
        $url = $callback_url . $separator . 'token=' . $token;
        ?>

        <a href="<?= esc($url, 'attr') ?>" class="btn"><?= lang('StarGate.login') ?></a>

        <p><?= lang('StarGate.emailOrCopyLink') ?></p>
        <p><a href="<?= esc($url, 'attr') ?>"><?= esc($url) ?></a></p>

        <p><?= lang('StarGate.emailIgnoreRequest') ?></p>

        <div class="footer">
            <p><?= lang('StarGate.copyright', [date('Y'), config('StarGate')->appName]) ?></p>
        </div>
    </div>
</body>

</html>