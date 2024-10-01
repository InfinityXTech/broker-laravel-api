<?php

namespace App\Repository\Settings;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use App\Repository\IRepository;

interface ISettingsRepository extends IRepository
{
    public function get(): ?Model;
    public function set(array $payload): bool;
    public function feed_payment_methods(): Collection;
    public function create_payment_methods(array $payload): bool;
    public function feed_payment_companies(): Collection;
    public function create_payment_companies(array $payload): bool;
    public function get_subscribers(): array;
    public function update_subscribers(array $payload): bool;
}
