<?php

class BaseController extends ActionController
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
        $username = $this->authContext->getUsername();
        $preferences = [];
        $errors = [];

        if (isset($_POST['theme'])) {
            $theme = trim($_POST['theme']);

            if (preg_match('/^[a-zA-Z0-9_-]+$/', $theme) && strlen($theme) <= 50) {
                $preferences['theme'] = $theme;
            } else {
                $errors['theme'] = 'Invalid theme format. Only alphanumeric, underscore and dash allowed (max 50 chars)';
            }
        }

        if (isset($_POST['minutes_objective'])) {
            $minutesObjective = intval($_POST['minutes_objective']);

            if ($minutesObjective > 0) {
                $preferences['minutes_objective'] = $minutesObjective;
            } else {
                $errors['minutes_objective'] = 'Invalid minutes objective. Must be > 0';
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
            // Ensure token is generated/retrieved
            $this->authContext->getOrGenerateToken();

            // Return all user data (same format as data.json, filtered for this user)
            $userData = $this->storage->getUserData($username);
            JsonResponse::success($userData);
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
        $token = $this->authContext->getOrGenerateToken();

        try {
            // jsessionid already obtained by middleware, just use it
            if ($jsessionid === null) {
                throw new \Exception('No jsessionid available from middleware');
            }

            // Fetch hours data
            $hoursArrays = $this->kelioClient->fetchAllHours($jsessionid);
            $mergedHours = $this->timeCalculator->mergeHoursByDay(...$hoursArrays);

            // Calculate weekly data with all details
            $weeks = $this->timeCalculator->calculateWeeklyData($mergedHours);

            // Save the successful result with token
            $this->storage->saveUserData($username, $weeks, $token);

            // Return all user data (same format as data.json, filtered for this user)
            $userData = $this->storage->getUserData($username);

            JsonResponse::success($userData);
        } catch (\Throwable $th) {
            // Invalidate token on kelio fetch error
            $this->storage->invalidateToken($username);

            // Return error - no fallback to cached data
            JsonResponse::error('Failed to fetch data from Kelio. Please login again.', 401, [
                'token_invalidated' => true
            ]);
        }
    }

}
