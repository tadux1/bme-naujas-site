<?php
// BME Services — kontaktų formos siuntimas į info@servicesbme.com (be trečių šalių, tas pats domenas).
// Reikalauja PHP + veikiančio mail() (Hostinger). Nuo botų — honeypot laukas.
declare(strict_types=1);

$TO   = 'info@servicesbme.com';
$FROM = 'info@servicesbme.com'; // egzistuojantis to paties domeno paštas → patikimas pristatymas

$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos((string)$_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
$back = $_SERVER['HTTP_REFERER'] ?? '/contact/';

function respond(bool $ok, bool $isAjax, string $back): void
{
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        if (!$ok) {
            http_response_code(422);
        }
        echo json_encode(['ok' => $ok]);
    } else {
        $sep = (strpos($back, '?') === false) ? '?' : '&';
        header('Location: ' . $back . $sep . 'sent=' . ($ok ? '1' : '0'));
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, $isAjax, $back);
}

// honeypot: botai užpildo paslėptą lauką → tyliai "pavyko", laiško nesiunčiam
if (trim((string)($_POST['company_website'] ?? '')) !== '') {
    respond(true, $isAjax, $back);
}

$name    = trim((string)($_POST['name'] ?? ''));
$company = trim((string)($_POST['company'] ?? ''));
$email   = trim((string)($_POST['email'] ?? ''));
$phone   = trim((string)($_POST['phone'] ?? ''));
$service = trim((string)($_POST['service'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($name === '' || $email === '' || $message === '') {
    respond(false, $isAjax, $back);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, $isAjax, $back);
}

// header-injection apsauga (CR/LF šalinimas iš į antraštes patenkančių reikšmių)
$oneLine    = static fn (string $s): string => preg_replace('/[\r\n]+/', ' ', $s);
$replyEmail = $oneLine($email);

$subject = 'Website inquiry — ' . ($service !== '' ? $service : 'General');
$body = implode("\n", [
    'New inquiry from servicesbme.com',
    '',
    'Name:    ' . $name,
    'Company: ' . $company,
    'Email:   ' . $email,
    'Phone:   ' . $phone,
    'Service: ' . $service,
    '',
    'Message:',
    $message,
]) . "\n";

$headers = implode("\r\n", [
    'From: BME Services <' . $FROM . '>',
    'Reply-To: ' . $replyEmail,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'X-Mailer: BME-web',
]);
$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

$sent = @mail($TO, $encodedSubject, $body, $headers);
respond((bool)$sent, $isAjax, $back);
