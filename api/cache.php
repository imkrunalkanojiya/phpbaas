<?php

class Cache {
    private $cacheDir;
    private $cacheTime;

    public function __construct($cacheDir = __DIR__ . '/cache', $cacheTime = 3600) {
        $this->cacheDir = $cacheDir;
        $this->cacheTime = $cacheTime;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    public function get($key) {
        $cacheFile = $this->getCacheFile($key);

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->cacheTime) {
            return unserialize(file_get_contents($cacheFile));
        }

        return null;
    }

    public function set($key, $data) {
        $cacheFile = $this->getCacheFile($key);
        file_put_contents($cacheFile, serialize($data));
    }

    private function getCacheFile($key) {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }

    public function clear($key) {
        $cacheFile = $this->getCacheFile($key);
        if (file_exists($cacheFile)) {
            unlink($cacheFile);
        }
    }

    public function clearAll() {
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
}

?>
