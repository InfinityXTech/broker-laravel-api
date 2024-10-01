<?php

namespace App\Helpers;

use App\Classes\StorageWrapper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class StorageHelper
{
    public static function injectFile(string $storage_name, &$items, string $files_field = 'file')
    {
        $storage = StorageWrapper::instance($storage_name);
        if (is_array($items) || $items instanceof Collection) {
            foreach ($items as &$item) {
                if (isset($item[$files_field]) && !empty($item[$files_field])) {
                    $item[$files_field] = $storage->get_file($item[$files_field]);
                }
            }
        } else if (isset($items[$files_field]) && is_array($items[$files_field])) {
            $file_items = [];
            foreach ((array)$items[$files_field] as &$item) {
                $file_items[] = $storage->get_file($item);
            }
            $items[$files_field] = $file_items;
        } else if (isset($items[$files_field]) && !empty($items[$files_field])) {
            $items[$files_field] = $storage->get_file($items[$files_field]);
        }
    }

    public static function injectFiles(string $storage_name, &$items, string $files_field = 'file')
    {
        $storage = StorageWrapper::instance($storage_name);
        if (is_array($items) || $items instanceof Collection) {
            foreach ($items as &$item) {
                if (isset($item[$files_field]) && !empty($item[$files_field])) {
                    $item[$files_field] = $storage->get_files($item[$files_field]);
                }
            }
        } else {
            if (isset($items[$files_field]) && !empty($items[$files_field])) {
                $items[$files_field] = $storage->get_files($items[$files_field]);
            }
        }
    }

    public static function syncFiles(string $storage_name, ?Model $model, array &$payload, string $files_field, array $allowed_extensions)
    {
        $files_source = isset($_FILES[$files_field]) || isset($payload[$files_field]) ? $files_field : 'file';

        $result_files = array_filter($payload[$files_source] ?? [], fn ($file) => is_string($file));

        $storage = StorageWrapper::instance($storage_name);
        // try {
        //     $storage->login();
        // } catch (\Exception $ex) {
        // }
        $storage->set_allowed_extensions($allowed_extensions);
        if (isset($_FILES[$files_source])) {
            try {
                $uploaded_files = $storage->upload_files($files_source);

                foreach ($uploaded_files as $file) {
                    if ($file['success']) {
                        $result_files[] = $file['id'];
                    }
                }

                foreach ($uploaded_files as $file) {
                    if (!$file['success']) {
                        throw new \Exception($file['error']);
                    }
                }
            } catch (\Exception $ex) {
                throw $ex;
            }
        }

        // remove 
        if ($model != null) {
            foreach ($model->$files_field ?? [] as $file) {
                if (!in_array($file, $result_files)) {
                    $storage->delete_file($file);
                }
            };
        }

        $payload[$files_field] = $result_files;
    }

    public static function deleteFiles(string $storage_name, ?Model $model, string $files_field)
    {
        if ($model != null) {
            $storage = StorageWrapper::instance($storage_name);
            $storage->delete_files($model->$files_field ?? []);
        }
    }
}
