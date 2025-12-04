<?php

abstract class ActionController
{
    /**
     * Dispatch to the appropriate action method based on the 'action' parameter
     * Convention: action=update_preferences -> updatePreferencesAction()
     *
     * @param string|null $defaultAction Default action if no action parameter
     */
    public function dispatch(?string $defaultAction = null): void
    {
        $action = $_POST['action'] ?? $_GET['action'] ?? $defaultAction;

        if ($action === null) {
            $this->indexAction();
            return;
        }

        // Convert action from snake_case to camelCase and add "Action" suffix
        $methodName = $this->actionToMethod($action);

        if (method_exists($this, $methodName)) {
            $this->$methodName();
        } else {
            $this->handleUnknownAction($action);
        }
    }

    /**
     * Convert action name to method name
     * Example: update_preferences -> updatePreferencesAction
     */
    private function actionToMethod(string $action): string
    {
        // Convert snake_case to camelCase
        $camelCase = str_replace('_', '', ucwords($action, '_'));
        $camelCase = lcfirst($camelCase);

        return $camelCase . 'Action';
    }

    /**
     * Handle missing action parameter
     * Can be overridden in child classes
     */
    protected function indexAction(): void
    {
        JsonResponse::error('Action parameter is required', 400);
    }

    /**
     * Handle unknown action
     * Can be overridden in child classes
     */
    protected function handleUnknownAction(string $action): void
    {
        $methodName = $this->actionToMethod($action);

        JsonResponse::error("Unknown action: $action", 400, [
            'debug' => [
                'controller_class' => get_class($this),
                'requested_action' => $action,
                'expected_method' => $methodName,
                'method_exists' => method_exists($this, $methodName),
                'available_methods' => array_filter(
                    get_class_methods($this),
                    fn($method) => str_ends_with($method, 'Action')
                ),
                'post_data' => array_keys($_POST),
                'get_data' => array_keys($_GET),
            ]
        ]);
    }
}
