<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class W04ThrottleTest extends TestCase
{
    private mysqli $db;

    protected function setUp(): void
    {
        $this->db = new mysqli((string)getenv('DB_HOST'), (string)getenv('DB_USER'), (string)getenv('DB_PASSWORD'), (string)getenv('DB_NAME'));
        self::assertSame(CRM_TEST_DATABASE, $this->db->query('SELECT DATABASE()')->fetch_row()[0]);
        self::assertSame('crm_test_native_20261118', CRM_TEST_DATABASE);
        $this->db->query('DELETE FROM login_throttle');
        $this->db->query('DELETE FROM security_events');
    }

    protected function tearDown(): void
    {
        $this->db->query('DELETE FROM login_throttle');
        $this->db->query('DELETE FROM security_events');
        $this->db->close();
    }

    public function testFifthFailureThrottlesAcrossFreshSessionsAndLogsNoSecrets(): void
    {
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $page = $this->request('/login.php');
            $token = $this->token($page['body']);
            $responses[] = $this->request('/login.php', 'POST', [
                'username' => 'w01-fake-user',
                'password' => 'W04-Fictional-Secret',
                '_csrf_token' => $token,
            ], $page['cookie']);
        }

        self::assertStringContainsString('Λάθος στοιχεία σύνδεσης!', $responses[4]['body']);
        $newSession = $this->request('/login.php');
        $throttled = $this->request('/login.php', 'POST', [
            'username' => 'w01-fake-user',
            'password' => 'W04-Fictional-Secret',
            '_csrf_token' => $this->token($newSession['body']),
        ], $newSession['cookie']);
        self::assertSame(429, $throttled['status']);
        self::assertStringContainsString('Λάθος στοιχεία σύνδεσης!', $throttled['body']);
        $this->db->query("UPDATE login_throttle SET locked_until=UTC_TIMESTAMP()-INTERVAL 1 SECOND, window_started_at=UTC_TIMESTAMP()-INTERVAL 16 MINUTE");
        $page = $this->request('/login.php');
        $success = $this->request('/login.php', 'POST', ['username'=>'w01-fake-user','password'=>'W01-Fictional-Password-2026','_csrf_token'=>$this->token($page['body'])], $page['cookie']);
        self::assertSame(302, $success['status']);
        $home = $this->request('/index.php', 'GET', [], $success['cookie']);
        $logout = $this->request('/logout.php', 'POST', ['_csrf_token'=>$this->token($home['body'])], $success['cookie']);
        self::assertSame(302, $logout['status']);
        $types = array_column($this->db->query('SELECT event_type FROM security_events')->fetch_all(MYSQLI_ASSOC), 'event_type');
        foreach (['login_failure','login_throttled','login_success','logout'] as $type) self::assertContains($type, $types);

        $stmt = $this->db->prepare("SELECT event_type, result, metadata FROM security_events WHERE event_type IN ('login_failure','login_throttled') ORDER BY id DESC LIMIT 10");
        $stmt->execute();
        $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        self::assertNotEmpty($events);
        $serialized = json_encode($events);
        self::assertStringNotContainsString('W04-Fictional-Secret', $serialized);
        self::assertStringNotContainsString('PHPSESSID', $serialized);
    }

    public function testThirtyFailuresFromOneSourceThrottleDifferentUsernames(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $page = $this->request('/login.php');
            $this->request('/login.php', 'POST', [
                'username' => 'w04-source-user-' . $i,
                'password' => 'W04-Source-Fictional-Secret',
                '_csrf_token' => $this->token($page['body']),
            ], $page['cookie']);
        }
        $page = $this->request('/login.php');
        $response = $this->request('/login.php', 'POST', [
            'username' => 'w04-source-user-last',
            'password' => 'W04-Source-Fictional-Secret',
            '_csrf_token' => $this->token($page['body']),
        ], $page['cookie']);
        self::assertSame(429, $response['status']);
    }

    public function testConcurrentCountersAndRetention(): void
    {
        require_once CRM_TEST_APP_ROOT . '/includes/security_events.php';
        $identity = hash('sha256', 'w04-concurrency-identity');
        $source = hash('sha256', 'w04-concurrency-source');
        $code = 'require ' . var_export(CRM_TEST_APP_ROOT . '/includes/security_events.php', true) . '; $db=new mysqli(getenv("DB_HOST"),getenv("DB_USER"),getenv("DB_PASSWORD"),getenv("DB_NAME")); $state=crm_login_begin($db,' . var_export($identity,true) . ',' . var_export($source,true) . '); if (!$state["limited"]) crm_login_failure($db,' . var_export($identity,true) . ',' . var_export($source,true) . '); $db->commit();';
        $children = [];
        for ($i=0; $i<10; $i++) {
            $process = proc_open([PHP_BINARY, '-r', $code], [0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']], $pipes);
            self::assertIsResource($process);
            $children[] = $process;
        }
        foreach ($children as $process) self::assertSame(0, proc_close($process));
        $row = $this->db->query("SELECT failure_count FROM login_throttle WHERE scope_type='identity' AND scope_hash='$identity'")->fetch_assoc();
        self::assertSame(5, (int)$row['failure_count']);
        $this->db->query("INSERT INTO security_events (occurred_at,event_type,result) VALUES (UTC_TIMESTAMP()-INTERVAL 31 DAY,'login_failure','failure'),(UTC_TIMESTAMP(),'login_success','success')");
        crm_security_cleanup($this->db);
        self::assertSame(1, (int)$this->db->query('SELECT COUNT(*) FROM security_events')->fetch_row()[0]);
    }

    private function request(string $path, string $method='GET', array $data=[], string $cookie=''): array
    {
        $headers = $cookie === '' ? [] : ['Cookie: ' . $cookie];
        if ($method === 'POST') $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $context = stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$method==='POST'?http_build_query($data):'','ignore_errors'=>true,'follow_location'=>0]]);
        $body = file_get_contents(rtrim((string)getenv('CRM_BASE_URL'),'/').$path,false,$context) ?: '';
        preg_match('/PHPSESSID=([^;]+)/', implode("\n",$http_response_header ?? []), $matches);
        preg_match('/HTTP\/\S+ (\d+)/', ($http_response_header ?? [])[0] ?? '', $status);
        return ['status'=>(int)($status[1]??0),'body'=>$body,'cookie'=>isset($matches[1])?'PHPSESSID='.$matches[1]:$cookie];
    }

    private function token(string $html): string
    {
        $matched = preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $html, $matches);
        self::assertSame(1, $matched, 'Login page should contain a CSRF token.');
        return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
