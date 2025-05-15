<?php

use App\Models\TenantUser;
use Illuminate\Support\Str;

$user = new TenantUser();
$user->id = Str::uuid()->toString();
$user->name = 'Utilisateur Konan';
$user->email = 'user@konan.com';
$user->password = bcrypt('password');
$user->save();

echo "Utilisateur créé avec succès!\n";
