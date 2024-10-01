<?php

namespace App\Classes\TrafficEndpoints;

class CodeWriter
{
    private $html = [];
    private $stack = [];
    private $integration_names;
    private $broker_names;

    public function __construct($integration_names = null, $broker_names = null)
    {
        $this->integration_names = $integration_names ?? [];
        $this->broker_names = $broker_names ?? [];
    }

    public function write($line)
    {
        $fn = 'write_' . $line['type'];
        if (method_exists($this, $fn)) {
            $this->$fn($line);
        } else {
            $this->html[] = print_r($line, true);
        }
    }

    public function write_comment($line)
    {
        $this->html[] = $this->indent() . $this->txt($line['text']);
    }

    public function write_val($line)
    {
        $this->html[] = $this->indent() . $this->val($line['text']) . ' = ' . $line['value'];
    }

    public function write_send($line)
    {
        foreach ($line['waterfall'] as $integration_id => $int_waterfalls) {
            foreach ($int_waterfalls as $waterfall) {
                $this->html[] = $this->indent() . $this->fn('SendTo') . '(' .
                    $this->integration($integration_id) . ', ' .
                    $this->broker($waterfall['broker_id']) . ')' .
                    '<div style="display:none">' . print_r($waterfall, true) . '</div>';
            }
        }
    }

    public function write_eq($line)
    {
        $this->write_condition($line, '==');
    }

    public function write_gt($line)
    {
        $this->write_condition($line, '>');
    }

    public function write_condition($line, $symbol)
    {
        $val = json_encode($line['value']);
        $exp = json_encode($line['expected']);
        $txt = $val . ' ' . $symbol . ' ' . $exp . ' is ' . ($line['result'] ? 'true' : 'false');

        $this->html[] = $this->indent() . $this->kw('if') . ' (' . $this->val($line['text']) . ' ' . $symbol . ' ' . $exp . ') ' . $this->txt($txt);

        if ($line['result']) {
            $this->html[] = $this->indent() . '{';
            $this->stack[] = [
                $this->indent() . '}',
                $this->indent() . $this->kw('else'),
                $this->indent() . '{',
                $this->indent(1) . '...',
                $this->indent() . '}'
            ];
        } else {
            $this->html[] = $this->indent() . '{';
            $this->html[] = $this->indent(1) . '...';
            $this->html[] = $this->indent() . '}';
            $this->html[] = $this->indent() . $this->kw('else');
            $this->html[] = $this->indent() . '{';
            $this->stack[] = [$this->indent() . '}'];
        }
    }

    public function write_end_if($line)
    {
        foreach (array_pop($this->stack) as $line) {
            $this->html[] = $line;
        }
    }

    public function output()
    {
        return '<pre class="code">' . implode("\n", $this->html) . '</pre>';
    }

    private function integration($integration_id)
    {
        return '<span class="mute">integration: </span><span class="arg">' . ($this->integration_names[$integration_id] ?? $integration_id) . '</span>';
    }

    private function broker($broker_id)
    {
        return '<span class="mute">broker: </span><span class="arg">' . ($this->broker_names[$broker_id] ?? $broker_id) . '</span>';
    }

    private function val($text)
    {
        return '<span class="value">' . $text . '</span>';
    }

    private function txt($text)
    {
        return '<span class="comment">// ' . $text . '</span>';
    }

    private function kw($text)
    {
        return '<span class="keyword">' . $text . '</span>';
    }

    private function fn($text)
    {
        return '<span class="func">' . $text . '</span>';
    }

    private function indent($add = 0)
    {
        return str_repeat('   ', count($this->stack) + $add);
    }
}
