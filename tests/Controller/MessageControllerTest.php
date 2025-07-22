<?php
declare(strict_types=1);

namespace Controller;

use App\Message\SendMessage;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class MessageControllerTest extends WebTestCase
{
    use InteractsWithMessenger;
    
    /**
     * Helper method to safely get response content as string
     */
    private function getResponseContent(Response $response): string
    {
        $content = $response->getContent();
        $this->assertIsString($content, 'Response content should be a string');
        return $content;
    }
    
    function test_list(): void
    {
        $client = static::createClient();
        
        // Test basic list request
        $client->request('GET', '/messages');
        $this->assertResponseIsSuccessful();
        
        // Test response is JSON
        $response = $client->getResponse();
        $content = $this->getResponseContent($response);
        $this->assertJson($content);
        
        // Test response has expected structure
        $data = json_decode($content, true);
        $this->assertIsArray($data, 'Decoded JSON should be an array');
        $this->assertArrayHasKey('messages', $data);
        $this->assertIsArray($data['messages']);
    }
    
    function test_that_it_sends_a_message(): void
    {
        $client = static::createClient();
        $client->request('GET', '/messages/send', [
            'text' => 'Hello World',
        ]);

        $this->assertResponseIsSuccessful();
        // This is using https://packagist.org/packages/zenstruck/messenger-test
        $this->transport('sync')
            ->queue()
            ->assertContains(SendMessage::class, 1);
    }
    
    function test_list_with_status_filter(): void
    {
        $client = static::createClient();
        
        // Test with valid status parameter
        $client->request('GET', '/messages?status=sent');
        $this->assertResponseIsSuccessful();
        
        $response = $client->getResponse();
        $content = $this->getResponseContent($response);
        $this->assertJson($content);
        
        $data = json_decode($content, true);
        $this->assertIsArray($data, 'Decoded JSON should be an array');
        $this->assertArrayHasKey('messages', $data);
        $this->assertIsArray($data['messages']);
    }
    
    function test_list_with_empty_status(): void
    {
        $client = static::createClient();
        
        // Test with empty status parameter
        $client->request('GET', '/messages?status=');
        $this->assertResponseIsSuccessful();
        
        // Should return all messages (like no status parameter)
        $response = $client->getResponse();
        $content = $this->getResponseContent($response);
        $this->assertJson($content);
    }
    
    function test_list_with_sql_injection_attempt(): void
    {
        $client = static::createClient();
        
        // Test potential SQL injection
        $client->request('GET', "/messages?status='; DROP TABLE messages; --");
        $this->assertResponseIsSuccessful();
        
        // Should not crash and return valid JSON
        $response = $client->getResponse();
        $content = $this->getResponseContent($response);
        $this->assertJson($content);
        
        $data = json_decode($content, true);
        $this->assertIsArray($data, 'Decoded JSON should be an array');
        $this->assertArrayHasKey('messages', $data);
    }
    
    function test_send_message_without_text(): void
    {
        $client = static::createClient();
        
        // Test without text parameter
        $client->request('GET', '/messages/send');
        $this->assertResponseStatusCodeSame(400);
        
        $response = $client->getResponse();
        $content = $this->getResponseContent($response);
        $this->assertStringContainsString('Text is required', $content);
    }
    
    function test_send_message_with_empty_text(): void
    {
        $client = static::createClient();
        
        // Test with empty text parameter
        $client->request('GET', '/messages/send?text=');
        $this->assertResponseStatusCodeSame(400);
        
        $response = $client->getResponse();
        $content = $this->getResponseContent($response);
        $this->assertStringContainsString('Text is required', $content);
    }
    
    function test_send_message_with_numeric_text(): void
    {
        $client = static::createClient();
        
        // Test with numeric text (should be converted to string)
        $client->request('GET', '/messages/send?text=123');
        $this->assertResponseIsSuccessful();
        
        // Should successfully send the message "123"
        $this->transport('sync')
            ->queue()
            ->assertContains(SendMessage::class, 1);
    }
    
    function test_send_message_with_boolean_text(): void
    {
        $client = static::createClient();
        
        // Test with boolean text (should be converted to string)
        $client->request('GET', '/messages/send?text=true');
        $this->assertResponseIsSuccessful();
        
        $this->transport('sync')
            ->queue()
            ->assertContains(SendMessage::class, 1);
    }
}