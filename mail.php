<?php ini_set('display_errors', 'Off');

$email = 'arif@vtbaza.ru'; // адрес куда отправлять письмо, можно несколько через запятую
$subject = 'Новое сообщение с сайта '.$_SERVER['HTTP_HOST']; // тема письма с указанием адреса сайта
$message = 'Данные формы:'; // вводная часть письма
$addreply = ''; // адрес куда отвечать (необязательно)
$from = ''; // имя отправителя (необязательно)
$smtp = 0; // отправлять ли через почтовый ящик, 1 - да, 0 - нет, отправлять через хостинг

// настройки почтового сервера для режима $smtp = 1 (Внимание: с GMAIL не работает)
$host = 'smtp.yandex.ru'; // сервер отправки писем (приведен пример для Яндекса)
$username = 'arift@yandex.ru'; // логин вашего почтового ящика
$password = 'solmaz7077484'; // пароль вашего почтового ящика
$auth = 1; // нужна ли авторизация, 1 - нужна, 0 - не нужна
$secure = 'ssl'; // тип защиты
$port = 465; // порт сервера
$charset = 'utf-8'; // кодировка письма

// дополнительные настройки
$cc = ''; // копия письма
$bcc = ''; // скрытая копия

$client_email = ''; // поле откуда брать адрес клиента
$client_message = ''; // текст письма, которое будет отправлено клиенту
$client_file = ''; // вложение, которое будет отправлено клиенту

$export_file = ''; // имя файла для экспорта в CSV
$export_fields = ''; // список полей для экспорта (через запятую)

$recaptcha_secret_key = ''; // секретный ключ для Recaptcha

$disable_email = 0;
$actions = ''; // [ ['type' => 'post', 'url' => '', 'fields' => ''], ];

$fields = "";
foreach ($_POST as $key => $value) {
    if ($value === 'on') {
        $value = 'Да';
    }
    if ($key === 'sendto') {
        $email = $value;
    }
    if ($key === 'g-recaptcha-response') {
        $recaptcha = $value;
        if (!empty($recaptcha)) {
            $google_url = "https://www.google.com/recaptcha/api/siteverify";
            $url = $google_url."?secret=".$recaptcha_secret_key."&response=".$recaptcha."&remoteip=".$_SERVER['REMOTE_ADDR'];
            $res = SiteVerify($url);
            $res = json_decode($res, true);
            if (!$res['success']) {
                echo 'ERROR_RECAPTCHA';
                die();
            }
        } else {
            echo 'ERROR_RECAPTCHA';
            die();
        }
    } elseif ($key === 'required_fields') {
        $required = explode(',', $value);
    } else {
        if (in_array($key, $required) && $value === '') {
            echo 'ERROR_REQUIRED';
            die();
        }
        if (is_array($value)) {
            $fields .= str_replace('_', ' ', $key).': '.implode(', ', $value).'<br />';
        } else {
            if ($value !== '') {
                $fields .= str_replace('_', ' ', $key).': '.$value.'<br />';
            }
        }
    }
}

if ($export_file !== '') {
    $vars = explode(',', $export_fields);
    $str_arr[] = '"'.date("d.m.y H:i:s").'"';
    foreach ($vars as $var_name) {
        if (isset($_POST[$var_name])) {
            $str_arr[] = '"'.$_POST[$var_name].'"';
        }
    }
    file_put_contents($export_file, implode(';', $str_arr)."\n", FILE_APPEND | LOCK_EX);
}

if ($email_off !== 1) {
    smtpmail($email, $subject, $message.'<br>'.$fields);
}

if ($client_email !== '') {
    $client_message === '' ? $message .= '<br>'.$fields : $message = $client_message;
    smtpmail($_POST[$client_email], $subject, $message, true);
}

if (isset($actions) && $actions !== '') {
    foreach ($actions as $action) {
        $fields_array = explode(',', $action['fields']);
        $action_fields = [];
        foreach ($fields_array as $field) {
            $field_array = explode('=', $field);
            $field_name = $field_array[0];
            if (count($field_array) === 2) {
                $field_name = $field_array[1];
            }
            if (isset($_POST[$field_array[0]])) {
                $action_fields[$field_name] = $_POST[$field_array[0]];
            }
        }
    
        if ($action['type'] === 'post') {
            $headers = stream_context_create(array(
          'http' => array(
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded' . PHP_EOL,
            'content' => http_build_query($action_fields),
          ),
        ));
            file_get_contents($action['url'], false, $headers);
        }
    }
}

function smtpmail($to, $subject, $content, $client_mode = false)
{
    global $success, $smtp, $host, $auth, $secure, $port, $username, $password, $from, $addreply, $charset, $cc, $bcc, $client_email, $client_message, $client_file;

    require_once('./class-phpmailer.php');
    $mail = new PHPMailer(true);
    if ($smtp) {
        $mail->IsSMTP();
    }
    try {
        $mail->SMTPDebug  = 0;
        $mail->Host       = $host;
        $mail->SMTPAuth   = $auth;
        $mail->SMTPSecure = $secure;
        $mail->Port       = $port;
        $mail->CharSet    = $charset;
        $mail->Username   = $username;
        $mail->Password   = $password;

        if ($username !== '') {
            $mail->SetFrom($username, $from);
        }
        if ($addreply !== '') {
            $mail->AddReplyTo($addreply, $from);
        }

        $to_array = explode(',', $to);
        foreach ($to_array as $to) {
            $mail->AddAddress($to);
        }
        if ($cc !== '') {
            $to_array = explode(',', $cc);
            foreach ($to_array as $to) {
                $mail->AddCC($to);
            }
        }
        if ($bcc !== '') {
            $to_array = explode(',', $bcc);
            foreach ($to_array as $to) {
                $mail->AddBCC($to);
            }
        }

        $mail->Subject = htmlspecialchars($subject);
        $mail->MsgHTML($content);

        $files_array = reArrayFiles($_FILES['file']);
        if ($files_array !== false) {
            foreach ($files_array as $file) {
                if ($file['error'] === UPLOAD_ERR_OK) {
                    $mail->AddAttachment($file['tmp_name'], $file['name']);
                }
            }
        }

        if ($client_file !== '' && $client_mode) {
            $mail->AddAttachment($client_file);
        }

        $mail->Send();
        if (!$client_mode) {
            echo('success');
        }
    } catch (phpmailerException $e) {
        echo $e->errorMessage();
    } catch (Exception $e) {
        echo $e->getMessage();
    }
}

function reArrayFiles(&$file_post)
{
    if ($file_post === null) {
        return false;
    }
    $files_array = array();
    $file_count = count($file_post['name']);
    $file_keys = array_keys($file_post);
    for ($i=0; $i<$file_count; $i++) {
        foreach ($file_keys as $key) {
            $files_array[$i][$key] = $file_post[$key][$i];
        }
    }
    return $files_array;
}

function SiteVerify($url)
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_TIMEOUT, 15);
    curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36");
    $curlData = curl_exec($curl);
    curl_close($curl);
    return $curlData;
}

function send_post($url, $fields)
{
    if ($curl = curl_init()) {
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
        curl_exec($curl);
        curl_close($curl);
    }
}