<?php

namespace Marvel\Database\Repositories;


interface UserPackageRespoInterface
{
    public function create(int $user_id, int $package_id);
}