<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Services\JwtService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles all /api/auth/* endpoints.
 * Mirrors the behaviour of the original Node.js auth.js router.
 */
class AuthController extends Controller
{
    public function __construct(
        private AuthService  $authService,
        private JwtService   $jwtService,
        private EmailService $emailService,
    ) {}

    // ── POST /api/auth/login ───────────────────────────────────────────────────

    public function login(Request $request): JsonResponse
    {
        $username = $request->input('user');
        $password = $request->input('password');

        if (!$username || !$password) {
            return response()->json(['error' => '"user" and "password" are required'], 400);
        }

        $user = User::where('user', $username)->first();

        if (!$user) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        if (!$this->authService->verifyPassword($password, $user->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $roles   = (array) ($user->roles ?? []);
        $payload = [
            '_id'   => (string) $user->_id,
            'id'    => $user->id,
            'name'  => $user->name,
            'user'  => $user->user,
            'role'  => $roles,
            'roles' => $roles,
        ];

        $token = $this->jwtService->sign($payload);

        return response()
            ->json(['message' => 'Login successful', 'user' => $payload])
            ->cookie(
                'token',
                $token,
                (int) ($this->jwtService->getTtlMs() / 60000), // minutes
                '/',
                null,    // domain — let PHP infer
                true,    // secure (HTTPS)
                true,    // httpOnly
                false,
                'None'   // sameSite
            );
    }

    // ── GET /api/auth/me ───────────────────────────────────────────────────────

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->get('_jwt_payload')]);
    }

    // ── POST /api/auth/logout ──────────────────────────────────────────────────

    public function logout(): JsonResponse
    {
        return response()
            ->json(['message' => 'Logged out successfully'])
            ->withoutCookie('token');
    }

    // ── POST /api/auth/users ───────────────────────────────────────────────────

    public function createUser(Request $request): JsonResponse
    {
        $id        = $request->input('id');
        $name      = $request->input('name');
        $email     = $request->input('email');
        $username  = $request->input('user');
        $password  = $request->input('password');
        $roleInput = $request->input('roles') ?? $request->input('role');

        if (!$id || !$name || !$email || !$username || !$password || !$roleInput) {
            return response()->json(['error' => 'id, name, email, user, password, and role(s) are required'], 400);
        }

        $roleValues   = (array) $roleInput;
        $allowedRoles = ['vendedor', 'administrador'];
        $invalidRoles = array_diff($roleValues, $allowedRoles);

        if ($invalidRoles) {
            return response()->json(['error' => 'Invalid role(s): ' . implode(', ', $invalidRoles)], 400);
        }

        $existing = User::where('user', $username)->orWhere('id', $id)->first();
        if ($existing) {
            return response()->json(['error' => 'User with this username or id already exists'], 409);
        }

        $user = User::create([
            'id'       => $id,
            'name'     => $name,
            'email'    => $email,
            'user'     => $username,
            'password' => $this->authService->encryptPassword($password),
            'roles'    => array_values(array_unique($roleValues)),
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user'    => ['id' => $user->id, 'name' => $user->name, 'user' => $user->user, 'roles' => $user->roles],
        ], 201);
    }

    // ── DELETE /api/auth/users/{id} ────────────────────────────────────────────

    public function deleteUser(Request $request, string $userId): JsonResponse
    {
        $currentUserId = $request->get('_jwt_payload')['id'] ?? null;

        if ($userId === $currentUserId) {
            return response()->json(['error' => 'Cannot delete your own account'], 400);
        }

        $user = User::where('id', $userId)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
            'user'    => ['id' => $user->id, 'name' => $user->name, 'user' => $user->user],
        ]);
    }

    // ── GET /api/auth/users ────────────────────────────────────────────────────

    public function listUsers(): JsonResponse
    {
        $users = User::all()->map(fn($u) => [
            'id'    => $u->id,
            'name'  => $u->name,
            'email' => $u->email,
            'user'  => $u->user,
            'roles' => $u->roles,
        ]);

        return response()->json($users);
    }

    // ── GET /api/auth/users/{id} ───────────────────────────────────────────────

    public function getUserById(string $userId): JsonResponse
    {
        $user = User::where('id', $userId)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json(['email' => $user->email]);
    }

    // ── POST /api/auth/sendRestoreEmail ────────────────────────────────────────

    public function sendRestoreEmail(Request $request): JsonResponse
    {
        $email = $request->input('email');
        $user  = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $link = "https://lacasitadelsabor.com/auth/restore/{$user->id}";

        $this->emailService->sendEmailWithAttachment([
            'to'      => [$email],
            'subject' => 'Recuperar Contraseña',
            'html'    => "<p>Hola {$user->name},</p>"
                       . "<p>Este es el link para recuperar tu contraseña:</p>"
                       . "<p><a href=\"{$link}\">Recuperar Contraseña</a></p>"
                       . "<p>Saludos,<br>El equipo de Casita del Sabor</p>",
        ], 'security');

        return response()->json(['message' => 'Recovery email sent']);
    }

    // ── POST /api/auth/restore ─────────────────────────────────────────────────

    public function restorePassword(Request $request): JsonResponse
    {
        $email    = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            return response()->json(['error' => '"email" and "password" are required'], 400);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->update(['password' => $this->authService->encryptPassword($password)]);

        return response()->json([
            'message' => 'Password restored successfully',
            'user'    => ['id' => $user->id, 'name' => $user->name, 'user' => $user->user, 'roles' => $user->roles],
        ]);
    }
}
