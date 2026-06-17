<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Create the manage_risks permission if it doesn't exist
        Permission::firstOrCreate(
            ['slug' => 'manage_risks'],
            [
                'name'        => '管理风险',
                'description' => '确认、忽略风险并管理风险建议',
                'group'       => 'risks',
            ]
        );

        // Assign manage_risks to admin (via *) and manager roles
        $managerRole = Role::where('slug', 'manager')->first();
        if ($managerRole) {
            $perm = Permission::where('slug', 'manage_risks')->first();
            if ($perm && ! $managerRole->hasPermission('manage_risks')) {
                $managerRole->permissions()->attach($perm->id);
            }
        }
    }

    public function down(): void
    {
        $perm = Permission::where('slug', 'manage_risks')->first();
        if ($perm) {
            $perm->roles()->detach();
            $perm->delete();
        }
    }
};
