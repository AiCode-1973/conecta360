<?php
/**
 * AuthController — Endpoints de Autenticação
 *
 * Responsabilidades exclusivas:
 *   - Receber a requisição HTTP
 *   - Validar e sanitizar INPUTS (primeiro nível)
 *   - Chamar AuthService para a lógica de negócio
 *   - Retornar a View ou Redirect correto
 *   - NUNCA conter lógica de autenticação diretamente
 *
 * ROTAS mapeadas (ver routes/web.php):
 *   GET  /login                → showLogin()
 *   POST /login                → login()          [CsrfMiddleware]
 *   POST /logout               → logout()         [AuthMiddleware]
 *   GET  /register             → showRegister()
 *   POST /register             → register()       [CsrfMiddleware]
 *   GET  /forgot-password      → showForgotPassword()
 *   POST /forgot-password      → forgotPassword() [CsrfMiddleware]
 *   GET  /reset-password       → showResetPassword()
 *   POST /reset-password       → resetPassword()  [CsrfMiddleware]
 *   GET  /verify-email         → verifyEmail()
 *
 * VALIDAÇÃO DE INPUT (antes de qualquer chamada ao serviço):
 *   - Sanitizar: htmlspecialchars, trim, filter_var
 *   - Validar: formato de e-mail, comprimento mínimo de senha, campos obrigatórios
 *   - Se inválido → retornar formulário com flash de erro (sem redirecionar)
 *
 * @package Conecta360\Modules\Auth
 */

declare(strict_types=1);

namespace Conecta360\Modules\Auth;

use Conecta360\Services\CsrfService;

final class AuthController
{
    public function __construct(private readonly AuthService $authService) {}

    /** GET /login — exibe o formulário de login com token CSRF */
    public function showLogin(): void { /* ... */ }

    /**
     * POST /login — processa o login
     * Inputs validados: email, password, remember_me
     * Em caso de sucesso: redirect para dashboard ou ?redirect= URL
     * Em caso de falha: redirect de volta com flash message genérica
     */
    public function login(): void { /* ... */ }

    /** POST /logout — destroi sessão e redireciona para /login */
    public function logout(): void { /* ... */ }

    /** GET /register — exibe formulário de cadastro */
    public function showRegister(): void { /* ... */ }

    /** POST /register — cria usuário com status='invited', envia e-mail */
    public function register(): void { /* ... */ }

    /** GET /forgot-password — exibe formulário de recuperação */
    public function showForgotPassword(): void { /* ... */ }

    /** POST /forgot-password — envia link de reset (resposta sempre genérica) */
    public function forgotPassword(): void { /* ... */ }

    /** GET /reset-password?token=... — exibe formulário de nova senha */
    public function showResetPassword(): void { /* ... */ }

    /** POST /reset-password — redefine senha, revoga sessões, força re-login */
    public function resetPassword(): void { /* ... */ }

    /** GET /verify-email?token=... — ativa a conta, redirect para login */
    public function verifyEmail(): void { /* ... */ }

    /** Redireciona com mensagem flash (salva em $_SESSION['flash']) */
    private function redirectWithFlash(string $url, string $type, string $message): never { /* ... */ }

    /** Sanitiza e-mail: trim + strtolower + filter_var */
    private function sanitizeEmail(string $email): string { /* ... */ }
}
