<?php

namespace App\Classes;

use App\Models\StorageModel;
use App\Classes\StorageLaravel;
use App\Helpers\GeneralHelper;
use Illuminate\Support\Facades\Storage;

class StorageWrapper
{
    private static $instance = [];

    public static function instance_by_type($type_storage)
    {
        return self::instance('', null, $type_storage);
    }

    public static function instance(string $module_name = '', string $prefix_name = null, $type_storage = null)
    {
        if ($type_storage == null) {
            $type_storage = config('filesystems.default');
        }

        $name = $module_name . '_' . (isset($prefix_name) ? $prefix_name : '') . '_' . (isset($type_storage) ? $type_storage : '');

        // TODO: Problem with ssl, let's try use without singlton
        if (isset(self::$instance[$name])) {
            return self::$instance[$name];
        }

        $storage = Storage::disk($type_storage);

        // GeneralHelper::PrintR($storage->getAdapter());die();
        // $storage->getDriver()->getAdapter()->disconnect();

        self::$instance[$name] = new StorageLaravel($storage, $module_name, $prefix_name);
        return self::$instance[$name];
    }

    private static function get_db_files($ids)
    {
        $in = [];
        foreach ($ids as $id) {
            if (!empty($id) && is_string($id)) {
                $in[] = $id;
            }
        }

        $data = StorageModel::all()->whereIn('_id', $in)->toArray();

        return $data;

        // global $config;
        // $c = unserialize(serialize(self::$collections));
        // $collection = $c[$config['storage']];

        // $where = ['_id' => new MongoDB\BSON\ObjectId($id)];
        // $mongo = new MongoDBObjects($collection, $where);
        // $data = $mongo->find();

        // if (isset($data) && count($data) > 0) {
        //     $data['type'] = $config['storage'];
        // } else {
        //     unset($c[$config['storage']]);
        //     $collection = array_values($c)[0];
        //     $mongo = new MongoDBObjects($collection, $where);
        //     $data = $mongo->find();
        //     $data['type'] = array_keys($c)[0];
        // }
        // return $data;
    }

    private static function sync_fields(&$data)
    {
        if (!isset($data['original_file_name'])) {
            $data['original_file_name'] = $data['file_name_original'];
            if (empty($data['original_file_name'])) {
                $data['original_file_name'] = $data['file_name'];
            }
        }
        if (!isset($data['file_size'])) {
            $data['file_size'] = $data['size'];
        }
        return $data;
    }

    public static function get_files($ids)
    {
        // $result = $instance::get_files($ids);

        $data = self::get_db_files($ids);

        // $result = [];
        // foreach (self::$collections as $type => $collection) {
        //     $instance = self::instance('', null, $type);
        //     $result = array_merge($result, $instance::get_files($ids));
        //     for ($i = 0; $i < count($result); $i++) {
        //         self::sync_fields($result[$i]);
        //     }
        // }
        return $data;
    }

    public static function get_file($id)
    {
        $data = self::get_db_files([$id]);
        // $instance = self::instance('', null, $file['type']);
        // $data = $instance->get_file($id);
        // self::sync_fields($data);
        return isset($data) && count($data) > 0 ? array_values($data)[0] : false;
    }

    public static function get_link($data)
    {
        $id = (array)$data['_id'];
        $id = $id['oid'];
        $file = self::get_file($id);
        if ($file) {
            $instance = self::instance_by_type($file['type']);
            return $instance::get_link($data);
        }
    }

    public static function get_content($id)
    {
        $file = self::get_file($id);
        if ($file) {
            $instance = self::instance_by_type($file['type']);
            return $instance->get_content($file);
        }
        return false;
    }

    public static function download_to_file_path(string $id, string $file_path)
    {
        $file = self::get_file($id);
        if ($file) {
            $instance = self::instance_by_type($file['type']);
            return $instance->download_file($file, $file_path);
        }
        return false;
    }

    public static function download($id)
    {
        $file = self::get_file($id);
        if ($file) {
            $instance = self::instance_by_type($file['type']);
            $content = $instance->get_content($file);
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header("Cache-Control: no-cache, must-revalidate");
            header("Expires: 0");
            header('Content-Disposition: attachment; filename="' . basename($file['original_file_name']) . '"');
            header('Content-Length: ' . strlen($content));
            header('Pragma: public');
            flush();

            echo $content;
            die();
            // return $instance->download($file, $file['original_file_name'], [
            //     'Content-Disposition' => 'inline'
            // ]);
        }
        return false;
    }
}
