<?php

namespace App\Classes;

class SlackNotification
{
    /**
     * https://api.slack.com/methods
     * https://api.slack.com/messaging/sending
     */
    private $api_key;
    private $channel = false;
    private $create_channel_if_not_exist = false;
    private $baseApiUrl = 'https://slack.com/api/';
    private $commands = [
        'channels' => 'conversations.list',
        'users_conversations' => 'users.conversations',
        'create_channel' => 'conversations.create',
        'send_to_channel' => 'chat.postMessage',
    ];

    private static $instance = [];

    public static function instance($api_key, $channel = [])
    {
        $key = $api_key . (isset($channel['id']) ? $channel['id'] : (isset($channel['name']) ? $channel['name'] : ''));
        if (isset(self::$instance[$key])) {
            return self::$instance[$key];
        }
        self::$instance[$key] = new self($api_key, $channel);
        return self::$instance[$key];
    }

    public function __construct($api_key, $channel)
    {
        $this->api_key = $api_key;
        if (isset($channel['id'])) {
            $this->channel = $channel['id'];
        } elseif (!empty($channel['id'])) {
            $this->channel = $this->get_channel_by_name($channel['id']);
        }
    }

    private function request($command, $post = [])
    {
        $result = false;
        $ch = curl_init();
        try {
            curl_setopt($ch, CURLOPT_URL, $this->baseApiUrl . $command);

            if (count($post) > 0) {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                "Authorization: Bearer " . $this->api_key,
                "Content-Type:application/json"
            ));

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec($ch);

            $_result = json_decode($server_output, true);

            if ($_result && isset($_result['ok']) && (int)$_result['ok'] == 1) {
                $result = $_result;
            } else {
                // echo 'command :' . $command . PHP_EOL;
                // echo 'data :' . print_r($post, true) . PHP_EOL;
                // echo 'output :' . $server_output . PHP_EOL;
            }
        } catch (\Exception $ex) {
            // echo 'command :' . $command . PHP_EOL;
            // echo 'data :' . print_r($post, true) . PHP_EOL;
            // echo $ex->getMessage();
        } finally {
            curl_close($ch);
        }
        return $result;
    }

    public function create_channel($channel_name)
    {

        $post = array();
        $post['name'] = $channel_name;
        $post['pretty'] = 1;

        $channel = $this->request($this->commands['create_channel'], $post);
        if ($channel) {
            return $channel['channel'];
        }
        return false;
    }

    public function get_channels()
    {
        $channels = $this->request($this->commands['channels']);
        if ($channels) {
            return $channels['channels'];
        }
        return false;
    }

    public function get_channels_by_user()
    {
        $post = [
            'user' => 'U02JRSTGW1X',
            'charset' => 'application/json'
        ];

        $channels = $this->request($this->commands['users_conversations'], $post);

        if ($channels) {
            return $channels['channels'];
        }

        return false;
    }

    public function get_channel_by_name($channel_name)
    {
        $channels = $this->get_channels();
        if ($channels) {
            foreach ($channels as $channel) {
                if ($channel['name'] == $channel_name) {
                    return $channel;
                }
            }
        }
        return false;
    }

    public function get_channel_by_id($channel_id)
    {
        $channels = $this->get_channels();
        if ($channels) {
            foreach ($channels as $channel) {
                if ($channel['id'] == $channel_id) {
                    return $channel;
                }
            }
        }
        return false;
    }

    public function send($message)
    {
        if ($this->channel) {
            $post = [
                'channel' => $this->channel['id'],
                'text' => $message
            ];

            $response = $this->request($this->commands['send_to_channel'], $post);

            return $response;
        }
        return false;
    }

    public function send_to_channel_name($message, $channel_name)
    {

        $channel = $this->get_channel_by_name($channel_name);

        if (!$channel) {
            if ($this->create_channel_if_not_exist) {
                if ($this->create_channel($channel_name)) {
                    $channel = $this->get_channel_by_name($channel_name);
                }
            } else {
                // throw new \Exception('The channel "' . $channel_name . '" is not found');
            }
        }

        if ($channel && isset($channel['id']) && !empty($channel['id'])) {
            return $this->send_to_channel_id($message, $channel['id']);
        }

        return false;
    }

    public function send_to_channel_id($message, $channel_id)
    {
        if (!empty($channel_id)) {
            $post = [
                'channel' => $channel_id,
                'text' => $message
            ];

            $response = $this->request($this->commands['send_to_channel'], $post);
            return $response;
        }
        return false;
    }

    public function reply($thread_ts, $message)
    {
        if ($this->channel) {
            $post = [
                'channel' => $this->channel['id'],
                "thread_ts" => $thread_ts,
                'text' => $message
            ];

            $response = $this->request($this->commands['send_to_channel'], $post);

            return $response;
        }
        return false;
    }
}
