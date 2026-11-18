<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Regression: submit the actual new-collaborator form, including hidden values. */
final class W08CollaboratorTest extends TestCase
{
    public function testNewCollaboratorFromRenderedFormIsSavedAndListed(): void
    {
        self::assertTrue(CRM_TEST_DB_SAFE);
        $db = new mysqli((string)getenv('DB_HOST'), (string)getenv('DB_USER'), (string)getenv('DB_PASSWORD'), (string)getenv('DB_NAME'));
        self::assertSame(CRM_TEST_DATABASE, $db->query('SELECT DATABASE()')->fetch_row()[0]);
        $name = 'W08 Collaborator ' . bin2hex(random_bytes(6));
        try {
            [$headers, $body] = $this->request('/login.php');
            preg_match('/name="_csrf_token"\s+value="([^"]+)"/', $body, $matches);
            [$headers] = $this->request('/login.php', $this->cookie($headers), [
                'username' => 'w01-fake-user', 'password' => 'W01-Fictional-Password-2026', '_csrf_token' => $matches[1],
            ]);
            self::assertStringContainsString('302', $headers[0]);
            $cookie = $this->cookie($headers);
            [, $body] = $this->request('/partner_form.php', $cookie);
            $doc = new DOMDocument();
            @$doc->loadHTML('<?xml encoding="UTF-8">' . $body);
            $fields = [];
            foreach ($doc->getElementsByTagName('input') as $input) {
                if ($input->hasAttribute('name')) $fields[$input->getAttribute('name')] = $input->getAttribute('value');
            }
            $fields['fullname'] = $name;
            $fields['phone'] = '';
            $fields['email'] = '';
            [$headers] = $this->request('/partner_form.php', $cookie, $fields);
            self::assertStringContainsString('302', $headers[0]);
            $stmt = $db->prepare('SELECT COUNT(*) FROM partners WHERE fullname=?');
            $stmt->execute([$name]);
            self::assertSame(1, $stmt->get_result()->fetch_row()[0], 'Submitting the rendered new form must insert a collaborator.');
            [, $list] = $this->request('/partners.php?search=' . rawurlencode($name), $cookie);
            self::assertStringContainsString('<td>' . $name . '</td>', $list);
            self::assertDoesNotMatchRegularExpression('/Deprecated|Warning|Fatal error/', strip_tags($body));
        } finally {
            $stmt = $db->prepare('DELETE FROM partners WHERE fullname=?');
            $stmt->execute([$name]);
            $db->close();
        }
    }

    private function cookie(array $headers): string
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Set-Cookie: PHPSESSID=') === 0) {
                return explode(';', substr($header, strlen('Set-Cookie: ')))[0];
            }
        }
        self::fail('Expected a session cookie.');
    }

    private function request(string $path, string $cookie = '', ?array $data = null): array
    {
        $context = stream_context_create(['http' => [
            'method' => $data === null ? 'GET' : 'POST',
            'header' => 'Cookie: ' . $cookie . "\r\nContent-Type: application/x-www-form-urlencoded",
            'content' => $data === null ? '' : http_build_query($data),
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);
        $body = file_get_contents(rtrim((string)getenv('CRM_BASE_URL'), '/') . $path, false, $context);
        return [$http_response_header ?? [], (string)$body];
    }
}
