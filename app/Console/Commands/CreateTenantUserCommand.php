<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantUser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateTenantUserCommand extends Command
{
    protected $signature = 'tenant:create-user {tenant} {name} {email} {password?}';
    protected $description = 'Crée un utilisateur dans un tenant spécifique';

    public function handle()
    {
        $tenantId = $this->argument('tenant');
        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->argument('password') ?? 'password';

        $tenant = Tenant::find($tenantId);

        if (!$tenant) {
            $this->error("Tenant avec ID {$tenantId} non trouvé.");
            return 1;
        }

        $this->info("Création d'un utilisateur pour le tenant {$tenant->name}...");

        try {
            $tenant->run(function () use ($name, $email, $password) {
                $user = TenantUser::create([
                    // Ne pas spécifier l'ID, laissez la base de données l'auto-incrémenter
                    'name' => $name,
                    'email' => $email,
                    'password' => bcrypt($password),
                ]);

                $this->info("Utilisateur créé avec succès: {$user->name} ({$user->email})");
            });

            return 0;
        } catch (\Exception $e) {
            $this->error("Erreur lors de la création de l'utilisateur: " . $e->getMessage());
            return 1;
        }
    }
}
