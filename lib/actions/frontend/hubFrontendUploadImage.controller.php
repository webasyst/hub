<?php

class hubFrontendUploadImageController extends waController
{
    public function execute()
    {
        $path = wa()->getDataPath('upload/images/', true);
        $redactor_version = waRequest::request('_version', 1, waRequest::TYPE_INT);

        $response = '';

        if (!is_writable($path)) {
            $p = substr($path, strlen(wa()->getDataPath('', true)));
            $errors = sprintf(_w("File could not be saved due to insufficient write permissions for the %s folder."), $p);
        } else {
            $errors = array();
            $f = waRequest::file('file');
            $f->transliterateFilename();
            $name = $f->name;
            if ($this->processFile($f, $path, $name, $errors)) {
                $response = wa()->getDataUrl('upload/images/'.$name, true, null, !waRequest::get('relative'));
            }
            $errors = implode(" \r\n", $errors);
        }
        $this->getResponse()->sendHeaders();
        if ($errors) {
            echo json_encode(array('error' => $errors));
        } else {
            $key = ($redactor_version == 2) ? 'url' : 'filelink';
            echo json_encode(array($key => $response));
        }
    }

    /**
     * @param waRequestFile $f
     * @param string $path
     * @param string $name
     * @param array $errors
     * @return bool
     */
    protected function processFile(waRequestFile $f, $path, &$name, &$errors = array())
    {
        if ($f->uploaded()) {
            if (!$this->isFileValid($f, $errors)) {
                return false;
            }
            if (!$this->saveFile($f, $path, $name)) {
                $errors[] = sprintf(_w('Failed to upload file %s.'), $f->name);
                return false;
            }
            return true;
        } else {
            $errors[] = sprintf(_w('Failed to upload file %s.'), $f->name).' ('.$f->error.')';
            return false;
        }
    }

    protected function isFileValid($f, &$errors = array())
    {
        $allowed = array('jpg', 'jpeg', 'png', 'gif');
        if (!in_array(strtolower($f->extension), $allowed)) {
            $errors[] = sprintf(_ws("Files with extensions %s are allowed only."), '*.'.implode(', *.', $allowed));
            return false;
        }
        return true;
    }

    protected function saveFile(waRequestFile $f, $path, &$name)
    {
        $name = $f->name;
        if (!preg_match('//u', $name)) {
            $tmp_name = @iconv('windows-1251', 'utf-8//ignore', $name);
            if ($tmp_name) {
                $name = $tmp_name;
            }
        }
        if (file_exists($path.DIRECTORY_SEPARATOR.$name)) {
            $i = strrpos($name, '.');
            $ext = substr($name, $i + 1);
            $name = substr($name, 0, $i);
            $i = 1;
            while (file_exists($path.DIRECTORY_SEPARATOR.$name.'-'.$i.'.'.$ext)) {
                $i++;
            }
            $name = $name.'-'.$i.'.'.$ext;
        }
        return $f->moveTo($path, $name);
    }
}
