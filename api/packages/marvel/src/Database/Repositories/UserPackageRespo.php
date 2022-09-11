<?php


namespace Marvel\Database\Repositories;

use App\Models\UserPackageD;

class UserPackageRespo implements UserPackageRespoInterface
{
    public function create(int $user_id, int $package_id)
    {
        return UserPackageD::create([
            'user_id' => $user_id,
            'package_id' => $package_id,
        ]);
    }
}