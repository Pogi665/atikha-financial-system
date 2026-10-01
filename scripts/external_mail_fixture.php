<?php
/** Test transport only. No sockets, credentials, or external email are used. */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
class FixtureExternalSMTP extends ExternalMailSMTP
{
    public string $mode;
    public string $mime = '';
    private bool $open = false;
    public function __construct(string $mode = 'sent') { $this->mode = $mode; }
    public function connect($host, $port = null, $timeout = 30, $options = []) { $this->open = true; return true; }
    public function connected() { return $this->open; }
    public function hello($host = '') { return true; }
    public function getServerExt($name) { return $name === 'STARTTLS' ? true : ($name === 'AUTH' ? ['LOGIN'] : false); }
    public function startTLS() { return true; }
    public function authenticate($username, $password, $authtype = null, $OAuth = null)
    {
        if ($this->mode === 'auth') { $this->setError('Fixture authentication rejection', '', '535'); return false; }
        return true;
    }
    public function mail($from) { return true; }
    public function recipient($address, $dsn = '') { return true; }
    public function data($msg_data)
    {
        $this->dataAttempted = true;
        $this->mime = $msg_data;
        if ($this->mode === 'reject') { $this->dataRejected = true; $this->setError('Fixture rejection', '', '550'); return false; }
        if ($this->mode === 'unknown') { $this->setError('Fixture lost acknowledgement'); return false; }
        $this->dataAccepted = true;
        return true;
    }
    public function getLastTransactionID() { return 'fixture'; }
    public function quit($close_on_error = true)
    {
        if ($this->mode === 'quit') { throw new RuntimeException('Fixture lost QUIT'); }
        return true;
    }
    public function close() { $this->open = false; }
}

function fixture_external_send(array $message): array
{
    $transport = new FixtureExternalSMTP($_SESSION['fixture_smtp_mode'] ?? 'sent');
    $result = send_external_email($message, $transport);
    if (defined('EXTERNAL_FIXTURE_CAPTURE') && $transport->mime !== '') {
        file_put_contents(EXTERNAL_FIXTURE_CAPTURE, $transport->mime);
    }
    return $result;
}
