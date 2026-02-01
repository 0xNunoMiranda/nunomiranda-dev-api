<?php

class SettingsStore
{
    private string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public function load(): array
    {
        if (!file_exists($this->path)) {
            return [];
        }

        $contents = file_get_contents($this->path);
        if ($contents === false) {
            throw new RuntimeException('Não foi possível ler o ficheiro de settings.');
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            return [];
        }

        return $data;
    }

    public function save(array $settings): void
    {
        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Não foi possível serializar os settings.');
        }

        $dir = dirname($this->path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $result = file_put_contents($this->path, $json);
        if ($result === false) {
            throw new RuntimeException('Não foi possível gravar os settings.');
        }
    }
}
