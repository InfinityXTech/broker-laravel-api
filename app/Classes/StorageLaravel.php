<?php

namespace App\Classes;

use App\Helpers\GeneralHelper;
use Exception;
use App\Models\StorageModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Contracts\Filesystem\Filesystem;

class StorageLaravel
{
    protected $prefix_name;
    protected $baseDirectory;
    protected $subDirectory;
    protected $allowed = ['doc', 'docx', 'xls', 'xlsx', 'txt', 'pdf', 'jpg', 'jpeg', 'png', 'gif'];

    private Filesystem $storage;

    private static $instance = null;

    public static function instance(Filesystem &$storage, string $subDirectory = '', string $prefix_name = null)
    {
        if (isset(self::$instance)) {
            return self::$instance;
        }
        self::$instance = new self($storage, $subDirectory, $prefix_name);
        return self::$instance;
    }

    public function __construct(Filesystem &$storage, string $subDirectory = '', string $prefix_name = null)
    {
        $this->storage = $storage;
        $this->prefix_name = $prefix_name;
        $this->subDirectory = (!empty($subDirectory) ? $subDirectory . '/' : '') . trim($prefix ?? date('Y/m/d/'), '/\\') . '/';
        $this->baseDirectory = $this->get_storage_path();
    }

    private function get_base_path()
    {
        return '/';
    }

    public function get_storage_path()
    {
        $uploaddir = str_replace('//', '/', $this->get_base_path() . $this->subDirectory);
        return $uploaddir;
    }

    private function _get_file($remoteFile, $localFile = false)
    {
        try {
            $default = config('filesystems.default');
            if ($default == 'local') {
                $disks = config('filesystems.disks');
                $root = $disks[$default]['root'];
                $content = file_get_contents($root . '/' . $remoteFile);
                if ($localFile) {
                    file_put_contents($localFile, $content);
                } else {
                    return $content;
                }
            } else {
                if ($this->storage->exists($remoteFile)) {
                    $content = $this->storage->get($remoteFile);
                    $this->disconnect();
                    if ($localFile) {
                        file_put_contents($localFile, $content);
                    } else {
                        return $content;
                    }
                } else {
                    Log::error("file [" . $remoteFile . "] is not found");
                    throw new Exception("File is not found " . $remoteFile);
                }
            }
        } catch (Exception $ex) {
            $this->disconnect();
            Log::error("file [" . $remoteFile . "] error: " . $ex->getMessage());
            throw $ex;
        }
    }

    private function disconnect()
    {
        if (method_exists($this->storage, 'getAdapter')) {
            $this->storage->getAdapter()->__destruct();
        }
    }

    public function download($file, $file_name, $headers): string
    {
        $file_path = str_replace('//', '/', $file['path'] . '/' . $file['file_name']);
        $content = $this->storage->download($file_path, $file_name, $headers);
        $this->disconnect();
        return $content;
    }

    public function download_file($file, $localFile = false): string
    {
        $file_path = str_replace('//', '/', $file['path'] . '/' . $file['file_name']);
        if ($localFile == false) {
            $content = $this->_get_file($file_path, $localFile);
            return $content;
        }
        return '';
    }

    public function get_content($file)
    {
        $file_path = str_replace('//', '/', $file['path'] . '/' . $file['file_name']);
        $content = $this->_get_file($file_path, false);
        return $content;
    }

    private function make_link($file_id)
    {
        return '/storage/download/' . $file_id;
    }

    public static function get_link($data)
    {
        return '/storage/download/' . $data['_id'];
    }

    private function get_rand_file_name($file_name)
    {
        $extension_of_file_here = pathinfo($file_name, PATHINFO_EXTENSION);
        $charsz = "abcdefghijklmnopqrstuvwxyz";
        $token = substr(str_shuffle($charsz), 0, 2);
        $new_name = $token . '' . random_int(100000, 999999) . time() . '.' . $extension_of_file_here;
        return $new_name;
    }

    public function set_allowed_extensions($allowed)
    {
        $this->allowed = $allowed;
        return $this;
    }

    public function get_file_object($id)
    {
        $model = StorageModel::query()->find($id);
        return $model ? $model->toArray() : null;
    }

    public function get_file($id): array
    {
        try {
            $data = $this->get_file_object($id);
            if (isset($data)) {
                return [
                    '_id' => $data['_id'],
                    'path' => $data['path'],
                    'created_by' => $data['created_by'],
                    'file_name' => $data['file_name'],
                    'original_file_name' => $data['original_file_name'],
                    'file_size' => $data['file_size'],
                    'timestamp' => $data['timestamp'],
                    'link' => self::get_link($data),
                ];
            }
        } catch (\Exception $ex) {
        }
        return [];
    }

    public function get_files($ids): array
    {
        $response = [];

        $files = [];
        foreach ($ids as $id) {
            try {
                $files[] = $id;
            } catch (\Exception $ex) {
            }
        }
        $datas = StorageModel::all()->whereIn('_id', $files)->toArray();
        foreach ($datas as $data) {
            $response[] = [
                '_id' => $data['_id'],
                'created_by' => $data['created_by'],
                'path' => $data['path'],
                'file_name' => $data['file_name'],
                'original_file_name' => $data['original_file_name'],
                'file_size' => $data['file_size'],
                'timestamp' => $data['timestamp'],
                'link' => self::get_link($data),
            ];
        }
        return $response;
    }

    public function delete_file($id)
    {
        $file = $this->get_file($id);
        if (!$file) {
            return;
        }

        $file_uploaded = $this->get_storage_path() . $file['file_name'];

        if ($this->storage->exists($file_uploaded)) {
            $this->storage->delete($file_uploaded);
            $this->disconnect();
        }

        StorageModel::query()->find($id)->delete();
    }

    public function delete_files($ids)
    {
        $files = $this->get_files($ids);
        foreach ($files as $file) {

            if (!$file) {
                continue;
            }

            $file_uploaded = $this->get_storage_path() . $file['file_name'];
            if ($this->storage->exists($file_uploaded)) {
                $this->storage->delete($file_uploaded);
                $this->disconnect();
            }

            StorageModel::query()->find($file['_id'])->delete();
        }
    }

    public function upload_files($field_name = 'file')
    {

        $files = [];

        if (isset($_FILES[$field_name])) {

            $countfiles = count($_FILES[$field_name]['name']);

            $storage_path = $this->get_storage_path();
            $remoteDirs = explode('/', $storage_path);

            if ($countfiles > 0) {
                foreach ($remoteDirs as $dir) {
                    if (!empty($dir)) {
                        if (!$this->storage->exists($dir)) {
                            if (!$this->storage->makeDirectory($dir)) {
                                Log::error("file [" . $field_name . "] mkdir error");
                                throw new \Exception("mkdir error");
                            }
                        }
                    }
                }
            }

            for ($i = 0; $i < $countfiles; $i++) {
                $uploadfile = '';
                $id = '';
                try {
                    if (0 < $_FILES[$field_name]['error'][$i]) {
                        Log::error('Error: ' . $_FILES[$field_name]['error'][$i]);
                        throw new \Exception('Error: ' . $_FILES[$field_name]['error'][$i]);
                    }
                    $filename = $_FILES[$field_name]['name'][$i];
                    if (empty($filename)) {
                        continue;
                    }
                    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                    $basename_filename = basename($filename);
                    $file_name = $this->get_rand_file_name($basename_filename);

                    $uploadfile = $_FILES[$field_name]['tmp_name'][$i];

                    if (!in_array($ext, $this->allowed)) {
                        $allow_str = implode(', ', $this->allowed);
                        Log::error('Permission denied! You can\'t upload extension ' . $ext . '. Allow ' . $allow_str);
                        throw new \Exception('Permission denied! You can\'t upload extension ' . $ext . '. Allow ' . $allow_str);
                    }
                    if ($_FILES[$field_name]['size'][$i] > 10000000) {
                        throw new \Exception('Filesize limit');
                    }

                    $default = config('filesystems.default');
                    if ($default == 'local') {
                        $disks = config('filesystems.disks');
                        $root = $disks[$default]['root'];

                        $path = $root . '/' . $storage_path;
                        if (!File::isDirectory($path)) {
                            File::makeDirectory($path, 0777, true, true);
                        }
                    }

                    // echo $storage_path;
                    // $this->storage->makeDirectory($storage_path);

                    // if (!file_exists($storage_path)) {
                    //     $this->storage->makeDirectory($dir);
                    //     if (mkdir($storage_path, 0766, true)) {
                    //         throw new \Exception("Directory is not writable " . $storage_path);
                    //     }
                    // }

                    // if (!$this->storage->exists($storage_path)) {
                    //     if (!$this->storage->makeDirectory($storage_path)) {
                    //         throw new \Exception("mkdir error");
                    //     }
                    // }

                    $content = file_get_contents($uploadfile);
                    $r = $this->storage->put($storage_path . $file_name, $content);
                    $this->disconnect();

                    if (!$r) {
                        throw new \Exception("Error uploading file");
                    }

                    $insert = [
                        'type' => 'ftp',
                        'created_by' => Auth::id(),
                        'path' => $this->subDirectory,
                        'original_file_name' => $basename_filename,
                        'file_name' => $file_name,
                        'file_size' => filesize($uploadfile),
                        'ext' => $ext,
                    ];

                    $var = date("Y-m-d H:i:s");
                    $insert['timestamp'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);
                    $insert['last_date_access'] = new \MongoDB\BSON\UTCDateTime(strtotime($var) * 1000);

                    $model = new StorageModel();
                    $model->fill($insert);
                    $model->save();

                    $id = $model->_id;

                    $files[] = [
                        'success' => true,
                        'id' => $id,
                        'file_name' => $file_name,
                        'full_path' => $uploadfile,
                        'link' => $this->make_link($id)
                    ];
                } catch (\Exception $ex) {
                    if (file_exists($uploadfile)) {
                        unlink($uploadfile);
                    }
                    if (!empty($id)) {
                        $this->delete_file($id);
                    }
                    $files[] = ['success' => false, 'error' => $ex->getMessage()];
                } finally {
                    $this->disconnect();
                }
            }
        }

        return $files;
    }
}
