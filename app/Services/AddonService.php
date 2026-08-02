<?php

namespace App\Services;

use App\Traits\ActivationClass;
use App\Traits\AddonHelper;
use App\Traits\FileManagerTrait;
use App\Traits\SettingsTrait;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use ZipArchive;

class AddonService
{
    use SettingsTrait;
    use FileManagerTrait;
    use ActivationClass;
    use AddonHelper;

    public function getUploadData(object $request): array
    {
        $tempFolderPath = storage_path('app/temp/');
        if (!File::exists($tempFolderPath)) {
            File::makeDirectory($tempFolderPath, 0775, true);
        }

        $file = $request->file('file_upload');
        $filename = $file->getClientOriginalName();
        $tempPath = $file->storeAs('temp', $filename);

        $zip = new ZipArchive();
        if ($zip->open(storage_path('app/' . $tempPath)) === TRUE) {

            $genFolderName = explode('/', $zip->getNameIndex(0))[0];
            if ($genFolderName === "__MACOSX") {
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    if (strpos($zip->getNameIndex($i), "__MACOSX") === false) {
                        $getAddonFolder = explode('/', $zip->getNameIndex($i))[0];
                        break;
                    }
                }
            }
            $getAddonFolder = explode('.', $genFolderName)[0];

            $zip->extractTo(storage_path('app/temp'));
            $infoPath = storage_path('app/temp/' . $getAddonFolder . '/Addon/info.php');

            if (File::exists($infoPath)) {
                $extractPath = base_path('Modules');
                if (!File::exists($extractPath)) {
                    File::makeDirectory($extractPath, 0775, true);
                }
                if (File::exists($extractPath . '/' . $getAddonFolder)) {
                    $message = translate('already_installed');
                    $status = 'error';
                } else {
                    $zip->extractTo($extractPath);
                    $zip->close();
                    File::chmod($extractPath . '/' . $getAddonFolder . '/Addon', 0777);
                    $status = 'success';
                    $message = translate('upload_successfully');

                    if (DOMAIN_POINTED_DIRECTORY == 'public' && function_exists('shell_exec')) {
                        shell_exec('ln -s ../Modules Modules');
                        Artisan::call('optimize:clear');
                        Artisan::call('view:clear');
                    }
                }
            } else {
                File::cleanDirectory(storage_path('app/temp'));
                $status = 'error';
                $message = translate('invalid_file!');
            }
        } else {
            $status = 'error';
            $message = translate('file_upload_fail!');
        }

        if (File::exists(base_path('Modules/__MACOSX'))) {
            File::deleteDirectory(base_path('Modules/__MACOSX'));
        }

        File::cleanDirectory(storage_path('app/temp'));

        return [
            'status' => $status,
            'message' => $message
        ];
    }

    public function getPublishData(object $request): array
    {
        $fullData = include(base_path($request['path'] . '/Addon/info.php'));
        $fullData['is_published'] = $fullData['is_published'] ? 0 : 1;
        $str = "<?php return " . var_export($fullData, true) . ";";
        file_put_contents(base_path($request['path'] . '/Addon/info.php'), $str);

        Artisan::call('optimize:clear');
        Artisan::call('view:clear');

        return [
            'status' => 'success',
            'message' => translate('status_updated_successfully')
        ];
    }

    public function getActivationData(object $request): array
    {
        $full_data = include(base_path($request['path'] . '/Addon/info.php'));
        $full_data['is_published'] = 1;
        $full_data['username'] = $request['username'] ?? '';
        $str = "<?php return " . var_export($full_data, true) . ";";
        file_put_contents(base_path($request['path'] . '/Addon/info.php'), $str);

        return [
            'status' => 1,
            'activationUrl' => url('/')
        ];
    }

    public function deleteAddon(object $request): array
    {
        $path = $request['path'];
        $full_path = base_path($path);
        $old = base_path('app/Traits/Payment.php');
        $new = base_path('app/Traits/Payment.txt');
        copy($new, $old);

        if (File::deleteDirectory($full_path)) {
            $status = 'success';
            $message = translate('file_delete_successfully');
        } else {
            $status = 'error';
            $message = translate('file_delete_fail');
        }

        return [
            'status' => $status,
            'message' => $message
        ];
    }

    public function getCurrentDomain(): string
    {
        return str_replace(["http://", "https://", "www."], "", url('/'));
    }

    public function addonActivationProcess(object $request): array
    {
        $response = $this->getRequestConfig(
            username: $request['username'] ?? '',
            softwareId: $request['software_id'] ?? SOFTWARE_ID,
            softwareType: $request['software_type'] ?? base64_decode('cHJvZHVjdA=='),
        );
        $this->updateActivationConfig(app: $request['addon_name'], response: $response);

        $status = $response['active'] ?? 0;

        if ((int)$status) {
            return [
                'status' => (int)$status,
                'activation_status' => 1,
                'username' => $request['username'] ?? '',
            ];
        }

        return [
            'status' => (int)$status,
            'message' => $response['message'] ?? translate('Activation_failed')
        ];
    }

}
