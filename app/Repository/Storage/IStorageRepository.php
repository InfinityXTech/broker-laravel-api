<?php

namespace App\Repository\Storage;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface IStorageRepository extends IRepository {
    public function info(string $fileId): array;
    public function content(string $fileId): string;
}