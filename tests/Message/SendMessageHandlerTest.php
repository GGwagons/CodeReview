<?php
declare(strict_types=1);

namespace Message;

use App\Message\SendMessage;
use App\Message\SendMessageHandler;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SendMessageHandlerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->entityManager = $entityManager;
        
        // Start a transaction that we'll rollback after each test
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        // Rollback the transaction to clean up any changes
        $this->entityManager->rollback();
        parent::tearDown();
    }

    public function test_it_creates_a_message(): void
    {
        $container = self::getContainer();

        // Get the handler and repository
        /** @var SendMessageHandler $handler */
        $handler = $container->get(SendMessageHandler::class);
        /** @var MessageRepository $repository */
        $repository = $container->get(MessageRepository::class);

        // Create and send a message
        $sendMessage = new SendMessage('Test message');
        $handler($sendMessage);

        // Assert that the message was saved
        $messages = $repository->findBy(['text' => 'Test message']);
        $this->assertNotEmpty($messages);
        $this->assertSame('sent', $messages[0]->getStatus());
        $this->assertNotNull($messages[0]->getUuid());
        $this->assertInstanceOf(\DateTime::class, $messages[0]->getCreatedAt());
    }
}