<?php

namespace Codeception_With_Suite_Overridings\Tests\functional;

use Codeception_With_Suite_Overridings\Covered\UserService;
use Codeception_With_Suite_Overridings\FunctionalTester;

class UserServiceCest
{
    private UserService $service;

    public function _before(FunctionalTester $I): void
    {
        $this->service = new UserService();
    }

    public function testAddUser(FunctionalTester $I): void
    {
        $I->wantTo('add a user successfully');

        $I->assertTrue($this->service->addUser('John Doe', 'john@example.com'));
        $I->assertEquals(1, $this->service->getUserCount());
        $I->assertTrue($this->service->hasLogs());
    }

    public function testAddUserWithEmptyNameFails(FunctionalTester $I): void
    {
        $I->wantTo('verify adding user with empty name fails');

        $I->assertFalse($this->service->addUser('', 'john@example.com'));
        $I->assertEquals(0, $this->service->getUserCount());
    }

    public function testAddUserWithEmptyEmailFails(FunctionalTester $I): void
    {
        $I->wantTo('verify adding user with empty email fails');

        $I->assertFalse($this->service->addUser('John Doe', ''));
        $I->assertEquals(0, $this->service->getUserCount());
    }

    public function testAddDuplicateUserFails(FunctionalTester $I): void
    {
        $I->wantTo('verify adding duplicate user fails');

        $this->service->addUser('John Doe', 'john@example.com');
        $I->assertFalse($this->service->addUser('Jane Doe', 'john@example.com'));
        $I->assertEquals(1, $this->service->getUserCount());
    }

    public function testRemoveUser(FunctionalTester $I): void
    {
        $I->wantTo('remove a user successfully');

        $this->service->addUser('John Doe', 'john@example.com');
        $I->assertTrue($this->service->removeUser('john@example.com'));
        $I->assertEquals(0, $this->service->getUserCount());
    }

    public function testRemoveNonExistentUserFails(FunctionalTester $I): void
    {
        $I->wantTo('verify removing non-existent user fails');

        $I->assertFalse($this->service->removeUser('john@example.com'));
    }

    public function testGetUser(FunctionalTester $I): void
    {
        $I->wantTo('retrieve a user by email');

        $this->service->addUser('John Doe', 'john@example.com');
        $user = $this->service->getUser('john@example.com');

        $I->assertIsArray($user);
        $I->assertEquals('John Doe', $user['name']);
        $I->assertEquals('john@example.com', $user['email']);
    }

    public function testGetNonExistentUserReturnsNull(FunctionalTester $I): void
    {
        $I->wantTo('verify getting non-existent user returns null');

        $I->assertNull($this->service->getUser('john@example.com'));
    }

    public function testUserExists(FunctionalTester $I): void
    {
        $I->wantTo('check if user exists');

        $this->service->addUser('John Doe', 'john@example.com');
        $I->assertTrue($this->service->userExists('john@example.com'));
        $I->assertFalse($this->service->userExists('jane@example.com'));
    }

    public function testClearLogs(FunctionalTester $I): void
    {
        $I->wantTo('clear logs');

        $this->service->addUser('John Doe', 'john@example.com');
        $this->service->clearLogs();
        $I->assertFalse($this->service->hasLogs());
    }
}