<?php

namespace Modules\Helpdesk\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Helpdesk\Models\Department;

class HelpdeskTicketDepartmentSeeder extends Seeder
{
    /**
     * Seed default helpdesk departments.
     */
    public function run(): void
    {
        $auditUserId = User::query()->orderBy('id')->value('id');

        $departments = [
            ['name' => 'Feedback', 'description' => null],
            ['name' => 'Sales', 'description' => 'Sales related enquiry'],
            ['name' => 'Support', 'description' => 'General Support Queries'],
            ['name' => 'Technical', 'description' => 'Technical issues and incident handling'],
        ];

        foreach ($departments as $dept) {
            Department::query()->updateOrCreate(
                ['name' => $dept['name']],
                [
                    'description' => $dept['description'],
                    'visibility' => 'public',
                    'department_head' => $auditUserId,
                    'status' => 'active',
                    ...($auditUserId !== null ? [
                        'created_by' => $auditUserId,
                        'updated_by' => $auditUserId,
                    ] : []),
                ],
            );
        }
    }
}
