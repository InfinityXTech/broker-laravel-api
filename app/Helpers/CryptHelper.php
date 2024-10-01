<?php

namespace App\Helpers;

class CryptHelper
{

    /**
     * Encrypt string
     * $key:
     *  - empty - default
     *  - cm - client managment
     *
     * @param string $string_to_encrypt
     * @param string $key
     * @return string
     */
    public static function encrypt(string $string_to_encrypt, string $key = '')
    {
        if (empty($string_to_encrypt)) {
            return '';
        }

        if ($key == 'cm') {
            $crypt_key = config('crypt.cm_key');
            $iv = config('crypt.cm_iv');
        } else {
            $crypt_key = config('crypt.key');
            $iv = config('crypt.iv');
        }

        if (empty($crypt_key) || empty($iv)) {
            throw new \Exception('Crypt keys empty');
        }
        return openssl_encrypt($string_to_encrypt, "AES-256-CBC", $crypt_key, 0, $iv);
    }

    /**
     * Decrypt string
     * $key:
     *  - empty - default
     *  - cm - client managment
     *
     * @param string $encrypted_string
     * @return string
     */
    public static function decrypt(string $encrypted_string, string $key = '')
    {
        if ($key == 'cm') {
            $crypt_key = config('crypt.cm_key');
            $iv = config('crypt.cm_iv');
        } else {
            $crypt_key = config('crypt.key');
            $iv = config('crypt.iv');
        }

        if (empty($crypt_key) || empty($iv)) {
            throw new \Exception('Crypt keys empty');
        }

        $v = openssl_decrypt($encrypted_string, "AES-256-CBC", $crypt_key, 0, $iv);
        // GeneralHelper::PrintR([$crypt_key, $iv, $v]);

        if ((empty($v) || $v == false || $v == null) && !empty($encrypted_string)) {
            return $encrypted_string;
        }
        return $v;
    }

    public static function decrypt_lead_data_array(array &$lead)
    {
        $encrypted_fields = ['first_name', 'last_name', 'email', 'phone', 'short_phone'];
        foreach ($encrypted_fields as $field) {
            if (isset($lead[$field])/* && strpos($lead[$field] ?? '', '=') !== false*/) {
                $lead[$field] = CryptHelper::decrypt($lead[$field] ?? '');
                if ($field == 'first_name' || $field == 'last_name') {
                    $lead[$field] = ucfirst($lead[$field]);
                }
            }
        }
    }

    public static function decrypt_lead_data_model(&$lead)
    {
        $encrypted_fields = ['first_name', 'last_name', 'email', 'phone', 'short_phone'];
        foreach ($encrypted_fields as $field) {
            if (!empty($lead->{$field})/* && strpos($lead->{$field} ?? '', '=') !== false*/) {
                $lead->{$field} = CryptHelper::decrypt($lead->{$field} ?? '');
                if ($field == 'first_name' || $field == 'last_name') {
                    $lead->{$field} = ucfirst($lead->{$field});
                }
            }
        }
    }

    public static function decrypt_broker_name(string $broker_name)
    {
        if (is_string($broker_name) && !empty($broker_name)) {
            $c = $broker_name[strlen($broker_name) - 1];
            if ($c == '=') {
                return self::decrypt($broker_name);
            }
        }
        return $broker_name;
    }
}
