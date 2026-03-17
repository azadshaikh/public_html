<?php

namespace Tests\Unit;

use App\Modules\ModuleManager;
use App\Modules\Support\ModuleAutoloader;
use Modules\ChatBot\Database\Seeders\DatabaseSeeder as ChatBotDatabaseSeeder;
use Modules\CMS\Database\Seeders\DatabaseSeeder as CmsDatabaseSeeder;
use Modules\Platform\Database\Seeders\DatabaseSeeder as PlatformDatabaseSeeder;
use Modules\ReleaseManager\Database\Seeders\DatabaseSeeder as ReleaseManagerDatabaseSeeder;
use Modules\Todos\Database\Factories\TodoFactory;
use Modules\Todos\Database\Seeders\DatabaseSeeder as TodosDatabaseSeeder;
use Tests\TestCase;

class ModuleDatabasePathConventionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ModuleAutoloader::register(app(ModuleManager::class)->all()->all());
    }

    public function test_platform_seeders_are_loaded_from_standard_database_seeders_directory(): void
    {
        $this->assertFileExists(base_path('modules/Platform/database/seeders/DatabaseSeeder.php'));
        $this->assertFileDoesNotExist(base_path('modules/Platform/app/Database/Seeders/DatabaseSeeder.php'));
        $this->assertTrue(class_exists(PlatformDatabaseSeeder::class));
    }

    public function test_all_module_seeders_are_loaded_from_standard_database_seeders_directories(): void
    {
        $this->assertFileExists(base_path('modules/CMS/database/seeders/DatabaseSeeder.php'));
        $this->assertFileDoesNotExist(base_path('modules/CMS/app/Database/Seeders/DatabaseSeeder.php'));
        $this->assertTrue(class_exists(CmsDatabaseSeeder::class));

        $this->assertFileExists(base_path('modules/ChatBot/database/seeders/DatabaseSeeder.php'));
        $this->assertFileDoesNotExist(base_path('modules/ChatBot/app/Database/Seeders/DatabaseSeeder.php'));
        $this->assertTrue(class_exists(ChatBotDatabaseSeeder::class));

        $this->assertFileExists(base_path('modules/ReleaseManager/database/seeders/DatabaseSeeder.php'));
        $this->assertFileDoesNotExist(base_path('modules/ReleaseManager/database/seeders/ReleaseManagerDatabaseSeeder.php'));
        $this->assertTrue(class_exists(ReleaseManagerDatabaseSeeder::class));

        $this->assertFileExists(base_path('modules/Todos/database/seeders/DatabaseSeeder.php'));
        $this->assertFileDoesNotExist(base_path('modules/Todos/app/Database/Seeders/DatabaseSeeder.php'));
        $this->assertTrue(class_exists(TodosDatabaseSeeder::class));
    }

    public function test_module_database_factories_continue_to_autoload(): void
    {
        $this->assertFileExists(base_path('modules/Todos/database/factories/TodoFactory.php'));
        $this->assertFileDoesNotExist(base_path('modules/Todos/app/Database/Factories/TodoFactory.php'));
        $this->assertTrue(class_exists(TodoFactory::class));
    }
}
