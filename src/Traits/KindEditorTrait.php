<?php
/**
 * KindEditor编辑器的接口
 *
 * 路由定义参考:
 * Route::post('/kind_editor/upload', 'KindEditorController@upload'); // 上传
 * Route::get('/kind_editor/system', 'KindEditorController@system'); // 目录结构
 * 需要修改别的存储路径请重新定义getFilePath方法
 */

namespace Yeosz\LaravelCurd\Traits;

trait KindEditorTrait
{
    /**
     * 目录分类符
     *
     * @var string
     */
    public $directorySeparator = '/';

    /**
     * 排序方式
     *
     * @var string
     */
    public static $orderBy = 'size';

    /**
     * 上传
     *
     * @return array
     */
    public function upload()
    {
        $request = request();
        $requestDir = $request->input('dir', 'file');
        if (!$request->files->has('file')) {
            return ['error' => 1, 'message' => '未上传文件'];
        }
        // 校验扩展名是否合法
        $file = $request->file('file');
        $extension = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
        $allow = $this->getAllowExtensionName();
        if (!isset($allow[$extension])) {
            return ['error' => 1, 'message' => '上传文件扩展名是不允许的扩展名'];
        }
        // 校验扩展名与目录是否相符
        $fileType = $allow[$extension];
        $requestDir = $requestDir == 'flash' ? 'media' : $requestDir;
        if (in_array($requestDir, ['image', 'media']) && $fileType != $requestDir) {
            return ['error' => 1, 'message' => '上传文件扩展名是不允许的扩展名'];
        }

        list($dir, $url) = $this->getFilePath($fileType, 1);

        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true)) {
                return ['error' => 1, 'message' => '创建目录失败'];
            }
        }

        if ($fileType == 'image') {
            // 使用文件md5哈希值作为文件名
            $filename = md5_file($file->path()) . '.' . $extension;
        } else {
            // 使用原始文件名
            $filename = strtolower($file->getClientOriginalName());
            // 检查是否已经存在同名文件，并且使用md5检查文件是否完全相同
            // 如果没有同名文件，则使用原始文件名保存，否则，在原始文件名后加上一个数字后缀保存
            $fullPath = $dir . $this->directorySeparator . $filename;
            if (file_exists($fullPath)) {
                $oldFileHash = md5_file($fullPath);
                $newFileHash = md5_file($file->path());
                if ($newFileHash != $oldFileHash) {
                    $fullPath = $this->getAvailableFilename($fullPath);
                    $filename = pathinfo($fullPath, PATHINFO_BASENAME);
                }
            }
        }

        $file->move($dir, $filename);
        $url = $url . $filename;
        $data = [
            'error' => 0,
            'url' => $url,
            'type' => $fileType,
            'path' => parse_url($url, PHP_URL_PATH),
            'original_name' => $file->getClientOriginalName(),
            'filename' => $filename,
            'extension' => $extension,
            'size' => $file->getSize(),
        ];
        return $data;
    }

    /**
     * 文件空间
     *
     * @return string|array
     */
    public function system()
    {
        $request = request();
        // 目录名
        $dirName = empty($request->input('dir')) ? '' : trim($request->input('dir'));
        if (!in_array($dirName, ['image', 'flash', 'media', 'file'])) {
            return 'Invalid Directory name';
        }
        $fileType = $dirName == 'flash' ? 'media' : $dirName;

        list($dir, $rootUrl) = $this->getFilePath($fileType, 0);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $rootPath = $dir . $this->directorySeparator;
        $extArr = ['gif', 'jpg', 'jpeg', 'png', 'bmp'];

        // 根据path参数，设置各路径和URL
        if (empty($request->input('path'))) {
            $currentPath = realpath($rootPath) . $this->directorySeparator;
            $currentUrl = $rootUrl;
            $currentDirPath = '';
            $moveUpDirPath = '';
        } else {
            $currentPath = realpath($rootPath) . $this->directorySeparator . $request->input('path');
            $currentUrl = $rootUrl . $request->input('path');
            $currentDirPath = $request->input('path');
            $moveUpDirPath = preg_replace('/(.*?)[^\/]+\/$/', '$1', $currentDirPath);
        }
        $realPath = realpath($rootPath);
        // 排序形式，name or size or type
        self::$orderBy = empty($request->input('order')) ? 'name' : strtolower($request->input('order'));

        //不允许使用..移动到上一级目录
        if (preg_match('/\.\./', $currentPath)) {
            return 'Access is not allowed';
        }
        // 最后一个字符不是/
        if (!preg_match('/\/$/', $currentPath)) {
            return 'Parameter is not valid';
        }
        // 目录不存在或不是目录
        if (!file_exists($currentPath) || !is_dir($currentPath)) {
            return $realPath . 'Directory does not exist';
        }

        // 遍历目录取得文件信息
        $fileList = [];
        $handle = opendir($currentPath);
        if ($handle) {
            $i = 0;
            while (false !== ($filename = readdir($handle))) {
                if ($filename{0} == '.') {
                    continue;
                }
                $file = $currentPath . $filename;
                if (is_dir($file)) {
                    if (!is_readable($file)) {
                        continue;
                    }
                    $fileList[$i]['is_dir'] = true;
                    $fileList[$i]['has_file'] = (count(scandir($file)) > 2);
                    $fileList[$i]['filesize'] = 0;
                    $fileList[$i]['is_photo'] = false;
                    $fileList[$i]['filetype'] = '';
                } else {
                    $fileList[$i]['is_dir'] = false;
                    $fileList[$i]['has_file'] = false;
                    $fileList[$i]['filesize'] = filesize($file);
                    $fileList[$i]['dir_path'] = '';
                    $fileExt = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $fileList[$i]['is_photo'] = in_array($fileExt, $extArr);
                    $fileList[$i]['filetype'] = $fileExt;
                }
                $fileList[$i]['filename'] = $filename;
                $fileList[$i]['datetime'] = date('Y-m-d H:i:s', filemtime($file));
                $i++;
            }
            closedir($handle);
        }
        // 排序
        usort($fileList, 'self::sortFile');
        // 文件系统
        $fileSystem = [];
        $fileSystem['moveup_dir_path'] = $moveUpDirPath;
        $fileSystem['current_dir_path'] = $currentDirPath;
        $fileSystem['current_url'] = $currentUrl;
        $fileSystem['total_count'] = count($fileList);
        $fileSystem['file_list'] = $fileList;

        return $fileSystem;
    }

    /**
     * 文件排序
     *
     * @param $a
     * @param $b
     * @return int
     */
    protected static function sortFile($a, $b)
    {
        if ($a['is_dir'] && !$b['is_dir']) {
            return -1;
        }

        if (!$a['is_dir'] && $b['is_dir']) {
            return 1;
        }

        if (self::$orderBy != 'size') {
            $key = 'file' . self::$orderBy;
            return strcmp($a[$key], $b[$key]);
        }

        if ($a['filesize'] > $b['filesize']) {
            return 1;
        } else if ($a['filesize'] < $b['filesize']) {
            return -1;
        } else {
            return 0;
        }
    }

    /**
     * 获取一个可用的文件名
     *
     * 本方法在$path文件所在的相同目录下生成一个可用的文件名，
     * 规则是在原来的文件名后面加上一个后缀
     *
     * @param string $path 文件名
     * @return string
     */
    protected function getAvailableFilename($path)
    {
        $fileInfo = pathinfo($path);
        $dirname = $fileInfo['dirname'];
        $filename = $fileInfo['filename'];
        $extension = $fileInfo['extension'];
        $filePath = $dirname . $this->directorySeparator . $filename . '_' . mt_rand(11111, 99999) . '.' . $extension;

        if (file_exists($filePath)) {
            return $this->getAvailableFilename($path);
        }

        return $filePath;
    }

    /**
     * 存储路径,以及url
     * 需要修改路径请重新定义该方法
     *
     * @param $fileType
     * @param $period 0无,1按月,2按天
     * @return array
     */
    protected function getFilePath($fileType, $period = 0)
    {
        $dirArr = [
            'uploads',
            'editor',
            $fileType,
        ];
        if ($period) {
            $periodDir = $period == 1 ? date('Ym') : date('Ymd');
            $dirArr[] = $periodDir;
        }

        $url = implode('/', $dirArr);
        $dir = public_path($url);
        $url = '/' . $url . '/';

        return [$dir, $url];
    }

    /**
     * 扩展名级类型
     *
     * @return array
     */
    public function getAllowExtensionName()
    {
        return [
            'gif' => 'image', 'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'bmp' => 'image',
            'swf' => 'media', 'flv' => 'media', 'mp3' => 'media', 'mp4' => 'media', 'wav' => 'media',
            'wma' => 'media', 'wmv' => 'media', 'mid' => 'media', 'avi' => 'media', 'mpg' => 'media',
            'asf' => 'media', 'rm' => 'media', 'rmvb' => 'media',
            'doc' => 'file', 'docx' => 'file', 'xls' => 'file', 'xlsx' => 'file', 'ppt' => 'file',
            'htm' => 'file', 'html' => 'file', 'txt' => 'file', 'zip' => 'file', 'rar' => 'file',
            'gz' => 'file', 'bz2' => 'file', 'pdf' => 'file', 'csv' => 'file',
        ];
    }
}
