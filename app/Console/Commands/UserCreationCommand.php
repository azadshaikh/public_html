<?php

namespace App\Console\Commands;

use App\Services\UserService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class UserCreationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create {firstName} {lastName} {email} {password} {role} {agencyadmin?} {skipemail?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create user with parameters: First Name, Last Name, Email, Password';

    public function __construct(protected UserService $userService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('User creation process start...');
        if (Schema::hasTable('users')) {
            $result = $this->userService->createUser([
                'firstName' => $this->argument('firstName'),
                'lastName' => $this->argument('lastName'),
                'email' => $this->argument('email'),
                'password' => $this->argument('password'),
                'role' => $this->argument('role'),
                'agencyadmin' => $this->argument('agencyadmin'),
                'skipemail' => $this->argument('skipemail'),
            ]);
            if ($result['success']) {
                $this->info('User created successfully.');
                if (! empty($result['errors'])) {
                    foreach ($result['errors'] as $err) {
                        $this->warn($err);
                    }
                }

                return self::SUCCESS;
            }

            $this->error('Failed to create user: '.implode('; ', $result['errors'] ?? []));

            return self::FAILURE;
        }

        $this->error('Users table not found.');

        return self::FAILURE;
    }
}
