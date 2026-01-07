<?php

namespace Codeception_Basic\Tests\unit\Covered;

use Codeception_Basic\Covered\UserService;

class UserServiceTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    public function testAddUser()
    {
        $service = new UserService();
        $this->assertTrue($service->addUser('John Doe', 'john@example.com'));
        $this->assertSame(1, $service->getUserCount());
        $this->assertTrue($service->hasLogs());
    }

    public function testAddUserWithEmptyName()
    {
        $service = new UserService();
        $this->assertFalse($service->addUser('', 'john@example.com'));
        $this->assertSame(0, $service->getUserCount());
    }

    public function testAddUserWithEmptyEmail()
    {
        $service = new UserService();
        $this->assertFalse($service->addUser('John Doe', ''));
        $this->assertSame(0, $service->getUserCount());
    }

    public function testAddDuplicateUser()
    {
        $service = new UserService();
        $service->addUser('John Doe', 'john@example.com');
        $this->assertFalse($service->addUser('Jane Doe', 'john@example.com'));
        $this->assertSame(1, $service->getUserCount());
    }

    public function testRemoveUser()
    {
        $service = new UserService();
        $service->addUser('John Doe', 'john@example.com');
        $this->assertTrue($service->removeUser('john@example.com'));
        $this->assertSame(0, $service->getUserCount());
    }

    public function testRemoveNonExistentUser()
    {
        $service = new UserService();
        $this->assertFalse($service->removeUser('john@example.com'));
    }

    public function testGetUser()
    {
        $service = new UserService();
        $service->addUser('John Doe', 'john@example.com');
        $user = $service->getUser('john@example.com');
        $this->assertIsArray($user);
        $this->assertSame('John Doe', $user['name']);
        $this->assertSame('john@example.com', $user['email']);
    }

    public function testGetNonExistentUser()
    {
        $service = new UserService();
        $this->assertNull($service->getUser('john@example.com'));
    }

    public function testUserExists()
    {
        $service = new UserService();
        $service->addUser('John Doe', 'john@example.com');
        $this->assertTrue($service->userExists('john@example.com'));
        $this->assertFalse($service->userExists('jane@example.com'));
    }

    public function testGetLogs()
    {
        $service = new UserService();
        $service->addUser('John Doe', 'john@example.com');
        $logs = $service->getLogs();
        $this->assertIsArray($logs);
        $this->assertGreaterThan(0, count($logs));
    }

    public function testClearLogs()
    {
        $service = new UserService();
        $service->addUser('John Doe', 'john@example.com');
        $service->clearLogs();
        $this->assertFalse($service->hasLogs());
    }
}
