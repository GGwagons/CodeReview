# Backend Code Challenge - Completion Report

## Project Overview

This document details all the improvements made to the project. The project is a messaging API with endpoints for listing and sending messages.

## Original Requirements

- [ ] Make `vendor/bin/phpstan` pass without errors
- [ ] Make `vendor/bin/phpunit` pass without errors  
- [ ] Handle all open TODOs in the project

## What We Accomplished

### 1. **Fixed PHPStan Type Safety Issues**

#### **Problem**: Type safety violations causing PHPStan to fail
#### **Solutions Implemented**:

**A. Fixed Controller Type Issues (`src/Controller/MessageController.php`)**
```php
// BEFORE (Line 57):
$text = $request->query->get('text');

// AFTER:
$text = (string) $request->query->get('text');
```
**Why**: `$request->query->get()` returns `mixed`, but `SendMessage` constructor expects `string`. Type casting ensures compatibility.

**B. Fixed Repository Return Types (`src/Repository/MessageRepository.php`)**
```php
// BEFORE:
public function by(Request $request): array

// AFTER:
/**
 * @return Message[]
 */
public function by(Request $request): array
```
**Why**: PHPStan needed to know the array contains `Message` objects, not just any array.

**C. Fixed SQL Injection Vulnerability**
```php
// BEFORE (DANGEROUS):
sprintf("SELECT m FROM App\Entity\Message m WHERE m.status = '%s'", $status)

// AFTER (SECURE):
->createQuery("SELECT m FROM App\Entity\Message m WHERE m.status = :status")
->setParameter('status', $status)
```
**Why**: The original code was vulnerable to SQL injection attacks. Parameter binding prevents this security issue.

**D. Fixed Test Type Annotations (`tests/`)**
```php
// BEFORE:
$messages = self::getContainer()->get(MessageRepository::class);

// AFTER:
/** @var MessageRepository $messages */
$messages = self::getContainer()->get(MessageRepository::class);
```
**Why**: PHPStan didn't know the service container returns specific types.

### 2. **Fixed PHPUnit Test Issues**

#### **Problem**: Incomplete tests and test isolation issues

**A. Completed Incomplete Test (`tests/Repository/MessageRepositoryTest.php`)**
```php
// BEFORE:
$this->markTestIncomplete('the Controller-Action needs tests');

// AFTER:
$client = static::createClient();
$client->request('GET', '/messages');
$this->assertResponseIsSuccessful();
// ... comprehensive assertions
```

**B. Implemented Database Transaction Isolation**
```php
// Added to tests:
protected function setUp(): void
{
    $this->entityManager->beginTransaction();
}

protected function tearDown(): void
{
    $this->entityManager->rollback();
}
```
**Why**: Ensures tests don't interfere with each other by rolling back database changes.

**C. Created Comprehensive Test Coverage**
- Basic endpoint functionality
- Error handling (400 responses)
- Security testing (SQL injection attempts)
- Type conversion testing (numeric/boolean inputs)
- Edge cases (empty/missing parameters)

### 3. **Created SendMessageHandler Tests**

#### **File**: `tests/Message/SendMessageHandlerTest.php`

**New test covering**:
- Message creation and persistence
- UUID generation
- Status setting
- Timestamp creation
- Database isolation using transactions

```php
public function test_it_creates_a_message(): void
{
    $sendMessage = new SendMessage('Test message');
    $handler($sendMessage);
    
    $messages = $repository->findBy(['text' => 'Test message']);
    $this->assertNotEmpty($messages);
    $this->assertSame('sent', $messages[0]->getStatus());
    $this->assertNotNull($messages[0]->getUuid());
}
```

### 4. **Fixed OpenAPI Specification**

#### **File**: `openapi.yaml`

**A. Added Missing Error Response**
```yaml
# ADDED:
'400':
  description: Bad request - text parameter is missing or empty
  content:
    text/plain:
      schema:
        type: "string"
      example: "Text is required"
```

**B. Fixed Incomplete Examples**
```yaml
# BEFORE:
messages:
  - text: "Hello, World!"
    status: "read"

# AFTER:
messages:
  - uuid: "1f063b8b-a175-6a66-a91a-d58145a4ded4"
    text: "Hello, World!"
    status: "read"
```
**Why**: Examples should match the actual API response structure.

### 5. **Enhanced Message Entity**

#### **File**: `src/Entity/Message.php`

**A. Added Field Validation**
```php
#[Assert\NotBlank(message: 'Message text cannot be empty')]
private ?string $text = null;
```

**B. Improved Status Field**
```php
// Added constants:
public const STATUS_PENDING = 'pending';
public const STATUS_SENT = 'sent';
public const STATUS_READ = 'read';

// Added validation:
#[Assert\Choice(choices: [self::STATUS_PENDING, self::STATUS_SENT, self::STATUS_READ])]
private string $status = self::STATUS_PENDING;
```

**C. Fixed CreatedAt Field**
```php
// Made nullable to prevent initialization issues:
private ?DateTime $createdAt = null;
```

### 6. **Controller Improvements**

#### **File**: `src/Controller/MessageController.php`

**A. Fixed Route Naming**
```php
// BEFORE:
#[Route('/messages')]

// AFTER:
#[Route('/messages', name: 'messages_list')]
```
**Why**: The `index()` method was trying to redirect to `'messages_list'` but the route had no name.

**B. Refactored List Method**
```php
// BEFORE:
foreach ($messages as $key=>$message) {
    $messages[$key] = [
        'uuid' => $message->getUuid(),
        'text' => $message->getText(),
        'status' => $message->getStatus(),
    ];
}
return new Response(json_encode([
    'messages' => $messages,
], JSON_THROW_ON_ERROR), headers: ['Content-Type' => 'application/json']);

// AFTER:
$messageData = array_map(function ($message) {
    return [
        'uuid' => $message->getUuid(),
        'text' => $message->getText(),
        'status' => $message->getStatus(),
    ];
}, $messages);

return $this->json([
    'messages' => $messageData,
]);
```

**Improvements**:
- Better variable naming (`$messageRepository` vs `$messages`)
- Cleaner code structure using `array_map()`
- Symfony best practices with `$this->json()`
- Automatic error handling
- No variable reuse confusion

### 13. **Fixed DelayStamp Deprecation Warnings**

#### **Problem**: DelayStamp deprecation warnings from zenstruck/messenger-test package
#### **Solution**: Enabled DelayStamp support in test transport configuration

**Implementation** (`config/packages/messenger.yaml`):
```yaml
    # BEFORE:
    when@test:
        framework:
            messenger:
                transports:
                    async: test://
                    sync: test://

    # AFTER:
    when@test:
        framework:
            messenger:
                transports:
                    async: 'test://?support_delay_stamp=true'
                    sync: 'test://?support_delay_stamp=true'
```
## Setup Instructions

```bash
  # Install dependencies and setup database:
  just install
  
  # Run
  just start
  
  # Test
  just test
```
