<?php

class LoginController extends ActionController
{
    public function __construct(
        private Storage $storage,
        private Auth $auth,
        private KelioClient $kelioClient,
        private TimeCalculator $timeCalculator,
        private AuthContext $authContext,
        private array $config
    ) {
    }

    /**
     * Login action - Handle user authentication
     * POST /?action=login
     *
     * Note: Authentication is already validated by AuthMiddleware
     * Middleware connects to Kelio ONCE and provides jsessionid
     * This method fetches fresh data from Kelio
     */
    public function loginAction(): void
    {
        if (!$this->authContext->isAuthenticated()) {
            JsonResponse::error('Not authenticated', 401);
            return;
        }

        // Both token and credentials authentication now fetch fresh data
        $this->fetchFreshData();
    }

    /**
     * Update preferences action
     * POST /?action=update_preferences
     *
     * Requires authentication (already validated by middleware)
     */
    public function updatePreferencesAction(): void
    {
        if (!$this->authContext->isAuthenticated()) {
            JsonResponse::error('Not authenticated', 401);
            return;
        }

        $username = $this->authContext->getUsername();

        $preferences = [];
        $errors = [];

        if (isset($_POST['theme'])) {
            $theme = trim($_POST['theme']);
            // Validate theme (alphanumeric, underscore, dash only)
            if (preg_match('/^[a-zA-Z0-9_-]+$/', $theme) && strlen($theme) <= 50) {
                $preferences['theme'] = $theme;
            } else {
                $errors['theme'] = 'Invalid theme format. Only alphanumeric, underscore and dash allowed (max 50 chars)';
            }
        }

        if (isset($_POST['minutes_objective'])) {
            $minutesObjective = intval($_POST['minutes_objective']);
            // Validate minutes_objective (positive integer, reasonable range)
            if ($minutesObjective >= 0 && $minutesObjective <= 1440) { // 0 to 24 hours
                $preferences['minutes_objective'] = $minutesObjective;
            } else {
                $errors['minutes_objective'] = 'Invalid minutes objective. Must be between 0 and 1440 (24 hours)';
            }
        }

        if (!empty($errors)) {
            JsonResponse::validationError('Validation failed', $errors);
            return;
        }

        if (empty($preferences)) {
            JsonResponse::error('No valid preferences provided', 400);
            return;
        }

        $success = $this->storage->saveUserPreferences($username, $preferences);

        if ($success) {
            JsonResponse::success([
                'success' => true,
                'username' => $username,
                'preferences' => $this->storage->getUserPreferences($username)
            ]);
        } else {
            JsonResponse::error('Failed to save preferences', 500);
        }
    }

    /**
     * Fetch fresh data from Kelio (jsessionid already obtained by middleware)
     */
    private function fetchFreshData(): void
    {
        $username = $this->authContext->getUsername();
        $password = $this->authContext->getPassword();
        $jsessionid = $this->authContext->getJSessionId();

        try {
            // jsessionid already obtained by middleware, just use it
            if ($jsessionid === null) {
                throw new \Exception('No jsessionid available from middleware');
            }

            // Fetch hours data
            $hoursArrays = $this->kelioClient->fetchAllHours($jsessionid);
            $hours = $this->timeCalculator->mergeHoursByDay(...$hoursArrays);

            // Calculate totals
            $totalEffective = $this->timeCalculator->calculateTotalWorkingHours($hours);
            $totalPaid = $this->timeCalculator->calculateTotalWorkingHours($hours, $this->config['pause_time']);

            // Generate session token
            $token = $this->auth->generateToken($username, $password);

            // Save the successful result with token
            $saveSuccess = $this->storage->saveUserData($username, $hours, $totalEffective, $totalPaid, $token);

            // Get user preferences
            $preferences = $this->storage->getUserPreferences($username);

            JsonResponse::success([
                'authenticated_with' => $this->authContext->getAuthenticatedWith(),
                'username' => $username,
                'hours' => $hours,
                'total_effective' => $totalEffective,
                'total_paid' => $totalPaid,
                'preferences' => $preferences,
                'token' => $token,
                'data_saved' => $saveSuccess,
                'data_file_path' => $this->storage->getDataFilePath(),
                'cache' => false
            ]);
        } catch (\Throwable $th) {
            // Try to get fallback data
            $this->handleFallbackData();
        }
    }

    /**
     * Handle fallback data when Kelio connection fails
     */
    private function handleFallbackData(): void
    {
        $username = $this->authContext->getUsername();
        $password = $this->authContext->getPassword();

        $savedData = $this->storage->getUserData($username);

        if ($savedData !== null) {
            $preferences = $this->storage->getUserPreferences($username);

            // Use existing token if available, otherwise generate a new one
            $token = $savedData['session_token'] ?? $this->auth->generateToken($username, $password);

            // Update token if it was just generated
            if (!isset($savedData['session_token'])) {
                $this->storage->saveUserData($username, $savedData['hours'], $savedData['total_effective'], $savedData['total_paid'], $token);
            }

            JsonResponse::success([
                'error' => 'Failed to fetch fresh data, using cached data',
                'fallback' => true,
                'authenticated_with' => $this->authContext->getAuthenticatedWith(),
                'username' => $username,
                'hours' => $savedData['hours'],
                'total_effective' => $savedData['total_effective'],
                'total_paid' => $savedData['total_paid'],
                'last_save' => $savedData['last_save'],
                'preferences' => $preferences,
                'token' => $token,
                'cache' => true
            ]);
        } else {
            JsonResponse::error('No fresh data available and no cached data found');
        }
    }
}
