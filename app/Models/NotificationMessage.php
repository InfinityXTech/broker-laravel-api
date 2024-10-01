<?php

namespace App\Models;

use DateTime;

class NotificationMessage {

    public $userId;

    public int $notifiable_id;
    public string $notifiable_type;
    public $data;
    public DateTime $created_at;

    public function __construct($userId, array $data, string $notifiable_type = '') {
        $this->userId = $userId;
        $this->data = $data;
        $this->notifiable_type = $notifiable_type;
        $this->notifiable_id = time();
        $this->created_at = new \DateTime();
    }
}