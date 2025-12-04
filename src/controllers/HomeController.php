<?php

class HomeController
{
    public function __construct(private array $config)
    {
    }

    /**
     * Display the login form
     * GET /
     */
    public function indexAction(): void
    {
        // Check if form access is disabled
        if (!$this->config['enable_form_access']) {
            JsonResponse::error('Form access is disabled. Please use POST method to access the API.', 403);
            return;
        }

        // Display the login form
        $this->renderLoginForm();
    }

    /**
     * Render the login form HTML
     */
    private function renderLoginForm(): void
    {
        ?>
<!DOCTYPE html>
<html>
<head>
    <title>Connexion Kelio</title>
    <meta charset="UTF-8">
</head>
<body>
    <form method="POST">
        <input type="text" name="username" placeholder="Identifiant" required><br>
        <input type="password" name="password" placeholder="Mot de passe" required><br>
        <input type="submit" value="Connexion">
    </form>
</body>
</html>
        <?php
    }
}
