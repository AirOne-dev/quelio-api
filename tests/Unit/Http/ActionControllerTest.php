<?php

namespace Tests\Unit\Http;

use Tests\TestCase;
use JsonResponse;

class ActionControllerTest extends TestCase
{
    private TestActionController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new TestActionController();
        $_POST = [];
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
        parent::tearDown();
    }

    public function testDispatchCallsIndexActionWhenNoActionProvided(): void
    {
        $this->controller->dispatch();

        $this->assertEquals('indexAction', $this->controller->lastCalledMethod);
    }

    public function testDispatchCallsDefaultActionWhenProvided(): void
    {
        $this->controller->dispatch('test');

        $this->assertEquals('testAction', $this->controller->lastCalledMethod);
    }

    public function testDispatchUsesPostAction(): void
    {
        $_POST['action'] = 'update_user';

        $this->controller->dispatch();

        $this->assertEquals('updateUserAction', $this->controller->lastCalledMethod);
    }

    public function testDispatchUsesGetAction(): void
    {
        $_GET['action'] = 'delete_item';

        $this->controller->dispatch();

        $this->assertEquals('deleteItemAction', $this->controller->lastCalledMethod);
    }

    public function testDispatchPrefersPostOverGet(): void
    {
        $_POST['action'] = 'post_action';
        $_GET['action'] = 'get_action';

        $this->controller->dispatch();

        $this->assertEquals('postActionAction', $this->controller->lastCalledMethod);
    }

    public function testDispatchHandlesUnknownAction(): void
    {
        $_POST['action'] = 'unknown_method';

        $this->controller->dispatch();

        $this->assertEquals('handleUnknownAction', $this->controller->lastCalledMethod);
    }

    public function testActionToMethodConvertsSnakeCaseToCamelCase(): void
    {
        $_POST['action'] = 'update_user_preferences';

        $this->controller->dispatch();

        $this->assertEquals('updateUserPreferencesAction', $this->controller->lastCalledMethod);
    }

    public function testActionToMethodHandlesSingleWord(): void
    {
        $_POST['action'] = 'refresh';

        $this->controller->dispatch();

        $this->assertEquals('refreshAction', $this->controller->lastCalledMethod);
    }

}

/**
 * Test implementation of ActionController
 */
class TestActionController extends \ActionController
{
    public ?string $lastCalledMethod = null;

    public function testAction(): void
    {
        $this->lastCalledMethod = 'testAction';
    }

    public function updateUserAction(): void
    {
        $this->lastCalledMethod = 'updateUserAction';
    }

    public function deleteItemAction(): void
    {
        $this->lastCalledMethod = 'deleteItemAction';
    }

    public function postActionAction(): void
    {
        $this->lastCalledMethod = 'postActionAction';
    }

    public function refreshAction(): void
    {
        $this->lastCalledMethod = 'refreshAction';
    }

    public function updateUserPreferencesAction(): void
    {
        $this->lastCalledMethod = 'updateUserPreferencesAction';
    }

    protected function indexAction(): void
    {
        $this->lastCalledMethod = 'indexAction';
    }

    protected function handleUnknownAction(string $action): void
    {
        $this->lastCalledMethod = 'handleUnknownAction';
    }
}
