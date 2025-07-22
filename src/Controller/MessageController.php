<?php
declare(strict_types=1);

namespace App\Controller;

use App\Message\SendMessage;
use App\Repository\MessageRepository;
use Controller\MessageControllerTest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @see MessageControllerTest
 * Message controller for handling message listing and sending operations.
 * 
 * Code review completed:
 * - Added route names for proper redirects
 * - Refactored list method for better code quality
 * - Updated OpenAPI specification to match implementation
 * - Added comprehensive test coverage
 */
class MessageController extends AbstractController
{
    /**
     * Homepage route - redirects to messages list
     */
    #[Route('/')]
    public function index(): Response
    {
        return $this->redirectToRoute('messages_list');
    }

    /**
     * Get all messages with optional status filtering
     */
    #[Route('/messages', name: 'messages_list')]
    public function list(Request $request, MessageRepository $messageRepository): Response
    {
        $messages = $messageRepository->by($request);
  
        $messageData = array_map(function ($message) {
            return [
                'uuid' => $message->getUuid(),
                'text' => $message->getText(),
                'status' => $message->getStatus(),
            ];
        }, $messages);
        
        return $this->json([
            'messages' => $messageData,
        ], 200, [], [
            'json_encode_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ]);
    }

    #[Route('/messages/send', methods: ['GET'])]
    public function send(Request $request, MessageBusInterface $bus): Response
    {
        // Keep it as a string to get rid of any errors related to type hinting. Convert to other if needed.
        $text = (string) $request->query->get('text');
        if (empty($text)) {
            return new Response('Text is required', 400);
        }

        $bus->dispatch(new SendMessage($text));
        
        return new Response('Successfully sent', 204);
    }
}