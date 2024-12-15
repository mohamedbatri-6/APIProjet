<?php

namespace App\Controller\Api;

use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\SerializerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api/users', name: 'api_users_')]
class UserController extends AbstractController
{
    private $userRepository;
    private $entityManager;
    private $serializer;

    // Injecting dependencies into the controller
    public function __construct(
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer
    ) {
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    // GET /api/users: Get a list of users with pagination
    #[Route("/", name: "index", methods: ["GET"])]
    public function index(Request $request): JsonResponse
    {
        $page = $request->query->get('page', 1); 
        $limit = 10;

        $users = $this->userRepository->findBy([], null, $limit, ($page - 1) * $limit);

        if (empty($users)) {
            return new JsonResponse(['message' => 'No users found'], 404);
        }

        $data = $this->serializer->serialize($users, 'json', ['groups' => 'user:read']);
        return new JsonResponse($data, 200, [], true);
    }

    // POST /api/users: Create a new user
    #[Route("/", name: "create", methods: ["POST"])]
    public function create(Request $request, ValidatorInterface $validator, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = new User();
        $user->setName($data['name'] ?? null);
        $user->setEmail($data['email'] ?? null);
        $user->setPassword($data['password'] ?? null);  

        // Validate the user data
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], 400);
        }

        // Hash the password before saving
        $hashedPassword = $passwordHasher->hashPassword($user, $user->getPassword());
        $user->setPassword($hashedPassword);

        // Persist the user in the database
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'User created!'], 201);
    }

    // GET /api/users/{id}: Show a specific user by ID
    #[Route("/{id}", name: "show", methods: ["GET"])]
    public function show($id): JsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['message' => 'User not found'], 404);
        }

        $data = $this->serializer->serialize($user, 'json', ['groups' => 'user:read']);
        return new JsonResponse($data, 200, [], true);
    }

    // PUT /api/users/{id}: Update an existing user by ID
    #[Route("/{id}", name: "update", methods: ["PUT"])]
    public function update(Request $request, $id, ValidatorInterface $validator, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['message' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        // Validate the updated data
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            return new JsonResponse(['message' => 'Name, email, and password are required.'], 400);
        }

        $user->setName($data['name']);
        $user->setEmail($data['email']);

        // Hash the updated password
        $hashedPassword = $passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        // Validate the updated user data
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], 400);
        }

        // Persist the changes in the database
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'User updated!'], 200);
    }

    // DELETE /api/users/{id}: Delete a user by ID
    #[Route("/{id}", name: "delete", methods: ["DELETE"])]
    public function delete($id): JsonResponse
    {
        $user = $this->userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['message' => 'User not found'], 404);
        }

        // Remove the user from the database
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'User deleted!'], 200);
    }
}
